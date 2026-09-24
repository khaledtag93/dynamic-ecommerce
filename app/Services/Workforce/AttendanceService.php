<?php

namespace App\Services\Workforce;

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function clockIn(User $user, ?string $notes = null): EmployeeAttendanceSession
    {
        return DB::transaction(function () use ($user, $notes) {
            $employee = EmployeeProfile::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $employee) {
                throw ValidationException::withMessages([
                    'attendance' => __('Your employee profile is not configured yet.'),
                ]);
            }

            if (! $employee->canClockTime()) {
                throw ValidationException::withMessages([
                    'attendance' => __('Your employee status does not allow time clock actions.'),
                ]);
            }

            $openSession = EmployeeAttendanceSession::query()
                ->where('employee_profile_id', $employee->id)
                ->whereNull('clock_out_at')
                ->lockForUpdate()
                ->first();

            if ($openSession) {
                throw ValidationException::withMessages([
                    'attendance' => __('You are already clocked in.'),
                ]);
            }

            $session = EmployeeAttendanceSession::query()->create([
                'employee_profile_id' => $employee->id,
                'clock_in_at' => now(),
                'source' => 'admin',
                'clock_in_notes' => $this->cleanNotes($notes),
            ]);

            $this->activityLogService->log(
                'workforce',
                'employee_clocked_in',
                __('Employee clocked in.'),
                $user->id,
                $session,
                ['employee_profile_id' => $employee->id]
            );

            return $session;
        });
    }

    public function clockOut(User $user, ?string $notes = null): EmployeeAttendanceSession
    {
        return DB::transaction(function () use ($user, $notes) {
            $employee = EmployeeProfile::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $employee) {
                throw ValidationException::withMessages([
                    'attendance' => __('Your employee profile is not configured yet.'),
                ]);
            }

            $session = EmployeeAttendanceSession::query()
                ->where('employee_profile_id', $employee->id)
                ->whereNull('clock_out_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'attendance' => __('You do not have an open attendance session.'),
                ]);
            }

            $session->update([
                'clock_out_at' => now(),
                'clock_out_notes' => $this->cleanNotes($notes),
            ]);

            $session = $session->fresh();

            $this->activityLogService->log(
                'workforce',
                'employee_clocked_out',
                __('Employee clocked out.'),
                $user->id,
                $session,
                [
                    'employee_profile_id' => $employee->id,
                    'duration_minutes' => $session->durationMinutes(),
                ]
            );

            return $session;
        });
    }

    private function cleanNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }
}
