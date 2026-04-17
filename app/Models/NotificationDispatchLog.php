<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDispatchLog extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'order_id',
        'user_id',
        'event',
        'channel',
        'status',
        'title',
        'message',
        'recipient',
        'provider',
        'error_message',
        'payload',
        'response_payload',
        'attempted_at',
        'sent_at',
        'failed_at',
        'retried_at',
        'retry_of_id',
        'meta',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_payload' => 'array',
        'meta' => 'array',
        'attempted_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'retried_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($search) {
            $inner->where('event', 'like', "%{$search}%")
                ->orWhere('channel', 'like', "%{$search}%")
                ->orWhere('recipient', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")
                ->orWhere('error_message', 'like', "%{$search}%")
                ->orWhereHas('order', function (Builder $orderQuery) use ($search) {
                    $orderQuery->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%");
                });
        });
    }
}
