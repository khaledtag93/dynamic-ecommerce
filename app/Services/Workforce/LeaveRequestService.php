<?php

namespace App\Services\Workforce;

use App\Models\EmployeeLeaveAdjustment;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(
        protected LeaveBalanceService $balanceService,
        protected AdminActivityLogService $activityLogService,
    ) {
    }

    public function request(
        User $user,
        EmployeeLeaveType $type,
        string $startsOn,
        string $endsOn,
        ?string $reason = null,
    ): EmployeeLeaveRequest {
        return DB::transaction(function () use ($user, $type, $startsOn, $endsOn, $reason) {
            $employee = $user->employeeProfile()->lockForUpdate()->first();

            if (! $employee) {
                throw ValidationException::withMessages([
                    'leave' => __('Your employee profile is not configured yet.'),
                ]);
            }

            if (! in_array($employee->status, [EmployeeProfile::STATUS_ACTIVE, EmployeeProfile::STATUS_ON_LEAVE], true)) {
                throw ValidationException::withMessages([
                    'leave' => __('Your employee status does not allow new leave requests.'),
                ]);
            }

            if (! $type->is_active) {
                throw ValidationException::withMessages([
                    'employee_leave_type_id' => __('This leave type is not currently available.'),
                ]);
            }

            [$start, $end] = $this->normalizeRange($startsOn, $endsOn);

            $overlap = EmployeeLeaveRequest::query()
                ->where('employee_profile_id', $employee->id)
                ->whereIn('status', [
                    EmployeeLeaveRequest::STATUS_PENDING,
                    EmployeeLeaveRequest::STATUS_APPROVED,
                ])
                ->where('starts_on', '<=', $end->toDateString())
                ->where('ends_on', '>=', $start->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'starts_on' => __('You already have a pending or approved leave request overlapping these dates.'),
                ]);
            }

            $days = $start->diffInDays($end) + 1;

            $request = EmployeeLeaveRequest::query()->create([
                'employee_profile_id' => $employee->id,
                'employee_leave_type_id' => $type->id,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'requested_days' => $days,
                'reason' => $this->nullableTrim($reason),
                'status' => EmployeeLeaveRequest::STATUS_PENDING,
            ]);

            $this->activityLogService->log(
                'workforce',
                'leave_requested',
                __('Leave requested.'),
                $user->id,
                $request,
                [
                    'employee_profile_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'requested_days' => $days,
                ]
            );

            return $request;
        });
    }

    public function approve(EmployeeLeaveRequest $request, User $reviewer, ?string $notes = null): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $notes) {
            $locked = EmployeeLeaveRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'leave' => __('Only pending leave requests can be approved.'),
                ]);
            }

            $employee = EmployeeProfile::query()->whereKey($locked->employee_profile_id)->lockForUpdate()->firstOrFail();
            $type = EmployeeLeaveType::query()->whereKey($locked->employee_leave_type_id)->lockForUpdate()->firstOrFail();

            $balance = $this->balanceService->balance($employee, $type, (int) $locked->starts_on->year);
            if ($balance['available'] < (float) $locked->requested_days) {
                throw ValidationException::withMessages([
                    'leave' => __('This employee does not have enough available leave balance for approval.'),
                ]);
            }

            $conflictingShift = EmployeeWorkShift::query()
                ->where('employee_profile_id', $employee->id)
                ->where('status', '!=', EmployeeWorkShift::STATUS_CANCELLED)
                ->where('starts_at', '<', $locked->ends_on->copy()->addDay()->startOfDay())
                ->where('ends_at', '>', $locked->starts_on->copy()->startOfDay())
                ->lockForUpdate()
                ->exists();

            if ($conflictingShift) {
                throw ValidationException::withMessages([
                    'leave' => __('Cancel or move overlapping work shifts before approving this leave request.'),
                ]);
            }

            $locked->update([
                'status' => EmployeeLeaveRequest::STATUS_APPROVED,
                'review_notes' => $this->nullableTrim($notes),
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->activityLogService->log(
                'workforce',
                'leave_approved',
                __('Leave request approved.'),
                $reviewer->id,
                $locked,
                ['employee_profile_id' => $employee->id]
            );

            return $locked->fresh();
        });
    }

    public function reject(EmployeeLeaveRequest $request, User $reviewer, ?string $notes = null): EmployeeLeaveRequest
    {
        return $this->reviewWithoutBalance($request, $reviewer, EmployeeLeaveRequest::STATUS_REJECTED, $notes);
    }

    public function cancel(EmployeeLeaveRequest $request, User $user): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $employee = $user->employeeProfile()->lockForUpdate()->first();

            if (! $employee || (int) $request->employee_profile_id !== (int) $employee->id) {
                throw ValidationException::withMessages([
                    'leave' => __('You can only cancel your own leave requests.'),
                ]);
            }

            $locked = EmployeeLeaveRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'leave' => __('Only pending leave requests can be cancelled by the employee.'),
                ]);
            }

            $locked->update([
                'status' => EmployeeLeaveRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            $this->activityLogService->log(
                'workforce',
                'leave_cancelled',
                __('Leave request cancelled.'),
                $user->id,
                $locked,
                ['employee_profile_id' => $employee->id]
            );

            return $locked->fresh();
        });
    }

    public function adjustBalance(
        EmployeeProfile $employee,
        EmployeeLeaveType $type,
        int $year,
        string $adjustmentType,
        float $days,
        string $reason,
        User $actor,
    ): EmployeeLeaveAdjustment {
        return DB::transaction(function () use ($employee, $type, $year, $adjustmentType, $days, $reason, $actor) {
            $lockedEmployee = EmployeeProfile::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (abs($days) < 0.001) {
                throw ValidationException::withMessages([
                    'days' => __('Leave balance adjustment cannot be zero.'),
                ]);
            }

            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => __('A reason is required for every leave balance adjustment.'),
                ]);
            }

            $current = $this->balanceService->balance($lockedEmployee, $type, $year);
            if (($current['available'] + $days) < 0) {
                throw ValidationException::withMessages([
                    'days' => __('This adjustment would make the available leave balance negative.'),
                ]);
            }

            $adjustment = EmployeeLeaveAdjustment::query()->create([
                'employee_profile_id' => $lockedEmployee->id,
                'employee_leave_type_id' => $type->id,
                'year' => $year,
                'type' => $adjustmentType,
                'days' => $days,
                'reason' => $reason,
                'created_by_user_id' => $actor->id,
            ]);

            $this->activityLogService->log(
                'workforce',
                'leave_balance_adjusted',
                __('Leave balance adjusted.'),
                $actor->id,
                $adjustment,
                [
                    'employee_profile_id' => $lockedEmployee->id,
                    'leave_type_id' => $type->id,
                    'year' => $year,
                    'days' => $days,
                ]
            );

            return $adjustment;
        });
    }

    private function reviewWithoutBalance(
        EmployeeLeaveRequest $request,
        User $reviewer,
        string $status,
        ?string $notes,
    ): EmployeeLeaveRequest {
        return DB::transaction(function () use ($request, $reviewer, $status, $notes) {
            $locked = EmployeeLeaveRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'leave' => __('Only pending leave requests can be reviewed.'),
                ]);
            }

            $locked->update([
                'status' => $status,
                'review_notes' => $this->nullableTrim($notes),
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->activityLogService->log(
                'workforce',
                'leave_rejected',
                __('Leave request rejected.'),
                $reviewer->id,
                $locked,
                ['employee_profile_id' => $locked->employee_profile_id]
            );

            return $locked->fresh();
        });
    }

    private function normalizeRange(string $startsOn, string $endsOn): array
    {
        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'ends_on' => __('Leave end date must be on or after the start date.'),
            ]);
        }

        if ($start->year !== $end->year) {
            throw ValidationException::withMessages([
                'ends_on' => __('Leave requests cannot cross calendar years in this version.'),
            ]);
        }

        return [$start, $end];
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
