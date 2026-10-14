<?php

namespace App\Services\Workforce;

use App\Models\EmployeeLeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class LeavePolicyService
{
    /**
     * Date-only leave policy. Local calendar dates (no UTC timestamp conversion).
     * Returns inclusive start/end and charged number of days.
     */
    public function resolve(EmployeeLeaveType $type, string $startsOn, ?string $endsOn): array
    {
        $start = $this->date($startsOn, 'starts_on');
        $mode = $type->duration_mode ?: 'legacy';

        if ($mode === 'legacy') {
            $end = $this->date($endsOn, 'ends_on');
            $this->guardRange($start, $end);

            return [$start, $end, $start->diffInDays($end) + 1];
        }

        if (! in_array($mode, ['fixed', 'flexible'], true)) {
            throw ValidationException::withMessages(['employee_leave_type_id' => __('Invalid leave policy configuration.')]);
        }

        $workdays = $type->counting_mode === 'workdays';
        $weekdays = array_map('intval', $type->working_weekdays ?? []);
        $holidays = $type->holiday_dates ?? [];
        if (! in_array($type->counting_mode, ['calendar', 'workdays'], true) ||
            ($workdays && ($weekdays === [] || array_diff($weekdays, [1, 2, 3, 4, 5, 6, 7])))) {
            throw ValidationException::withMessages(['employee_leave_type_id' => __('Invalid leave policy configuration.')]);
        }

        if ($mode === 'fixed') {
            $fixed = (int) $type->fixed_duration_days;
            if ($fixed < 1 || $fixed > 365) {
                throw ValidationException::withMessages(['employee_leave_type_id' => __('Invalid leave policy configuration.')]);
            }

            $end = $start;
            $charged = 0;
            // The existing leave-request contract does not permit crossing years.
            while ($end->year === $start->year) {
                if ($this->isChargeable($end, $workdays, $weekdays, $holidays)) {
                    $charged++;
                }
                if ($charged === $fixed) {
                    break;
                }
                $end = $end->addDay();
            }
            if ($charged !== $fixed) {
                throw ValidationException::withMessages(['starts_on' => __('The selected dates are not valid for this leave policy.')]);
            }
            if ($endsOn !== null && trim($endsOn) !== '' && $this->date($endsOn, 'ends_on')->toDateString() !== $end->toDateString()) {
                throw ValidationException::withMessages(['ends_on' => __('The end date is determined by the selected fixed-duration leave policy.')]);
            }
        } else {
            $end = $this->date($endsOn, 'ends_on');
            $this->guardRange($start, $end);
            $min = (int) $type->min_request_days;
            $max = (int) $type->max_request_days;
            if ($min < 1 || $max < $min || $max > 365) {
                throw ValidationException::withMessages(['employee_leave_type_id' => __('Invalid leave policy configuration.')]);
            }
            $charged = $this->countChargeable($start, $end, $workdays, $weekdays, $holidays);
            if ($charged < $min || $charged > $max) {
                throw ValidationException::withMessages(['ends_on' => __('Requested leave duration is outside the configured limits.')]);
            }
        }

        $this->guardRange($start, $end);

        return [$start, $end, $mode === 'fixed' ? $fixed : $charged];
    }

    /**
     * Store the request-time calculation inputs, never the live type reference
     * as a substitute for history. Effective-date policy is intentionally not
     * inferred here; that requires a separate approved product decision.
     */
    public function snapshot(EmployeeLeaveType $type): array
    {
        $mode = $type->duration_mode ?: 'legacy';
        $counting = $mode === 'legacy' ? 'calendar' : ($type->counting_mode ?: 'calendar');
        $weekdays = $counting === 'workdays' ? array_map('intval', $type->working_weekdays ?? []) : [];
        $holidays = $counting === 'workdays' ? array_values($type->holiday_dates ?? []) : [];
        sort($weekdays, SORT_NUMERIC);
        sort($holidays, SORT_STRING);

        return [
            'schema_version' => 1,
            'type_code' => (string) $type->code,
            'duration_mode' => $mode,
            'counting_mode' => $counting,
            'fixed_duration_days' => $mode === 'fixed' ? (int) $type->fixed_duration_days : null,
            'min_request_days' => $mode === 'flexible' ? (int) $type->min_request_days : null,
            'max_request_days' => $mode === 'flexible' ? (int) $type->max_request_days : null,
            'working_weekdays' => $weekdays,
            'holiday_dates' => $holidays,
        ];
    }

    private function countChargeable(CarbonImmutable $start, CarbonImmutable $end, bool $workdays, array $weekdays, array $holidays): int
    {
        $days = 0;
        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $days += (int) $this->isChargeable($day, $workdays, $weekdays, $holidays);
        }

        return $days;
    }

    private function isChargeable(CarbonImmutable $day, bool $workdays, array $weekdays, array $holidays): bool
    {
        return ! $workdays || (in_array($day->dayOfWeekIso, $weekdays, true) &&
            ! in_array($day->toDateString(), $holidays, true));
    }

    private function guardRange(CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['ends_on' => __('Leave end date must be on or after the start date.')]);
        }
        if ($start->year !== $end->year) {
            throw ValidationException::withMessages(['ends_on' => __('Leave requests cannot cross calendar years in this version.')]);
        }
    }

    private function date(?string $value, string $field): CarbonImmutable
    {
        $value = trim((string) $value);
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            $date = null;
        }
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => __('Enter a valid leave date.')]);
        }

        return $date;
    }
}
