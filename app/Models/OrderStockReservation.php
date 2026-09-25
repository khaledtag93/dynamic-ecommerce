<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStockReservation extends Model
{
    use HasFactory;

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'status',
        'reserved_at',
        'expires_at',
        'committed_at',
        'released_at',
        'release_reason',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'committed_at' => 'datetime',
        'released_at' => 'datetime',
        'meta' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function isReserved(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    public function isCommitted(): bool
    {
        return $this->status === self::STATUS_COMMITTED;
    }
}
