<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Support\WorkforceClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkforceTimezoneAttendanceUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_display_round_trip_and_day_boundary_preserve_utc(): void
    {
        config()->set('workforce.timezone', 'Africa/Cairo');

        $utc = CarbonImmutable::parse('2026-10-14 14:35:00', 'UTC');
        $this->assertSame('14/10/2026 17:35', WorkforceClock::local($utc));
        $this->assertSame('2026-10-14T17:35', WorkforceClock::input($utc));
        $this->assertSame('2026-10-14 14:35:00', WorkforceClock::fromLocal('2026-10-14T17:35', 'starts_at'));

        $this->assertSame(
            ['2026-10-13 21:00:00', '2026-10-14 21:00:00'],
            WorkforceClock::localDayUtcBounds('2026-10-14')
        );
    }

    public function test_invalid_or_nonexistent_local_time_is_not_silently_shifted(): void
    {
        config()->set('workforce.timezone', 'America/New_York');

        $this->expectException(ValidationException::class);
        WorkforceClock::fromLocal('2026-03-08T02:30', 'starts_at');
    }

    public function test_schedule_input_is_stored_as_utc_and_displayed_as_local(): void
    {
        config()->set('workforce.timezone', 'Africa/Cairo');
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-TZ-CLOCK');

        $this->actingAs($manager)->post(route('admin.workforce.schedule.store'), [
            'employee_profile_id' => $employee->id,
            'starts_at' => '2026-10-16T09:00',
            'ends_at' => '2026-10-16T17:00',
            'status' => 'published',
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_work_shifts', [
            'employee_profile_id' => $employee->id,
            'starts_at' => '2026-10-16 06:00:00',
            'ends_at' => '2026-10-16 14:00:00',
        ]);

        $this->get(route('admin.workforce.schedule.index'))
            ->assertOk()
            ->assertSee('09:00')
            ->assertSee('17:00');
    }

    public function test_correction_local_input_saves_utc_without_rewriting_original_attendance(): void
    {
        config()->set('workforce.timezone', 'Africa/Cairo');
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-TZ-CORR');

        $session = EmployeeAttendanceSession::create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => '2026-10-14 06:30:00',
            'clock_out_at' => '2026-10-14 14:00:00',
            'source' => 'admin',
        ]);

        $this->actingAs($cashier)
            ->get(route('admin.workforce.corrections.create', $session))
            ->assertOk()
            ->assertSee('2026-10-14T09:30');

        $this->post(route('admin.workforce.corrections.store', $session), [
            'requested_clock_in_at' => '2026-10-14T09:00',
            'requested_clock_out_at' => '2026-10-14T17:00',
            'reason' => 'Recorded terminal time was incorrect.',
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_attendance_corrections', [
            'employee_attendance_session_id' => $session->id,
            'requested_clock_in_at' => '2026-10-14 06:00:00',
            'requested_clock_out_at' => '2026-10-14 14:00:00',
            'status' => 'pending',
        ]);
        $this->assertSame('2026-10-14 06:30:00', $session->fresh()->clock_in_at->format('Y-m-d H:i:s'));
    }

    public function test_void_is_manager_only_non_destructive_and_excluded_from_attendance_totals(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-TZ-VOID');

        $session = EmployeeAttendanceSession::create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => '2026-10-14 09:00:00',
            'clock_out_at' => '2026-10-14 17:00:00',
            'source' => 'admin',
        ]);

        $this->actingAs($cashier)
            ->post(route('admin.workforce.attendance.void', $session), ['void_reason' => 'Erroneous demo session'])
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admin.workforce.attendance.void', $session), ['void_reason' => 'Erroneous demo session'])
            ->assertRedirect(route('admin.workforce.attendance.index'));

        $session->refresh();
        $this->assertNotNull($session->voided_at);
        $this->assertSame('Erroneous demo session', $session->void_reason);
        $this->assertSame($manager->id, $session->voided_by_user_id);
        $this->assertSame('2026-10-14 09:00:00', $session->clock_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, EmployeeAttendanceSession::query()->active()->count());

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'attendance_session_voided',
            'subject_id' => $session->id,
            'admin_user_id' => $manager->id,
        ]);

        $this->post(route('admin.workforce.attendance.void', $session), ['void_reason' => 'Erroneous demo session'])
            ->assertSessionHasErrors('void_reason');

        $this->actingAs($cashier)
            ->get(route('admin.workforce.corrections.create', $session))
            ->assertStatus(422);
    }

    public function test_open_session_cannot_be_voided(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-TZ-OPEN');

        $session = EmployeeAttendanceSession::create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => now()->subHour(),
            'source' => 'admin',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.workforce.attendance.void', $session), ['void_reason' => 'Test open session should not be voided'])
            ->assertSessionHasErrors('void_reason');

        $this->assertNull($session->fresh()->voided_at);
    }

    public function test_payroll_snapshot_blocks_void_even_when_run_is_draft(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-TZ-PAY');

        $session = EmployeeAttendanceSession::create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => '2026-10-14 09:00:00',
            'clock_out_at' => '2026-10-14 17:00:00',
            'source' => 'admin',
        ]);
        $period = \App\Models\PayrollPeriod::create([
            'name' => 'October payroll',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'status' => 'open',
        ]);
        $run = \App\Models\PayrollRun::create([
            'payroll_period_id' => $period->id,
            'status' => 'draft',
            'created_by_user_id' => $manager->id,
        ]);
        \App\Models\PayrollEntry::create([
            'payroll_run_id' => $run->id,
            'employee_profile_id' => $employee->id,
            'employee_code_snapshot' => $employee->employee_code,
            'employee_name_snapshot' => $cashier->name,
            'pay_basis_snapshot' => 'hourly',
            'base_rate_snapshot' => '100.00',
            'currency_snapshot' => 'EGP',
            'attendance_session_count' => 1,
            'net_work_minutes' => 480,
            'calculation_snapshot' => ['attendance_session_ids' => [$session->id]],
        ]);

        $this->actingAs($manager)
            ->post(route('admin.workforce.attendance.void', $session), [
                'void_reason' => 'Attempt to void a payroll snapshot session',
            ])
            ->assertSessionHasErrors('void_reason');

        $this->assertNull($session->fresh()->voided_at);
    }

    private function staffWithRole(string $code): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = \App\Models\Role::query()->where('slug', $code)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function employeeFor(User $user, string $code): EmployeeProfile
    {
        return EmployeeProfile::create([
            'user_id' => $user->id,
            'employee_code' => $code,
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ]);
    }
}
