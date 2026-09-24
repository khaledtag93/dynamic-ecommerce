<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_staff_roles_receive_scoped_workforce_permissions(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $support = $this->staffWithRole('support_agent');
        $finance = $this->staffWithRole('finance_manager');

        $this->assertTrue($manager->hasPermission('workforce.view'));
        $this->assertTrue($manager->hasPermission('workforce.manage'));
        $this->assertTrue($manager->hasPermission('workforce.clock'));

        $this->assertTrue($cashier->hasPermission('workforce.clock'));
        $this->assertFalse($cashier->hasPermission('workforce.view'));
        $this->assertFalse($cashier->hasPermission('workforce.manage'));

        $this->assertTrue($support->hasPermission('workforce.clock'));
        $this->assertFalse($support->hasPermission('workforce.manage'));

        $this->assertTrue($finance->hasPermission('workforce.clock'));
        $this->assertFalse($finance->hasPermission('workforce.manage'));
    }

    public function test_manager_can_link_staff_account_to_one_employee_profile_but_not_customer_account(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $customer = User::factory()->create(['role_as' => 0]);

        $this->actingAs($manager)
            ->post(route('admin.workforce.employees.store'), [
                'user_id' => $cashier->id,
                'employee_code' => 'emp-001',
                'job_title' => 'Cashier',
                'department' => 'Retail',
                'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
                'status' => EmployeeProfile::STATUS_ACTIVE,
                'hire_date' => now()->subMonth()->toDateString(),
                'phone' => '01000000000',
            ])
            ->assertRedirect();

        $employee = EmployeeProfile::query()->where('user_id', $cashier->id)->firstOrFail();
        $this->assertSame('EMP-001', $employee->employee_code);
        $this->assertSame('Retail', $employee->department);

        $this->actingAs($manager)
            ->post(route('admin.workforce.employees.store'), [
                'user_id' => $cashier->id,
                'employee_code' => 'EMP-002',
                'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
                'status' => EmployeeProfile::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($manager)
            ->post(route('admin.workforce.employees.store'), [
                'user_id' => $customer->id,
                'employee_code' => 'EMP-003',
                'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
                'status' => EmployeeProfile::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_cashier_can_clock_in_once_and_clock_out_once_with_activity_audit(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-CASH-1');

        $this->actingAs($cashier)
            ->get(route('admin.workforce.time-clock'))
            ->assertOk()
            ->assertSee(__('My time clock'));

        $this->post(route('admin.workforce.clock-in'), ['notes' => 'Opening attendance'])
            ->assertRedirect(route('admin.workforce.time-clock'))
            ->assertSessionHas('success');

        $session = EmployeeAttendanceSession::query()->where('employee_profile_id', $employee->id)->firstOrFail();
        $this->assertNull($session->clock_out_at);
        $this->assertSame('Opening attendance', $session->clock_in_notes);

        $this->post(route('admin.workforce.clock-in'))
            ->assertSessionHasErrors('attendance');

        $this->post(route('admin.workforce.clock-out'), ['notes' => 'Shift handover complete'])
            ->assertRedirect(route('admin.workforce.time-clock'))
            ->assertSessionHas('success');

        $session->refresh();
        $this->assertNotNull($session->clock_out_at);
        $this->assertSame('Shift handover complete', $session->clock_out_notes);

        $this->post(route('admin.workforce.clock-out'))
            ->assertSessionHasErrors('attendance');

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $cashier->id,
            'action' => 'employee_clocked_in',
            'subject_id' => $session->id,
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $cashier->id,
            'action' => 'employee_clocked_out',
            'subject_id' => $session->id,
        ]);
    }

    public function test_manager_cannot_deactivate_employee_with_an_open_attendance_session(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-OPEN-1');

        EmployeeAttendanceSession::query()->create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => now()->subMinutes(20),
            'source' => 'admin',
        ]);

        $this->actingAs($manager)
            ->put(route('admin.workforce.employees.update', $employee), [
                'employee_code' => $employee->employee_code,
                'job_title' => $employee->job_title,
                'department' => $employee->department,
                'employment_type' => $employee->employment_type,
                'status' => EmployeeProfile::STATUS_ON_LEAVE,
                'hire_date' => optional($employee->hire_date)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(EmployeeProfile::STATUS_ACTIVE, $employee->fresh()->status);
    }

    public function test_non_active_employee_cannot_start_attendance_session(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');
        $this->employeeFor($cashier, 'EMP-LEAVE-1', EmployeeProfile::STATUS_ON_LEAVE);

        $this->actingAs($cashier)
            ->post(route('admin.workforce.clock-in'))
            ->assertSessionHasErrors('attendance');

        $this->assertDatabaseCount('employee_attendance_sessions', 0);
    }

    public function test_employee_directory_and_attendance_review_use_live_fragments_and_permissions(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $cashier->update(['name' => '<script>Live Employee</script>']);
        $other = $this->staffWithRole('cashier');

        $employee = $this->employeeFor($cashier, 'EMP-LIVE');
        $employee->update(['department' => 'Retail', 'job_title' => '<script>Cashier</script>']);
        $this->employeeFor($other, 'EMP-OTHER')->update(['department' => 'Warehouse']);

        EmployeeAttendanceSession::query()->create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => now()->subHour(),
            'source' => 'admin',
            'clock_in_notes' => '<script>attendance note</script>',
        ]);

        $directoryUrl = route('admin.workforce.employees.index', [
            'search' => 'EMP-LIVE',
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'department' => 'Retail',
        ]);

        $this->actingAs($manager)->get($directoryUrl)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Live Employee&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;Cashier&lt;/script&gt;', false)
            ->assertDontSee('EMP-OTHER');

        $this->withHeader('X-Live-List', '1')->get($directoryUrl)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Live Employee&lt;/script&gt;', false)
            ->assertDontSee('EMP-OTHER')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $attendanceUrl = route('admin.workforce.attendance.index', [
            'search' => 'EMP-LIVE',
            'status' => 'open',
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($manager)->get($attendanceUrl)
            ->assertOk()
            ->assertSee('&lt;script&gt;attendance note&lt;/script&gt;', false)
            ->assertSee('data-live-results', false);

        $this->withHeader('X-Live-List', '1')->get($attendanceUrl)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;attendance note&lt;/script&gt;', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)
            ->get(route('admin.workforce.employees.index'))
            ->assertForbidden();

        $this->get(route('admin.workforce.attendance.index'))
            ->assertForbidden();
    }

    public function test_time_clock_without_employee_profile_is_safe_and_explains_missing_setup(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');

        $this->actingAs($cashier)
            ->get(route('admin.workforce.time-clock'))
            ->assertOk()
            ->assertSee(__('Employee profile not configured'));

        $this->post(route('admin.workforce.clock-in'))
            ->assertSessionHasErrors('attendance');

        $this->assertDatabaseCount('employee_attendance_sessions', 0);
    }

    private function staffWithRole(string $slug): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function employeeFor(User $user, string $code, string $status = EmployeeProfile::STATUS_ACTIVE): EmployeeProfile
    {
        return EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => $code,
            'job_title' => 'Team Member',
            'department' => 'Retail',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => $status,
            'hire_date' => now()->subMonth()->toDateString(),
        ]);
    }
}
