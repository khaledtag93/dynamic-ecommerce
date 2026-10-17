<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountRememberTokenRotationTest extends TestCase
{
    use RefreshDatabase;


    public function test_rejected_password_change_keeps_existing_remember_token(): void
    {
        $rememberToken = Str::random(60);
        $user = User::factory()->create(['remember_token' => $rememberToken]);

        $this->actingAs($user)->patch(route('account.password.update'), [
            'current_password' => 'incorrect-current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password', null, 'passwordUpdate');

        $this->assertSame($rememberToken, $user->fresh()->getRememberToken());
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_changing_password_rotates_remember_me_token_without_ending_current_session(): void
    {
        $user = User::factory()->create([
            'email' => 'remember-security@example.test',
            'remember_token' => Str::random(60),
        ]);

        // Simulate a browser carrying its original session cookie into login.
        $this->get(route('login'))->assertOk();
        $guestSessionId = session()->getId();

        $login = $this->withCookie(config('session.cookie'), $guestSessionId)
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
                'remember' => '1',
            ]);

        $login->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $login->assertCookie(Auth::guard('web')->getRecallerName());

        $rememberTokenBefore = $user->fresh()->getRememberToken();
        $this->assertNotEmpty($rememberTokenBefore);
        $this->assertNotSame($guestSessionId, session()->getId());

        // Same browser sends back its authenticated session cookie.
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
        $this->assertNotSame(
            $rememberTokenBefore,
            $user->fresh()->getRememberToken(),
            'An old persistent Remember Me cookie must not stay valid after password change.'
        );
    }
}
