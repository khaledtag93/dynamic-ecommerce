<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PasswordRecoveryPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_request_does_not_disclose_unknown_account_or_send_any_notification(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'missing-account@example.test',
        ]);

        // Every syntactically valid email should receive the same neutral
        // redirect and success flash as a known account: no user enumeration.
        $response->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_password_reset_broker_throttles_repeat_requests_without_exposing_it_to_callers(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'recovery@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('passwords.sent'));

        Notification::assertSentTo($user, ResetPassword::class, 1);

        // The broker must still throttle token issue. Its throttle result
        // must NOT reveal whether this address belongs to a real account.
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('passwords.sent'));

        Notification::assertSentTo($user, ResetPassword::class, 1);
    }

    public function test_recovery_input_validation_and_route_contract_remain_unchanged(): void
    {
        foreach (['password.request', 'password.email', 'password.reset', 'password.update'] as $route) {
            $this->assertTrue(Route::has($route), "Expected existing Laravel auth route {$route}.");
        }

        $this->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        // This app currently calls Auth::routes() without ['verify' => true].
        // It would be unsafe to claim email verification is active.
        $this->assertFalse(Route::has('verification.notice'));
        $this->assertFalse(Route::has('verification.resend'));
    }
}
