<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'source_inventory_movement_id',
        'purchase_id',
        'purchase_receipt_id',
        'purchase_item_id',
        'lot_code',
        'source_type',
        'initial_quantity',
        'quantity_on_hand',
        'unit_cost',
        'expiration_date',
        'received_at',
        'meta',
    ];

    protected $casts = [
        'initial_quantity' => 'integer',
        'quantity_on_hand' => 'integer',
        'unit_cost' => 'decimal:2',
        'expiration_date' => 'date',
        'received_at' => 'datetime',
        'meta' => 'array',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function sourceMovement() { return $this->belongsTo(InventoryMovement::class, 'source_inventory_movement_id'); }
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function purchaseReceipt() { return $this->belongsTo(PurchaseReceipt::class); }
    public function purchaseItem() { return $this->belongsTo(PurchaseItem::class); }
    public function movements() { return $this->hasMany(InventoryLotMovement::class); }
}
