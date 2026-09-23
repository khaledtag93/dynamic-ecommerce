<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPromotionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_be_promoted_without_an_explicit_staff_role(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
        $customer = User::factory()->create(['role_as' => 0]);

        $this->actingAs($owner)
            ->patch(route('admin.customers.update-role', $customer), ['role_as' => 1])
            ->assertSessionHasErrors('role_id');

        $this->assertSame(0, $customer->fresh()->role_as);
        $this->assertFalse($customer->roles()->exists());
    }

    public function test_customer_cannot_be_promoted_with_the_super_admin_role(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $owner = User::factory()->create(['role_as' => 1]);
        $customer = User::factory()->create(['role_as' => 0]);
        $superRole = Role::query()->where('slug', 'super_admin')->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('admin.customers.update-role', $customer), [
                'role_as' => 1,
                'role_id' => $superRole->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertSame(0, $customer->fresh()->role_as);
        $this->assertFalse($customer->roles()->exists());
    }

    public function test_promotion_assigns_a_limited_role_and_demotion_removes_it(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $owner = User::factory()->create(['role_as' => 1]);
        $customer = User::factory()->create(['role_as' => 0]);
        $staffRole = Role::query()->where('slug', 'support_agent')->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('admin.customers.update-role', $customer), [
                'role_as' => 1,
                'role_id' => $staffRole->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $customer->fresh()->role_as);
        $this->assertTrue($customer->roles()->whereKey($staffRole->id)->exists());
        $this->assertFalse($customer->fresh()->isSuperAdmin());
        $this->assertFalse($customer->fresh()->hasPermission('permissions.manage'));

        $this->actingAs($owner)
            ->patch(route('admin.customers.update-role', $customer), ['role_as' => 0])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $customer->fresh()->role_as);
        $this->assertFalse($customer->roles()->exists());
    }

    public function test_owner_access_cannot_be_changed_from_customer_management(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $owner = User::factory()->create(['role_as' => 1]);
        $otherLegacyOwner = User::factory()->create(['role_as' => 1]);
        $staffRole = Role::query()->where('slug', 'support_agent')->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('admin.customers.update-role', $otherLegacyOwner), [
                'role_as' => 1,
                'role_id' => $staffRole->id,
            ])
            ->assertForbidden();

        $this->assertSame(1, $otherLegacyOwner->fresh()->role_as);
        $this->assertFalse($otherLegacyOwner->roles()->exists());
    }

    public function test_staff_with_customer_access_cannot_promote_another_account(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $staff = User::factory()->create(['role_as' => 1]);
        $staffRole = Role::query()->where('slug', 'support_agent')->firstOrFail();
        $staff->roles()->attach($staffRole->id);
        $customer = User::factory()->create(['role_as' => 0]);

        $this->actingAs($staff)
            ->patch(route('admin.customers.update-role', $customer), [
                'role_as' => 1,
                'role_id' => $staffRole->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, $customer->fresh()->role_as);
        $this->assertFalse($customer->roles()->exists());
    }
}
