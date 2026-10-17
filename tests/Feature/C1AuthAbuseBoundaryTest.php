<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class C1AuthAbuseBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_credentials_are_throttled_and_successful_login_rotates_guest_session(): void
    {
        $user = User::factory()->create(['email' => 'auth-abuse@example.test']);
        $this->get(route('login'))->assertOk();
        $guestId = session()->getId();

        // Framework AuthenticatesUsers / ThrottlesLogins current source contract:
        // five failures in the same window block the next attempt.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withCookie(config('session.cookie'), $guestId)
                ->post(route('login'), [
                    'email' => $user->email,
                    'password' => 'incorrect-password',
                ])->assertRedirect()->assertSessionHasErrors('email');
            $this->assertGuest();
        }

        // A correct password must NOT bypass an active login lockout.
        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect()->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(2)->minutes();
        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($guestId, session()->getId(), 'Session fixation protection must rotate the guest ID.');
    }

    public function test_registration_enforces_email_uniqueness_and_ignores_admin_role_injection(): void
    {
        User::factory()->create(['email' => 'already-registered@example.test']);
        $this->get(route('register'))->assertOk();
        $guestId = session()->getId();

        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('register'), [
                'name' => 'Duplicate Customer',
                'email' => 'already-registered@example.test',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertRedirect()->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('register'), [
                'name' => 'Ordinary Customer',
                'email' => 'ordinary-customer@example.test',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
                'role_as' => 1,
                'email_verified_at' => now()->toDateTimeString(),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $newUser = User::query()->where('email', 'ordinary-customer@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('new-password-123', $newUser->password));
        $this->assertSame(0, (int) $newUser->role_as);
        $this->assertNull($newUser->email_verified_at);
        $this->assertFalse($newUser->roles()->exists());
        $this->assertAuthenticatedAs($newUser);
        $this->assertNotSame($guestId, session()->getId());
        $this->get('/admin/dashboard')->assertRedirect('/');
    }

    public function test_login_requires_csrf_when_real_forgery_middleware_is_enforced(): void
    {
        $this->enforceCsrfEvenInPhpunit();
        $user = User::factory()->create(['email' => 'login-csrf@example.test']);

        $this->get(route('login'))->assertOk();
        $guestId = session()->getId();
        $token = session()->token();

        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertStatus(419);
        $this->assertGuest();

        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('login'), [
                '_token' => $token,
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_csrf_when_real_forgery_middleware_is_enforced(): void
    {
        $this->enforceCsrfEvenInPhpunit();

        $this->get(route('register'))->assertOk();
        $guestId = session()->getId();
        $token = session()->token();
        $data = [
            'name' => 'CSRF Registration',
            'email' => 'registration-csrf@example.test',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ];

        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('register'), $data)->assertStatus(419);
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);

        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('register'), array_merge($data, ['_token' => $token]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => $data['email']]);
    }

    public function test_customer_account_and_admin_routes_enforce_distinct_access(): void
    {
        $this->get(route('account.index'))->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));

        $customer = User::factory()->create(['role_as' => 0]);
        $this->actingAs($customer)->get(route('account.index'))->assertOk();
        $this->get('/admin/dashboard')->assertRedirect('/');
        $this->assertFalse($customer->hasPermission('dashboard.view'));
    }

    private function enforceCsrfEvenInPhpunit(): void
    {
        // Laravel normally skips CSRF during PHPUnit. Override in this test
        // only, identically to the already-green LogoutAndReset lifecycle test.
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
    }
}
