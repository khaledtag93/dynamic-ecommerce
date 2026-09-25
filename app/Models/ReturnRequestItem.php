<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnRequestItem extends Model
{
    use HasFactory;

    public const RESOLUTION_REFUND = 'refund';
    public const RESOLUTION_EXCHANGE = 'exchange';

    public const REASON_DAMAGED = 'damaged';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_NOT_AS_DESCRIBED = 'not_as_described';
    public const REASON_DEFECTIVE = 'defective';
    public const REASON_CHANGED_MIND = 'changed_mind';
    public const REASON_OTHER = 'other';

    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'requested_quantity',
        'approved_quantity',
        'received_quantity',
        'restock_quantity',
        'reason_code',
        'reason_details',
        'requested_resolution',
    ];

    protected $casts = [
        'requested_quantity' => 'integer',
        'approved_quantity' => 'integer',
        'received_quantity' => 'integer',
        'restock_quantity' => 'integer',
    ];

    public static function reasonOptions(): array
    {
        return [
            self::REASON_DAMAGED => __('Damaged'),
            self::REASON_WRONG_ITEM => __('Wrong item'),
            self::REASON_NOT_AS_DESCRIBED => __('Not as described'),
            self::REASON_DEFECTIVE => __('Defective'),
            self::REASON_CHANGED_MIND => __('Changed mind'),
            self::REASON_OTHER => __('Other'),
        ];
    }

    public static function resolutionOptions(): array
    {
        return [
            self::RESOLUTION_REFUND => __('Refund'),
            self::RESOLUTION_EXCHANGE => __('Exchange'),
        ];
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function reasonLabel(): string
    {
        return static::reasonOptions()[$this->reason_code] ?? $this->reason_code;
    }

    public function resolutionLabel(): string
    {
        return static::resolutionOptions()[$this->requested_resolution] ?? $this->requested_resolution;
    }
}
