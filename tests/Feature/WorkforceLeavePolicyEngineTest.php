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

class WorkforceLeavePolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_leave_requests_keep_the_original_inclusive_calendar_day_behavior(): void
    {
        [$worker] = $this->actors();
        $type = $this->createType('LEGACY');

        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
            'ends_on' => '2026-10-19',
        ])->assertRedirect(route('admin.workforce.my-leave'));

        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('2026-10-19', $leave->ends_on->format('Y-m-d'));
        $this->assertSame('4.00', $leave->requested_days);
        $this->assertSame('legacy', $type->fresh()->duration_mode);
    }

    public function test_fixed_calendar_duration_is_server_authoritative_and_mismatched_payload_is_rejected(): void
    {
        [$worker] = $this->actors();
        $type = $this->createType('FIXED', [
            'duration_mode' => 'fixed', 'fixed_duration_days' => 1,
        ]);

        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
            'ends_on' => '2026-10-17',
        ])->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount('employee_leave_requests', 0);

        $this->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
        ])->assertRedirect(route('admin.workforce.my-leave'));

        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('2026-10-16', $leave->ends_on->format('Y-m-d'));
        $this->assertSame('1.00', $leave->requested_days);
    }

    public function test_fixed_workdays_skip_only_explicit_weekends_and_configured_holidays(): void
    {
        [$worker] = $this->actors();
        $type = $this->createType('FIXWD', [
            'duration_mode' => 'fixed', 'fixed_duration_days' => 2,
            'counting_mode' => 'workdays',
            'working_weekdays' => [1, 2, 3, 4, 5],
            'holiday_dates' => ['2026-10-19'],
        ]);

        // Friday Oct 16 counts as day 1. Saturday/Sunday and Monday holiday
        // are excluded, so Tuesday Oct 20 is day 2.
        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
        ])->assertRedirect(route('admin.workforce.my-leave'));

        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('2026-10-20', $leave->ends_on->format('Y-m-d'));
        $this->assertSame('2.00', $leave->requested_days);
    }

    public function test_flexible_workdays_enforce_min_max_charged_days_and_reject_empty_workday_period(): void
    {
        [$worker] = $this->actors();
        $type = $this->createType('FLEX', [
            'duration_mode' => 'flexible',
            'min_request_days' => 2, 'max_request_days' => 2,
            'counting_mode' => 'workdays',
            'working_weekdays' => [1, 2, 3, 4, 5],
            'holiday_dates' => ['2026-10-19'],
        ]);

        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-17',
            'ends_on' => '2026-10-18',
        ])->assertSessionHasErrors('ends_on');

        $this->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
            'ends_on' => '2026-10-21',
        ])->assertSessionHasErrors('ends_on');

        $this->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16',
            'ends_on' => '2026-10-20',
        ])->assertRedirect(route('admin.workforce.my-leave'));

        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('2.00', $leave->requested_days);
        $this->assertSame('2026-10-20', $leave->ends_on->format('Y-m-d'));
        $this->assertDatabaseCount('employee_leave_requests', 1);
    }

    public function test_workday_policy_counts_from_a_nonworking_start_without_inventing_a_rejection_rule(): void
    {
        [$worker] = $this->actors();
        $type = $this->createType('SATSTART', [
            'duration_mode' => 'fixed', 'fixed_duration_days' => 2,
            'counting_mode' => 'workdays', 'working_weekdays' => [1, 2, 3, 4, 5],
            'holiday_dates' => ['2026-10-19'],
        ]);

        // Sat Oct 17 and Sun Oct 18 are not charged; Mon Oct 19 is a holiday;
        // Tue Oct 20 and Wed Oct 21 are the two chargeable days.
        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-17',
        ])->assertRedirect();

        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('2026-10-21', $leave->ends_on->format('Y-m-d'));
        $this->assertSame('2.00', $leave->requested_days);
    }

    public function test_fixed_leave_cannot_cross_the_existing_year_boundary(): void
    {
        [$worker] = $this->actors();
        $type = $this->createType('YEAR', [
            'duration_mode' => 'fixed', 'fixed_duration_days' => 2,
        ]);

        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-12-31',
        ])->assertSessionHasErrors('starts_on');

        $this->assertDatabaseCount('employee_leave_requests', 0);
    }

    public function test_policy_admin_validation_fails_closed_without_approved_rules(): void
    {
        [, $manager] = $this->actors();
        $base = [
            'code' => 'SICK', 'name' => 'Sick leave', 'is_paid' => 0,
            'is_active' => 1, 'default_annual_entitlement_days' => 15,
            'duration_mode' => 'flexible',
            'min_request_days' => 3, 'max_request_days' => 1,
        ];
        $this->actingAs($manager)
            ->post(route('admin.workforce.leave-types.store'), $base)
            ->assertSessionHasErrors('max_request_days');

        $this->post(route('admin.workforce.leave-types.store'), array_merge($base, [
            'min_request_days' => 1, 'max_request_days' => 3,
            'counting_mode' => 'workdays',
        ]))->assertSessionHasErrors('working_weekdays');

        $this->post(route('admin.workforce.leave-types.store'), array_merge($base, [
            'min_request_days' => 1, 'max_request_days' => 3,
            'counting_mode' => 'workdays',
            'working_weekdays' => [1, 2, 3, 4, 5],
            'holiday_dates_input' => '2026-02-30',
        ]))->assertSessionHasErrors('holiday_dates.0');

        $this->assertDatabaseCount('employee_leave_types', 0);
    }

    public function test_policy_updates_do_not_rewrite_existing_leave_requests(): void
    {
        [$worker, $manager] = $this->actors();
        $type = $this->createType('HISTORY');
        $this->actingAs($worker)->post(route('admin.workforce.leave.request'), [
            'employee_leave_type_id' => $type->id,
            'starts_on' => '2026-10-16', 'ends_on' => '2026-10-18',
        ])->assertRedirect();

        $before = EmployeeLeaveRequest::query()->firstOrFail();
        $this->assertSame('3.00', $before->requested_days);

        $this->actingAs($manager)->put(route('admin.workforce.leave-types.update', $type), [
            'code' => 'HISTORY', 'name' => 'Historic leave', 'is_paid' => 1,
            'is_active' => 1, 'default_annual_entitlement_days' => 20,
            'duration_mode' => 'fixed', 'fixed_duration_days' => 1,
            'counting_mode' => 'calendar',
        ])->assertRedirect();

        $after = $before->fresh();
        $this->assertSame('3.00', $after->requested_days);
        $this->assertSame('2026-10-18', $after->ends_on->format('Y-m-d'));
        $this->assertSame('fixed', $type->fresh()->duration_mode);
    }

    private function actors(): array
    {
        app(AuthorizationService::class)->syncDefaults();
        $worker = User::factory()->create(['role_as' => 1]);
        $workerRole = Role::query()->where('slug', 'cashier')->firstOrFail();
        $worker->roles()->sync([$workerRole->id]);

        EmployeeProfile::query()->create([
            'user_id' => $worker->id, 'employee_code' => 'QA-LEAVE-POLICY',
            'department' => 'QA', 'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ]);

        $manager = User::factory()->create(['role_as' => 1]);
        $managerRole = Role::query()->where('slug', 'operations_manager')->firstOrFail();
        $manager->roles()->sync([$managerRole->id]);

        return [$worker->fresh(), $manager->fresh()];
    }

    private function createType(string $code, array $overrides = []): EmployeeLeaveType
    {
        return EmployeeLeaveType::query()->create(array_merge([
            'code' => $code, 'name' => $code.' leave',
            'is_paid' => true, 'default_annual_entitlement_days' => 20, 'is_active' => true,
        ], $overrides));
    }
}
