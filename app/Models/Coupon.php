<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENT = 'percent';

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_FIXED => __('Fixed amount'),
            self::TYPE_PERCENT => __('Percentage'),
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon) {
            $coupon->code = Str::upper(trim((string) $coupon->code));
        });
    }

    public function getTypeLabelAttribute(): string
    {
        return static::typeOptions()[$this->type] ?? Str::headline((string) $this->type);
    }

    public function isWithinSchedule(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function hasRemainingUsage(): bool
    {
        if ($this->usage_limit === null) {
            return true;
        }

        return (int) $this->used_count < (int) $this->usage_limit;
    }

    public function isUsable(?Carbon $now = null): bool
    {
        return $this->is_active && $this->isWithinSchedule($now) && $this->hasRemainingUsage();
    }

    public function meetsMinimumSubtotal(float $subtotal): bool
    {
        return $this->meetsMinimumSubtotalAmount($this->subtotalAmount($subtotal));
    }

    public function calculateDiscount(float $subtotal): float
    {
        $subtotalAmount = $this->subtotalAmount($subtotal);

        if ($subtotalAmount->compareTo('0.00') <= 0) {
            return 0.0;
        }

        if (! $this->meetsMinimumSubtotalAmount($subtotalAmount)) {
            return 0.0;
        }

        $couponValue = BigDecimal::of((string) $this->value)
            ->toScale(2, RoundingMode::Unnecessary);

        $discount = match ($this->type) {
            self::TYPE_FIXED => $couponValue,
            self::TYPE_PERCENT => $subtotalAmount
                ->multipliedBy($couponValue)
                ->dividedBy('100', 2, RoundingMode::HalfUp),
            default => BigDecimal::of('0.00'),
        };

        if ($this->max_discount_amount !== null) {
            $maximumDiscount = BigDecimal::of((string) $this->max_discount_amount)
                ->toScale(2, RoundingMode::Unnecessary);

            if ($discount->compareTo($maximumDiscount) > 0) {
                $discount = $maximumDiscount;
            }
        }

        if ($discount->compareTo('0.00') < 0) {
            $discount = BigDecimal::of('0.00');
        }

        if ($discount->compareTo($subtotalAmount) > 0) {
            $discount = $subtotalAmount;
        }

        return (float) (string) $discount->toScale(2, RoundingMode::Unnecessary);
    }

    private function meetsMinimumSubtotalAmount(BigDecimal $subtotal): bool
    {
        if ($this->min_order_amount === null) {
            return true;
        }

        $minimumSubtotal = BigDecimal::of((string) $this->min_order_amount)
            ->toScale(2, RoundingMode::Unnecessary);

        return $subtotal->compareTo($minimumSubtotal) >= 0;
    }

    private function subtotalAmount(float $subtotal): BigDecimal
    {
        return BigDecimal::of((string) $subtotal)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
