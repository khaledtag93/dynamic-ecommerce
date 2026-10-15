<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\WorkforceAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceScopedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_only_see_own_reporting_tree_in_workforce_lists_and_metrics(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        [$supervisor, $lead, $worker, $outsider] = $this->tree();

        $this->assertFalse($supervisor->hasPermission('workforce.scope.all'));
        $this->assertFalse($supervisor->hasPermission('workforce.payroll.view'));
        $this->assertTrue(app(WorkforceAccessService::class)->canView($supervisor, $worker));
        $this->assertFalse(app(WorkforceAccessService::class)->canView($supervisor, $outsider));

        EmployeeAttendanceSession::create(['employee_profile_id' => $worker->id, 'clock_in_at' => now()->subHour(), 'source' => 'admin']);
        EmployeeAttendanceSession::create(['employee_profile_id' => $outsider->id, 'clock_in_at' => now()->subHour(), 'source' => 'admin']);

        $this->actingAs($supervisor)
            ->get(route('admin.workforce.employees.index'))
            ->assertOk()
            ->assertSee($worker->employee_code)
            ->assertSee($lead->employee_code)
            ->assertDontSee($outsider->employee_code);

        $this->get(route('admin.workforce.attendance.index'))
            ->assertOk()
            ->assertSee($worker->employee_code)
            ->assertDontSee($outsider->employee_code);

        $this->get(route('admin.workforce.payroll.compensation.index'))->assertForbidden();
    }

    public function test_supervisor_cannot_mutate_outsiders_or_create_employee_or_change_leave_type(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        [$supervisor, $lead, $worker, $outsider] = $this->tree();

        $this->actingAs($supervisor)
            ->get(route('admin.workforce.employees.create'))->assertForbidden();

        $this->get(route('admin.workforce.employees.edit', $outsider))->assertForbidden();
        $this->put(route('admin.workforce.employees.update', $outsider), $this->profileForm($outsider))->assertForbidden();

        $this->get(route('admin.workforce.employees.edit', $worker))->assertOk();
        $this->put(route('admin.workforce.employees.update', $worker), $this->profileForm($worker))
            ->assertRedirect()->assertSessionHas('success');

        $this->put(route('admin.workforce.employees.update', $worker), [
            ...$this->profileForm($worker),
            'manager_employee_profile_id' => $outsider->id,
        ])->assertForbidden();

        $this->get(route('admin.workforce.leave-types.index'))->assertForbidden();
        $this->get(route('admin.workforce.leave-types.create'))->assertForbidden();
        $this->get(route('admin.workforce.leave.adjustment'))->assertForbidden();
        $this->get(route('admin.workforce.payroll.index'))->assertForbidden();
    }

    public function test_shift_and_leave_approvals_are_denied_for_other_teams_and_self(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        [$supervisor, $lead, $worker, $outsider] = $this->tree();

        $externalShift = EmployeeWorkShift::create([
            'employee_profile_id' => $outsider->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(7),
            'status' => EmployeeWorkShift::STATUS_DRAFT,
            'created_by_user_id' => $supervisor->id,
        ]);
        $this->actingAs($supervisor)
            ->get(route('admin.workforce.schedule.edit', $externalShift))->assertForbidden();
        $this->patch(route('admin.workforce.schedule.cancel', $externalShift))->assertForbidden();
        $this->get(route('admin.workforce.schedule.index'))->assertOk()->assertDontSee($outsider->employee_code);

        $type = EmployeeLeaveType::create([
            'code' => 'TEST-LEAVE',
            'name' => 'Test leave',
            'is_active' => true,
            'is_paid' => true,
            'default_annual_entitlement_days' => 20,
        ]);
        $outsideLeave = $this->leave($outsider, $type);
        $ownLeave = $this->leave($supervisor->employeeProfile()->firstOrFail(), $type);

        $this->get(route('admin.workforce.leave.index'))
            ->assertOk()->assertDontSee($outsider->employee_code);

        $this->patch(route('admin.workforce.leave.approve', $outsideLeave))->assertForbidden();
        $this->patch(route('admin.workforce.leave.approve', $ownLeave))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $outsideLeave->fresh()->status);
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $ownLeave->fresh()->status);
    }

    public function test_organization_override_is_an_explicit_permission_separate_from_payroll(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        [$supervisor, $lead, $worker, $outsider] = $this->tree();

        $orgAdmin = $this->staff('operations_manager');
        $this->assertTrue($orgAdmin->hasPermission('workforce.scope.all'));
        $this->assertFalse($orgAdmin->hasPermission('workforce.payroll.view'));

        $this->actingAs($orgAdmin)->get(route('admin.workforce.employees.index'))
            ->assertOk()->assertSee($outsider->employee_code);
        $this->get(route('admin.workforce.payroll.compensation.index'))->assertForbidden();
    }

    public function test_direct_shift_create_rejects_other_team_and_supervisor_self(): void
    {
        app(\App\Services\Auth\AuthorizationService::class)->syncDefaults();
        [$supervisor, $lead, $worker, $outsider] = $this->tree();
        $self = EmployeeProfile::query()->where('user_id', $supervisor->id)->firstOrFail();
        $payload = ['starts_at' => '2026-11-02 09:00:00', 'ends_at' => '2026-11-02 17:00:00'];
        foreach ([$outsider, $self] as $target) {
            try {
                app(\App\Services\Workforce\WorkShiftService::class)->create($target, $payload, $supervisor);
                $this->fail('Unauthorized shift create succeeded');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertDatabaseCount('employee_work_shifts', 0);
        $this->assertDatabaseMissing('admin_activity_logs', ['action' => 'work_shift_created']);
    }
    public function test_shift_update_denies_cross_team_and_self_targets(): void
    {
        app(\App\Services\Auth\AuthorizationService::class)->syncDefaults();
        [$supervisor, $lead, $worker, $outsider] = $this->tree();
        $self = EmployeeProfile::query()->where('user_id', $supervisor->id)->firstOrFail();
        $service = app(\App\Services\Workforce\WorkShiftService::class);
        $shift = $service->create($worker, ['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 17:00:00'], $supervisor);
        foreach ([$outsider, $self] as $target) {
            try {
                $service->update($shift, ['employee_profile_id' => $target->id], $supervisor);
                $this->fail('Unauthorized reassignment accepted');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
            $this->assertSame($worker->id, $shift->fresh()->employee_profile_id);
        }
        $this->assertDatabaseMissing('admin_activity_logs', ['action' => 'work_shift_updated', 'subject_id' => $shift->id]);
    }
    public function test_stale_shift_cannot_edit_current_owner_outside_team(): void
    {
        app(\App\Services\Auth\AuthorizationService::class)->syncDefaults();
        [$supervisor, $lead, $worker, $outsider] = $this->tree();
        $admin = $this->staff('operations_manager');
        $service = app(\App\Services\Workforce\WorkShiftService::class);
        $stale = $service->create($worker, ['starts_at' => '2026-11-04 09:00:00', 'ends_at' => '2026-11-04 17:00:00'], $supervisor);
        $service->update($stale, ['employee_profile_id' => $outsider->id], $admin);
        try {
            $service->update($stale, ['notes' => 'Unauthorized'], $supervisor);
            $this->fail('Stale ownership bypassed access checks');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame($outsider->id, $stale->fresh()->employee_profile_id);
        $this->assertNull($stale->fresh()->notes);
        $this->assertSame(1, \App\Models\AdminActivityLog::query()->where('action', 'work_shift_updated')->where('subject_id', $stale->id)->count());
    }
    private function tree(): array
    {
        $supervisor = $this->staff('team_supervisor');
        $leadUser = $this->staff('team_supervisor');
        $outsiderUser = $this->staff('cashier');
        $workerUser = $this->staff('cashier');

        $head = $this->employee($supervisor, 'EMP-SUPERVISOR');
        $lead = $this->employee($leadUser, 'EMP-LEAD', $head->id);
        $worker = $this->employee($workerUser, 'EMP-CHILD', $lead->id);
        $outsider = $this->employee($outsiderUser, 'EMP-PRIVATE');

        return [$supervisor, $lead, $worker, $outsider];
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $user->roles()->sync([Role::query()->where('slug', $role)->firstOrFail()->id]);

        return $user->fresh();
    }

    private function employee(User $user, string $code, ?int $managerId = null): EmployeeProfile
    {
        $employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'employee_code' => $code,
            'manager_employee_profile_id' => $managerId,
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ]);
        // Relationship was intentionally left off of the original mass-assignable
        // employee profile: direct use a separate trusted assignment.
        if ($managerId !== null && (int) $employee->manager_employee_profile_id !== $managerId) {
            $employee->manager_employee_profile_id = $managerId;
            $employee->save();
        }

        return $employee;
    }

    private function leave(EmployeeProfile $employee, EmployeeLeaveType $type): EmployeeLeaveRequest
    {
        return EmployeeLeaveRequest::create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => now()->addMonth()->startOfDay()->toDateString(),
            'ends_on' => now()->addMonth()->startOfDay()->toDateString(),
            'requested_days' => 1,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);
    }

    private function profileForm(EmployeeProfile $employee): array
    {
        return [
            'employee_code' => $employee->employee_code,
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ];
    }
}
