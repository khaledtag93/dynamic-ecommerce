<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthCouponIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id','trigger_log_id','delivery_id','user_id','coupon_id','coupon_code','offer_key','offer_label','discount_type','discount_value','expires_at','meta',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function campaign() { return $this->belongsTo(GrowthCampaign::class, 'campaign_id'); }
    public function triggerLog() { return $this->belongsTo(GrowthTriggerLog::class, 'trigger_log_id'); }
    public function delivery() { return $this->belongsTo(GrowthDelivery::class, 'delivery_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
