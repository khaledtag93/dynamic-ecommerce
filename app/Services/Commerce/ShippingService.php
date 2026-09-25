<?php

namespace App\Services\Commerce;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCity;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingService
{
    public function activeMethods(): Collection
    {
        return ShippingMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function methodOptions(): array
    {
        return $this->activeMethods()
            ->mapWithKeys(fn (ShippingMethod $method) => [$method->code => $method->displayName()])
            ->all();
    }

    public function quote(
        string $methodCode,
        string $city,
        string $country,
        float $subtotal,
        float $discount = 0,
    ): array {
        $method = ShippingMethod::query()
            ->where('code', $methodCode)
            ->where('is_active', true)
            ->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'delivery_method' => __('This delivery method is not currently available.'),
            ]);
        }

        if ($method->isPickup()) {
            return $this->pickupQuote($method, $subtotal, $discount);
        }

        $zone = $this->resolveZone($city, $country);

        if (! $zone) {
            throw ValidationException::withMessages([
                'shipping_city' => __('Shipping is not configured for this city and country yet.'),
            ]);
        }

        $rate = ShippingRate::query()
            ->with(['method', 'zone'])
            ->where('shipping_method_id', $method->id)
            ->where('shipping_zone_id', $zone->id)
            ->where('is_active', true)
            ->first();

        if (! $rate) {
            throw ValidationException::withMessages([
                'delivery_method' => __('The selected delivery method is not available for this shipping zone.'),
            ]);
        }

        $basisAmount = $rate->threshold_basis === ShippingRate::BASIS_AFTER_DISCOUNTS
            ? max(0, $subtotal - $discount)
            : $subtotal;

        $threshold = $rate->free_shipping_threshold !== null
            ? (float) $rate->free_shipping_threshold
            : null;

        $qualified = $threshold !== null && $basisAmount >= $threshold;
        $amount = $qualified ? 0.0 : (float) $rate->amount;
        $remaining = $threshold !== null ? max(0, $threshold - $basisAmount) : null;
        $progress = $threshold !== null && $threshold > 0
            ? min(100, (int) round(($basisAmount / $threshold) * 100))
            : null;

        return [
            'method_id' => $method->id,
            'method_code' => $method->code,
            'method_name' => $method->displayName(),
            'method_name_snapshot' => $method->name,
            'method_name_ar_snapshot' => $method->name_ar,
            'zone_id' => $zone->id,
            'zone_code' => $zone->code,
            'zone_name' => $zone->displayName(),
            'zone_name_snapshot' => $zone->name,
            'zone_name_ar_snapshot' => $zone->name_ar,
            'rate_id' => $rate->id,
            'amount' => round($amount, 2),
            'configured_amount' => round((float) $rate->amount, 2),
            'free_shipping_threshold' => $threshold,
            'threshold_basis' => $rate->threshold_basis,
            'threshold_basis_amount' => round($basisAmount, 2),
            'free_shipping_qualified' => $qualified,
            'free_shipping_remaining' => $remaining !== null ? round($remaining, 2) : null,
            'free_shipping_progress' => $progress,
            'eta_min_days' => $method->eta_min_days,
            'eta_max_days' => $method->eta_max_days,
            'eta_label' => $method->etaLabel(),
            'country_code' => $zone->country_code,
            'country_name' => $zone->country_name,
            'city' => trim($city),
            'pickup' => false,
        ];
    }

    public function resolveZone(string $city, string $country): ?ShippingZone
    {
        $cityKey = $this->normalizeCity($city);
        $countryKey = $this->normalizeCountry($country);

        if ($cityKey === '' || $countryKey === '') {
            return null;
        }

        $cityRow = ShippingZoneCity::query()
            ->with('zone')
            ->where('normalized_name', $cityKey)
            ->whereHas('zone', function ($query) use ($countryKey) {
                $query->where('is_active', true)
                    ->where(function ($countryQuery) use ($countryKey) {
                        $countryQuery->whereRaw('LOWER(country_code) = ?', [$countryKey])
                            ->orWhereRaw('LOWER(country_name) = ?', [$countryKey]);
                    });
            })
            ->get()
            ->sortBy(fn (ShippingZoneCity $row) => $row->zone?->priority ?? 9999)
            ->first();

        return $cityRow?->zone;
    }

    public function normalizeCity(string $value): string
    {
        return Str::of($value)->squish()->lower()->value();
    }

    public function normalizeCountry(string $value): string
    {
        return Str::of($value)->squish()->lower()->value();
    }

    private function pickupQuote(ShippingMethod $method, float $subtotal, float $discount): array
    {
        return [
            'method_id' => $method->id,
            'method_code' => $method->code,
            'method_name' => $method->displayName(),
            'method_name_snapshot' => $method->name,
            'method_name_ar_snapshot' => $method->name_ar,
            'zone_id' => null,
            'zone_code' => null,
            'zone_name' => null,
            'zone_name_snapshot' => null,
            'zone_name_ar_snapshot' => null,
            'rate_id' => null,
            'amount' => 0.0,
            'configured_amount' => 0.0,
            'free_shipping_threshold' => null,
            'threshold_basis' => null,
            'threshold_basis_amount' => round(max(0, $subtotal - $discount), 2),
            'free_shipping_qualified' => false,
            'free_shipping_remaining' => null,
            'free_shipping_progress' => null,
            'eta_min_days' => $method->eta_min_days,
            'eta_max_days' => $method->eta_max_days,
            'eta_label' => $method->etaLabel(),
            'country_code' => null,
            'country_name' => null,
            'city' => null,
            'pickup' => true,
        ];
    }
}
