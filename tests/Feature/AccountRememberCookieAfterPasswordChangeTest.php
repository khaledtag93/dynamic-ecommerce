<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountRememberCookieAfterPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_cookie_restores_an_unmodified_account_without_a_session(): void
    {
        $user = User::factory()->create(['email' => 'remember-valid@example.test']);
        $cookie = $this->rememberCookieAfterRealLogin($user);

        $this->switchToAnotherBrowserWithRememberCookie($cookie);

        $this->get(route('account.index'))->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::guard('web')->viaRemember());
    }

    public function test_old_remember_cookie_does_not_authenticate_after_password_change(): void
    {
        $user = User::factory()->create(['email' => 'remember-expired@example.test']);
        $cookie = $this->rememberCookieAfterRealLogin($user);

        $oldRememberToken = $user->fresh()->getRememberToken();
        $oldPasswordHash = $user->fresh()->getAuthPassword();

        $this->withCookie(config('session.cookie'), session()->getId())
            ->patch(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNotSame($oldPasswordHash, $user->fresh()->getAuthPassword());

        // Laravel 13 also validates the password-derived hash in recaller
        // cookies, so explicitly rotating the database token is unnecessary.
        $this->assertSame($oldRememberToken, $user->fresh()->getRememberToken());

        $this->switchToAnotherBrowserWithRememberCookie($cookie);

        $this->get(route('account.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function rememberCookieAfterRealLogin(User $user): string
    {
        $this->get(route('login'))->assertOk();
        $guestSessionId = session()->getId();

        $response = $this->withCookie(config('session.cookie'), $guestSessionId)
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
                'remember' => '1',
            ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($guestSessionId, session()->getId());

        $recallerName = Auth::guard('web')->getRecallerName();
        $response->assertCookie($recallerName);
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $recallerName) {
                // Response-side cookie value is encrypted by Laravel web middleware.
                return $cookie->getValue();
            }
        }
        $this->fail('Remember Me cookie missing on successful remembered login.');

        return '';
    }

    private function switchToAnotherBrowserWithRememberCookie(string $encryptedRecaller): void
    {
        // No session ID is carried over from the authenticated first browser.
        // Do NOT logout(): it would rotate the remember token and hide the
        // password-change behavior this test is supposed to inspect.
        Auth::guard('web')->forgetUser();
        session()->invalidate();

        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->withUnencryptedCookie(
            Auth::guard('web')->getRecallerName(),
            $encryptedRecaller
        );
    }
}
