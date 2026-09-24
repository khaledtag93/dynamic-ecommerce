<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceivingProgress extends Model
{
    use HasFactory;

    protected $table = 'purchase_receiving_progress';

    protected $fillable = [
        'purchase_id',
        'purchase_item_id',
        'verified_quantity',
        'last_scanned_by',
        'last_scanned_at',
    ];

    protected $casts = [
        'verified_quantity' => 'integer',
        'last_scanned_at' => 'datetime',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function lastScannedBy()
    {
        return $this->belongsTo(User::class, 'last_scanned_by');
    }
}
