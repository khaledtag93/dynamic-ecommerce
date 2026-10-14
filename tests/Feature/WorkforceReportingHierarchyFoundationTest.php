<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Workforce\ReportingHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceReportingHierarchyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_manager_can_assign_reporting_line_and_reassignment_is_audited(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $operator = $this->user('operations_manager');
        $director = $this->employee($this->user('operations_manager'), 'EMP-DIRECTOR');
        $supervisor = $this->employee($this->user('operations_manager'), 'EMP-SUPERVISOR');
        $cashier = $this->employee($this->user('cashier'), 'EMP-WORKER');

        $this->actingAs($operator)->put(route('admin.workforce.employees.update', $cashier), [
            'employee_code' => $cashier->employee_code,
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'manager_employee_profile_id' => $supervisor->id,
        ])->assertRedirect()->assertSessionHas('success');

        $cashier->refresh();
        $this->assertSame($supervisor->id, (int) $cashier->manager_employee_profile_id);
        $this->assertTrue(app(ReportingHierarchyService::class)->reportsTo($cashier, $supervisor));
        $this->assertFalse(app(ReportingHierarchyService::class)->reportsTo($supervisor, $cashier));

        app(ReportingHierarchyService::class)->assignManager($supervisor, $director->id, $operator);
        $this->assertTrue(app(ReportingHierarchyService::class)->reportsTo($cashier->fresh(), $director));

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'employee_reporting_manager_changed',
            'subject_id' => $cashier->id,
            'admin_user_id' => $operator->id,
        ]);
        $this->assertCount(1, $supervisor->directReports()->get());
        $this->assertTrue($director->directReports()->whereKey($supervisor->id)->exists());
    }

    public function test_cycles_self_management_and_non_manager_assignment_are_rejected(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $operator = $this->user('operations_manager');
        $a = $this->employee($this->user('operations_manager'), 'EMP-A');
        $b = $this->employee($this->user('operations_manager'), 'EMP-B');
        $ordinary = $this->employee($this->user('cashier'), 'EMP-STAFF');
        $svc = app(ReportingHierarchyService::class);
        $svc->assignManager($b, $a->id, $operator);

        $this->actingAs($operator)->put(route('admin.workforce.employees.update', $a), $this->form($a, $b->id))
            ->assertSessionHasErrors('manager_employee_profile_id');
        $this->put(route('admin.workforce.employees.update', $a), $this->form($a, $a->id))
            ->assertSessionHasErrors('manager_employee_profile_id');
        $this->put(route('admin.workforce.employees.update', $a), $this->form($a, $ordinary->id))
            ->assertSessionHasErrors('manager_employee_profile_id');

        $this->assertNull($a->fresh()->manager_employee_profile_id);
        $this->assertSame($a->id, (int) $b->fresh()->manager_employee_profile_id);
    }

    public function test_inactive_manager_rejected_and_unassigned_employee_remains_supported(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $operator = $this->user('operations_manager');
        $inactive = $this->employee($this->user('operations_manager'), 'EMP-INACTIVE');
        $inactive->update(['status' => EmployeeProfile::STATUS_INACTIVE]);
        $worker = $this->employee($this->user('cashier'), 'EMP-NEW');

        $this->actingAs($operator)->put(route('admin.workforce.employees.update', $worker), $this->form($worker, $inactive->id))
            ->assertSessionHasErrors('manager_employee_profile_id');

        $this->put(route('admin.workforce.employees.update', $worker), $this->form($worker, null))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNull($worker->fresh()->manager_employee_profile_id);
    }

    public function test_new_employee_can_be_created_with_an_existing_manager(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $operator = $this->user('operations_manager');
        $manager = $this->employee($this->user('operations_manager'), 'EMP-MANAGER-CREATE');
        $newAccount = $this->user('cashier');

        $this->actingAs($operator)->get(route('admin.workforce.employees.create'))
            ->assertOk()
            ->assertSee(__('Direct manager'));

        $this->post(route('admin.workforce.employees.store'), [
            'user_id' => $newAccount->id,
            'employee_code' => 'EMP-NEW-ASSIGNED',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'manager_employee_profile_id' => $manager->id,
        ])->assertRedirect()->assertSessionHas('success');

        $created = EmployeeProfile::where('employee_code', 'EMP-NEW-ASSIGNED')->firstOrFail();
        $this->assertSame($manager->id, (int) $created->manager_employee_profile_id);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'employee_reporting_manager_changed',
            'subject_id' => $created->id,
            'admin_user_id' => $operator->id,
        ]);
    }

    public function test_cashier_cannot_edit_reporting_hierarchy(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $cashier = $this->user('cashier');
        $worker = $this->employee($this->user('cashier'), 'EMP-WORKER-2');
        $manager = $this->employee($this->user('operations_manager'), 'EMP-MGR-2');

        $this->actingAs($cashier)->put(route('admin.workforce.employees.update', $worker), $this->form($worker, $manager->id))
            ->assertForbidden();
        $this->assertNull($worker->fresh()->manager_employee_profile_id);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $user->roles()->sync([Role::where('slug', $role)->firstOrFail()->id]);
        return $user->fresh();
    }

    private function employee(User $user, string $code): EmployeeProfile
    {
        return EmployeeProfile::create([
            'user_id' => $user->id,
            'employee_code' => $code,
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
        ]);
    }

    private function form(EmployeeProfile $employee, ?int $manager): array
    {
        return [
            'employee_code' => $employee->employee_code,
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'manager_employee_profile_id' => $manager,
        ];
    }
}
