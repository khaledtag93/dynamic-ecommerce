<?php

namespace App\Services\Workforce;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use App\Support\WorkforceClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkShiftService
{
    public function __construct(protected AdminActivityLogService $activityLogService, protected WorkforceAccessService $access)
    {
    }

    public function create(EmployeeProfile $employee, array $data, User $actor): EmployeeWorkShift
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $lockedEmployee = EmployeeProfile::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $this->access->assertManage($actor, $lockedEmployee);
            $this->guardSchedulable($lockedEmployee);
            $this->guardChronology($data['starts_at'], $data['ends_at']);
            $this->guardNoApprovedLeave($lockedEmployee, $data['starts_at'], $data['ends_at']);
            $this->guardNoOverlap($lockedEmployee, $data['starts_at'], $data['ends_at']);

            $status = $data['status'] ?? EmployeeWorkShift::STATUS_DRAFT;
            $this->guardEditableStatus($status);

            $shift = EmployeeWorkShift::query()->create([
                'employee_profile_id' => $lockedEmployee->id,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'status' => $status,
                'location' => $this->nullableTrim($data['location'] ?? null),
                'notes' => $this->nullableTrim($data['notes'] ?? null),
                'published_at' => $status === EmployeeWorkShift::STATUS_PUBLISHED ? now() : null,
                'cancelled_at' => null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->activityLogService->log(
                'workforce',
                'work_shift_created',
                __('Work shift created.'),
                $actor->id,
                $shift,
                ['employee_profile_id' => $lockedEmployee->id, 'status' => $status]
            );

            return $shift;
        });
    }

    public function update(EmployeeWorkShift $shift, array $data, User $actor): EmployeeWorkShift
    {
        return DB::transaction(function () use ($shift, $data, $actor) {
            $lockedShift = EmployeeWorkShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            $this->access->assertManage($actor, (int) $lockedShift->employee_profile_id);
            $employeeId = (int) ($data['employee_profile_id'] ?? $lockedShift->employee_profile_id);
            $employee = EmployeeProfile::query()->whereKey($employeeId)->lockForUpdate()->firstOrFail();
            $this->access->assertManage($actor, $employee);

            if ($lockedShift->isCancelled()) {
                throw ValidationException::withMessages([
                    'shift' => __('Cancelled shifts cannot be edited.'),
                ]);
            }

            $this->guardSchedulable($employee);

            $startsAt = $data['starts_at'] ?? $lockedShift->starts_at;
            $endsAt = $data['ends_at'] ?? $lockedShift->ends_at;
            $this->guardChronology($startsAt, $endsAt);
            $this->guardNoApprovedLeave($employee, $startsAt, $endsAt);
            $this->guardNoOverlap($employee, $startsAt, $endsAt, $lockedShift->id);

            $status = $data['status'] ?? $lockedShift->status;
            $this->guardEditableStatus($status);
            $publishedAt = $lockedShift->published_at;
            if ($status === EmployeeWorkShift::STATUS_PUBLISHED && ! $publishedAt) {
                $publishedAt = now();
            }
            if ($status === EmployeeWorkShift::STATUS_DRAFT) {
                $publishedAt = null;
            }

            $lockedShift->update([
                'employee_profile_id' => $employee->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $status,
                'location' => $this->nullableTrim(array_key_exists('location', $data) ? $data['location'] : $lockedShift->location),
                'notes' => $this->nullableTrim(array_key_exists('notes', $data) ? $data['notes'] : $lockedShift->notes),
                'published_at' => $publishedAt,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->activityLogService->log(
                'workforce',
                'work_shift_updated',
                __('Work shift updated.'),
                $actor->id,
                $lockedShift,
                ['employee_profile_id' => $employee->id, 'status' => $status]
            );

            return $lockedShift->fresh();
        });
    }

    public function cancel(EmployeeWorkShift $shift, User $actor): EmployeeWorkShift
    {
        return DB::transaction(function () use ($shift, $actor) {
            $lockedShift = EmployeeWorkShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            $this->access->assertManage($actor, (int) $lockedShift->employee_profile_id);

            if ($lockedShift->isCancelled()) {
                return $lockedShift;
            }

            $lockedShift->update([
                'status' => EmployeeWorkShift::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'updated_by_user_id' => $actor->id,
            ]);

            $this->activityLogService->log(
                'workforce',
                'work_shift_cancelled',
                __('Work shift cancelled.'),
                $actor->id,
                $lockedShift,
                ['employee_profile_id' => $lockedShift->employee_profile_id]
            );

            return $lockedShift->fresh();
        });
    }

    private function guardEditableStatus(string $status): void
    {
        if (! in_array($status, [EmployeeWorkShift::STATUS_DRAFT, EmployeeWorkShift::STATUS_PUBLISHED], true)) {
            throw ValidationException::withMessages([
                'status' => __('Select a valid work shift status.'),
            ]);
        }
    }
    private function guardChronology(CarbonInterface|string $startsAt, CarbonInterface|string $endsAt): void
    {
        try {
            $start = $startsAt instanceof CarbonInterface ? CarbonImmutable::instance($startsAt) : CarbonImmutable::parse($startsAt, 'UTC');
            $end = $endsAt instanceof CarbonInterface ? CarbonImmutable::instance($endsAt) : CarbonImmutable::parse($endsAt, 'UTC');
        } catch (\Carbon\Exceptions\InvalidFormatException $exception) {
            throw ValidationException::withMessages([
                'starts_at' => __('Enter valid shift start and end dates.'),
            ]);
        }
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'ends_at' => __('Shift end must be after shift start.'),
            ]);
        }
    }
    private function guardSchedulable(EmployeeProfile $employee): void
    {
        if ($employee->status !== EmployeeProfile::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'employee_profile_id' => __('Only active employees can be assigned new work shifts.'),
            ]);
        }
    }

    private function guardNoApprovedLeave(
        EmployeeProfile $employee,
        CarbonInterface|string $startsAt,
        CarbonInterface|string $endsAt,
    ): void {
        // Shift timestamps are UTC instants, while leave dates denote LOCAL calendar days.
        // Treat the shift end as exclusive: an end exactly at local midnight does
        // not consume the next day. This also survives 23/25-hour DST dates.
        $startUtc = $startsAt instanceof CarbonInterface
            ? CarbonImmutable::instance($startsAt)->setTimezone('UTC')
            : CarbonImmutable::parse($startsAt, 'UTC');
        $endUtc = $endsAt instanceof CarbonInterface
            ? CarbonImmutable::instance($endsAt)->setTimezone('UTC')
            : CarbonImmutable::parse($endsAt, 'UTC');
        $startDate = $startUtc->setTimezone(WorkforceClock::timezone())->toDateString();
        $endDate = $endUtc->subMicrosecond()->setTimezone(WorkforceClock::timezone())->toDateString();

        $approvedLeave = EmployeeLeaveRequest::query()
            ->where('employee_profile_id', $employee->id)
            ->where('status', EmployeeLeaveRequest::STATUS_APPROVED)
            ->where('starts_on', '<=', $endDate)
            ->where('ends_on', '>=', $startDate)
            ->lockForUpdate()
            ->exists();

        if ($approvedLeave) {
            throw ValidationException::withMessages([
                'starts_at' => __('This employee has approved leave overlapping the requested work shift.'),
            ]);
        }
    }

    private function guardNoOverlap(
        EmployeeProfile $employee,
        CarbonInterface|string $startsAt,
        CarbonInterface|string $endsAt,
        ?int $ignoreShiftId = null,
    ): void {
        $overlap = EmployeeWorkShift::query()
            ->where('employee_profile_id', $employee->id)
            ->where('status', '!=', EmployeeWorkShift::STATUS_CANCELLED)
            ->when($ignoreShiftId, fn ($query) => $query->where('id', '!=', $ignoreShiftId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->lockForUpdate()
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => __('This employee already has an overlapping work shift.'),
            ]);
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
