<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthAutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rule_key',
        'trigger_type',
        'channel',
        'audience_type',
        'segment_id',
        'subject',
        'default_template_key',
        'message',
        'coupon_code',
        'config',
        'stats',
        'priority',
        'is_active',
        'last_run_at',
    ];

    protected $casts = [
        'config' => 'array',
        'stats' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function segment()
    {
        return $this->belongsTo(GrowthAudienceSegment::class, 'segment_id');
    }
}
