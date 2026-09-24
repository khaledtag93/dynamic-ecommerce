<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_cart_id',
        'product_id',
        'product_variant_id',
        'item_key',
        'product_name',
        'variant_name',
        'sku',
        'barcode',
        'unit_price',
        'quantity',
        'discount_type',
        'discount_value',
        'discount_reason',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'discount_value' => 'decimal:2',
    ];

    public function cart()
    {
        return $this->belongsTo(PosCart::class, 'pos_cart_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
