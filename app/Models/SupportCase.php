<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportCase extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING_CUSTOMER = 'pending_customer';
    public const STATUS_PENDING_TEAM = 'pending_team';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'case_number',
        'customer_id',
        'order_id',
        'assigned_to_user_id',
        'subject',
        'category',
        'priority',
        'status',
        'source',
        'first_response_at',
        'last_customer_message_at',
        'last_staff_message_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_staff_message_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => __('Open'),
            self::STATUS_PENDING_CUSTOMER => __('Waiting for customer'),
            self::STATUS_PENDING_TEAM => __('Waiting for team'),
            self::STATUS_RESOLVED => __('Resolved'),
            self::STATUS_CLOSED => __('Closed'),
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => __('Low'),
            self::PRIORITY_NORMAL => __('Normal'),
            self::PRIORITY_HIGH => __('High'),
            self::PRIORITY_URGENT => __('Urgent'),
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportCaseMessage::class)->orderBy('id');
    }

    public function customerMessages(): HasMany
    {
        return $this->hasMany(SupportCaseMessage::class)
            ->where('visibility', SupportCaseMessage::VISIBILITY_CUSTOMER)
            ->orderBy('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function getPriorityLabelAttribute(): string
    {
        return static::priorityOptions()[$this->priority] ?? Str::headline((string) $this->priority);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'badge-soft-info',
            self::STATUS_PENDING_CUSTOMER, self::STATUS_PENDING_TEAM => 'badge-soft-warning',
            self::STATUS_RESOLVED => 'badge-soft-success',
            self::STATUS_CLOSED => 'badge-soft-secondary',
            default => 'badge-soft-secondary',
        };
    }

    public function getPriorityBadgeClassAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'badge-soft-danger',
            self::PRIORITY_HIGH => 'badge-soft-warning',
            self::PRIORITY_LOW => 'badge-soft-secondary',
            default => 'badge-soft-info',
        };
    }
}
