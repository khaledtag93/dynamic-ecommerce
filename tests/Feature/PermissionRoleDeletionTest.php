<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionRoleDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_custom_role_cannot_be_deleted(): void
    {
        $superAdmin = User::factory()->create(['role_as' => 1]);
        $staffAdmin = User::factory()->create(['role_as' => 1]);

        $role = Role::query()->create([
            'name' => 'Warehouse Staff',
            'slug' => 'warehouse_staff',
            'description' => 'Test role',
            'is_system' => false,
        ]);

        $staffAdmin->roles()->attach($role->id);

        $response = $this
            ->actingAs($superAdmin)
            ->delete(route('admin.permissions.roles.destroy', $role));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('role_user', [
            'role_id' => $role->id,
            'user_id' => $staffAdmin->id,
        ]);

        $this->assertFalse($staffAdmin->fresh()->isSuperAdmin());
    }

    public function test_unassigned_custom_role_can_be_deleted(): void
    {
        $superAdmin = User::factory()->create(['role_as' => 1]);

        $role = Role::query()->create([
            'name' => 'Temporary Staff',
            'slug' => 'temporary_staff',
            'description' => 'Test role',
            'is_system' => false,
        ]);

        $response = $this
            ->actingAs($superAdmin)
            ->delete(route('admin.permissions.roles.destroy', $role));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
