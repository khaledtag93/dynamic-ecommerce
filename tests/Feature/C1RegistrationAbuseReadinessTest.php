<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class C1RegistrationAbuseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_post_has_no_explicit_route_throttle_contract(): void
    {
        $registrationPost = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === 'register'
                && in_array('POST', $route->methods(), true)
        );

        $this->assertNotNull($registrationPost, 'Laravel UI registration POST route must exist.');
        $middleware = $registrationPost->gatherMiddleware();

        // This records the currently deployed route-level contract. It does
        // not assert that an upstream proxy/WAF imposes no traffic limits.
        $this->assertFalse(
            collect($middleware)->contains(
                fn ($name) => is_string($name) && str_starts_with($name, 'throttle:')
            ),
            'Registration currently has no explicit throttle: middleware.'
        );
        $this->assertContains('guest', $middleware);
    }

    public function test_two_independent_guest_browsers_can_register_distinct_accounts_from_same_ip(): void
    {
        Notification::fake();
        Mail::fake();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.44']);

        // Separate guest browsers with unrelated cookies. No actingAs(),
        // logout(), production data, provider, or background messages.
        $first = $this->registerInFreshGuestBrowser('first-from-shared-ip@example.test');
        $second = $this->registerInFreshGuestBrowser('second-from-shared-ip@example.test');

        $this->assertNotSame($first['guestId'], $second['guestId']);
        $this->assertNotSame($first['authenticatedId'], $second['authenticatedId']);
        $this->assertSame(2, User::query()->whereIn('email', [
            'first-from-shared-ip@example.test',
            'second-from-shared-ip@example.test',
        ])->count());
        $this->assertSame(0, (int) $first['user']->role_as);
        $this->assertSame(0, (int) $second['user']->role_as);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_duplicate_email_and_invalid_registration_input_cannot_create_accounts(): void
    {
        Notification::fake();
        Mail::fake();
        User::factory()->create(['email' => 'registered@example.test']);

        $this->get(route('register'))->assertOk();
        $guestId = session()->getId();

        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('register'), $this->registrationData('registered@example.test'))
            ->assertRedirect()->assertSessionHasErrors('email');
        $this->assertGuest();

        $invalidCases = [
            [['name' => ''], ['name']],
            [['email' => 'invalid-email'], ['email']],
            [['password' => 'short', 'password_confirmation' => 'short'], ['password']],
            [['password_confirmation' => 'wrong-confirmation'], ['password']],
        ];

        foreach ($invalidCases as [$overrides, $expectedErrors]) {
            $this->withCookie(config('session.cookie'), $guestId)
                ->post(route('register'), array_merge(
                    $this->registrationData('another@example.test'),
                    $overrides
                ))
                ->assertRedirect()->assertSessionHasErrors($expectedErrors);
            $this->assertGuest();
        }

        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    /**
     * @return array{guestId: string, authenticatedId: string, user: User}
     */
    private function registerInFreshGuestBrowser(string $email): array
    {
        Auth::forgetGuards();
        session()->flush();
        $this->withCookie(config('session.cookie'), Str::random(40));

        $this->get(route('register'))->assertOk();
        $guestId = session()->getId();

        $this->withCookie(config('session.cookie'), $guestId)
            ->post(route('register'), $this->registrationData($email))
            ->assertRedirect()->assertSessionHasNoErrors();

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $authenticatedId = session()->getId();
        $this->assertNotSame($guestId, $authenticatedId);

        return compact('guestId', 'authenticatedId', 'user');
    }

    /** @return array<string, string> */
    private function registrationData(string $email): array
    {
        return [
            'name' => 'Synthetic CI Customer',
            'email' => $email,
            'password' => 'test-password-123',
            'password_confirmation' => 'test-password-123',
        ];
    }
}
