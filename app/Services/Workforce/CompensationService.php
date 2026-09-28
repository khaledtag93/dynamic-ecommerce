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

            $start = Carbon::parse($data['effective_from'])->startOfDay();
            $end = ! empty($data['effective_to'])
                ? Carbon::parse($data['effective_to'])->startOfDay()
                : null;

            $history = EmployeeCompensation::query()
                ->where('employee_profile_id', $lockedEmployee->id)
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->get();

            $sameStart = $history->first(
                fn (EmployeeCompensation $item) => $item->effective_from->isSameDay($start)
            );
            $next = $history->first(
                fn (EmployeeCompensation $item) => $item->effective_from->gt($start)
            );

            if ($next && $end && $end->gte($next->effective_from)) {
                throw ValidationException::withMessages([
                    'effective_to' => __('Compensation periods cannot overlap. End this rate before the next effective compensation begins.'),
                ]);
            }

            if ($next && ! $end) {
                $end = $next->effective_from->copy()->subDay();
            }

            $payload = [
                'employee_profile_id' => $lockedEmployee->id,
                'pay_basis' => $data['pay_basis'],
                'base_rate' => $data['base_rate'],
                'currency' => $data['currency'],
                'effective_from' => $start->toDateString(),
                'effective_to' => $end?->toDateString(),
                'overtime_eligible' => (bool) ($data['overtime_eligible'] ?? false),
                'overtime_rate_multiplier' => $data['overtime_rate_multiplier'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($sameStart) {
                $sameStart->update($payload);
                $compensation = $sameStart;
                $action = 'compensation_updated';
                $description = __('Employee compensation updated.');
            } else {
                $previous = $history
                    ->filter(fn (EmployeeCompensation $item) => $item->effective_from->lt($start))
                    ->last();

                if ($previous && (! $previous->effective_to || $previous->effective_to->gte($start))) {
                    $previous->update([
                        'effective_to' => $start->copy()->subDay()->toDateString(),
                    ]);
                }

                $compensation = EmployeeCompensation::query()->create($payload);
                $action = 'compensation_created';
                $description = __('Employee compensation history created.');
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
