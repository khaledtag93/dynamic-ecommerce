<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthAttributionTouch extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'campaign_id',
        'experiment_id',
        'user_id',
        'order_id',
        'touch_type',
        'status',
        'attribution_weight',
        'revenue',
        'discount_total',
        'profit_total',
        'occurred_at',
        'attributed_at',
        'meta',
    ];

    protected $casts = [
        'attribution_weight' => 'decimal:4',
        'revenue' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'profit_total' => 'decimal:2',
        'occurred_at' => 'datetime',
        'attributed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function delivery()
    {
        return $this->belongsTo(GrowthDelivery::class, 'delivery_id');
    }

    public function campaign()
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }

    public function experiment()
    {
        return $this->belongsTo(GrowthExperiment::class, 'experiment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
