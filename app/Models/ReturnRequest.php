<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReturnRequest extends Model
{
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reference',
        'order_id',
        'user_id',
        'status',
        'customer_notes',
        'review_notes',
        'completion_notes',
        'exchange_order_id',
        'reviewed_by_user_id',
        'received_by_user_id',
        'completed_by_user_id',
        'requested_at',
        'reviewed_at',
        'received_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'status_badge_class',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_REQUESTED => __('Requested'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
            self::STATUS_RECEIVED => __('Received'),
            self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function refunds()
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function exchangeOrder()
    {
        return $this->belongsTo(Order::class, 'exchange_order_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_REQUESTED => 'badge-soft-warning',
            self::STATUS_APPROVED => 'badge-soft-info',
            self::STATUS_RECEIVED => 'badge-soft-purple',
            self::STATUS_COMPLETED => 'badge-soft-success',
            self::STATUS_REJECTED, self::STATUS_CANCELLED => 'badge-soft-danger',
            default => 'badge-soft-secondary',
        };
    }

    public function canTransitionTo(string $status): bool
    {
        if ($status === $this->status) {
            return true;
        }

        return in_array($status, match ($this->status) {
            self::STATUS_REQUESTED => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
            self::STATUS_APPROVED => [self::STATUS_RECEIVED],
            self::STATUS_RECEIVED => [self::STATUS_COMPLETED],
            default => [],
        }, true);
    }
}
