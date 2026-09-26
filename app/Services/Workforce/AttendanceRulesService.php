<?php

namespace App\Services\Workforce;

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeWorkShift;

class AttendanceRulesService
{
    public const START_GRACE_MINUTES = 5;
    public const END_GRACE_MINUTES = 5;

    public function assessShift(EmployeeWorkShift $shift, ?EmployeeAttendanceSession $session): array
    {
        if ($shift->isCancelled()) {
            return $this->emptyAssessment('cancelled');
        }

        if (! $shift->isPublished()) {
            return $this->emptyAssessment('draft');
        }

        if (! $session) {
            if ($shift->ends_at->isPast()) {
                return $this->emptyAssessment('absent');
            }

            if ($shift->starts_at->isPast()) {
                return $this->emptyAssessment('not_clocked_in');
            }

            return $this->emptyAssessment('upcoming');
        }

        $clockIn = $session->effectiveClockInAt();
        $clockOut = $session->effectiveClockOutAt();
        $startDelta = (int) $shift->starts_at->diffInMinutes($clockIn, false);

        $startStatus = 'on_time';
        if ($startDelta > self::START_GRACE_MINUTES) {
            $startStatus = 'late';
        } elseif ($startDelta < -self::START_GRACE_MINUTES) {
            $startStatus = 'early';
        }

        $endStatus = $clockOut ? 'on_time' : 'open';
        $endDelta = null;

        if ($clockOut) {
            $endDelta = (int) $shift->ends_at->diffInMinutes($clockOut, false);

            if ($endDelta < -self::END_GRACE_MINUTES) {
                $endStatus = 'early_departure';
            } elseif ($endDelta > self::END_GRACE_MINUTES) {
                $endStatus = 'after_shift';
            }
        }

        return [
            'state' => $clockOut ? 'completed' : 'open',
            'start_status' => $startStatus,
            'start_delta_minutes' => $startDelta,
            'end_status' => $endStatus,
            'end_delta_minutes' => $endDelta,
            'gross_minutes' => $session->effectiveDurationMinutes(),
            'break_minutes' => $session->breakMinutes(),
            'net_minutes' => $session->netWorkedMinutes(),
        ];
    }

    private function emptyAssessment(string $state): array
    {
        return [
            'state' => $state,
            'start_status' => null,
            'start_delta_minutes' => null,
            'end_status' => null,
            'end_delta_minutes' => null,
            'gross_minutes' => null,
            'break_minutes' => null,
            'net_minutes' => null,
        ];
    }
}
