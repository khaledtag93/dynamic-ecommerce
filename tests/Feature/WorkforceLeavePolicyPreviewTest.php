<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceLeavePolicyPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_requires_login_and_existing_workforce_clock_permission(): void
    {
        $this->postJson(route('admin.workforce.leave.preview'), [])
            ->assertUnauthorized();

        $user = User::factory()->create(['role_as' => 1]);
        // Existing admin entry middleware redirects staff without a role rather
        // than returning JSON 403; in both cases access to the endpoint is denied.
        $this->actingAs($user)->postJson(route('admin.workforce.leave.preview'), [])
            ->assertRedirect();
    }

    public function test_legacy_preview_uses_current_balances_pending_and_only_own_employee_records_without_writes(): void
    {
        [$worker, $employee] = $this->worker('OWN');
        [$otherWorker, $other] = $this->worker('OTHER');
        $type = $this->type('ANNUAL', ['default_annual_entitlement_days' => 10]);

        $this->leave($employee, $type, '2026-10-01', '2026-10-03', 3, EmployeeLeaveRequest::STATUS_APPROVED);
        $this->leave($employee, $type, '2026-10-05', '2026-10-06', 2, EmployeeLeaveRequest::STATUS_PENDING);
        $this->leave($other, $type, '2026-10-01', '2026-10-09', 9, EmployeeLeaveRequest::STATUS_APPROVED);

        $before = EmployeeLeaveRequest::query()->count();

        $response = $this->actingAs($worker)->postJson(route('admin.workforce.leave.preview'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-11',
            // Untrusted client-controlled ID must not affect the self-service scope.
            'employee_profile_id' => $other->id,
            'user_id' => $otherWorker->id,
        ])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('preview.starts_on', '2026-10-10')
            ->assertJsonPath('preview.ends_on', '2026-10-11')
            ->assertJsonPath('preview.requested_days', 2)
            ->assertJsonPath('preview.available', 7)
            ->assertJsonPath('preview.pending', 2)
            ->assertJsonPath('preview.projected_available', 5)
            ->assertJsonPath('preview.projected_after_request', 3)
            ->assertJsonPath('preview.sufficient_balance_now', true);

        $response->assertJsonMissing(['employee_profile_id' => $other->id]);
        $this->assertSame($before, EmployeeLeaveRequest::query()->count());
        $this->assertDatabaseCount('employee_leave_adjustments', 0);
        $this->assertSame('pending', $employee->leaveRequests()->where('status', 'pending')->firstOrFail()->status);
    }

    public function test_fixed_workday_preview_uses_server_computed_dates_and_submission_revalidates(): void
    {
        [$worker] = $this->worker('FIXED');
        $type = $this->type('FIXED', [
            'duration_mode' => 'fixed', 'fixed_duration_days' => 2,
            'counting_mode' => 'workdays', 'working_weekdays' => [1, 2, 3, 4, 5],
            'holiday_dates' => ['2026-10-19'],
        ]);
        $this->actingAs($worker)->postJson(route('admin.workforce.leave.preview'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
        ])->assertOk()
            ->assertJsonPath('preview.ends_on', '2026-10-20')
            ->assertJsonPath('preview.requested_days', 2)
            ->assertJsonPath('preview.projected_after_request', 18);

        $this->assertDatabaseCount('employee_leave_requests', 0);
        $this->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
        ])->assertRedirect(route('admin.workforce.my-leave'));

        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('2026-10-20', $leave->ends_on->format('Y-m-d'));
        $this->assertSame('2.00', $leave->requested_days);
    }

    public function test_flexible_preview_rejects_wrong_charged_duration_and_does_not_create_a_request(): void
    {
        [$worker] = $this->worker('FLEX');
        $type = $this->type('FLEX', [
            'duration_mode' => 'flexible', 'counting_mode' => 'workdays',
            'working_weekdays' => [1, 2, 3, 4, 5],
            'min_request_days' => 2, 'max_request_days' => 3,
        ]);

        $this->actingAs($worker)->postJson(route('admin.workforce.leave.preview'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-17',
            'ends_on' => '2026-10-18',
        ])->assertUnprocessable()->assertJsonValidationErrors('ends_on');

        $this->postJson(route('admin.workforce.leave.preview'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
            'ends_on' => '2026-10-20',
        ])->assertOk()->assertJsonPath('preview.requested_days', 3);

        $this->assertDatabaseCount('employee_leave_requests', 0);
    }

    public function test_overlap_rejected_equally_in_preview_and_submission(): void
    {
        [$worker, $employee] = $this->worker('OVERLAP');
        $type = $this->type('OVERLAP');
        $this->leave($employee, $type, '2026-10-10', '2026-10-12', 3, EmployeeLeaveRequest::STATUS_PENDING);
        $payload = ['employee_leave_type_id' => $type->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-15'];

        $this->actingAs($worker)->postJson(route('admin.workforce.leave.preview'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->post(route('admin.workforce.leave.request'), $payload)
            ->assertSessionHasErrors('starts_on');
        $this->assertDatabaseCount('employee_leave_requests', 1);
    }

    public function test_preview_denies_missing_profile_inactive_worker_and_inactive_leave_type(): void
    {
        [$worker, $employee] = $this->worker('STATUS');
        $type = $this->type('STATUS');
        $payload = ['employee_leave_type_id' => $type->id, 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-11'];

        $employee->update(['status' => EmployeeProfile::STATUS_INACTIVE]);
        $this->actingAs($worker)->postJson(route('admin.workforce.leave.preview'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('leave');

        $employee->update(['status' => EmployeeProfile::STATUS_ACTIVE]);
        $type->update(['is_active' => false]);
        $this->postJson(route('admin.workforce.leave.preview'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('employee_leave_type_id');

        $type->update(['is_active' => true]);
        $employee->delete();
        $this->postJson(route('admin.workforce.leave.preview'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('leave');

        $this->assertDatabaseCount('employee_leave_requests', 0);
    }

    public function test_insufficient_available_balance_warns_but_does_not_automatically_block_new_pending_leave(): void
    {
        [$worker] = $this->worker('BALANCE');
        $type = $this->type('LOW', ['default_annual_entitlement_days' => 1]);

        $this->actingAs($worker)->postJson(route('admin.workforce.leave.preview'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-12',
        ])->assertOk()
            ->assertJsonPath('preview.requested_days', 3)
            ->assertJsonPath('preview.available', 1)
            ->assertJsonPath('preview.projected_after_request', -2)
            ->assertJsonPath('preview.sufficient_balance_now', false);

        $this->assertDatabaseCount('employee_leave_requests', 0);
        $this->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-12',
        ])->assertRedirect(route('admin.workforce.my-leave'));
        $this->assertDatabaseCount('employee_leave_requests', 1);
    }

    public function test_preview_recomputes_pending_balance_without_reserving_it(): void
    {
        [$worker, $employee] = $this->worker('PENDING');
        $type = $this->type('PENDING', ['default_annual_entitlement_days' => 8]);
        $payload = ['employee_leave_type_id' => $type->id, 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-21'];

        $this->actingAs($worker)->postJson(route('admin.workforce.leave.preview'), $payload)
            ->assertOk()->assertJsonPath('preview.projected_after_request', 6);
        $this->assertDatabaseCount('employee_leave_requests', 0);

        $this->leave($employee, $type, '2026-10-01', '2026-10-03', 3, EmployeeLeaveRequest::STATUS_PENDING);
        $this->postJson(route('admin.workforce.leave.preview'), $payload)
            ->assertOk()->assertJsonPath('preview.projected_after_request', 3);
        $this->assertDatabaseCount('employee_leave_requests', 1);
    }

    private function worker(string $suffix): array
    {
        app(AuthorizationService::class)->syncDefaults();
        $worker = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', 'cashier')->firstOrFail();
        $worker->roles()->sync([$role->id]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $worker->id,
            'employee_code' => 'QA-PREVIEW-'.$suffix,
            'department' => 'QA', 'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ]);

        return [$worker->fresh(), $profile];
    }

    private function type(string $code, array $overrides = []): EmployeeLeaveType
    {
        return EmployeeLeaveType::query()->create(array_merge([
            'code' => 'QA-PREV-'.$code,
            'name' => 'Test preview type '.$code,
            'is_paid' => true,
            'default_annual_entitlement_days' => 20,
            'is_active' => true,
        ], $overrides));
    }

    private function leave(
        EmployeeProfile $employee,
        EmployeeLeaveType $type,
        string $from,
        string $to,
        int $days,
        string $status,
    ): EmployeeLeaveRequest {
        return EmployeeLeaveRequest::query()->create([
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_on' => $from,
            'ends_on' => $to,
            'requested_days' => $days,
            'status' => $status,
        ]);
    }
}
