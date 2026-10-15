<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\LeaveApprovalReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkforceLeaveApprovalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_scoped_mode_keeps_legacy_global_reviewer_and_never_invents_unassigned(): void
    {
        $this->roles();
        $admin = $this->staff('operations_manager');
        $worker = $this->employee($this->staff('cashier'), 'READY-WORKER');
        $type = $this->type();
        $pending = $this->leave($worker, $type, '2026-10-10');

        $response = $this->actingAs($admin)->get(route('admin.workforce.leave.index'))
            ->assertOk()
            ->assertSee(route('admin.workforce.leave.approve', $pending), false)
            ->assertSee(__('Readiness figures cover only the currently displayed, permission-scoped page; they are not organization-wide totals.'));

        $readiness = $response->viewData('reviewReadiness');
        $this->assertSame(LeaveApprovalReadinessService::MINE, $readiness['states'][$pending->id]);
        $this->assertSame(1, $readiness['counts']['mine']);
        $this->assertSame(0, $readiness['counts']['unassigned']);
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $pending->fresh()->status);
    }

    public function test_scoped_manager_safely_distinguishes_self_from_another_eligible_ancestor(): void
    {
        $this->roles();
        $head = $this->employee($this->staff('team_supervisor'), 'READY-HEAD');
        $lead = $this->employee($this->staff('team_supervisor'), 'READY-LEAD', $head->id);
        $worker = $this->employee($this->staff('cashier'), 'READY-CHILD', $lead->id);
        $type = $this->type();
        $self = $this->leave($lead, $type, '2026-10-10');
        $child = $this->leave($worker, $type, '2026-10-20');

        $response = $this->actingAs($lead->user)->get(route('admin.workforce.leave.index'))->assertOk();
        $data = $response->viewData('reviewReadiness');

        $this->assertSame(LeaveApprovalReadinessService::OTHER, $data['states'][$self->id]);
        $this->assertSame(LeaveApprovalReadinessService::MINE, $data['states'][$child->id]);
        $response->assertDontSee(route('admin.workforce.leave.approve', $self), false)
            ->assertSee(route('admin.workforce.leave.approve', $child), false)
            ->assertSee(__('Another reviewer is eligible'));
        $this->patch(route('admin.workforce.leave.approve', $self))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $self->fresh()->status);
    }

    public function test_scoped_mode_can_prove_no_reviewer_when_no_global_or_parent_reviewer_exists(): void
    {
        $this->roles();
        $manager = $this->employee($this->staff('team_supervisor'), 'READY-ONLY');
        $type = $this->type();
        $self = $this->leave($manager, $type, '2026-10-10');

        $response = $this->actingAs($manager->user)->get(route('admin.workforce.leave.index'))->assertOk();
        $readiness = $response->viewData('reviewReadiness');
        $this->assertSame(LeaveApprovalReadinessService::UNASSIGNED, $readiness['states'][$self->id]);
        $this->assertSame(1, $readiness['counts']['unassigned']);
        $response->assertSee(__('No eligible reviewer currently'))
            ->assertDontSee(route('admin.workforce.leave.approve', $self), false);
        $this->patch(route('admin.workforce.leave.reject', $self))->assertForbidden();
    }

    public function test_direct_manager_readiness_never_falls_back_to_ancestor_or_org_admin(): void
    {
        $this->roles();
        config()->set('workforce.leave_approval.routing_mode', 'direct_manager');
        $head = $this->employee($this->staff('team_supervisor'), 'READY-HEAD');
        $lead = $this->employee($this->staff('team_supervisor'), 'READY-LEAD', $head->id);
        $worker = $this->employee($this->staff('cashier'), 'READY-CHILD', $lead->id);
        $alone = $this->employee($this->staff('cashier'), 'READY-ALONE');
        $admin = $this->staff('operations_manager');
        $type = $this->type();
        $workerLeave = $this->leave($worker, $type, '2026-10-10');
        $unassigned = $this->leave($alone, $type, '2026-10-20');

        $headResponse = $this->actingAs($head->user)->get(route('admin.workforce.leave.index'))->assertOk();
        $headState = $headResponse->viewData('reviewReadiness');
        $this->assertSame(LeaveApprovalReadinessService::OTHER, $headState['states'][$workerLeave->id]);
        $headResponse->assertDontSee(route('admin.workforce.leave.approve', $workerLeave), false);
        $this->patch(route('admin.workforce.leave.approve', $workerLeave))->assertForbidden();

        $leadResponse = $this->actingAs($lead->user)->get(route('admin.workforce.leave.index'))->assertOk();
        $this->assertSame(
            LeaveApprovalReadinessService::MINE,
            $leadResponse->viewData('reviewReadiness')['states'][$workerLeave->id],
        );

        $adminResponse = $this->actingAs($admin)->get(route('admin.workforce.leave.index'))->assertOk();
        $adminStates = $adminResponse->viewData('reviewReadiness')['states'];
        $this->assertSame(LeaveApprovalReadinessService::UNASSIGNED, $adminStates[$unassigned->id]);
        $this->assertSame(LeaveApprovalReadinessService::OTHER, $adminStates[$workerLeave->id]);
        $adminResponse->assertDontSee(route('admin.workforce.leave.approve', $unassigned), false);
        $this->patch(route('admin.workforce.leave.approve', $unassigned))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $unassigned->fresh()->status);
    }

    public function test_missing_inactive_or_permissionless_direct_manager_is_not_counted_as_eligible(): void
    {
        $this->roles();
        config()->set('workforce.leave_approval.routing_mode', 'direct_manager');
        $manager = $this->employee($this->staff('team_supervisor'), 'READY-MANAGER');
        $worker = $this->employee($this->staff('cashier'), 'READY-WORKER', $manager->id);
        $admin = $this->staff('operations_manager');
        $leave = $this->leave($worker, $this->type(), '2026-10-10');

        $check = function () use ($admin, $leave): void {
            $response = $this->actingAs($admin)->get(route('admin.workforce.leave.index'))->assertOk();
            $this->assertSame(
                LeaveApprovalReadinessService::UNASSIGNED,
                $response->viewData('reviewReadiness')['states'][$leave->id],
            );
            $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
        };

        $manager->update(['status' => EmployeeProfile::STATUS_INACTIVE]);
        $check();
        $manager->update(['status' => EmployeeProfile::STATUS_ACTIVE]);
        $manager->user->roles()->sync([Role::query()->where('slug', 'cashier')->firstOrFail()->id]);
        $check();

        $worker->manager_employee_profile_id = null;
        $worker->save();
        $check();
    }

    public function test_invalid_configuration_is_attention_needed_not_falsely_unassigned(): void
    {
        $this->roles();
        config()->set('workforce.leave_approval.routing_mode', 'unapproved_fallback');
        $admin = $this->staff('operations_manager');
        $worker = $this->employee($this->staff('cashier'), 'READY-WORKER');
        $leave = $this->leave($worker, $this->type(), '2026-10-10');

        $response = $this->actingAs($admin)->get(route('admin.workforce.leave.index'))->assertOk()
            ->assertSee(__('Reviewer routing needs attention'))
            ->assertDontSee(route('admin.workforce.leave.approve', $leave), false);
        $this->assertSame(
            LeaveApprovalReadinessService::CONFIGURATION,
            $response->viewData('reviewReadiness')['states'][$leave->id],
        );
        $this->patch(route('admin.workforce.leave.reject', $leave))->assertForbidden();
    }

    public function test_live_list_readiness_only_counts_current_scoped_page_and_hides_other_team(): void
    {
        $this->roles();
        $head = $this->employee($this->staff('team_supervisor'), 'READY-HEAD');
        $worker = $this->employee($this->staff('cashier'), 'READY-CHILD', $head->id);
        $outsider = $this->employee($this->staff('cashier'), 'READY-SECRET');
        $type = $this->type();
        for ($i = 1; $i <= 21; $i++) {
            $this->leave($worker, $type, sprintf('2026-10-%02d', $i));
        }
        $this->leave($outsider, $type, '2026-11-01', 'PRIVATE-LEAVE-REASON-OUTSIDE-SCOPE');

        $response = $this->actingAs($head->user)->withHeader('X-Live-List', '1')
            ->get(route('admin.workforce.leave.index', ['status' => 'pending', 'per_page' => 20]))
            ->assertOk()
            ->assertDontSee('PRIVATE-LEAVE-REASON-OUTSIDE-SCOPE')
            ->assertSee(__('Pending on this page'));

        $readiness = $response->viewData('reviewReadiness');
        $this->assertCount(20, $readiness['states']);
        $this->assertSame(20, $readiness['counts']['mine']);
        $this->assertSame(0, $readiness['counts']['unassigned']);
        $this->assertDatabaseCount('employee_leave_requests', 22);
    }

    public function test_completed_leaves_are_excluded_and_state_recomputed_after_authorized_approval(): void
    {
        $this->roles();
        $admin = $this->staff('operations_manager');
        $worker = $this->employee($this->staff('cashier'), 'READY-WORKER');
        $type = $this->type();
        $approved = $this->leave($worker, $type, '2026-10-10');
        $rejected = $this->leave($worker, $type, '2026-10-20');

        $this->actingAs($admin)->patch(route('admin.workforce.leave.approve', $approved))
            ->assertRedirect()->assertSessionHas('success');
        $this->patch(route('admin.workforce.leave.reject', $rejected))
            ->assertRedirect()->assertSessionHas('success');

        $response = $this->get(route('admin.workforce.leave.index'))->assertOk();
        $state = $response->viewData('reviewReadiness');
        $this->assertSame([], $state['states']);
        $this->assertSame(0, array_sum($state['counts']));
        $response->assertDontSee(route('admin.workforce.leave.approve', $approved), false);
        $this->assertSame(EmployeeLeaveRequest::STATUS_APPROVED, $approved->fresh()->status);
        $this->assertSame(EmployeeLeaveRequest::STATUS_REJECTED, $rejected->fresh()->status);
    }

    public function test_cyclic_scoped_hierarchy_reports_configuration_without_self_review(): void
    {
        $this->roles();
        $head = $this->employee($this->staff('team_supervisor'), 'READY-CYCLE-HEAD');
        $child = $this->employee($this->staff('cashier'), 'READY-CYCLE-CHILD', $head->id);
        // The manager FK is guarded from mass assignment; simulate a legacy cycle explicitly.
        $head->manager_employee_profile_id = $child->id;
        $head->save();
        $leave = $this->leave($head, $this->type(), '2026-10-10');

        $response = $this->actingAs($head->user)->get(route('admin.workforce.leave.index'))->assertOk();
        $this->assertSame(
            LeaveApprovalReadinessService::CONFIGURATION,
            $response->viewData('reviewReadiness')['states'][$leave->id],
        );
        $response->assertDontSee(route('admin.workforce.leave.approve', $leave), false);
        $this->patch(route('admin.workforce.leave.approve', $leave))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
    }

    public function test_revoked_ancestor_permission_is_not_counted_as_other_reviewer(): void
    {
        $this->roles();
        $head = $this->employee($this->staff('cashier'), 'READY-REVOKED-HEAD');
        $lead = $this->employee($this->staff('team_supervisor'), 'READY-REVOKED-LEAD', $head->id);
        $leave = $this->leave($lead, $this->type(), '2026-10-10');

        $response = $this->actingAs($lead->user)->get(route('admin.workforce.leave.index'))->assertOk();
        $this->assertSame(
            LeaveApprovalReadinessService::UNASSIGNED,
            $response->viewData('reviewReadiness')['states'][$leave->id],
        );
        $response->assertDontSee(route('admin.workforce.leave.approve', $leave), false);
        $this->patch(route('admin.workforce.leave.reject', $leave))->assertForbidden();
    }

    public function test_stale_direct_manager_reference_is_configuration_without_cross_team_disclosure(): void
    {
        $this->roles();
        config()->set('workforce.leave_approval.routing_mode', 'direct_manager');
        $admin = $this->staff('operations_manager');
        $worker = $this->employee($this->staff('cashier'), 'READY-STALE-MANAGER');
        $outsider = $this->employee($this->staff('cashier'), 'READY-PRIVATE-TEAM');
        $type = $this->type();
        $leave = $this->leave($worker, $type, '2026-10-10');
        $this->leave($outsider, $type, '2026-11-10', 'PRIVATE-STALE-MANAGER-REASON');

        // Simulate a historical broken FK only inside the isolated test database.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $worker->manager_employee_profile_id = 999999999;
            $worker->save();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $response = $this->actingAs($admin)->get(route('admin.workforce.leave.index'))->assertOk();
        $this->assertSame(
            LeaveApprovalReadinessService::CONFIGURATION,
            $response->viewData('reviewReadiness')['states'][$leave->id],
        );
        $response->assertDontSee(route('admin.workforce.leave.approve', $leave), false);
        $this->patch(route('admin.workforce.leave.approve', $leave))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);

        $scoped = $this->actingAs($worker->user)->get(route('admin.workforce.leave.index'));
        $scoped->assertForbidden();
        $scoped->assertDontSee('PRIVATE-STALE-MANAGER-REASON');
    }

    private function roles(): void
    {
        app(AuthorizationService::class)->syncDefaults();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $user->roles()->sync([Role::query()->where('slug', $role)->firstOrFail()->id]);
        return $user->fresh();
    }

    private function employee(User $user, string $code, ?int $managerId = null): EmployeeProfile
    {
        $profile = EmployeeProfile::query()->create([
            'user_id' => $user->id, 'employee_code' => $code,
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ]);
        if ($managerId !== null) {
            $profile->manager_employee_profile_id = $managerId;
            $profile->save();
        }

        return $profile->fresh(['user']);
    }

    private function type(): EmployeeLeaveType
    {
        return EmployeeLeaveType::query()->create([
            'code' => 'READY-LEAVE', 'name' => 'Readiness Test Leave',
            'is_paid' => true, 'is_active' => true,
            'default_annual_entitlement_days' => 24,
        ]);
    }

    private function leave(EmployeeProfile $employee, EmployeeLeaveType $type, string $start, ?string $reason = null): EmployeeLeaveRequest
    {
        return EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => $start, 'ends_on' => $start,
            'requested_days' => 1, 'reason' => $reason,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);
    }
}
