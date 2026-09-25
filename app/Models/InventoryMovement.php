<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_PURCHASE_IN = 'purchase_in';
    public const TYPE_ORDER_OUT = 'order_out';
    public const TYPE_ORDER_RESERVATION = 'order_reservation';
    public const TYPE_RESERVATION_RELEASE = 'reservation_release';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_LOST = 'lost';
    public const TYPE_REFUND_RESTOCK = 'refund_restock';

    protected $fillable = [
        'product_id', 'product_variant_id', 'purchase_id', 'order_id', 'type', 'reason', 'quantity_change', 'balance_after', 'unit_cost', 'expiration_date', 'meta',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'balance_after' => 'integer',
        'unit_cost' => 'decimal:2',
        'expiration_date' => 'date',
        'meta' => 'array',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_PURCHASE_IN => __('Purchase receipt'),
            self::TYPE_ORDER_OUT => __('Order sale'),
            self::TYPE_ORDER_RESERVATION => __('Online payment reservation'),
            self::TYPE_RESERVATION_RELEASE => __('Reservation release'),
            self::TYPE_ADJUSTMENT => __('Stock adjustment'),
            self::TYPE_DAMAGE => __('Damaged stock'),
            self::TYPE_LOST => __('Lost stock'),
            self::TYPE_REFUND_RESTOCK => __('Refund / cancellation restock'),
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return static::typeOptions()[$this->type] ?? \Illuminate\Support\Str::headline((string) $this->type);
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
