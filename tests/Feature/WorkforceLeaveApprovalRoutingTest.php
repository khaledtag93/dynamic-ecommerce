<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\LeaveApprovalRoutingService;
use App\Services\Workforce\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkforceLeaveApprovalRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_scoped_mode_preserves_org_admin_review_and_existing_transitions(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $this->assertSame('scoped', app(LeaveApprovalRoutingService::class)->mode());
        $admin = $this->user('operations_manager');
        $worker = $this->profile($this->user('cashier'), 'ORG-WORKER');
        $type = $this->type();
        $approved = $this->leave($worker, $type, '2026-10-10');
        $rejected = $this->leave($worker, $type, '2026-10-20');

        // Legacy org-wide permission has not become dependent on having a
        // reporting-manager profile, and review actions stay available.
        $this->actingAs($admin)->get(route('admin.workforce.leave.index'))
            ->assertOk()
            ->assertSee(route('admin.workforce.leave.approve', $approved), false);

        $this->patch(route('admin.workforce.leave.approve', $approved))
            ->assertRedirect()->assertSessionHas('success');
        $this->patch(route('admin.workforce.leave.reject', $rejected))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(EmployeeLeaveRequest::STATUS_APPROVED, $approved->fresh()->status);
        $this->assertSame(EmployeeLeaveRequest::STATUS_REJECTED, $rejected->fresh()->status);
        $this->assertSame($admin->id, (int) $approved->fresh()->reviewed_by_user_id);
        $this->assertSame($admin->id, (int) $rejected->fresh()->reviewed_by_user_id);

        $this->patch(route('admin.workforce.leave.approve', $rejected))
            ->assertSessionHasErrors('leave');
        $this->assertSame(EmployeeLeaveRequest::STATUS_REJECTED, $rejected->fresh()->status);
    }

    public function test_scoped_supervisor_gets_only_their_team_review_actions_and_no_self_review(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        [$head, $lead, $worker, $outsider] = $this->team();
        $type = $this->type();
        $own = $this->leave($head, $type, '2026-10-05');
        $inTeam = $this->leave($worker, $type, '2026-10-10');
        $outside = $this->leave($outsider, $type, '2026-10-20');

        $this->actingAs($head->user)->get(route('admin.workforce.leave.index'))
            ->assertOk()
            ->assertSee(route('admin.workforce.leave.approve', $inTeam), false)
            ->assertDontSee(route('admin.workforce.leave.approve', $own), false)
            ->assertDontSee('ORG-OUTSIDE');

        $this->patch(route('admin.workforce.leave.approve', $outside))->assertForbidden();
        $this->patch(route('admin.workforce.leave.reject', $own))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $outside->fresh()->status);
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $own->fresh()->status);

        $this->patch(route('admin.workforce.leave.approve', $inTeam))->assertRedirect();
        $this->assertSame(EmployeeLeaveRequest::STATUS_APPROVED, $inTeam->fresh()->status);
    }

    public function test_opt_in_direct_manager_allows_exact_active_manager_not_indirect_ancestor(): void
    {
        config()->set('workforce.leave_approval.routing_mode', 'direct_manager');
        app(AuthorizationService::class)->syncDefaults();
        [$head, $lead, $worker] = $this->team();
        $type = $this->type();
        $toWorker = $this->leave($worker, $type, '2026-10-10');
        $toLead = $this->leave($lead, $type, '2026-10-20');

        $routing = app(LeaveApprovalRoutingService::class);
        $this->assertTrue($routing->canReview($lead->user, $worker));
        $this->assertTrue($routing->canReview($head->user, $lead));
        $this->assertFalse($routing->canReview($head->user, $worker));
        $this->assertFalse($routing->canReview($lead->user, $lead));

        // Ancestor can still SEE descendants through existing scoped list,
        // but review actions are hidden and direct write attempts are denied.
        $this->actingAs($head->user)->get(route('admin.workforce.leave.index'))
            ->assertOk()
            ->assertDontSee(route('admin.workforce.leave.approve', $toWorker), false)
            ->assertSee(route('admin.workforce.leave.approve', $toLead), false);
        $this->patch(route('admin.workforce.leave.approve', $toWorker))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $toWorker->fresh()->status);

        $this->actingAs($lead->user)->get(route('admin.workforce.leave.index'))
            ->assertOk()
            ->assertSee(route('admin.workforce.leave.approve', $toWorker), false);
        $this->patch(route('admin.workforce.leave.approve', $toWorker))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(EmployeeLeaveRequest::STATUS_APPROVED, $toWorker->fresh()->status);
    }

    public function test_direct_manager_mode_has_no_auto_fallback_for_missing_manager_or_org_admin(): void
    {
        config()->set('workforce.leave_approval.routing_mode', 'direct_manager');
        app(AuthorizationService::class)->syncDefaults();
        $admin = $this->user('operations_manager');
        $worker = $this->profile($this->user('cashier'), 'NO-MANAGER');
        $leave = $this->leave($worker, $this->type(), '2026-10-10');

        $this->actingAs($admin)->get(route('admin.workforce.leave.index'))
            ->assertOk()->assertDontSee(route('admin.workforce.leave.approve', $leave), false);
        $this->patch(route('admin.workforce.leave.approve', $leave))->assertForbidden();
        $this->patch(route('admin.workforce.leave.reject', $leave))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
        $this->assertNull($leave->fresh()->reviewed_by_user_id);
    }

    public function test_direct_manager_requires_active_staff_manager_permission_and_does_not_delegate(): void
    {
        config()->set('workforce.leave_approval.routing_mode', 'direct_manager');
        app(AuthorizationService::class)->syncDefaults();
        [$head, $lead, $worker] = $this->team();
        $leave = $this->leave($worker, $this->type(), '2026-10-10');

        $lead->update(['status' => EmployeeProfile::STATUS_INACTIVE]);
        $this->actingAs($lead->user)
            ->patch(route('admin.workforce.leave.reject', $leave))->assertForbidden();

        $lead->update(['status' => EmployeeProfile::STATUS_ACTIVE]);
        $lead->user->roles()->sync([Role::query()->where('slug', 'cashier')->firstOrFail()->id]);
        $this->actingAs($lead->user->fresh())
            ->patch(route('admin.workforce.leave.reject', $leave))->assertForbidden();

        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
        $this->assertNull($leave->fresh()->reviewed_at);
    }

    public function test_invalid_routing_configuration_fails_closed_without_policy_guess(): void
    {
        config()->set('workforce.leave_approval.routing_mode', 'auto_escalate');
        app(AuthorizationService::class)->syncDefaults();
        $admin = $this->user('operations_manager');
        $worker = $this->profile($this->user('cashier'), 'WRONG-MODE');
        $leave = $this->leave($worker, $this->type(), '2026-10-10');

        $this->actingAs($admin)->get(route('admin.workforce.leave.index'))
            ->assertOk()->assertDontSee(route('admin.workforce.leave.approve', $leave), false);
        $this->patch(route('admin.workforce.leave.approve', $leave))->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
    }

    public function test_authorization_is_enforced_inside_leave_service_even_if_controller_is_bypassed(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        [$head, $lead, $worker, $outside] = $this->team();
        $leave = $this->leave($outside, $this->type(), '2026-10-10');

        try {
            app(LeaveRequestService::class)->approve($leave, $head->user);
            $this->fail('The service must reject unscoped review.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        try {
            app(LeaveRequestService::class)->reject($leave, $head->user);
            $this->fail('The service must reject unscoped rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
        $this->assertNull($leave->fresh()->reviewed_by_user_id);
    }

    public function test_cannot_read_another_teams_private_leave_reason_or_mutate_by_guessing_id(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        [$head, $lead, $worker, $outsider] = $this->team();
        $leave = $this->leave($outsider, $this->type(), '2026-10-10', 'QA-PRIVATE-REASON-ONLY-BETA');

        $this->actingAs($head->user)->get(route('admin.workforce.leave.index'))
            ->assertOk()->assertDontSee('QA-PRIVATE-REASON-ONLY-BETA');
        $this->patch(route('admin.workforce.leave.reject', $leave), ['review_notes' => 'INTRUSION'])
            ->assertForbidden();
        $this->assertSame(EmployeeLeaveRequest::STATUS_PENDING, $leave->fresh()->status);
        $this->assertNull($leave->fresh()->review_notes);
    }

    private function team(): array
    {
        $head = $this->profile($this->user('team_supervisor'), 'ORG-HEAD');
        $lead = $this->profile($this->user('team_supervisor'), 'ORG-LEAD', $head->id);
        $worker = $this->profile($this->user('cashier'), 'ORG-WORKER', $lead->id);
        $outsider = $this->profile($this->user('cashier'), 'ORG-OUTSIDE');

        return [$head, $lead, $worker, $outsider];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $user->roles()->sync([Role::query()->where('slug', $role)->firstOrFail()->id]);
        return $user->fresh();
    }

    private function profile(User $user, string $code, ?int $managerId = null): EmployeeProfile
    {
        $profile = EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => $code,
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
            'code' => 'APP-ROUTING', 'name' => 'Approval route test',
            'is_active' => true, 'is_paid' => true,
            'default_annual_entitlement_days' => 20,
        ]);
    }

    private function leave(EmployeeProfile $employee, EmployeeLeaveType $type, string $start, ?string $reason = null): EmployeeLeaveRequest
    {
        return EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => $start, 'ends_on' => $start,
            'requested_days' => 1,
            'reason' => $reason,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);
    }
}
