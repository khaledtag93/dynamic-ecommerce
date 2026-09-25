<?php

namespace App\Services\Auth;

use App\Models\ConnectedIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConnectedIdentityService
{
    public function resolveExistingLogin(string $provider, string $providerUserId): ?User
    {
        $this->assertSupportedProvider($provider);

        $identity = ConnectedIdentity::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', trim($providerUserId))
            ->first();

        if (! $identity) {
            return null;
        }

        $identity->forceFill(['last_login_at' => now()])->save();

        return $identity->user;
    }

    public function linkVerifiedIdentity(User $user, array $identity): ConnectedIdentity
    {
        $provider = strtolower(trim((string) ($identity['provider'] ?? '')));
        $providerUserId = trim((string) ($identity['provider_user_id'] ?? ''));
        $providerEmail = Str::lower(trim((string) ($identity['email'] ?? '')));
        $emailVerified = (bool) ($identity['email_verified'] ?? false);

        $this->assertSupportedProvider($provider);

        if ($providerUserId === '') {
            throw ValidationException::withMessages([
                'provider' => __('The identity provider did not return a stable account identifier.'),
            ]);
        }

        if (! $emailVerified || $providerEmail === '') {
            throw ValidationException::withMessages([
                'provider' => __('A verified provider email is required before an identity can be linked.'),
            ]);
        }

        if ($providerEmail !== Str::lower(trim((string) $user->email))) {
            throw ValidationException::withMessages([
                'provider' => __('The provider email must match the signed-in account before linking.'),
            ]);
        }

        $claimedBy = ConnectedIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($claimedBy && (int) $claimedBy->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'provider' => __('This connected identity is already linked to another account.'),
            ]);
        }

        $existingForProvider = ConnectedIdentity::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if ($existingForProvider && $existingForProvider->provider_user_id !== $providerUserId) {
            throw ValidationException::withMessages([
                'provider' => __('This account already has a different identity linked for that provider.'),
            ]);
        }

        return DB::transaction(function () use ($user, $provider, $providerUserId, $providerEmail, $identity, $existingForProvider) {
            $record = $existingForProvider ?: new ConnectedIdentity([
                'user_id' => $user->id,
                'provider' => $provider,
            ]);

            $record->fill([
                'provider_user_id' => $providerUserId,
                'provider_email' => $providerEmail,
                'provider_email_verified_at' => now(),
                'avatar_url' => filled($identity['avatar_url'] ?? null)
                    ? trim((string) $identity['avatar_url'])
                    : null,
                'meta' => array_filter([
                    'display_name' => $identity['display_name'] ?? null,
                ], fn ($value) => filled($value)),
            ]);

            $record->save();

            return $record->fresh();
        });
    }

    public function assertNoSilentMerge(string $provider, array $identity): void
    {
        $this->assertSupportedProvider($provider);

        $email = Str::lower(trim((string) ($identity['email'] ?? '')));
        $emailVerified = (bool) ($identity['email_verified'] ?? false);

        if (! $emailVerified || $email === '') {
            throw ValidationException::withMessages([
                'provider' => __('The provider must return a verified email before sign-in can continue.'),
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'provider' => __('An account already uses this email. Sign in with your password first, then link the provider from your account settings.'),
            ]);
        }
    }

    private function assertSupportedProvider(string $provider): void
    {
        if (! in_array(strtolower(trim($provider)), ConnectedIdentity::supportedProviders(), true)) {
            throw ValidationException::withMessages([
                'provider' => __('This sign-in provider is not supported.'),
            ]);
        }
    }
}
