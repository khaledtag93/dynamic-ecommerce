<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'method',
        'provider',
        'provider_status',
        'status',
        'transaction_reference',
        'notes',
        'amount',
        'currency',
        'paid_at',
        'authorized_at',
        'failed_at',
        'refunded_at',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'authorized_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $appends = [
        'status_label',
        'status_badge_class',
        'method_label',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_AUTHORIZED => __('Authorized'),
            self::STATUS_PAID => __('Paid'),
            self::STATUS_FAILED => __('Failed'),
            self::STATUS_REFUNDED => __('Refunded'),
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function getMethodLabelAttribute(): string
    {
        return Order::paymentMethodOptions()[$this->method] ?? Str::headline((string) $this->method);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'badge-soft-success',
            self::STATUS_AUTHORIZED, self::STATUS_PENDING => 'badge-soft-warning',
            self::STATUS_FAILED => 'badge-soft-danger',
            self::STATUS_REFUNDED => 'badge-soft-info',
            default => 'badge-soft-secondary',
        };
    }
}
