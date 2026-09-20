<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionEscalationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_manager_who_is_not_super_admin_cannot_change_staff_roles(): void
    {
        $permission = Permission::query()->create([
            'name' => 'Manage permissions',
            'slug' => 'permissions.manage',
            'group' => 'permissions',
        ]);

        $operatorRole = Role::query()->create([
            'name' => 'Permission Manager',
            'slug' => 'permission_manager',
            'is_system' => false,
        ]);
        $operatorRole->permissions()->attach($permission->id);

        $operator = User::factory()->create(['role_as' => 1]);
        $operator->roles()->attach($operatorRole->id);

        $targetRole = Role::query()->create([
            'name' => 'Target Staff',
            'slug' => 'target_staff',
            'is_system' => false,
        ]);

        $newRole = Role::query()->create([
            'name' => 'New Staff',
            'slug' => 'new_staff',
            'is_system' => false,
        ]);

        $target = User::factory()->create(['role_as' => 1]);
        $target->roles()->attach($targetRole->id);

        $response = $this
            ->actingAs($operator)
            ->patch(route('admin.permissions.users.role', $target), [
                'role_id' => $newRole->id,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('role_user', [
            'user_id' => $target->id,
            'role_id' => $targetRole->id,
        ]);
        $this->assertDatabaseMissing('role_user', [
            'user_id' => $target->id,
            'role_id' => $newRole->id,
        ]);
        $this->assertFalse($operator->fresh()->isSuperAdmin());
    }

    public function test_super_admin_cannot_clear_an_admin_role_into_legacy_super_admin_fallback(): void
    {
        $superAdmin = User::factory()->create(['role_as' => 1]);

        $staffRole = Role::query()->create([
            'name' => 'Support Staff',
            'slug' => 'support_staff_test',
            'is_system' => false,
        ]);

        $target = User::factory()->create(['role_as' => 1]);
        $target->roles()->attach($staffRole->id);

        $response = $this
            ->actingAs($superAdmin)
            ->patch(route('admin.permissions.users.role', $target), [
                'role_id' => '',
            ]);

        $response->assertSessionHasErrors('role_id');

        $this->assertDatabaseHas('role_user', [
            'user_id' => $target->id,
            'role_id' => $staffRole->id,
        ]);
        $this->assertFalse($target->fresh()->isSuperAdmin());
    }
}
