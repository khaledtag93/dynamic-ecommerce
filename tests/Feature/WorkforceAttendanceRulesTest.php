<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendanceBreak;
use App\Models\EmployeeAttendanceCorrection;
use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\AttendanceRulesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceAttendanceRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_break_lifecycle_is_audited_and_open_break_blocks_clock_out(): void
    {
        Carbon::setTestNow('2026-09-25 09:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-BREAK-1');

        $this->actingAs($cashier)
            ->post(route('admin.workforce.clock-in'))
            ->assertRedirect(route('admin.workforce.time-clock'));

        Carbon::setTestNow('2026-09-25 12:00:00');

        $this->post(route('admin.workforce.break-start'), ['notes' => 'Lunch'])
            ->assertRedirect(route('admin.workforce.time-clock'))
            ->assertSessionHas('success');

        $break = EmployeeAttendanceBreak::query()->firstOrFail();
        $this->assertNull($break->ends_at);
        $this->assertSame('Lunch', $break->notes);

        $this->post(route('admin.workforce.break-start'))
            ->assertSessionHasErrors('attendance');

        $this->post(route('admin.workforce.clock-out'))
            ->assertSessionHasErrors('attendance');

        Carbon::setTestNow('2026-09-25 12:30:00');

        $this->post(route('admin.workforce.break-end'))
            ->assertRedirect(route('admin.workforce.time-clock'))
            ->assertSessionHas('success');

        Carbon::setTestNow('2026-09-25 17:00:00');

        $this->post(route('admin.workforce.clock-out'))
            ->assertRedirect(route('admin.workforce.time-clock'))
            ->assertSessionHas('success');

        $session = EmployeeAttendanceSession::query()
            ->where('employee_profile_id', $employee->id)
            ->with(['breaks', 'approvedCorrection'])
            ->firstOrFail();

        $this->assertSame(30, $session->breakMinutes());
        $this->assertSame(450, $session->netWorkedMinutes());

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $cashier->id,
            'action' => 'attendance_break_started',
            'subject_id' => $break->id,
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $cashier->id,
            'action' => 'attendance_break_ended',
            'subject_id' => $break->id,
        ]);
    }

    public function test_employee_correction_request_requires_ownership_closed_session_and_single_pending_request(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-CORR-1');
        $other = $this->staffWithRole('cashier');
        $otherEmployee = $this->employeeFor($other, 'EMP-CORR-2');

        $closed = $this->attendanceSession($employee, '2026-09-24 09:10:00', '2026-09-24 17:00:00');
        $otherSession = $this->attendanceSession($otherEmployee, '2026-09-24 09:00:00', '2026-09-24 17:00:00');
        $open = EmployeeAttendanceSession::query()->create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => '2026-09-25 09:00:00',
            'source' => 'admin',
        ]);

        $this->actingAs($cashier)
            ->get(route('admin.workforce.corrections.create', $otherSession))
            ->assertForbidden();

        $this->get(route('admin.workforce.corrections.create', $open))
            ->assertStatus(422);

        $this->post(route('admin.workforce.corrections.store', $closed), [
            'requested_clock_in_at' => '2026-09-24 09:00:00',
            'requested_clock_out_at' => '2026-09-24 17:05:00',
            'reason' => 'The terminal was unavailable when I arrived.',
        ])
            ->assertRedirect(route('admin.workforce.time-clock'))
            ->assertSessionHas('success');

        $correction = EmployeeAttendanceCorrection::query()->firstOrFail();

        $this->assertSame(EmployeeAttendanceCorrection::STATUS_PENDING, $correction->status);
        $this->assertSame('2026-09-24 09:10:00', $correction->previous_clock_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-24 09:00:00', $correction->requested_clock_in_at->format('Y-m-d H:i:s'));

        $this->post(route('admin.workforce.corrections.store', $closed), [
            'requested_clock_in_at' => '2026-09-24 08:55:00',
            'requested_clock_out_at' => '2026-09-24 17:05:00',
            'reason' => 'Second pending request',
        ])->assertSessionHasErrors('correction');

        $this->assertDatabaseCount('employee_attendance_corrections', 1);
    }

    public function test_manager_approval_changes_effective_time_without_rewriting_recorded_session(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-APPROVE-1');
        $session = $this->attendanceSession($employee, '2026-09-24 09:15:00', '2026-09-24 16:45:00');

        $this->actingAs($cashier)
            ->post(route('admin.workforce.corrections.store', $session), [
                'requested_clock_in_at' => '2026-09-24 09:00:00',
                'requested_clock_out_at' => '2026-09-24 17:00:00',
                'reason' => 'Approved correction example',
            ])
            ->assertRedirect();

        $correction = EmployeeAttendanceCorrection::query()->firstOrFail();

        $this->actingAs($manager)
            ->patch(route('admin.workforce.corrections.approve', $correction), [
                'review_notes' => 'Confirmed with supervisor.',
            ])
            ->assertRedirect(route('admin.workforce.corrections.index'))
            ->assertSessionHas('success');

        $session->refresh()->load('approvedCorrection');
        $correction->refresh();

        $this->assertSame('2026-09-24 09:15:00', $session->clock_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-24 16:45:00', $session->clock_out_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-24 09:00:00', $session->effectiveClockInAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-24 17:00:00', $session->effectiveClockOutAt()->format('Y-m-d H:i:s'));

        $this->assertSame(EmployeeAttendanceCorrection::STATUS_APPROVED, $correction->status);
        $this->assertSame($manager->id, $correction->reviewed_by_user_id);
        $this->assertSame('Confirmed with supervisor.', $correction->review_notes);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $manager->id,
            'action' => 'attendance_correction_approved',
            'subject_id' => $correction->id,
        ]);

        $this->actingAs($manager)
            ->patch(route('admin.workforce.corrections.reject', $correction))
            ->assertSessionHasErrors('correction');
    }

    public function test_rejected_correction_does_not_change_effective_attendance(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-REJECT-1');
        $session = $this->attendanceSession($employee, '2026-09-24 09:10:00', '2026-09-24 17:00:00');

        $this->actingAs($cashier)
            ->post(route('admin.workforce.corrections.store', $session), [
                'requested_clock_in_at' => '2026-09-24 09:00:00',
                'requested_clock_out_at' => '2026-09-24 17:00:00',
                'reason' => 'Please change my start time.',
            ])
            ->assertRedirect();

        $correction = EmployeeAttendanceCorrection::query()->firstOrFail();

        $this->actingAs($manager)
            ->patch(route('admin.workforce.corrections.reject', $correction), [
                'review_notes' => 'No supporting evidence.',
            ])
            ->assertRedirect();

        $session->refresh()->load('approvedCorrection');

        $this->assertSame('2026-09-24 09:10:00', $session->effectiveClockInAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-24 17:00:00', $session->effectiveClockOutAt()->format('Y-m-d H:i:s'));
    }

    public function test_attendance_rules_calculate_late_early_departure_breaks_net_work_and_absence(): void
    {
        Carbon::setTestNow('2026-09-25 20:00:00');

        $cashier = User::factory()->create(['role_as' => 1]);
        $employee = $this->employeeFor($cashier, 'EMP-RULE-1');

        $shift = EmployeeWorkShift::query()->create([
            'employee_profile_id' => $employee->id,
            'starts_at' => '2026-09-25 09:00:00',
            'ends_at' => '2026-09-25 17:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ]);

        $session = $this->attendanceSession($employee, '2026-09-25 09:12:00', '2026-09-25 16:45:00');

        EmployeeAttendanceBreak::query()->create([
            'employee_attendance_session_id' => $session->id,
            'starts_at' => '2026-09-25 12:00:00',
            'ends_at' => '2026-09-25 12:30:00',
        ]);

        $session->load(['breaks', 'approvedCorrection']);

        $assessment = app(AttendanceRulesService::class)->assessShift($shift, $session);

        $this->assertSame('completed', $assessment['state']);
        $this->assertSame('late', $assessment['start_status']);
        $this->assertSame(12, $assessment['start_delta_minutes']);
        $this->assertSame('early_departure', $assessment['end_status']);
        $this->assertSame(-15, $assessment['end_delta_minutes']);
        $this->assertSame(453, $assessment['gross_minutes']);
        $this->assertSame(30, $assessment['break_minutes']);
        $this->assertSame(423, $assessment['net_minutes']);

        $absentShift = EmployeeWorkShift::query()->create([
            'employee_profile_id' => $employee->id,
            'starts_at' => '2026-09-24 09:00:00',
            'ends_at' => '2026-09-24 17:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ]);

        $absentAssessment = app(AttendanceRulesService::class)->assessShift($absentShift, null);
        $this->assertSame('absent', $absentAssessment['state']);
    }

    public function test_manager_correction_workspace_is_live_and_cashier_cannot_review_it(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $cashier->update(['name' => '<script>Correction Employee</script>']);
        $employee = $this->employeeFor($cashier, 'EMP-CORR-LIVE');
        $session = $this->attendanceSession($employee, '2026-09-24 09:10:00', '2026-09-24 17:00:00');

        $this->actingAs($cashier)
            ->post(route('admin.workforce.corrections.store', $session), [
                'requested_clock_in_at' => '2026-09-24 09:00:00',
                'requested_clock_out_at' => '2026-09-24 17:00:00',
                'reason' => '<script>Terminal issue</script>',
            ])
            ->assertRedirect();

        $url = route('admin.workforce.corrections.index', [
            'search' => 'EMP-CORR-LIVE',
            'status' => EmployeeAttendanceCorrection::STATUS_PENDING,
        ]);

        $this->actingAs($manager)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('&lt;script&gt;Correction Employee&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;Terminal issue&lt;/script&gt;', false);

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Terminal issue&lt;/script&gt;', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)
            ->get(route('admin.workforce.corrections.index'))
            ->assertForbidden();
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

    private function attendanceSession(
        EmployeeProfile $employee,
        string $clockIn,
        string $clockOut,
    ): EmployeeAttendanceSession {
        return EmployeeAttendanceSession::query()->create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'source' => 'admin',
        ]);
    }
}
