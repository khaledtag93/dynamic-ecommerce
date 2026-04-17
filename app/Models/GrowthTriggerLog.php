<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthTriggerLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'rule_id',
        'user_id',
        'session_id',
        'trigger_event',
        'status',
        'channel',
        'audience_snapshot',
        'payload',
        'triggered_at',
        'processed_at',
    ];

    protected $casts = [
        'audience_snapshot' => 'array',
        'payload' => 'array',
        'triggered_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }

    public function rule()
    {
        return $this->belongsTo(GrowthAutomationRule::class, 'rule_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messageLogs()
    {
        return $this->hasMany(GrowthMessageLog::class, 'trigger_log_id');
    }
}
