<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'return_request_id',
        'idempotency_key',
        'amount',
        'allocation',
        'reason',
        'notes',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocation' => 'array',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Order refund ledger entries are append-only and cannot be modified.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Order refund ledger entries cannot be deleted.');
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function posReturnItems()
    {
        return $this->hasMany(PosReturnItem::class);
    }
}
