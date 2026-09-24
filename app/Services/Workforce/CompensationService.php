<?php

namespace App\Services\Workforce;

use App\Models\EmployeeCompensation;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompensationService
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function save(EmployeeProfile $employee, array $data, User $actor): EmployeeCompensation
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $lockedEmployee = EmployeeProfile::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (! empty($data['effective_to'])
                && Carbon::parse($data['effective_to'])->lt(Carbon::parse($data['effective_from']))) {
                throw ValidationException::withMessages([
                    'effective_to' => __('Compensation end date must be on or after the effective start date.'),
                ]);
            }

            $compensation = EmployeeCompensation::query()
                ->where('employee_profile_id', $lockedEmployee->id)
                ->lockForUpdate()
                ->first();

            $payload = [
                'employee_profile_id' => $lockedEmployee->id,
                'pay_basis' => $data['pay_basis'],
                'base_rate' => $data['base_rate'],
                'currency' => $data['currency'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'overtime_eligible' => (bool) ($data['overtime_eligible'] ?? false),
                'overtime_rate_multiplier' => $data['overtime_rate_multiplier'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($compensation) {
                $compensation->update($payload);
                $action = 'compensation_updated';
                $description = __('Employee compensation updated.');
            } else {
                $compensation = EmployeeCompensation::query()->create($payload);
                $action = 'compensation_created';
                $description = __('Employee compensation created.');
            }

            $this->activityLogService->log(
                'workforce',
                $action,
                $description,
                $actor->id,
                $compensation,
                [
                    'employee_profile_id' => $lockedEmployee->id,
                    'pay_basis' => $compensation->pay_basis,
                    'currency' => $compensation->currency,
                ]
            );

            return $compensation->fresh();
        });
    }
}
