<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogoutAndResetSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_invalidates_browser_session_and_prior_remember_cookie(): void
    {
        $user = User::factory()->create(['email' => 'logout-session@example.test']);
        [$rememberCookie, $loggedInSessionId] = $this->loginWithRememberMe($user);
        $oldRememberToken = $user->fresh()->getRememberToken();

        // Logout is a state-changing POST, not an unauthenticated GET.
        $this->assertTrue(Route::has('logout'));
        $this->assertContains('POST', Route::getRoutes()->getByName('logout')->methods());
        $this->withCookie(config('session.cookie'), $loggedInSessionId)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotSame($loggedInSessionId, session()->getId());
        $this->assertNotSame($oldRememberToken, $user->fresh()->getRememberToken());

        // First second-browser attempt: replay only the invalidated old session.
        Auth::guard('web')->forgetUser();
        $this->withCookie(config('session.cookie'), $loggedInSessionId)
            ->get(route('account.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        // Separate browser with no authenticated session; old persistent cookie
        // cannot restore the account after the guard's logout token rotation.
        $this->replayRememberCookieWithoutSession($rememberCookie);
        $this->get(route('account.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_http_password_reset_consumes_token_rotates_remember_token_and_logs_in(): void
    {
        $user = User::factory()->create(['email' => 'reset-session@example.test']);
        [$rememberCookie] = $this->loginWithRememberMe($user);
        $beforeToken = $user->fresh()->getRememberToken();

        // Different browser: leave the original login intact in session storage,
        // without using logout(), which would rotate the remembered token itself.
        Auth::guard('web')->forgetUser();
        session()->invalidate();

        $resetToken = Password::broker()->createToken($user);
        $this->assertTrue(Password::broker()->tokenExists($user, $resetToken));

        $this->get(route('password.reset', $resetToken))->assertOk();
        $guestResetSessionId = session()->getId();

        $this->withCookie(config('session.cookie'), $guestResetSessionId)
            ->post(route('password.update'), [
                'email' => $user->email,
                'token' => $resetToken,
                'password' => 'after-reset-password-123',
                'password_confirmation' => 'after-reset-password-123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($guestResetSessionId, session()->getId());
        $this->assertTrue(Hash::check('after-reset-password-123', $user->fresh()->password));
        $this->assertNotSame($beforeToken, $user->fresh()->getRememberToken());
        $this->assertFalse(Password::broker()->tokenExists($user, $resetToken));

        // Old persistent browser cannot re-authenticate with its pre-reset cookie.
        $this->replayRememberCookieWithoutSession($rememberCookie);
        $this->get(route('account.index'))->assertRedirect(route('login'));
        $this->assertGuest();

        // A completed reset link must not be accepted a second time.
        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'should-not-be-accepted-123',
            'password_confirmation' => 'should-not-be-accepted-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('after-reset-password-123', $user->fresh()->password));
    }

    /**
     * The test client does not persist response cookies between requests.
     * Explicitly replay the same guest session cookie as a real browser.
     *
     * @return array{0: string, 1: string}
     */
    private function loginWithRememberMe(User $user): array
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
                return [$cookie->getValue(), session()->getId()];
            }
        }

        $this->fail('Expected the remembered login to queue a recaller cookie.');

        return ['', ''];
    }

    private function replayRememberCookieWithoutSession(string $encryptedRecaller): void
    {
        Auth::guard('web')->forgetUser();
        session()->invalidate();
        $this->withCookie(config('session.cookie'), Str::random(40));

        // The response header already carries the encrypted cookie. Do not
        // encrypt it twice in Laravel's test-client cookie middleware.
        $this->withUnencryptedCookie(Auth::guard('web')->getRecallerName(), $encryptedRecaller);
    }
}
