<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_PURCHASE_IN = 'purchase_in';
    public const TYPE_ORDER_OUT = 'order_out';
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

    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
