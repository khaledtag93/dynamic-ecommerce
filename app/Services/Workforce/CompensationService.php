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

            $payBasis = (string) ($data['pay_basis'] ?? '');
            if (! array_key_exists($payBasis, EmployeeCompensation::payBasisOptions())) {
                throw ValidationException::withMessages([
                    'pay_basis' => __('Choose a valid payroll pay basis.'),
                ]);
            }

            $currency = strtoupper(trim((string) ($data['currency'] ?? '')));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw ValidationException::withMessages([
                    'currency' => __('Enter a valid three-letter ISO currency code.'),
                ]);
            }

            if (blank($data['effective_from'] ?? null)) {
                throw ValidationException::withMessages([
                    'effective_from' => __('Enter a valid compensation effective date.'),
                ]);
            }

            try {
                $start = Carbon::parse((string) $data['effective_from'])->startOfDay();
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'effective_from' => __('Enter a valid compensation effective date.'),
                ]);
            }

            $end = null;
            if (! empty($data['effective_to'])) {
                try {
                    $end = Carbon::parse((string) $data['effective_to'])->startOfDay();
                } catch (\Throwable) {
                    throw ValidationException::withMessages([
                        'effective_to' => __('Enter a valid compensation end date.'),
                    ]);
                }
            }

            if ($end && $end->lt($start)) {
                throw ValidationException::withMessages([
                    'effective_to' => __('Compensation end date must be on or after the effective start date.'),
                ]);
            }

            $overtimeEligible = (bool) ($data['overtime_eligible'] ?? false);
            $multiplier = $data['overtime_rate_multiplier'] ?? null;
            if ($overtimeEligible && filled($multiplier)) {
                $multiplierText = trim((string) $multiplier);

                if (! preg_match('/^\d{1,2}(?:\.\d{1,3})?$/', $multiplierText)
                    || (float) $multiplierText <= 0
                    || (float) $multiplierText > 10) {
                    throw ValidationException::withMessages([
                        'overtime_rate_multiplier' => __('Enter an overtime multiplier up to 10 with at most three decimal places.'),
                    ]);
                }

                $multiplier = $multiplierText;
            }

            $baseRateText = trim((string) ($data['base_rate'] ?? ''));
            if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $baseRateText)
                || $this->moneyToCents($baseRateText) < 1) {
                throw ValidationException::withMessages([
                    'base_rate' => __('Enter a base rate with up to two decimal places within the supported amount limit.'),
                ]);
            }

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
                'pay_basis' => $payBasis,
                'base_rate' => $this->centsToMoney($this->moneyToCents($baseRateText)),
                'currency' => $currency,
                'effective_from' => $start->toDateString(),
                'effective_to' => $end?->toDateString(),
                'overtime_eligible' => $overtimeEligible,
                'overtime_rate_multiplier' => $multiplier,
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

    private function moneyToCents(mixed $value): int
    {
        $money = trim((string) ($value ?? '0'));
        [$whole, $fraction] = array_pad(explode('.', $money, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
