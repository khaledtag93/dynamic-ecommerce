<?php

namespace App\Services\Workforce;

use App\Models\EmployeeAttendanceCorrection;
use App\Models\EmployeeAttendanceSession;
use App\Models\User;
use App\Services\Commerce\AdminActivityLogService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionService
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function request(
        User $user,
        EmployeeAttendanceSession $session,
        CarbonInterface|string $requestedClockInAt,
        CarbonInterface|string|null $requestedClockOutAt,
        string $reason,
    ): EmployeeAttendanceCorrection {
        return DB::transaction(function () use ($user, $session, $requestedClockInAt, $requestedClockOutAt, $reason) {
            $employee = $user->employeeProfile()->lockForUpdate()->first();

            if (! $employee || (int) $session->employee_profile_id !== (int) $employee->id) {
                throw ValidationException::withMessages([
                    'correction' => __('You can only request corrections for your own attendance sessions.'),
                ]);
            }

            $lockedSession = EmployeeAttendanceSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->isOpen()) {
                throw ValidationException::withMessages([
                    'correction' => __('Clock out before requesting a correction for this attendance session.'),
                ]);
            }

            $pendingExists = EmployeeAttendanceCorrection::query()
                ->where('employee_attendance_session_id', $lockedSession->id)
                ->where('status', EmployeeAttendanceCorrection::STATUS_PENDING)
                ->lockForUpdate()
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'correction' => __('This attendance session already has a pending correction request.'),
                ]);
            }

            $previousClockIn = $lockedSession->effectiveClockInAt();
            $previousClockOut = $lockedSession->effectiveClockOutAt();

            $requestedClockInAt = $requestedClockInAt instanceof CarbonInterface
                ? $requestedClockInAt
                : Carbon::parse($requestedClockInAt);
            $requestedClockOutAt = $requestedClockOutAt
                ? ($requestedClockOutAt instanceof CarbonInterface ? $requestedClockOutAt : Carbon::parse($requestedClockOutAt))
                : null;

            if ($requestedClockOutAt && $requestedClockOutAt->lte($requestedClockInAt)) {
                throw ValidationException::withMessages([
                    'requested_clock_out_at' => __('Corrected clock-out must be after corrected clock-in.'),
                ]);
            }

            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => __('Explain why the attendance correction is needed.'),
                ]);
            }

            $correction = EmployeeAttendanceCorrection::query()->create([
                'employee_attendance_session_id' => $lockedSession->id,
                'employee_profile_id' => $employee->id,
                'previous_clock_in_at' => $previousClockIn,
                'previous_clock_out_at' => $previousClockOut,
                'requested_clock_in_at' => $requestedClockInAt,
                'requested_clock_out_at' => $requestedClockOutAt,
                'reason' => $reason,
                'status' => EmployeeAttendanceCorrection::STATUS_PENDING,
            ]);

            $this->activityLogService->log(
                'workforce',
                'attendance_correction_requested',
                __('Attendance correction requested.'),
                $user->id,
                $correction,
                [
                    'employee_profile_id' => $employee->id,
                    'attendance_session_id' => $lockedSession->id,
                ]
            );

            return $correction;
        });
    }

    public function approve(EmployeeAttendanceCorrection $correction, User $reviewer, ?string $notes = null): EmployeeAttendanceCorrection
    {
        return $this->review($correction, $reviewer, EmployeeAttendanceCorrection::STATUS_APPROVED, $notes);
    }

    public function reject(EmployeeAttendanceCorrection $correction, User $reviewer, ?string $notes = null): EmployeeAttendanceCorrection
    {
        return $this->review($correction, $reviewer, EmployeeAttendanceCorrection::STATUS_REJECTED, $notes);
    }

    private function review(
        EmployeeAttendanceCorrection $correction,
        User $reviewer,
        string $status,
        ?string $notes,
    ): EmployeeAttendanceCorrection {
        return DB::transaction(function () use ($correction, $reviewer, $status, $notes) {
            $locked = EmployeeAttendanceCorrection::query()
                ->whereKey($correction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'correction' => __('Only pending correction requests can be reviewed.'),
                ]);
            }

            $locked->update([
                'status' => $status,
                'review_notes' => $this->nullableTrim($notes),
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $action = $status === EmployeeAttendanceCorrection::STATUS_APPROVED
                ? 'attendance_correction_approved'
                : 'attendance_correction_rejected';

            $description = $status === EmployeeAttendanceCorrection::STATUS_APPROVED
                ? __('Attendance correction approved.')
                : __('Attendance correction rejected.');

            $this->activityLogService->log(
                'workforce',
                $action,
                $description,
                $reviewer->id,
                $locked,
                [
                    'employee_profile_id' => $locked->employee_profile_id,
                    'attendance_session_id' => $locked->employee_attendance_session_id,
                ]
            );

            return $locked->fresh();
        });
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
