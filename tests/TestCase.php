<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Create an explicitly authorized Super Admin for feature tests.
     *
     * Production no longer treats role_as=1 without a staff role as Super Admin.
     * Older tests should use this helper instead of relying on the removed fallback.
     */
    protected function createSuperAdmin(array $attributes = []): User
    {
        app(AuthorizationService::class)->syncDefaults();

        $user = User::factory()->create(array_merge([
            'role_as' => 1,
        ], $attributes));

        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->fresh();
    }
}
