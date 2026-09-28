<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Purchase extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'supplier_id', 'reference', 'status', 'purchase_date', 'received_date', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'currency', 'subtotal', 'shipping_total', 'tax_total', 'grand_total', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'received_date' => 'date',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $purchase) {
            if (! $purchase->reference) {
                $purchase->reference = 'PO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
            }
        });
    }

    public static function statusOptions(): array
    {
        return [self::STATUS_DRAFT => __('Draft'), self::STATUS_ORDERED => __('Ordered'), self::STATUS_PARTIALLY_RECEIVED => __('Partially received'), self::STATUS_RECEIVED => __('Received'), self::STATUS_CANCELLED => __('Cancelled')];
    }

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function items() { return $this->hasMany(PurchaseItem::class); }
    public function receivingProgress() { return $this->hasMany(PurchaseReceivingProgress::class); }
    public function receipts() { return $this->hasMany(PurchaseReceipt::class); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
}
