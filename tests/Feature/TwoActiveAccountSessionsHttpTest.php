<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TwoActiveAccountSessionsHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{session: string, recaller: ?string}> */
    private array $browsers = [];

    private function requestAs(string $name, string $method, string $url, array $data = [])
    {
        // A browser must not inherit any cookie from a previous virtual device.
        $this->defaultCookies = [];
        Auth::guard('web')->forgetUser();

        if (isset($this->browsers[$name])) {
            $this->withCookie(config('session.cookie'), $this->browsers[$name]['session']);
        }
        if (($this->browsers[$name]['recaller'] ?? null) !== null) {
            // Value copied from a Set-Cookie response is already encrypted.
            $this->withUnencryptedCookie(Auth::guard('web')->getRecallerName(), $this->browsers[$name]['recaller']);
        }

        $response = $this->{strtolower($method)}($url, $data);
        $newId = session()->getId();
        $oldRecaller = $this->browsers[$name]['recaller'] ?? null;
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === Auth::guard('web')->getRecallerName()) {
                $oldRecaller = $cookie->getValue();
            }
        }
        $this->browsers[$name] = ['session' => $newId, 'recaller' => $oldRecaller];

        return $response;
    }

    private function loginDevice(string $name, User $user, bool $remember = false): void
    {
        $this->requestAs($name, 'get', route('login'))->assertOk();
        $before = $this->browsers[$name]['session'];
        $this->requestAs($name, 'post', route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => $remember ? '1' : '0',
        ])->assertRedirect();
        $this->assertNotSame($before, $this->browsers[$name]['session']);
        $this->requestAs($name, 'get', route('account.index'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    private function twoLiveDevices(User $user): array
    {
        $this->loginDevice('A', $user);
        $this->loginDevice('B', $user, true);

        $a = $this->browsers['A']['session'];
        $b = $this->browsers['B']['session'];
        $this->assertNotSame($a, $b);
        $handler = app('session')->driver()->getHandler();
        $this->assertNotEmpty($handler->read($a), 'Device A must have a persisted authenticated server session.');
        $this->assertNotEmpty($handler->read($b), 'Device B must have a separately persisted authenticated server session.');

        // Switch back and forth WITHOUT logging either device out.
        $this->requestAs('A', 'get', route('account.index'))->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->requestAs('B', 'get', route('account.index'))->assertOk();
        $this->assertAuthenticatedAs($user);

        return [$a, $b, $this->browsers['B']['recaller']];
    }

    private function observeExistingDevices(User $user, string $a, string $b): void
    {
        foreach (['A' => $a, 'B' => $b] as $device => $originalId) {
            $this->assertSame($originalId, $this->browsers[$device]['session']);
            $response = $this->requestAs($device, 'get', route('account.index'));
            $status = $response->getStatusCode();
            $this->assertContains($status, [200, 302], 'Unexpected device response');
            fwrite(STDERR, "C1B active server session {$device}: HTTP {$status}; originally {$originalId}\n");
            if ($status === 200) {
                $this->assertAuthenticatedAs($user);
            } else {
                $this->assertGuest();
            }
        }
    }

    private function rejectOldRecaller(string $cookie): void
    {
        $this->browsers['C'] = ['session' => str_repeat('c', 40), 'recaller' => $cookie];
        $this->requestAs('C', 'get', route('account.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_two_persisted_active_sessions_after_profile_password_change(): void
    {
        $user = User::factory()->create();
        [$a, $b, $recaller] = $this->twoLiveDevices($user);
        $this->assertNotNull($recaller);

        $this->requestAs('A', 'patch', route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'profile-updated-password-123',
            'password_confirmation' => 'profile-updated-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('profile-updated-password-123', $user->fresh()->password));
        $this->observeExistingDevices($user, $a, $b);
        $this->rejectOldRecaller($recaller);
    }

    public function test_two_persisted_active_sessions_after_http_password_reset(): void
    {
        $user = User::factory()->create();
        [$a, $b, $recaller] = $this->twoLiveDevices($user);
        $this->assertNotNull($recaller);

        // A third, fresh reset browser; token created only in isolated test DB.
        $token = Password::broker()->createToken($user);
        $this->requestAs('R', 'get', route('password.reset', $token))->assertOk();
        $this->requestAs('R', 'post', route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'reset-updated-password-123',
            'password_confirmation' => 'reset-updated-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('reset-updated-password-123', $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
        $this->observeExistingDevices($user, $a, $b);
        $this->rejectOldRecaller($recaller);
    }
}
