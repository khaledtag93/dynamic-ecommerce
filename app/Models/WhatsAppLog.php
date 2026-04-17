<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppLog extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_logs';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'order_id',
        'provider',
        'message_type',
        'status',
        'phone',
        'normalized_phone',
        'locale',
        'template_name',
        'request_payload',
        'response_payload',
        'provider_message_id',
        'attempts',
        'error_message',
        'sent_at',
        'failed_at',
        'meta',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'meta' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($search) {
            $inner->where('phone', 'like', "%{$search}%")
                ->orWhere('normalized_phone', 'like', "%{$search}%")
                ->orWhere('template_name', 'like', "%{$search}%")
                ->orWhere('provider_message_id', 'like', "%{$search}%")
                ->orWhere('error_message', 'like', "%{$search}%")
                ->orWhereHas('order', function (Builder $orderQuery) use ($search) {
                    $orderQuery->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
        });
    }

    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeForMessageType(Builder $query, string $messageType): Builder
    {
        return $query->where('message_type', $messageType);
    }

    public function scopeActiveForDuplicateGuard(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_SENT,
        ]);
    }
}