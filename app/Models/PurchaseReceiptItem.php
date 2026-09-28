<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'purchase_id',
        'purchase_item_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function receipt() { return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id'); }
    public function purchaseItem() { return $this->belongsTo(PurchaseItem::class); }
}
