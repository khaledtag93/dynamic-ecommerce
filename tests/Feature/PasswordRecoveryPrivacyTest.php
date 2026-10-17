<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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


    public function test_reset_tokens_expire_and_cannot_be_replayed_after_successful_reset(): void
    {
        $user = User::factory()->create(['email' => 'token-lifecycle@example.test']);
        $broker = Password::broker();

        // The broker is exercised directly: absolutely no email or transport.
        $expiredToken = $broker->createToken($user);
        $this->assertTrue($broker->tokenExists($user, $expiredToken));

        $this->travel(61)->minutes();
        $this->assertFalse($broker->tokenExists($user, $expiredToken));
        $this->travelBack();

        $validToken = $broker->createToken($user);
        $this->assertTrue($broker->tokenExists($user, $validToken));

        $credentials = [
            'email' => $user->email,
            'token' => $validToken,
            'password' => 'rotated-password-123',
            'password_confirmation' => 'rotated-password-123',
        ];

        $this->assertSame(Password::PASSWORD_RESET, $broker->reset(
            $credentials,
            function (User $resetUser, string $password): void {
                $resetUser->forceFill(['password' => Hash::make($password)])->save();
            }
        ));
        $this->assertTrue(Hash::check('rotated-password-123', $user->fresh()->password));
        $this->assertFalse($broker->tokenExists($user, $validToken));

        $this->assertSame(Password::INVALID_TOKEN, $broker->reset(
            $credentials,
            function (): void {
                $this->fail('A used reset token must never reset a password twice.');
            }
        ));
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
