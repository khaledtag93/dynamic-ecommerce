<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\LeaveRequestService;
use App\Services\Workforce\WorkShiftService;
use App\Support\WorkforceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkforceLeaveLocalDayBoundariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_approval_blocks_shift_from_previous_utc_date_inside_cairo_leave_day(): void
    {
        config()->set('workforce.timezone', 'Africa/Cairo');
        [$manager, $employee, $type] = $this->setupActors('CAIRO');
        $leave = $this->pendingLeave($employee, $type, '2026-10-14');

        // 13 Oct 22:00 UTC is 14 Oct 01:00 in Cairo; UTC-midnight
        // comparisons incorrectly let this shift through.
        $conflict = app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-10-13 22:00:00',
            'ends_at' => '2026-10-13 23:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ], $manager);

        $this->expectApprovalBlocked($leave, $manager);
        app(WorkShiftService::class)->cancel($conflict, $manager);

        // 14 Oct 22:00 UTC is 15 Oct 01:00 in Cairo: not the leave day.
        app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-10-14 22:00:00',
            'ends_at' => '2026-10-14 23:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ], $manager);

        $approved = app(LeaveRequestService::class)->approve($leave, $manager);
        $this->assertSame(EmployeeLeaveRequest::STATUS_APPROVED, $approved->status);
    }

    public function test_shift_guard_respects_exclusive_midnight_boundaries_in_cairo(): void
    {
        config()->set('workforce.timezone', 'Africa/Cairo');
        [$manager, $employee, $type] = $this->setupActors('CAIRO-SHIFT');
        $leave = $this->pendingLeave($employee, $type, '2026-10-14');
        $leave->update(['status' => EmployeeLeaveRequest::STATUS_APPROVED]);

        // Both boundaries of 14 Oct local: 13 Oct 21:00 -> 14 Oct 21:00 UTC.
        $this->assertSame(
            ['2026-10-13 21:00:00', '2026-10-14 21:00:00'],
            WorkforceClock::localDayUtcBounds('2026-10-14')
        );

        app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-10-13 20:00:00',
            'ends_at' => '2026-10-13 21:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ], $manager);

        $this->expectShiftBlocked($employee, $manager, '2026-10-13 21:00:00', '2026-10-13 22:00:00');

        app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-10-14 21:00:00',
            'ends_at' => '2026-10-14 22:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ], $manager);

        $this->assertSame(2, EmployeeWorkShift::query()->count());
    }

    public function test_new_york_spring_dst_leave_window_is_23_hours(): void
    {
        config()->set('workforce.timezone', 'America/New_York');
        [$manager, $employee, $type] = $this->setupActors('SPRING');
        $leave = $this->pendingLeave($employee, $type, '2026-03-08');
        $leave->update(['status' => EmployeeLeaveRequest::STATUS_APPROVED]);

        $this->assertSame(
            ['2026-03-08 05:00:00', '2026-03-09 04:00:00'],
            WorkforceClock::localDayUtcBounds('2026-03-08')
        );
        $this->expectShiftBlocked($employee, $manager, '2026-03-09 03:00:00', '2026-03-09 03:30:00');

        app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-03-09 04:00:00',
            'ends_at' => '2026-03-09 05:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ], $manager);
        $this->assertDatabaseCount('employee_work_shifts', 1);
    }

    public function test_new_york_fall_dst_leave_window_is_25_hours_and_approval_uses_it(): void
    {
        config()->set('workforce.timezone', 'America/New_York');
        [$manager, $employee, $type] = $this->setupActors('FALL');
        $leave = $this->pendingLeave($employee, $type, '2026-11-01');

        $this->assertSame(
            ['2026-11-01 04:00:00', '2026-11-02 05:00:00'],
            WorkforceClock::localDayUtcBounds('2026-11-01')
        );
        $shift = app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-11-02 04:15:00',
            'ends_at' => '2026-11-02 04:45:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ], $manager);

        $this->expectApprovalBlocked($leave, $manager);
        app(WorkShiftService::class)->cancel($shift, $manager);
        $this->assertSame(
            EmployeeLeaveRequest::STATUS_APPROVED,
            app(LeaveRequestService::class)->approve($leave, $manager)->status
        );
    }

    private function setupActors(string $code): array
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', 'operations_manager')->firstOrFail();
        $manager->roles()->sync([$role->id]);
        $worker = User::factory()->create(['role_as' => 1]);
        $employee = EmployeeProfile::query()->create([
            'user_id' => $worker->id,
            'employee_code' => 'QA-LEAVE-'.$code,
            'department' => 'QA',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ]);
        $type = EmployeeLeaveType::query()->create([
            'code' => 'LEAVE-'.$code,
            'name' => 'Local date boundary leave',
            'is_paid' => true,
            'default_annual_entitlement_days' => 10,
            'is_active' => true,
        ]);

        return [$manager, $employee, $type];
    }

    private function pendingLeave(EmployeeProfile $employee, EmployeeLeaveType $type, string $date): EmployeeLeaveRequest
    {
        return EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => $date,
            'ends_on' => $date,
            'requested_days' => 1,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);
    }

    private function expectApprovalBlocked(EmployeeLeaveRequest $leave, User $reviewer): void
    {
        try {
            app(LeaveRequestService::class)->approve($leave, $reviewer);
            $this->fail('Leave approval must be blocked by an overlapping local-day shift.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('leave', $exception->errors());
            $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
        }
    }

    private function expectShiftBlocked(EmployeeProfile $employee, User $manager, string $start, string $end): void
    {
        try {
            app(WorkShiftService::class)->create($employee, [
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => EmployeeWorkShift::STATUS_PUBLISHED,
            ], $manager);
            $this->fail('Shift must be blocked on approved leave in local calendar time.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('starts_at', $exception->errors());
        }
    }
}
