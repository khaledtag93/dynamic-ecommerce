<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Both browsers use different, independently stored Laravel session IDs.
 * No remember-me cookie is used to restore either authenticated browser.
 */
class ActiveTwoBrowserSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_existing_authenticated_browser_sessions_after_profile_password_change(): void
    {
        $user = User::factory()->create(['email' => 'two-active-profile@example.test']);

        [$browserA, $browserB] = $this->loginTwiceWithIndependentSessions($user);
        $this->assertAuthenticatedSession($browserA, $user);
        $this->assertAuthenticatedSession($browserB, $user);

        $this->switchToBrowser($browserA);
        $this->patch(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'profile-new-password-123',
            'password_confirmation' => 'profile-new-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('profile-new-password-123', $user->fresh()->password));

        // This is the current implementation's observable behavior, not an
        // approval of a policy to keep every pre-existing device signed in.
        $this->assertAuthenticatedSession($browserB, $user);
        $this->assertAuthenticatedSession($browserA, $user);
    }

    public function test_two_existing_authenticated_sessions_after_http_password_reset(): void
    {
        $user = User::factory()->create(['email' => 'two-active-reset@example.test']);

        [$browserA, $browserB] = $this->loginTwiceWithIndependentSessions($user);
        $this->assertAuthenticatedSession($browserA, $user);
        $this->assertAuthenticatedSession($browserB, $user);

        // Password-reset requester is an unrelated third browser. No logout()
        // or invalidation of the two stored authenticated sessions.
        $this->beginUnrelatedBrowser();
        $token = Password::broker()->createToken($user);
        $this->get(route('password.reset', $token))->assertOk();
        $resetGuestId = session()->getId();

        $this->switchToBrowser($resetGuestId);
        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'reset-new-password-123',
            'password_confirmation' => 'reset-new-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $resetBrowserId = session()->getId();
        $this->assertNotSame($resetGuestId, $resetBrowserId);
        $this->assertNotSame($browserA, $resetBrowserId);
        $this->assertNotSame($browserB, $resetBrowserId);
        $this->assertTrue(Hash::check('reset-new-password-123', $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));

        // Laravel UI's password-reset path rotates the recaller token, but
        // without auth.session the two *stored server sessions* survive.
        $this->assertAuthenticatedSession($browserB, $user);
        $this->assertAuthenticatedSession($browserA, $user);
        $this->assertAuthenticatedSession($resetBrowserId, $user);
    }

    /** @return array{0: string, 1: string} */
    private function loginTwiceWithIndependentSessions(User $user): array
    {
        $browserA = $this->freshLogin($user);
        $browserB = $this->freshLogin($user);

        $this->assertNotSame($browserA, $browserB);
        $this->assertNotEmpty(session()->getHandler()->read($browserA));
        $this->assertNotEmpty(session()->getHandler()->read($browserB));

        return [$browserA, $browserB];
    }

    private function freshLogin(User $user): string
    {
        $this->beginUnrelatedBrowser();
        $this->get(route('login'))->assertOk();
        $guestId = session()->getId();

        $this->switchToBrowser($guestId);
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $id = session()->getId();
        $this->assertNotSame($guestId, $id);

        return $id;
    }

    private function assertAuthenticatedSession(string $id, User $user): void
    {
        $this->switchToBrowser($id);
        $this->get(route('account.index'))->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Auth::guard('web')->viaRemember(), 'Session login must not be confused with recaller login.');
        $this->assertSame($id, session()->getId());
    }

    private function switchToBrowser(string $id): void
    {
        // Every separate browser request should resolve a fresh SessionGuard.
        // PHPUnit reuses its AuthManager guard instance unless it is forgotten.
        // Keep the stored sessions intact; never logout/invalidate either one.
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $id);
    }

    private function beginUnrelatedBrowser(): void
    {
        Auth::forgetGuards();
        // Do not call logout() here: it would deliberately revoke the recaller
        // token and could alter the behavior under measurement.
        $this->withCookie(config('session.cookie'), Str::random(40));
    }
}
