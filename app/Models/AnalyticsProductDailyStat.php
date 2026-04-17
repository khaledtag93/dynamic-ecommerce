<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsProductDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'stat_date',
        'product_id',
        'product_name',
        'product_slug',
        'category_id',
        'views',
        'add_to_cart_count',
        'purchases',
        'purchased_quantity',
        'revenue_gross',
        'conversion_rate',
        'meta',
        'aggregated_at',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'revenue_gross' => 'decimal:2',
        'conversion_rate' => 'decimal:4',
        'meta' => 'array',
        'aggregated_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
