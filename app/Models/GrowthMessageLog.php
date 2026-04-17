<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthMessageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'trigger_log_id',
        'delivery_id',
        'experiment_id',
        'user_id',
        'channel',
        'status',
        'recipient',
        'subject',
        'message',
        'coupon_code',
        'experiment_variant',
        'meta',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }

    public function triggerLog()
    {
        return $this->belongsTo(GrowthTriggerLog::class, 'trigger_log_id');
    }

    public function delivery()
    {
        return $this->belongsTo(GrowthDelivery::class, 'delivery_id');
    }

    public function experiment()
    {
        return $this->belongsTo(GrowthExperiment::class, 'experiment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
