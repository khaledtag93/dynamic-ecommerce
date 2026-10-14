<?php

namespace App\Services\Workforce;

use App\Models\EmployeeAttendanceSession;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use App\Support\WorkforceClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceVoidService
{
    public function __construct(private AdminActivityLogService $activityLogService)
    {
    }

    public function void(EmployeeAttendanceSession $session, User $actor, string $reason): EmployeeAttendanceSession
    {
        return DB::transaction(function () use ($session, $actor, $reason) {
            $locked = EmployeeAttendanceSession::query()
                ->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw ValidationException::withMessages(['void_reason' => __('This session is already voided.')]);
            }

            if ($locked->isOpen()) {
                throw ValidationException::withMessages(['void_reason' => __('Clock out before voiding an attendance session.')]);
            }

            if ($locked->pendingCorrection()->exists()) {
                throw ValidationException::withMessages(['void_reason' => __('Resolve the pending correction before voiding attendance.')]);
            }

            // Payroll is a financial snapshot. Refuse mutation when a snapshot
            // exists for this employee and overlapping local work dates, even Draft.
            $firstDate = WorkforceClock::local($locked->effectiveClockInAt(), 'Y-m-d');
            $lastDate = WorkforceClock::local($locked->effectiveClockOutAt(), 'Y-m-d');
            $isSnapshotted = PayrollRun::query()
                ->whereHas('entries', fn ($q) => $q->where('employee_profile_id', $locked->employee_profile_id))
                // A day of margin protects legacy UTC-day payroll snapshots
                // straddling a local midnight during timezone migration.
                ->whereHas('period', fn ($q) => $q
                    ->where('starts_on', '<=', \Carbon\CarbonImmutable::parse($lastDate)->addDay()->toDateString())
                    ->where('ends_on', '>=', \Carbon\CarbonImmutable::parse($firstDate)->subDay()->toDateString()))
                ->exists();

            if ($isSnapshotted) {
                throw ValidationException::withMessages(['void_reason' => __('Attendance cannot be voided after its payroll period has a generated snapshot.')]);
            }

            $locked->update([
                'voided_at' => now(),
                'voided_by_user_id' => $actor->id,
                'void_reason' => trim($reason),
            ]);

            $this->activityLogService->log(
                'workforce',
                'attendance_session_voided',
                __('Attendance session voided.'),
                $actor->id,
                $locked,
                [
                    'employee_profile_id' => $locked->employee_profile_id,
                    'recorded_clock_in_at' => $locked->clock_in_at?->toIso8601String(),
                    'recorded_clock_out_at' => $locked->clock_out_at?->toIso8601String(),
                    'reason' => trim($reason),
                ]
            );

            return $locked->fresh();
        });
    }
}
