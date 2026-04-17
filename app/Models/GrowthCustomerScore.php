<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthCustomerScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'orders_count',
        'completed_orders_count',
        'total_revenue',
        'average_order_value',
        'days_since_last_order',
        'last_order_at',
        'view_count_30d',
        'cart_count_30d',
        'checkout_count_30d',
        'purchase_count_90d',
        'ltv_score',
        'churn_risk_score',
        'engagement_score',
        'retention_stage',
        'next_best_campaign',
        'offer_bias',
        'adaptive_offer_preference',
        'adaptive_confidence',
        'winback_priority_score',
        'winback_priority_band',
        'winback_ready_at',
        'recommended_discount_type',
        'recommended_discount_value',
        'meta',
        'calculated_at',
    ];

    protected $casts = [
        'total_revenue' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'ltv_score' => 'decimal:2',
        'churn_risk_score' => 'decimal:2',
        'engagement_score' => 'decimal:2',
        'adaptive_confidence' => 'decimal:2',
        'winback_priority_score' => 'decimal:2',
        'recommended_discount_value' => 'decimal:2',
        'meta' => 'array',
        'last_order_at' => 'datetime',
        'calculated_at' => 'datetime',
        'winback_ready_at' => 'datetime',
        'orders_count' => 'integer',
        'completed_orders_count' => 'integer',
        'days_since_last_order' => 'integer',
        'view_count_30d' => 'integer',
        'cart_count_30d' => 'integer',
        'checkout_count_30d' => 'integer',
        'purchase_count_90d' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
