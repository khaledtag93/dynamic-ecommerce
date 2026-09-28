<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'idempotency_key',
        'request_hash',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function items() { return $this->hasMany(PurchaseReceiptItem::class); }
    public function receivedBy() { return $this->belongsTo(User::class, 'received_by'); }
}
