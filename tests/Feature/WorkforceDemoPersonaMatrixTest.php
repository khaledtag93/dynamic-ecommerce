<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\WorkforceAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seven entirely synthetic personas in the isolated PHPUnit database.
 * This class never connects to QAS or creates accounts on a running site.
 */
class WorkforceDemoPersonaMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_seven_personas_preserve_two_team_boundaries_and_separate_payroll_rights(): void
    {
        [$users, $staff] = $this->personas();
        $access = app(WorkforceAccessService::class);

        $this->assertEqualsCanonicalizing(
            [$staff['alpha_manager']->id, $staff['alpha_lead']->id, $staff['alpha_worker']->id],
            $access->visibleEmployeeIds($users['alpha_manager'])
        );
        $this->assertEqualsCanonicalizing(
            [$staff['alpha_lead']->id, $staff['alpha_worker']->id],
            $access->visibleEmployeeIds($users['alpha_lead'])
        );
        $this->assertEqualsCanonicalizing(
            [$staff['beta_manager']->id, $staff['beta_worker']->id],
            $access->visibleEmployeeIds($users['beta_manager'])
        );
        $this->assertEqualsCanonicalizing(
            array_map(fn (EmployeeProfile $profile) => $profile->id, array_values($staff)),
            $access->visibleEmployeeIds($users['operations_manager'])
        );

        EmployeeAttendanceSession::create([
            'employee_profile_id' => $staff['alpha_worker']->id,
            'clock_in_at' => now()->subHour(),
            'source' => 'admin',
        ]);
        EmployeeAttendanceSession::create([
            'employee_profile_id' => $staff['beta_worker']->id,
            'clock_in_at' => now()->subHour(),
            'source' => 'admin',
        ]);

        $this->actingAs($users['alpha_manager'])
            ->get(route('admin.workforce.employees.index'))
            ->assertOk()
            ->assertSee('QA-ALPHA-LEAD')
            ->assertSee('QA-ALPHA-WORKER')
            ->assertDontSee('QA-BETA-WORKER');
        $this->get(route('admin.workforce.attendance.index'))
            ->assertOk()
            ->assertSee('QA-ALPHA-WORKER')
            ->assertDontSee('QA-BETA-WORKER');
        $this->get(route('admin.workforce.payroll.compensation.index'))->assertForbidden();
        $this->get(route('admin.workforce.leave-types.index'))->assertForbidden();

        $this->actingAs($users['alpha_lead'])
            ->get(route('admin.workforce.employees.index'))
            ->assertOk()
            ->assertSee('QA-ALPHA-WORKER')
            ->assertDontSee('QA-BETA-WORKER');

        $this->actingAs($users['beta_manager'])
            ->get(route('admin.workforce.employees.index'))
            ->assertOk()
            ->assertSee('QA-BETA-WORKER')
            ->assertDontSee('QA-ALPHA-WORKER');

        $this->actingAs($users['alpha_worker'])
            ->get(route('admin.workforce.time-clock'))->assertOk();
        $this->get(route('admin.workforce.my-schedule'))->assertOk();
        $this->get(route('admin.workforce.my-leave'))->assertOk();
        $this->get(route('admin.workforce.employees.index'))->assertForbidden();
        $this->get(route('admin.workforce.payroll.compensation.index'))->assertForbidden();

        $this->actingAs($users['operations_manager'])
            ->get(route('admin.workforce.employees.index'))
            ->assertOk()
            ->assertSee('QA-ALPHA-WORKER')
            ->assertSee('QA-BETA-WORKER');
        $this->get(route('admin.workforce.leave-types.index'))->assertOk();
        $this->get(route('admin.workforce.payroll.compensation.index'))->assertForbidden();

        $this->actingAs($users['finance_manager'])
            ->get(route('admin.workforce.payroll.compensation.index'))
            ->assertOk()
            ->assertSee('QA-ALPHA-WORKER')
            ->assertSee('QA-BETA-WORKER');
        $this->get(route('admin.workforce.employees.index'))->assertForbidden();
        $this->get(route('admin.workforce.leave-types.index'))->assertForbidden();
    }

    public function test_team_manager_cannot_edit_other_team_or_approve_own_leave(): void
    {
        [$users, $staff] = $this->personas();
        $this->actingAs($users['alpha_manager']);

        $this->get(route('admin.workforce.employees.edit', $staff['beta_worker']))
            ->assertForbidden();
        $this->put(route('admin.workforce.employees.update', $staff['beta_worker']), [
            'employee_code' => 'QA-BETA-WORKER',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ])->assertForbidden();
        $this->get(route('admin.workforce.employees.edit', $staff['alpha_manager']))
            ->assertForbidden();

        $type = EmployeeLeaveType::create([
            'code' => 'QA-TEST-LEAVE',
            'name' => 'QA synthetic leave',
            'is_active' => true,
            'is_paid' => true,
            'default_annual_entitlement_days' => 20,
        ]);
        $outsideLeave = $this->leave($staff['beta_worker'], $type);
        $ownLeave = $this->leave($staff['alpha_manager'], $type);

        $this->patch(route('admin.workforce.leave.approve', $outsideLeave))->assertForbidden();
        $this->patch(route('admin.workforce.leave.approve', $ownLeave))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $outsideLeave->fresh()->status);
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $ownLeave->fresh()->status);

        $this->actingAs($users['beta_manager'])
            ->get(route('admin.workforce.employees.edit', $staff['alpha_worker']))
            ->assertForbidden();
    }

    /** @return array{0: array<string, User>, 1: array<string, EmployeeProfile>} */
    private function personas(): array
    {
        app(AuthorizationService::class)->syncDefaults();

        $roles = [
            'alpha_manager' => 'team_supervisor',
            'alpha_lead' => 'team_supervisor',
            'alpha_worker' => 'cashier',
            'beta_manager' => 'team_supervisor',
            'beta_worker' => 'cashier',
            'operations_manager' => 'operations_manager',
            'finance_manager' => 'finance_manager',
        ];
        $codes = [
            'alpha_manager' => 'QA-ALPHA-MANAGER',
            'alpha_lead' => 'QA-ALPHA-LEAD',
            'alpha_worker' => 'QA-ALPHA-WORKER',
            'beta_manager' => 'QA-BETA-MANAGER',
            'beta_worker' => 'QA-BETA-WORKER',
            'operations_manager' => 'QA-OPS-MANAGER',
            'finance_manager' => 'QA-FINANCE-MANAGER',
        ];

        $users = [];
        $staff = [];
        foreach ($roles as $key => $role) {
            $user = User::factory()->create(['role_as' => 1]);
            $user->roles()->sync([Role::query()->where('slug', $role)->firstOrFail()->id]);
            $users[$key] = $user->fresh();
            $staff[$key] = EmployeeProfile::create([
                'user_id' => $user->id,
                'employee_code' => $codes[$key],
                'department' => str_starts_with($key, 'alpha_') ? 'QA Alpha' : (str_starts_with($key, 'beta_') ? 'QA Beta' : 'QA Shared'),
                'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
                'status' => EmployeeProfile::STATUS_ACTIVE,
            ]);
        }

        foreach ([
            'alpha_lead' => 'alpha_manager',
            'alpha_worker' => 'alpha_lead',
            'beta_worker' => 'beta_manager',
        ] as $child => $parent) {
            $staff[$child]->manager_employee_profile_id = $staff[$parent]->id;
            $staff[$child]->save();
        }

        return [$users, $staff];
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
}
