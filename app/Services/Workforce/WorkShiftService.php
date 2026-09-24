<?php

namespace App\Services\Workforce;

use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkShiftService
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function create(EmployeeProfile $employee, array $data, User $actor): EmployeeWorkShift
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $lockedEmployee = EmployeeProfile::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $this->guardSchedulable($lockedEmployee);
            $this->guardNoOverlap($lockedEmployee, $data['starts_at'], $data['ends_at']);

            $status = $data['status'] ?? EmployeeWorkShift::STATUS_DRAFT;

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

            if ($lockedShift->isCancelled()) {
                throw ValidationException::withMessages([
                    'shift' => __('Cancelled shifts cannot be edited.'),
                ]);
            }

            $employeeId = (int) ($data['employee_profile_id'] ?? $lockedShift->employee_profile_id);
            $employee = EmployeeProfile::query()->whereKey($employeeId)->lockForUpdate()->firstOrFail();
            $this->guardSchedulable($employee);

            $startsAt = $data['starts_at'] ?? $lockedShift->starts_at;
            $endsAt = $data['ends_at'] ?? $lockedShift->ends_at;
            $this->guardNoOverlap($employee, $startsAt, $endsAt, $lockedShift->id);

            $status = $data['status'] ?? $lockedShift->status;
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
                'location' => $this->nullableTrim($data['location'] ?? $lockedShift->location),
                'notes' => $this->nullableTrim($data['notes'] ?? $lockedShift->notes),
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

    private function guardSchedulable(EmployeeProfile $employee): void
    {
        if ($employee->status !== EmployeeProfile::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'employee_profile_id' => __('Only active employees can be assigned new work shifts.'),
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
