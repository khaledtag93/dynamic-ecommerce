<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLotMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_lot_id',
        'inventory_movement_id',
        'order_id',
        'order_item_id',
        'order_stock_reservation_id',
        'quantity_change',
        'balance_after',
        'meta',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'balance_after' => 'integer',
        'meta' => 'array',
    ];

    public function lot() { return $this->belongsTo(InventoryLot::class, 'inventory_lot_id'); }
    public function inventoryMovement() { return $this->belongsTo(InventoryMovement::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function reservation() { return $this->belongsTo(OrderStockReservation::class, 'order_stock_reservation_id'); }
}
