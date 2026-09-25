<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExplicitAdminRoleHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_roleless_legacy_admin_is_not_super_admin_and_cannot_enter_admin(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $user = User::factory()->create(['role_as' => 1]);

        $this->assertTrue($user->isLegacyAdmin());
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->hasRole('super_admin'));
        $this->assertFalse($user->hasPermission('dashboard.view'));
        $this->assertSame('Unassigned admin', $user->primaryRoleName());

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertRedirect('/');
    }

    public function test_explicit_super_admin_keeps_full_back_office_access(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $user = User::factory()->create(['role_as' => 1]);
        $superAdmin = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $user->roles()->sync([$superAdmin->id]);

        $this->assertTrue($user->fresh()->isSuperAdmin());
        $this->assertTrue($user->fresh()->hasPermission('dashboard.view'));
        $this->assertSame('Super Admin', $user->fresh()->primaryRoleName());

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_transition_migration_assigns_super_admin_to_existing_roleless_admin(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $user = User::factory()->create(['role_as' => 1]);
        $user->roles()->detach();

        $migration = require database_path('migrations/2026_09_25_090000_explicitly_assign_legacy_super_admins.php');
        $migration->up();

        $this->assertTrue($user->fresh()->hasRole('super_admin'));
        $this->assertTrue($user->fresh()->isSuperAdmin());
    }

    public function test_non_super_staff_keeps_only_assigned_permissions(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $user = User::factory()->create(['role_as' => 1]);
        $cashier = Role::query()->where('slug', 'cashier')->firstOrFail();
        $user->roles()->sync([$cashier->id]);

        $fresh = $user->fresh();

        $this->assertFalse($fresh->isSuperAdmin());
        $this->assertTrue($fresh->hasPermission('pos.manage'));
        $this->assertFalse($fresh->hasPermission('permissions.manage'));
    }
}
