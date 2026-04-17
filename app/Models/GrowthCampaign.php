<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'campaign_key',
        'campaign_type',
        'trigger_event',
        'channel',
        'audience_type',
        'segment_id',
        'subject',
        'default_template_key',
        'message',
        'subject_translations',
        'message_translations',
        'coupon_code',
        'config',
        'stats',
        'priority',
        'is_active',
        'is_messaging_enabled',
        'last_run_at',
    ];

    protected $casts = [
        'config' => 'array',
        'stats' => 'array',
        'subject_translations' => 'array',
        'message_translations' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'is_messaging_enabled' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function segment()
    {
        return $this->belongsTo(GrowthAudienceSegment::class, 'segment_id');
    }

    public function triggerLogs()
    {
        return $this->hasMany(GrowthTriggerLog::class, 'campaign_id');
    }

    public function messageLogs()
    {
        return $this->hasMany(GrowthMessageLog::class, 'campaign_id');
    }


    public function experiments()
    {
        return $this->hasMany(GrowthExperiment::class, 'campaign_id');
    }

    public function deliveries()
    {
        return $this->hasMany(GrowthDelivery::class, 'campaign_id');
    }
}

