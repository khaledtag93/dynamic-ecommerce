<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'sku',
        'image',
        'unit_price',
        'unit_cost',
        'quantity',
        'line_total',
        'profit_amount',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'quantity' => 'integer',
        'line_total' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'expires_at' => 'date',
        'meta' => 'array',
    ];

    protected $appends = ['image_url'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return MediaPath::assetUrl($this->image, 'products');
    }
}
