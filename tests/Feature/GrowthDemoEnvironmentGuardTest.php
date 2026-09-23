<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Services\Growth\GrowthValidationDemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class GrowthDemoEnvironmentGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_refuses_demo_seed_and_clear_routes(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
        $this->app->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->actingAs($owner)
            ->post(route('admin.growth.validation-demo.seed'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('admin.growth.validation-demo.clear'))
            ->assertForbidden();
    }

    public function test_production_refuses_direct_demo_service_seeding(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(LogicException::class);
        app(GrowthValidationDemoService::class)->seed();
    }

    public function test_production_refuses_direct_demo_service_cleanup(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(LogicException::class);
        app(GrowthValidationDemoService::class)->clear(true);
    }
}
