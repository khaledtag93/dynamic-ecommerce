<?php

namespace App\Services\Frontend;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddressBookService
{
    public function save(User $user, array $data, ?int $addressId = null): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data, $addressId) {
            // Serialise changes for this customer so concurrent requests cannot create two defaults.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $address = $addressId === null
                ? $user->addresses()->make()
                : $user->addresses()->whereKey($addressId)->firstOrFail();
            $first = ! $user->addresses()->exists();
            $shipping = $first || (bool) ($data['is_default_shipping'] ?? false);
            $billing = $first || (bool) ($data['is_default_billing'] ?? false);

            $address->fill(collect($data)->except(['is_default_shipping', 'is_default_billing'])->all());
            $address->is_default_shipping = $shipping;
            $address->is_default_billing = $billing;

            if ($shipping) {
                $user->addresses()->where('is_default_shipping', true)
                    ->when($address->exists, fn ($query) => $query->where('id', '!=', $address->id))
                    ->update(['is_default_shipping' => false]);
            }
            if ($billing) {
                $user->addresses()->where('is_default_billing', true)
                    ->when($address->exists, fn ($query) => $query->where('id', '!=', $address->id))
                    ->update(['is_default_billing' => false]);
            }

            $address->save();
            $this->ensureDefaults($user);

            return $address->refresh();
        });
    }

    public function delete(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->addresses()->whereKey($addressId)->firstOrFail()->delete();
            $this->ensureDefaults($user);
        });
    }

    private function ensureDefaults(User $user): void
    {
        $oldest = $user->addresses()->orderBy('id')->first();
        if (! $oldest) {
            return;
        }

        if (! $user->addresses()->where('is_default_shipping', true)->exists()) {
            $oldest->update(['is_default_shipping' => true]);
        }
        if (! $user->addresses()->where('is_default_billing', true)->exists()) {
            $oldest->update(['is_default_billing' => true]);
        }
    }
}
