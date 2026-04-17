<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    public const PAYMENT_METHOD_COD = 'cash_on_delivery';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_ONLINE = 'online_payment';

    public const DELIVERY_STATUS_PENDING = 'pending';
    public const DELIVERY_STATUS_PREPARING = 'preparing';
    public const DELIVERY_STATUS_SHIPPED = 'shipped';
    public const DELIVERY_STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERY_STATUS_DELIVERED = 'delivered';
    public const DELIVERY_STATUS_RETURNED = 'returned';
    public const DELIVERY_STATUS_CANCELLED = 'cancelled';

    public const DELIVERY_METHOD_STANDARD = 'standard_shipping';
    public const DELIVERY_METHOD_EXPRESS = 'express_shipping';
    public const DELIVERY_METHOD_PICKUP = 'store_pickup';

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'payment_method',
        'delivery_status',
        'delivery_method',
        'shipping_provider',
        'tracking_number',
        'estimated_delivery_date',
        'shipped_at',
        'delivered_at',
        'delivery_notes',
        'currency',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'notes',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address_line_1',
        'shipping_address_line_2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'billing_same_as_shipping',
        'billing_address_line_1',
        'billing_address_line_2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'coupon_code',
        'coupon_snapshot',
        'cancelled_at',
        'cancelled_reason',
        'refund_total',
        'cost_total',
        'profit_total',
        'refunded_at',
        'meta',
        'placed_at',
    ];

    protected $appends = [
        'status_label',
        'status_badge_class',
        'payment_status_label',
        'payment_status_badge_class',
        'payment_method_label',
        'delivery_status_label',
        'delivery_status_badge_class',
        'delivery_method_label',
        'refundable_balance',
        'can_user_cancel',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'refund_total' => 'decimal:2',
        'cost_total' => 'decimal:2',
        'profit_total' => 'decimal:2',
        'billing_same_as_shipping' => 'boolean',
        'coupon_snapshot' => 'array',
        'meta' => 'array',
        'estimated_delivery_date' => 'date',
        'placed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_PROCESSING => __('Processing'),
            self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    public static function paymentStatusOptions(): array
    {
        return [
            self::PAYMENT_STATUS_UNPAID => __('Unpaid'),
            self::PAYMENT_STATUS_PENDING => __('Pending payment'),
            self::PAYMENT_STATUS_PAID => __('Paid'),
            self::PAYMENT_STATUS_FAILED => __('Failed'),
            self::PAYMENT_STATUS_PARTIALLY_REFUNDED => __('Partially Refunded'),
            self::PAYMENT_STATUS_REFUNDED => __('Refunded'),
        ];
    }

    public static function paymentMethodOptions(): array
    {
        return [
            self::PAYMENT_METHOD_COD => __('Cash on Delivery'),
            self::PAYMENT_METHOD_BANK_TRANSFER => __('Bank Transfer'),
            self::PAYMENT_METHOD_ONLINE => __('Online Payment'),
        ];
    }

    public static function deliveryStatusOptions(): array
    {
        return [
            self::DELIVERY_STATUS_PENDING => __('Pending'),
            self::DELIVERY_STATUS_PREPARING => __('Preparing'),
            self::DELIVERY_STATUS_SHIPPED => __('Shipped'),
            self::DELIVERY_STATUS_OUT_FOR_DELIVERY => __('Out for delivery'),
            self::DELIVERY_STATUS_DELIVERED => __('Delivered'),
            self::DELIVERY_STATUS_RETURNED => __('Returned'),
            self::DELIVERY_STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    public static function deliveryMethodOptions(): array
    {
        return [
            self::DELIVERY_METHOD_STANDARD => __('Standard shipping'),
            self::DELIVERY_METHOD_EXPRESS => __('Express shipping'),
            self::DELIVERY_METHOD_PICKUP => __('Store pickup'),
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function refunds()
    {
        return $this->hasMany(OrderRefund::class)->latest('id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return static::paymentStatusOptions()[$this->payment_status] ?? Str::headline((string) $this->payment_status);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return static::paymentMethodOptions()[$this->payment_method] ?? Str::headline((string) $this->payment_method);
    }

    public function getDeliveryStatusLabelAttribute(): string
    {
        return static::deliveryStatusOptions()[$this->delivery_status] ?? Str::headline((string) $this->delivery_status);
    }

    public function getDeliveryMethodLabelAttribute(): string
    {
        return static::deliveryMethodOptions()[$this->delivery_method] ?? Str::headline((string) $this->delivery_method);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'badge-soft-warning',
            self::STATUS_PROCESSING => 'badge-soft-info',
            self::STATUS_COMPLETED => 'badge-soft-success',
            self::STATUS_CANCELLED => 'badge-soft-danger',
            default => 'badge-soft-secondary',
        };
    }

    public function getPaymentStatusBadgeClassAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_STATUS_PAID => 'badge-soft-success',
            self::PAYMENT_STATUS_PENDING => 'badge-soft-warning',
            self::PAYMENT_STATUS_FAILED => 'badge-soft-danger',
            self::PAYMENT_STATUS_PARTIALLY_REFUNDED => 'badge-soft-info',
            self::PAYMENT_STATUS_REFUNDED => 'badge-soft-purple',
            self::PAYMENT_STATUS_UNPAID => 'badge-soft-secondary',
            default => 'badge-soft-secondary',
        };
    }

    public function getDeliveryStatusBadgeClassAttribute(): string
    {
        return match ($this->delivery_status) {
            self::DELIVERY_STATUS_PENDING, self::DELIVERY_STATUS_PREPARING => 'badge-soft-warning',
            self::DELIVERY_STATUS_SHIPPED, self::DELIVERY_STATUS_OUT_FOR_DELIVERY => 'badge-soft-info',
            self::DELIVERY_STATUS_DELIVERED => 'badge-soft-success',
            self::DELIVERY_STATUS_RETURNED, self::DELIVERY_STATUS_CANCELLED => 'badge-soft-danger',
            default => 'badge-soft-secondary',
        };
    }

    public function canTransitionTo(string $newStatus): bool
    {
        if ($newStatus === $this->status) {
            return true;
        }

        return in_array($newStatus, match ($this->status) {
            self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
            default => [],
        }, true);
    }

    public function canBeCancelledByUser(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_STATUS_PAID, self::PAYMENT_STATUS_PARTIALLY_REFUNDED], true)
            && $this->refundable_balance > 0;
    }

    public function getRefundableBalanceAttribute(): float
    {
        return round(max(0, (float) $this->grand_total - (float) $this->refund_total), 2);
    }

    public function getCanUserCancelAttribute(): bool
    {
        return $this->canBeCancelledByUser();
    }
}
