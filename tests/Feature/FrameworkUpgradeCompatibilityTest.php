<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class FrameworkUpgradeCompatibilityTest extends TestCase
{
    public function test_application_csrf_wrapper_uses_laravel_13_request_forgery_protection(): void
    {
        $this->assertTrue(is_subclass_of(
            VerifyCsrfToken::class,
            PreventRequestForgery::class,
        ));
    }

    public function test_session_serialization_is_explicit_and_upgrade_safe_by_default(): void
    {
        $this->assertSame('php', config('session.serialization'));
    }

    public function test_public_application_still_boots_after_framework_upgrade(): void
    {
        $this->get('/')->assertOk();
    }
}
