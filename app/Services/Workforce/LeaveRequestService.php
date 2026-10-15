<?php

namespace App\Services\Workforce;

use App\Models\EmployeeLeaveAdjustment;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use App\Support\WorkforceClock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(
        protected LeaveBalanceService $balanceService,
        protected AdminActivityLogService $activityLogService,
        protected LeavePolicyService $leavePolicy,
        protected LeaveApprovalRoutingService $approvalRouting,
        protected WorkforceAccessService $access,
    ) {
    }

    public function request(
        User $user,
        EmployeeLeaveType $type,
        string $startsOn,
        ?string $endsOn,
        ?string $reason = null,
    ): EmployeeLeaveRequest {
        return DB::transaction(function () use ($user, $type, $startsOn, $endsOn, $reason) {
            $employee = $user->employeeProfile()->lockForUpdate()->first();

            $type = EmployeeLeaveType::query()->whereKey($type->id)->lockForUpdate()->firstOrFail();
            $this->assertRequestEligible($employee, $type);
            [$start, $end, $days] = $this->leavePolicy->resolve($type, $startsOn, $endsOn);
            $this->assertNoRequestOverlap($employee, $start->toDateString(), $end->toDateString(), true);

            $request = EmployeeLeaveRequest::query()->create([
                'employee_profile_id' => $employee->id,
                'employee_leave_type_id' => $type->id,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'requested_days' => $days,
                // Immutable evidence of rules used at submission; never recalculate
                // existing requests when an administrator edits the live type.
                'policy_snapshot' => $this->leavePolicy->snapshot($type),
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

    /**
     * Authenticated employee-only estimate. Performs reads only: no reservation,
     * no new leave request, no balance adjustment and no activity log.
     * Submission and approval always revalidate using authoritative state.
     */
    public function preview(User $user, EmployeeLeaveType $type, string $startsOn, ?string $endsOn): array
    {
        $employee = $user->employeeProfile()->first();
        $this->assertRequestEligible($employee, $type);

        [$start, $end, $days] = $this->leavePolicy->resolve($type, $startsOn, $endsOn);
        $this->assertNoRequestOverlap($employee, $start->toDateString(), $end->toDateString());

        $balance = $this->balanceService->balance($employee, $type, (int) $start->year);

        return [
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'requested_days' => round((float) $days, 2),
            'available' => $balance['available'],
            'pending' => $balance['pending'],
            'projected_available' => $balance['projected_available'],
            'projected_after_request' => round($balance['projected_available'] - (float) $days, 2),
            'sufficient_balance_now' => $balance['available'] >= (float) $days,
        ];
    }

    private function assertRequestEligible(?EmployeeProfile $employee, EmployeeLeaveType $type): void
    {
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
    }

    private function assertNoRequestOverlap(
        EmployeeProfile $employee,
        string $startsOn,
        string $endsOn,
        bool $lock = false,
    ): void {
        $query = EmployeeLeaveRequest::query()
            ->where('employee_profile_id', $employee->id)
            ->whereIn('status', [
                EmployeeLeaveRequest::STATUS_PENDING,
                EmployeeLeaveRequest::STATUS_APPROVED,
            ])
            ->where('starts_on', '<=', $endsOn)
            ->where('ends_on', '>=', $startsOn);

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_on' => __('You already have a pending or approved leave request overlapping these dates.'),
            ]);
        }
    }

    public function approve(EmployeeLeaveRequest $request, User $reviewer, ?string $notes = null): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $notes) {
            $locked = EmployeeLeaveRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $employee = EmployeeProfile::query()->whereKey($locked->employee_profile_id)->lockForUpdate()->firstOrFail();
            // Recheck inside the transaction: callers other than the HTTP controller
            // must never bypass routing/ownership checks.
            $this->approvalRouting->assertReviewer($reviewer, $employee);

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'leave' => __('Only pending leave requests can be approved.'),
                ]);
            }

            $type = EmployeeLeaveType::query()->whereKey($locked->employee_leave_type_id)->lockForUpdate()->firstOrFail();

            $balance = $this->balanceService->balance($employee, $type, (int) $locked->starts_on->year);
            if ($balance['available'] < (float) $locked->requested_days) {
                throw ValidationException::withMessages([
                    'leave' => __('This employee does not have enough available leave balance for approval.'),
                ]);
            }

            // Leave dates belong to the configured LOCAL calendar. Convert the
            // inclusive leave range to a half-open UTC interval for stored shifts.
            // Do not use UTC midnight: Cairo/DST day boundaries can differ.
            [$startUtc] = WorkforceClock::localDayUtcBounds($locked->starts_on->format('Y-m-d'));
            [, $endExclusiveUtc] = WorkforceClock::localDayUtcBounds($locked->ends_on->format('Y-m-d'));

            $conflictingShift = EmployeeWorkShift::query()
                ->where('employee_profile_id', $employee->id)
                ->where('status', '!=', EmployeeWorkShift::STATUS_CANCELLED)
                ->where('starts_at', '<', $endExclusiveUtc)
                ->where('ends_at', '>', $startUtc)
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

            $locked = EmployeeLeaveRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $employee || (int) $locked->employee_profile_id !== (int) $employee->id) {
                throw ValidationException::withMessages([
                    'leave' => __('You can only cancel your own leave requests.'),
                ]);
            }

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
            $this->access->assertOrgAdmin($actor);
            $lockedEmployee = EmployeeProfile::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (! array_key_exists($adjustmentType, EmployeeLeaveAdjustment::typeOptions())) {
                throw ValidationException::withMessages([
                    'type' => __('Select a valid leave balance adjustment type.'),
                ]);
            }
            if (! is_finite($days) || abs($days) > 365) {
                throw ValidationException::withMessages([
                    'days' => __('Leave balance adjustment must be between -365 and 365 days.'),
                ]);
            }

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
            $this->approvalRouting->assertReviewer($reviewer, (int) $locked->employee_profile_id);

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
