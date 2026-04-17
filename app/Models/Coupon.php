<?php

namespace App\Models;

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

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return 0.0;
        }

        $discount = match ($this->type) {
            self::TYPE_FIXED => (float) $this->value,
            self::TYPE_PERCENT => $subtotal * ((float) $this->value / 100),
            default => 0.0,
        };

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return round(max(0, min($discount, $subtotal)), 2);
    }
}
