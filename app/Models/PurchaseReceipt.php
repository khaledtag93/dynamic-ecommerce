<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    use HasFactory;

    public const METHOD_MANUAL_REMAINING = 'manual_remaining';
    public const METHOD_MANUAL_PARTIAL = 'manual_partial';
    public const METHOD_BARCODE_VERIFIED = 'barcode_verified';

    protected $fillable = [
        'purchase_id',
        'idempotency_key',
        'request_hash',
        'receipt_method',
        'received_by',
        'received_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public static function methodOptions(): array
    {
        return [
            self::METHOD_MANUAL_REMAINING => __('Manual remaining receipt'),
            self::METHOD_MANUAL_PARTIAL => __('Manual partial receipt'),
            self::METHOD_BARCODE_VERIFIED => __('Barcode-verified receipt'),
            'legacy_unknown' => __('Legacy receipt'),
        ];
    }

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function items() { return $this->hasMany(PurchaseReceiptItem::class); }
    public function receivedBy() { return $this->belongsTo(User::class, 'received_by'); }
    public function reversedBy() { return $this->belongsTo(User::class, 'reversed_by'); }
}
