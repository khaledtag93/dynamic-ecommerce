<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseSettlement extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'purchase_id',
        'supplier_id',
        'amount',
        'currency',
        'payment_method',
        'reference',
        'idempotency_key',
        'request_hash',
        'paid_at',
        'recorded_by',
        'status',
        'voided_at',
        'voided_by',
        'void_reason',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (PurchaseSettlement $settlement): void {
            $dirty = array_keys($settlement->getDirty());
            $allowed = ['status', 'voided_at', 'voided_by', 'void_reason', 'updated_at'];
            $hasImmutableMutation = array_diff($dirty, $allowed) !== [];
            $validVoidTransition = $settlement->isDirty('status')
                && $settlement->getOriginal('status') === self::STATUS_ACTIVE
                && $settlement->status === self::STATUS_VOIDED;

            if ($hasImmutableMutation || ! $validVoidTransition) {
                throw new \LogicException('Supplier payment settlements are append-only and can only be voided.');
            }
        });

        static::deleting(function (): void {
            throw new \LogicException('Supplier payment settlements cannot be deleted; void them instead.');
        });
    }

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function voidedBy() { return $this->belongsTo(User::class, 'voided_by'); }

    public static function paymentMethodOptions(): array
    {
        return [
            'cash' => __('Cash'),
            'bank_transfer' => __('Bank transfer'),
            'card' => __('Card'),
            'cheque' => __('Cheque'),
            'other' => __('Other'),
        ];
    }
}
