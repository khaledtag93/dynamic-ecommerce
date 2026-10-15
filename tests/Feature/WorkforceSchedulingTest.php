<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeProfile;
use App\Models\EmployeeWorkShift;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\WorkShiftService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manager_can_create_published_shift_and_employee_can_see_it_in_my_schedule(): void
    {
        Carbon::setTestNow('2026-09-25 08:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-SCHED-1');

        $this->actingAs($manager)
            ->post(route('admin.workforce.schedule.store'), [
                'employee_profile_id' => $employee->id,
                'starts_at' => '2026-09-26 09:00:00',
                'ends_at' => '2026-09-26 17:00:00',
                'status' => EmployeeWorkShift::STATUS_PUBLISHED,
                'location' => 'Front Store',
                'notes' => 'Morning handover',
            ])
            ->assertRedirect();

        $shift = EmployeeWorkShift::query()->firstOrFail();

        $this->assertSame(EmployeeWorkShift::STATUS_PUBLISHED, $shift->status);
        $this->assertNotNull($shift->published_at);
        $this->assertSame($manager->id, $shift->created_by_user_id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $manager->id,
            'action' => 'work_shift_created',
            'subject_id' => $shift->id,
        ]);

        $this->actingAs($cashier)
            ->get(route('admin.workforce.my-schedule'))
            ->assertOk()
            ->assertSee('Front Store')
            ->assertSee('Morning handover')
            ->assertSee('26/09/2026');
    }

    public function test_shift_update_can_explicitly_clear_nullable_metadata_without_clearing_omitted_fields(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = $this->staffWithRole('operations_manager');
        $employee = $this->employeeFor($this->staffWithRole('cashier'), 'EMP-CLEAR-META');
        $service = app(WorkShiftService::class);
        $shift = $service->create($employee, [
            'starts_at' => '2026-09-27 09:00:00',
            'ends_at' => '2026-09-27 17:00:00',
            'location' => 'Front Counter',
            'notes' => 'Old note',
        ], $manager);

        $service->update($shift, ['location' => null], $manager);
        $this->assertNull($shift->fresh()->location);
        $this->assertSame('Old note', $shift->fresh()->notes);

        $service->update($shift, ['notes' => null], $manager);
        $this->assertNull($shift->fresh()->location);
        $this->assertNull($shift->fresh()->notes);
    }
    public function test_service_rejects_invalid_shift_chronology_without_mutation(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = $this->staffWithRole('operations_manager');
        $employee = $this->employeeFor($this->staffWithRole('cashier'), 'EMP-INVALID-INTERVAL');
        $service = app(WorkShiftService::class);
        try {
            $service->create($employee, ['starts_at' => '2026-09-28 18:00:00', 'ends_at' => '2026-09-28 09:00:00'], $manager);
            $this->fail('Invalid shift was created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('ends_at', $e->errors());
        }
        $this->assertDatabaseCount('employee_work_shifts', 0);
        $shift = $service->create($employee, ['starts_at' => '2026-09-28 09:00:00', 'ends_at' => '2026-09-28 17:00:00'], $manager);
        try {
            $service->update($shift, ['ends_at' => '2026-09-28 09:00:00'], $manager);
            $this->fail('Zero duration accepted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('ends_at', $e->errors());
        }
        $this->assertDatabaseCount('employee_work_shifts', 1);
    }
    public function test_stale_shift_object_cannot_restore_previous_employee_on_metadata_update(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = $this->staffWithRole('operations_manager');
        $first = $this->employeeFor($this->staffWithRole('cashier'), 'EMP-OLD-OWNER');
        $second = $this->employeeFor($this->staffWithRole('cashier'), 'EMP-NEW-OWNER');
        $service = app(WorkShiftService::class);
        $stale = $service->create($first, [
            'starts_at' => '2026-09-29 09:00:00',
            'ends_at' => '2026-09-29 17:00:00',
        ], $manager);
        $service->update($stale, ['employee_profile_id' => $second->id], $manager);
        $service->update($stale, ['notes' => 'Updated without employee id'], $manager);
        $this->assertSame($second->id, $stale->fresh()->employee_profile_id);
        $this->assertSame('Updated without employee id', $stale->fresh()->notes);
    }
    public function test_service_invalid_shift_date_returns_validation_error_without_creating_shift(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = $this->staffWithRole('operations_manager');
        $employee = $this->employeeFor($this->staffWithRole('cashier'), 'EMP-BAD-DATE');
        try {
            app(WorkShiftService::class)->create($employee, [
                'starts_at' => 'not-a-real-date',
                'ends_at' => '2026-09-28 17:00:00',
            ], $manager);
            $this->fail('Invalid date unexpectedly accepted');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('starts_at', $exception->errors());
        }
        $this->assertDatabaseCount('employee_work_shifts', 0);
    }
    public function test_service_rejects_cancelled_status_without_cancellation_flow(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $manager = $this->staffWithRole('operations_manager');
        $employee = $this->employeeFor($this->staffWithRole('cashier'), 'EMP-STATUS-GUARD');
        $service = app(WorkShiftService::class);
        $data = ['starts_at' => '2026-09-28 09:00:00', 'ends_at' => '2026-09-28 17:00:00'];
        try {
            $service->create($employee, $data + ['status' => EmployeeWorkShift::STATUS_CANCELLED], $manager);
            $this->fail('Cancelled creation accepted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->assertDatabaseCount('employee_work_shifts', 0);
        $shift = $service->create($employee, $data, $manager);
        try {
            $service->update($shift, ['status' => EmployeeWorkShift::STATUS_CANCELLED], $manager);
            $this->fail('Cancelled update bypassed cancellation flow');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->assertSame(EmployeeWorkShift::STATUS_DRAFT, $shift->fresh()->status);
    }
    public function test_draft_shift_is_hidden_from_employee_schedule_until_published(): void
    {
        Carbon::setTestNow('2026-09-25 08:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-DRAFT-1');

        $shift = app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-09-27 10:00:00',
            'ends_at' => '2026-09-27 18:00:00',
            'status' => EmployeeWorkShift::STATUS_DRAFT,
            'location' => 'Draft Counter',
        ], $manager);

        $this->actingAs($cashier)
            ->get(route('admin.workforce.my-schedule'))
            ->assertOk()
            ->assertDontSee('Draft Counter');

        $this->actingAs($manager)
            ->put(route('admin.workforce.schedule.update', $shift), [
                'employee_profile_id' => $employee->id,
                'starts_at' => '2026-09-27 10:00:00',
                'ends_at' => '2026-09-27 18:00:00',
                'status' => EmployeeWorkShift::STATUS_PUBLISHED,
                'location' => 'Published Counter',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($cashier)
            ->get(route('admin.workforce.my-schedule'))
            ->assertOk()
            ->assertSee('Published Counter');
    }

    public function test_overlapping_shift_is_rejected_but_adjacent_shift_is_allowed(): void
    {
        Carbon::setTestNow('2026-09-25 08:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-OVERLAP-1');

        app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-09-28 09:00:00',
            'ends_at' => '2026-09-28 17:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
        ], $manager);

        $this->actingAs($manager)
            ->post(route('admin.workforce.schedule.store'), [
                'employee_profile_id' => $employee->id,
                'starts_at' => '2026-09-28 16:00:00',
                'ends_at' => '2026-09-28 18:00:00',
                'status' => EmployeeWorkShift::STATUS_DRAFT,
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, EmployeeWorkShift::count());

        $this->actingAs($manager)
            ->post(route('admin.workforce.schedule.store'), [
                'employee_profile_id' => $employee->id,
                'starts_at' => '2026-09-28 17:00:00',
                'ends_at' => '2026-09-28 21:00:00',
                'status' => EmployeeWorkShift::STATUS_DRAFT,
            ])
            ->assertRedirect();

        $this->assertSame(2, EmployeeWorkShift::count());
    }

    public function test_non_active_employee_cannot_receive_new_work_shift(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-INACTIVE-SHIFT', EmployeeProfile::STATUS_ON_LEAVE);

        $this->actingAs($manager)
            ->post(route('admin.workforce.schedule.store'), [
                'employee_profile_id' => $employee->id,
                'starts_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->setTime(17, 0)->format('Y-m-d H:i:s'),
                'status' => EmployeeWorkShift::STATUS_DRAFT,
            ])
            ->assertSessionHasErrors('employee_profile_id');

        $this->assertDatabaseCount('employee_work_shifts', 0);
    }

    public function test_cashier_can_view_own_schedule_but_cannot_open_manager_schedule(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $cashier = $this->staffWithRole('cashier');
        $this->employeeFor($cashier, 'EMP-PERM-SHIFT');

        $this->actingAs($cashier)
            ->get(route('admin.workforce.my-schedule'))
            ->assertOk()
            ->assertSee(__('My schedule'));

        $this->get(route('admin.workforce.schedule.index'))->assertForbidden();
        $this->get(route('admin.workforce.schedule.create'))->assertForbidden();
    }

    public function test_cancelled_shift_is_kept_for_history_hidden_from_my_schedule_and_not_editable(): void
    {
        Carbon::setTestNow('2026-09-25 08:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $employee = $this->employeeFor($cashier, 'EMP-CANCEL-1');

        $shift = app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-09-29 09:00:00',
            'ends_at' => '2026-09-29 17:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
            'location' => 'Cancel Me',
        ], $manager);

        $this->actingAs($manager)
            ->patch(route('admin.workforce.schedule.cancel', $shift))
            ->assertRedirect(route('admin.workforce.schedule.index'))
            ->assertSessionHas('success');

        $this->assertSame(EmployeeWorkShift::STATUS_CANCELLED, $shift->fresh()->status);
        $this->assertNotNull($shift->fresh()->cancelled_at);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $manager->id,
            'action' => 'work_shift_cancelled',
            'subject_id' => $shift->id,
        ]);

        $this->actingAs($cashier)
            ->get(route('admin.workforce.my-schedule'))
            ->assertOk()
            ->assertDontSee('Cancel Me');

        $this->actingAs($manager)
            ->put(route('admin.workforce.schedule.update', $shift), [
                'employee_profile_id' => $employee->id,
                'starts_at' => '2026-09-29 10:00:00',
                'ends_at' => '2026-09-29 18:00:00',
                'status' => EmployeeWorkShift::STATUS_PUBLISHED,
            ])
            ->assertSessionHasErrors('shift');
    }

    public function test_manager_live_schedule_filters_and_compares_actual_attendance(): void
    {
        Carbon::setTestNow('2026-09-25 18:00:00');
        app(AuthorizationService::class)->syncDefaults();

        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        $cashier->update(['name' => '<script>Scheduled Cashier</script>']);
        $employee = $this->employeeFor($cashier, 'EMP-LIVE-SCHED');
        $employee->update(['department' => 'Retail']);

        $otherCashier = $this->staffWithRole('cashier');
        $other = $this->employeeFor($otherCashier, 'EMP-OTHER-SCHED');
        $other->update(['department' => 'Warehouse']);

        $shift = app(WorkShiftService::class)->create($employee, [
            'starts_at' => '2026-09-25 09:00:00',
            'ends_at' => '2026-09-25 17:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
            'location' => '<script>Front Counter</script>',
        ], $manager);

        app(WorkShiftService::class)->create($other, [
            'starts_at' => '2026-09-25 09:00:00',
            'ends_at' => '2026-09-25 17:00:00',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
            'location' => 'Warehouse',
        ], $manager);

        EmployeeAttendanceSession::query()->create([
            'employee_profile_id' => $employee->id,
            'clock_in_at' => Carbon::parse('2026-09-25 09:12:00'),
            'clock_out_at' => Carbon::parse('2026-09-25 17:03:00'),
            'source' => 'admin',
        ]);

        $url = route('admin.workforce.schedule.index', [
            'search' => 'EMP-LIVE-SCHED',
            'status' => EmployeeWorkShift::STATUS_PUBLISHED,
            'department' => 'Retail',
            'date_from' => '2026-09-25',
            'date_to' => '2026-09-25',
        ]);

        $this->actingAs($manager)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Scheduled Cashier&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;Front Counter&lt;/script&gt;', false)
            ->assertSee(__(':minutes min late', ['minutes' => 12]))
            ->assertDontSee('EMP-OTHER-SCHED');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Scheduled Cashier&lt;/script&gt;', false)
            ->assertSee(__(':minutes min late', ['minutes' => 12]))
            ->assertDontSee('EMP-OTHER-SCHED')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->assertSame($shift->id, EmployeeWorkShift::query()->where('employee_profile_id', $employee->id)->value('id'));
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
