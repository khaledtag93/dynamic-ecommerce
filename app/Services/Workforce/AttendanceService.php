<?php

namespace App\Services\Workforce;

use App\Models\EmployeeAttendanceBreak;
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

            $openBreak = EmployeeAttendanceBreak::query()
                ->where('employee_attendance_session_id', $session->id)
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->first();

            if ($openBreak) {
                throw ValidationException::withMessages([
                    'attendance' => __('End your active break before clocking out.'),
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

    public function startBreak(User $user, ?string $notes = null): EmployeeAttendanceBreak
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
                    'attendance' => __('Clock in before starting a break.'),
                ]);
            }

            $openBreak = EmployeeAttendanceBreak::query()
                ->where('employee_attendance_session_id', $session->id)
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->first();

            if ($openBreak) {
                throw ValidationException::withMessages([
                    'attendance' => __('You already have an active break.'),
                ]);
            }

            $break = EmployeeAttendanceBreak::query()->create([
                'employee_attendance_session_id' => $session->id,
                'starts_at' => now(),
                'notes' => $this->cleanNotes($notes),
            ]);

            $this->activityLogService->log(
                'workforce',
                'attendance_break_started',
                __('Attendance break started.'),
                $user->id,
                $break,
                [
                    'employee_profile_id' => $employee->id,
                    'attendance_session_id' => $session->id,
                ]
            );

            return $break;
        });
    }

    public function endBreak(User $user): EmployeeAttendanceBreak
    {
        return DB::transaction(function () use ($user) {
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

            $break = EmployeeAttendanceBreak::query()
                ->where('employee_attendance_session_id', $session->id)
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $break) {
                throw ValidationException::withMessages([
                    'attendance' => __('You do not have an active break.'),
                ]);
            }

            $break->update(['ends_at' => now()]);
            $break = $break->fresh();

            $this->activityLogService->log(
                'workforce',
                'attendance_break_ended',
                __('Attendance break ended.'),
                $user->id,
                $break,
                [
                    'employee_profile_id' => $employee->id,
                    'attendance_session_id' => $session->id,
                    'duration_minutes' => $break->durationMinutes(),
                ]
            );

            return $break;
        });
    }

    private function cleanNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }
}
