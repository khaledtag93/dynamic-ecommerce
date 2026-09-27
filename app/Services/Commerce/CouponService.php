<?php

namespace App\Services\Commerce;

use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public const SESSION_KEY = 'cart_coupon_code';

    public function currentCode(): ?string
    {
        return session(static::SESSION_KEY);
    }

    public function currentCoupon(): ?Coupon
    {
        $code = $this->currentCode();

        if (! $code) {
            return null;
        }

        return Coupon::query()->where('code', $code)->first();
    }

    public function applyFromCode(string $code, float $subtotal): Coupon
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon' => __('Coupon code was not found.'),
            ]);
        }

        $this->assertCouponUsable($coupon, $subtotal);

        session([static::SESSION_KEY => $coupon->code]);

        return $coupon;
    }

    public function remove(): void
    {
        session()->forget(static::SESSION_KEY);
    }

    public function resolveDiscountSummary(float $subtotal): array
    {
        $coupon = $this->currentCoupon();

        if (! $coupon) {
            return [
                'coupon' => null,
                'discount' => 0.0,
                'label' => null,
                'code' => null,
            ];
        }

        if (! $coupon->isUsable() || $coupon->calculateDiscount($subtotal) <= 0) {
            $this->remove();

            return [
                'coupon' => null,
                'discount' => 0.0,
                'label' => null,
                'code' => null,
            ];
        }

        return [
            'coupon' => $coupon,
            'discount' => $coupon->calculateDiscount($subtotal),
            'label' => $coupon->name ?: $coupon->code,
            'code' => $coupon->code,
        ];
    }

    public function couponSnapshot(Coupon $coupon, float $subtotal): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'discount' => $coupon->calculateDiscount($subtotal),
        ];
    }

    public function markCouponAsUsed(?Coupon $coupon, ?Order $order = null): void
    {
        if (! $coupon) {
            return;
        }

        $now = now();

        $affected = Coupon::query()
            ->whereKey($coupon->getKey())
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->increment('used_count');

        if ($affected !== 1) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon is no longer available. Please review your cart and try again.'),
            ]);
        }

        $coupon->refresh();

        if ($order) {
            $meta = $order->meta ?? [];
            data_set($meta, 'coupon_usage.counted_at', $now->toDateTimeString());
            data_set($meta, 'coupon_usage.coupon_id', $coupon->id);
            data_set($meta, 'coupon_usage.code', $coupon->code);
            data_forget($meta, 'coupon_usage.released_at');

            $order->update(['meta' => $meta]);
        }
    }

    public function releaseUsageForCancelledOrder(Order $order): void
    {
        $meta = $order->meta ?? [];

        if (
            ! data_get($meta, 'coupon_usage.counted_at')
            || data_get($meta, 'coupon_usage.released_at')
        ) {
            return;
        }

        $couponId = (int) data_get($meta, 'coupon_usage.coupon_id');
        if ($couponId < 1) {
            return;
        }

        $coupon = Coupon::query()
            ->whereKey($couponId)
            ->lockForUpdate()
            ->first();

        if (! $coupon) {
            return;
        }

        Coupon::query()
            ->whereKey($coupon->id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');

        data_set($meta, 'coupon_usage.released_at', now()->toDateTimeString());
        $order->update(['meta' => $meta]);
    }

    protected function assertCouponUsable(Coupon $coupon, float $subtotal): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon' => __('This coupon is not active right now.')]);
        }

        if (! $coupon->isWithinSchedule()) {
            throw ValidationException::withMessages(['coupon' => __('This coupon is not available at the current time.')]);
        }

        if (! $coupon->hasRemainingUsage()) {
            throw ValidationException::withMessages(['coupon' => __('This coupon has reached its usage limit.')]);
        }

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon' => __('Order subtotal must be at least :amount to use this coupon.', [
                    'amount' => 'EGP '.number_format((float) $coupon->min_order_amount, 2),
                ]),
            ]);
        }

        if ($coupon->calculateDiscount($subtotal) <= 0) {
            throw ValidationException::withMessages(['coupon' => __('This coupon does not apply to the current cart.')]);
        }
    }
}
