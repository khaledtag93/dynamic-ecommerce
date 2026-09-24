<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_refund_id',
        'order_item_id',
        'quantity',
        'amount',
        'restocked',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount' => 'decimal:2',
        'restocked' => 'boolean',
    ];

    public function refund()
    {
        return $this->belongsTo(OrderRefund::class, 'order_refund_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
