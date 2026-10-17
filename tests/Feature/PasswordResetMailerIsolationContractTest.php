<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Source-only baseline before any reset-only sandbox mailer is implemented.
 *
 * Notification::fake() and Mail::fake() guarantee zero outbound traffic.
 * These tests do not claim QAS delivery, Mailtrap capture, or future named
 * mailer isolation. No application or mail configuration is changed.
 */
class PasswordResetMailerIsolationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_legacy_staff_reset_requests_have_no_named_mailer_override(): void
    {
        Notification::fake();
        Mail::fake();

        $defaultMailer = config('mail.default');
        $this->assertSame('array', $defaultMailer);

        foreach ([
            ['email' => 'reset-customer@example.test', 'role_as' => 0],
            ['email' => 'reset-staff@example.test', 'role_as' => 1],
        ] as $attributes) {
            $user = User::factory()->create($attributes);

            $this->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect()
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', trans('passwords.sent'));

            Notification::assertSentTo(
                $user,
                ResetPassword::class,
                function (ResetPassword $notification, array $channels, User $notifiable) use ($user): bool {
                    // Inspect the constructed mail message without dispatching it.
                    // A null per-message override resolves via the global array mailer.
                    return $notifiable->is($user)
                        && $channels === ['mail']
                        && $notification->toMail($notifiable)->mailer === null;
                }
            );
        }

        // A fake-recorded notification is not an actual delivered email.
        Mail::assertNothingSent();
        $this->assertSame($defaultMailer, config('mail.default'));
    }

    public function test_unknown_account_still_returns_generic_success_without_mail_dispatch(): void
    {
        Notification::fake();
        Mail::fake();

        $this->post(route('password.email'), ['email' => 'reset-unknown@example.test'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', trans('passwords.sent'));

        Notification::assertNothingSent();
        Mail::assertNothingSent();

        $this->assertSame(60, config('auth.passwords.users.expire'));
        $this->assertSame(60, config('auth.passwords.users.throttle'));
    }
}
