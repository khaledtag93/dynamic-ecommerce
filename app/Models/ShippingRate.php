<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasFactory;

    public const BASIS_BEFORE_DISCOUNTS = 'subtotal_before_discounts';
    public const BASIS_AFTER_DISCOUNTS = 'subtotal_after_discounts';

    protected $fillable = [
        'shipping_method_id',
        'shipping_zone_id',
        'amount',
        'free_shipping_threshold',
        'threshold_basis',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function thresholdBasisOptions(): array
    {
        return [
            self::BASIS_BEFORE_DISCOUNTS => __('Subtotal before discounts'),
            self::BASIS_AFTER_DISCOUNTS => __('Subtotal after discounts'),
        ];
    }

    public function method()
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
