<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveAdjustment;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\LeaveBalanceService;
use App\Services\Workforce\WorkShiftService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceLeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manager_can_create_leave_type_with_normalized_code_and_audit(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = $this->staffWithRole('operations_manager');

        $this->actingAs($manager)
            ->post(route('admin.workforce.leave-types.store'), [
                'code' => ' annual ',
                'name' => 'Annual Leave',
                'name_ar' => 'إجازة سنوية',
                'is_paid' => 1,
                'default_annual_entitlement_days' => 21,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.workforce.leave-types.index'))
            ->assertSessionHas('success');

        $type = EmployeeLeaveType::query()->firstOrFail();

        $this->assertSame('ANNUAL', $type->code);
        $this->assertSame('21.00', $type->default_annual_entitlement_days);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $manager->id,
            'action' => 'leave_type_created',
            'subject_id' => $type->id,
        ]);
    }

    public function test_leave_balance_uses_entitlement_adjustments_approved_usage_and_pending_projection(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-LEAVE-BAL');
        $type = $this->leaveType('ANNUAL', 20);

        EmployeeLeaveAdjustment::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'year' => 2026,
            'type' => EmployeeLeaveAdjustment::TYPE_OPENING,
            'days' => 5,
            'reason' => 'Opening balance import',
            'created_by_user_id' => $manager->id,
        ]);

        $pending = EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-12',
            'requested_days' => 3,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);

        $balance = app(LeaveBalanceService::class)->balance($employee, $type, 2026);

        $this->assertSame(20.0, $balance['entitlement']);
        $this->assertSame(5.0, $balance['adjustments']);
        $this->assertSame(0.0, $balance['used']);
        $this->assertSame(3.0, $balance['pending']);
        $this->assertSame(25.0, $balance['available']);
        $this->assertSame(22.0, $balance['projected_available']);

        $this->actingAs($manager)
            ->patch(route('admin.workforce.leave.approve', $pending))
            ->assertRedirect(route('admin.workforce.leave.index'))
            ->assertSessionHas('success');

        $balance = app(LeaveBalanceService::class)->balance($employee, $type, 2026);

        $this->assertSame(3.0, $balance['used']);
        $this->assertSame(0.0, $balance['pending']);
        $this->assertSame(22.0, $balance['available']);
        $this->assertSame(22.0, $balance['projected_available']);
    }

    public function test_employee_leave_request_uses_inclusive_days_rejects_overlap_and_cross_year(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-LEAVE-REQ');
        $type = $this->leaveType('ANNUAL', 30);

        $this->actingAs($cashier)
            ->post(route('admin.workforce.leave.request'), [
                'employee_leave_type_id' => $type->id,
                'starts_on' => '2026-10-10',
                'ends_on' => '2026-10-12',
                'reason' => 'Family trip',
            ])
            ->assertRedirect(route('admin.workforce.my-leave'))
            ->assertSessionHas('success');

        $request = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('3.00', $request->requested_days);
        $this->assertSame($employee->id, $request->employee_profile_id);

        $this->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-12',
            'ends_on' => '2026-10-14',
        ])->assertSessionHasErrors('starts_on');

        $this->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-12-31',
            'ends_on' => '2027-01-02',
        ])->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount('employee_leave_requests', 1);
    }

    public function test_leave_approval_requires_balance_and_resolved_schedule_conflicts(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-LEAVE-CONFLICT');
        $type = $this->leaveType('ANNUAL', 10);

        $leave = EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-12',
            'requested_days' => 3,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);

        $shift = app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-10-11 09:00:00',
            'ends_at' => '2026-10-11 17:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ], $manager);

        $this->actingAs($manager)
            ->patch(route('admin.workforce.leave.approve', $leave))
            ->assertSessionHasErrors('leave');

        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);

        app(WorkShiftService::class)->cancel($shift, $manager);

        $this->patch(route('admin.workforce.leave.approve', $leave))
            ->assertRedirect(route('admin.workforce.leave.index'))
            ->assertSessionHas('success');

        $this->assertSame(EmployeeLeaveRequest::STATUS_APPROVED, $leave->fresh()->status);

        $this->post(route('admin.workforce.schedule.store'), [
            'employee_profile_id' => $employee->id,
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-10 17:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ])->assertSessionHasErrors('starts_at');
    }

    public function test_leave_approval_is_blocked_when_available_balance_is_insufficient(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-LEAVE-LOW');
        $type = $this->leaveType('ANNUAL', 2);

        $leave = EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-12',
            'requested_days' => 3,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);

        $this->actingAs($manager)
            ->patch(route('admin.workforce.leave.approve', $leave))
            ->assertSessionHasErrors('leave');

        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
    }

    public function test_leave_adjustments_are_append_only_audited_and_cannot_drive_available_balance_negative(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-LEAVE-ADJ');
        $type = $this->leaveType('ANNUAL', 5);

        $this->actingAs($manager)
            ->post(route('admin.workforce.leave.adjust'), [
                'employee_profile_id' => $employee->id,
                'employee_leave_type_id' => $type->id,
                'year' => 2026,
                'type' => EmployeeLeaveAdjustment::TYPE_CARRYOVER,
                'days' => 2,
                'reason' => 'Carryover from previous year',
            ])
            ->assertRedirect(route('admin.workforce.leave.index'))
            ->assertSessionHas('success');

        $adjustment = EmployeeLeaveAdjustment::query()->firstOrFail();
        $this->assertSame('2.00', $adjustment->days);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $manager->id,
            'action' => 'leave_balance_adjusted',
            'subject_id' => $adjustment->id,
        ]);

        $this->post(route('admin.workforce.leave.adjust'), [
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'year' => 2026,
            'type' => EmployeeLeaveAdjustment::TYPE_ADJUSTMENT,
            'days' => -8,
            'reason' => 'Invalid excessive debit',
        ])->assertSessionHasErrors('days');

        $this->assertDatabaseCount('employee_leave_adjustments', 1);
    }

    public function test_employee_can_cancel_only_own_pending_leave_request(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-LEAVE-CANCEL');
        $other = $this->staffWithRole('cashier');
        $otherEmployee = $this->employeeFor($other, 'EMP-LEAVE-OTHER');
        $type = $this->leaveType('ANNUAL', 10);

        $own = $this->pendingLeave($employee, $type, '2026-10-10', '2026-10-11', 2);
        $otherRequest = $this->pendingLeave($otherEmployee, $type, '2026-10-15', '2026-10-16', 2);

        $this->actingAs($cashier)
            ->patch(route('admin.workforce.leave.cancel', $otherRequest))
            ->assertSessionHasErrors('leave');

        $this->patch(route('admin.workforce.leave.cancel', $own))
            ->assertRedirect(route('admin.workforce.my-leave'))
            ->assertSessionHas('success');

        $this->assertSame(EmployeeLeaveRequest::STATUS_CANCELLED, $own->fresh()->status);
        $this->assertNotNull($own->fresh()->cancelled_at);
    }

    public function test_manager_leave_review_is_live_and_cashier_cannot_access_it(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $cashier->update(['name' => '<script>Leave Employee</script>']);
        $employee = $this->employeeFor($cashier, 'EMP-LEAVE-LIVE');
        $type = $this->leaveType('ANNUAL', 20);

        $this->pendingLeave($employee, $type, '2026-10-10', '2026-10-12', 3, '<script>Family</script>');

        $url = route('admin.workforce.leave.index', [
            'search' => 'EMP-LEAVE-LIVE',
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
            'type' => $type->id,
        ]);

        $this->actingAs($manager)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('&lt;script&gt;Leave Employee&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;Family&lt;/script&gt;', false);

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Family&lt;/script&gt;', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)
            ->get(route('admin.workforce.leave.index'))
            ->assertForbidden();

        $this->get(route('admin.workforce.my-leave'))
            ->assertOk();
    }

    private function staffWithRole(string $slug): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function employeeFor(User $user, string $code): EmployeeProfile
    {
        return EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => $code,
            'job_title' => 'Team Member',
            'department' => 'Retail',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'hire_date' => now()->subMonth()->toDateString(),
        ]);
    }

    private function leaveType(string $code, float $entitlement): EmployeeLeaveType
    {
        return EmployeeLeaveType::query()->create([
            'code' => $code,
            'name' => 'Annual Leave '.$code,
            'is_paid' => true,
            'default_annual_entitlement_days' => $entitlement,
            'is_active' => true,
        ]);
    }

    private function pendingLeave(
        EmployeeProfile $employee,
        EmployeeLeaveType $type,
        string $start,
        string $end,
        float $days,
        ?string $reason = null,
    ): EmployeeLeaveRequest {
        return EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => $start,
            'ends_on' => $end,
            'requested_days' => $days,
            'reason' => $reason,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);
    }
}
