<?php

namespace App\Services\Commerce;

use App\Models\Coupon;
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
                'coupon' => 'Coupon code was not found.',
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

    public function markCouponAsUsed(?Coupon $coupon): void
    {
        if (! $coupon) {
            return;
        }

        $coupon->increment('used_count');
    }

    protected function assertCouponUsable(Coupon $coupon, float $subtotal): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is not active right now.']);
        }

        if (! $coupon->isWithinSchedule()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is not available at the current time.']);
        }

        if (! $coupon->hasRemainingUsage()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon has reached its usage limit.']);
        }

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon' => 'Order subtotal must be at least EGP ' . number_format((float) $coupon->min_order_amount, 2) . ' to use this coupon.',
            ]);
        }

        if ($coupon->calculateDiscount($subtotal) <= 0) {
            throw ValidationException::withMessages(['coupon' => 'This coupon does not apply to the current cart.']);
        }
    }
}
