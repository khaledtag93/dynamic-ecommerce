<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionRule extends Model
{
    use HasFactory;

    public const TYPE_ORDER_PERCENTAGE = 'order_percentage';
    public const TYPE_ORDER_FIXED = 'order_fixed';
    public const TYPE_CATEGORY_PERCENTAGE = 'category_percentage';
    public const TYPE_BUY_X_GET_Y = 'buy_x_get_y';

    protected $fillable = [
        'name', 'type', 'discount_type', 'discount_value', 'category_id', 'buy_quantity', 'get_quantity', 'min_subtotal', 'priority', 'is_active', 'starts_at', 'ends_at', 'meta',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_subtotal' => 'decimal:2',
        'buy_quantity' => 'integer',
        'get_quantity' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function category() { return $this->belongsTo(Category::class); }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
            ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()); });
    }
}
