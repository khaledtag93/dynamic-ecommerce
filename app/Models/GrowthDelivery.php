<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'trigger_log_id',
        'message_log_id',
        'experiment_id',
        'user_id',
        'channel',
        'provider',
        'status',
        'recipient',
        'subject',
        'message',
        'experiment_variant',
        'payload',
        'meta',
        'attempts',
        'sent_at',
        'failed_at',
        'last_attempt_at',
        'scheduled_for',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'meta' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'scheduled_for' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }

    public function triggerLog()
    {
        return $this->belongsTo(GrowthTriggerLog::class, 'trigger_log_id');
    }

    public function messageLog()
    {
        return $this->belongsTo(GrowthMessageLog::class, 'message_log_id');
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
