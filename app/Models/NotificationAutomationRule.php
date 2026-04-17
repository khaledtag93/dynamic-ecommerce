<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationAutomationRule extends Model
{
    use HasFactory;

    public const TRIGGER_FAILED = 'failed';
    public const TRIGGER_SKIPPED = 'skipped';
    public const TRIGGER_PENDING_STALE = 'pending_stale';

    public const ACTION_RETRY_SAME_CHANNEL = 'retry_same_channel';
    public const ACTION_FALLBACK_CHANNEL = 'fallback_channel';
    public const ACTION_NOTIFY_ADMIN_EMAIL = 'notify_admin_email';
    public const ACTION_CREATE_ACTIVITY = 'create_activity';

    protected $fillable = [
        'name',
        'event',
        'trigger_status',
        'source_channel',
        'action_type',
        'target_channel',
        'escalation_level',
        'delay_minutes',
        'max_attempts',
        'admin_email',
        'notes',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}
