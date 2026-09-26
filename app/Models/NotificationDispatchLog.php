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
        $search = mb_substr(trim((string) $search), 0, 100);

        if ($search === '') {
            return $query;
        }

        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%' . $escapedSearch . '%';

        return $query->where(function (Builder $inner) use ($like) {
            $inner->where('event', 'like', $like)
                ->orWhere('channel', 'like', $like)
                ->orWhere('recipient', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('message', 'like', $like)
                ->orWhere('error_message', 'like', $like)
                ->orWhereHas('order', function (Builder $orderQuery) use ($like) {
                    $orderQuery->where('order_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_phone', 'like', $like)
                        ->orWhere('customer_email', 'like', $like);
                });
        });
    }
}
