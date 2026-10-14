<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\LeaveBalanceService;
use App\Services\Workforce\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceLeaveHistoricalPolicySnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_request_keeps_original_rules_and_days_when_admin_edits_type_before_approval(): void
    {
        [$worker, $profile, $manager] = $this->actors();
        $type = $this->type('FIXED', [
            'duration_mode' => 'fixed', 'fixed_duration_days' => 2,
            'counting_mode' => 'workdays',
            'working_weekdays' => [1, 2, 3, 4, 5],
            'holiday_dates' => ['2026-10-19'],
        ]);

        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
        ])->assertRedirect();

        $original = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('2026-10-20', $original->ends_on->format('Y-m-d'));
        $this->assertSame('2.00', $original->requested_days);
        $this->assertSame('fixed', $original->policy_snapshot['duration_mode']);
        $this->assertSame('workdays', $original->policy_snapshot['counting_mode']);
        $this->assertSame([1, 2, 3, 4, 5], $original->policy_snapshot['working_weekdays']);
        $this->assertSame(['2026-10-19'], $original->policy_snapshot['holiday_dates']);
        $this->assertSame(1, $original->policy_snapshot['schema_version']);

        $this->actingAs($manager)->put(route('admin.workforce.leave-types.update', $type), [
            'code' => 'FIXED', 'name' => 'Changed leave type',
            'is_paid' => 1, 'is_active' => 1,
            'default_annual_entitlement_days' => 20,
            'duration_mode' => 'flexible',
            'min_request_days' => 1, 'max_request_days' => 30,
            'counting_mode' => 'calendar',
        ])->assertRedirect();

        $pending = $original->fresh();
        $this->assertSame('2026-10-20', $pending->ends_on->format('Y-m-d'));
        $this->assertSame('2.00', $pending->requested_days);
        $this->assertSame($original->policy_snapshot, $pending->policy_snapshot);
        $this->assertSame('flexible', $type->fresh()->duration_mode);
        $this->assertSame(2.0, app(LeaveBalanceService::class)->balance($profile, $type, 2026)['pending']);

        // Approval still uses recorded charged days and the live balance gate.
        $this->assertSame(
            EmployeeLeaveRequest::STATUS_APPROVED,
            app(LeaveRequestService::class)->approve($pending, $manager)->status,
        );
        $approved = $pending->fresh();
        $this->assertSame($original->policy_snapshot, $approved->policy_snapshot);
        $balance = app(LeaveBalanceService::class)->balance($profile, $type, 2026);
        $this->assertSame(2.0, $balance['used']);
        $this->assertSame(0.0, $balance['pending']);
        $this->assertSame(18.0, $balance['available']);
    }

    public function test_approved_snapshot_remains_intact_after_type_edit_and_status_history_is_unchanged(): void
    {
        [$worker, , $manager] = $this->actors();
        $type = $this->type('LEGACY');
        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-10', 'ends_on' => '2026-10-12',
        ])->assertRedirect();

        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        app(LeaveRequestService::class)->approve($leave, $manager);
        $approved = $leave->fresh();
        $this->assertSame('legacy', $approved->policy_snapshot['duration_mode']);
        $this->assertSame('calendar', $approved->policy_snapshot['counting_mode']);

        $type->update([
            'duration_mode' => 'fixed', 'fixed_duration_days' => 5,
            'counting_mode' => 'workdays', 'working_weekdays' => [1, 2, 3],
            'holiday_dates' => ['2026-10-12'],
        ]);

        $after = $approved->fresh();
        $this->assertSame($approved->policy_snapshot, $after->policy_snapshot);
        $this->assertSame('3.00', $after->requested_days);
        $this->assertSame('2026-10-12', $after->ends_on->format('Y-m-d'));
        $this->assertSame(EmployeeLeaveRequest::STATUS_APPROVED, $after->status);
    }

    public function test_old_request_without_snapshot_is_left_null_and_is_never_backfilled_with_current_policy(): void
    {
        [$worker, $profile, $manager] = $this->actors();
        $type = $this->type('PREEXIST');
        $legacy = EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $profile->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-03',
            'requested_days' => 3,
            'status' => EmployeeLeaveRequest::STATUS_PENDING,
        ]);
        $this->assertNull($legacy->policy_snapshot);

        $type->update(['duration_mode' => 'fixed', 'fixed_duration_days' => 1]);
        app(LeaveRequestService::class)->approve($legacy, $manager);
        $historical = $legacy->fresh();
        $this->assertNull($historical->policy_snapshot);
        $this->assertSame('3.00', $historical->requested_days);
        $this->assertSame(EmployeeLeaveRequest::STATUS_APPROVED, $historical->status);

        $this->actingAs($worker)->get(route('admin.workforce.my-leave'))
            ->assertOk()
            ->assertSee(__('Historical policy details unavailable'));
    }

    public function test_policy_snapshot_cannot_be_mutated_during_later_status_or_manual_edits(): void
    {
        [$worker] = $this->actors();
        $type = $this->type('GUARD');
        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-13', 'ends_on' => '2026-10-14',
        ])->assertRedirect();
        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        $original = $leave->policy_snapshot;

        try {
            $leave->update(['policy_snapshot' => ['schema_version' => 1, 'duration_mode' => 'fixed']]);
            $this->fail('Mutating historical request-policy evidence must be rejected.');
        } catch (\LogicException $error) {
            $this->assertStringContainsString('immutable', $error->getMessage());
        }
        $this->assertSame($original, $leave->fresh()->policy_snapshot);
    }

    public function test_policy_details_are_scoped_to_own_employee_and_admin_review_is_permission_gated(): void
    {
        [$worker, , $manager] = $this->actors();
        [$otherWorker] = $this->actors('OTHER');
        $type = $this->type('PRIVATE');
        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-20', 'ends_on' => '2026-10-21',
            'reason' => 'PRIVATE-SNAPSHOT-OWNER-ONLY',
        ])->assertRedirect();

        $this->actingAs($otherWorker)->get(route('admin.workforce.my-leave'))
            ->assertOk()
            ->assertDontSee('PRIVATE-SNAPSHOT-OWNER-ONLY');

        $this->get(route('admin.workforce.leave.index'))
            ->assertForbidden();

        $this->actingAs($manager)->get(route('admin.workforce.leave.index'))
            ->assertOk()
            ->assertSee(__('Policy at submission'))
            ->assertSee('PRIVATE-SNAPSHOT-OWNER-ONLY');

        $this->actingAs($worker)->get(route('admin.workforce.my-leave'))
            ->assertOk()
            ->assertSee(__('Policy at submission'));
    }

    public function test_preview_does_not_create_policy_snapshot_until_actual_request_is_submitted(): void
    {
        [$worker] = $this->actors();
        $type = $this->type('PREVIEW', ['duration_mode' => 'fixed', 'fixed_duration_days' => 1]);

        $this->actingAs($worker)->postJson(route('admin.workforce.leave.preview'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-20',
        ])->assertOk()->assertJsonPath('preview.requested_days', 1);

        $this->assertDatabaseCount('employee_leave_requests', 0);
        $this->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-20',
            'policy_snapshot' => ['schema_version' => 777, 'duration_mode' => 'legacy'],
        ])->assertRedirect();

        $snapshot = EmployeeLeaveRequest::query()->firstOrFail()->policy_snapshot;
        $this->assertSame(1, $snapshot['schema_version']);
        $this->assertSame('fixed', $snapshot['duration_mode']);
    }

    private function actors(string $suffix = 'A'): array
    {
        app(AuthorizationService::class)->syncDefaults();
        $worker = User::factory()->create(['role_as' => 1]);
        $cashierRole = Role::query()->where('slug', 'cashier')->firstOrFail();
        $worker->roles()->sync([$cashierRole->id]);
        $profile = EmployeeProfile::query()->create([
            'user_id' => $worker->id,
            'employee_code' => 'QA-HISTORY-'.$suffix.'-'.$worker->id,
            'department' => 'QA', 'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ]);
        $manager = User::factory()->create(['role_as' => 1]);
        $managerRole = Role::query()->where('slug', 'operations_manager')->firstOrFail();
        $manager->roles()->sync([$managerRole->id]);

        return [$worker->fresh(), $profile, $manager->fresh()];
    }

    private function type(string $code, array $extra = []): EmployeeLeaveType
    {
        return EmployeeLeaveType::query()->create(array_merge([
            'code' => $code,
            'name' => 'Test leave policy '.$code,
            'default_annual_entitlement_days' => 20,
            'is_paid' => true,
            'is_active' => true,
        ], $extra));
    }
}
