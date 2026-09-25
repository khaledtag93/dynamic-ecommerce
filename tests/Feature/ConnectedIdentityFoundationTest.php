<?php

namespace Tests\Feature;

use App\Models\ConnectedIdentity;
use App\Models\User;
use App\Services\Auth\ConnectedIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConnectedIdentityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_identity_can_be_linked_only_to_matching_signed_in_email(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.test']);

        $identity = app(ConnectedIdentityService::class)->linkVerifiedIdentity($user, [
            'provider' => ConnectedIdentity::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-123',
            'email' => 'CUSTOMER@example.test',
            'email_verified' => true,
            'display_name' => 'Customer',
        ]);

        $this->assertSame($user->id, $identity->user_id);
        $this->assertSame('google', $identity->provider);
        $this->assertSame('google-123', $identity->provider_user_id);
        $this->assertSame('customer@example.test', $identity->provider_email);
        $this->assertNotNull($identity->provider_email_verified_at);
    }

    public function test_unverified_or_mismatched_provider_email_cannot_be_linked(): void
    {
        $service = app(ConnectedIdentityService::class);
        $user = User::factory()->create(['email' => 'customer@example.test']);

        foreach ([
            ['email' => 'customer@example.test', 'email_verified' => false],
            ['email' => 'other@example.test', 'email_verified' => true],
        ] as $payload) {
            try {
                $service->linkVerifiedIdentity($user, [
                    'provider' => ConnectedIdentity::PROVIDER_GOOGLE,
                    'provider_user_id' => 'google-'.md5(json_encode($payload)),
                    ...$payload,
                ]);
                $this->fail('Expected identity linking to be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('provider', $exception->errors());
            }
        }

        $this->assertDatabaseCount('connected_identities', 0);
    }

    public function test_provider_identity_cannot_be_claimed_by_two_accounts(): void
    {
        $service = app(ConnectedIdentityService::class);
        $first = User::factory()->create(['email' => 'first@example.test']);
        $second = User::factory()->create(['email' => 'first@example.test']);

        $service->linkVerifiedIdentity($first, [
            'provider' => ConnectedIdentity::PROVIDER_FACEBOOK,
            'provider_user_id' => 'facebook-123',
            'email' => 'first@example.test',
            'email_verified' => true,
        ]);

        $this->expectException(ValidationException::class);

        $service->linkVerifiedIdentity($second, [
            'provider' => ConnectedIdentity::PROVIDER_FACEBOOK,
            'provider_user_id' => 'facebook-123',
            'email' => 'first@example.test',
            'email_verified' => true,
        ]);
    }

    public function test_existing_email_requires_explicit_link_instead_of_silent_merge(): void
    {
        User::factory()->create(['email' => 'existing@example.test']);

        $this->expectException(ValidationException::class);

        app(ConnectedIdentityService::class)->assertNoSilentMerge(
            ConnectedIdentity::PROVIDER_GOOGLE,
            [
                'email' => 'existing@example.test',
                'email_verified' => true,
            ]
        );
    }

    public function test_existing_connected_identity_resolves_login_and_updates_last_login_time(): void
    {
        $service = app(ConnectedIdentityService::class);
        $user = User::factory()->create(['email' => 'linked@example.test']);

        $identity = $service->linkVerifiedIdentity($user, [
            'provider' => ConnectedIdentity::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-linked',
            'email' => 'linked@example.test',
            'email_verified' => true,
        ]);

        $this->assertNull($identity->last_login_at);

        $resolved = $service->resolveExistingLogin('google', 'google-linked');

        $this->assertTrue($resolved->is($user));
        $this->assertNotNull($identity->fresh()->last_login_at);
    }
}
