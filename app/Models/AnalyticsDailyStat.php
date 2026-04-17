<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'stat_date',
        'product_views',
        'cart_views',
        'add_to_cart_count',
        'remove_from_cart_count',
        'checkout_starts',
        'purchases',
        'orders_count',
        'sessions_count',
        'users_count',
        'revenue_gross',
        'discount_total',
        'shipping_total',
        'average_order_value',
        'cart_abandonment_rate',
        'checkout_completion_rate',
        'view_to_cart_rate',
        'view_to_purchase_rate',
        'meta',
        'aggregated_at',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'revenue_gross' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'cart_abandonment_rate' => 'decimal:4',
        'checkout_completion_rate' => 'decimal:4',
        'view_to_cart_rate' => 'decimal:4',
        'view_to_purchase_rate' => 'decimal:4',
        'meta' => 'array',
        'aggregated_at' => 'datetime',
    ];
}
