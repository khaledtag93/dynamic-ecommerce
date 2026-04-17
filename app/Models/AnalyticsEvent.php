<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public const EVENT_VIEW_PRODUCT = 'view_product';
    public const EVENT_VIEW_CART = 'view_cart';
    public const EVENT_ADD_TO_CART = 'add_to_cart';
    public const EVENT_REMOVE_FROM_CART = 'remove_from_cart';
    public const EVENT_CHECKOUT_START = 'checkout_start';
    public const EVENT_PURCHASE_SUCCESS = 'purchase_success';

    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_CART = 'cart';
    public const ENTITY_CHECKOUT = 'checkout';
    public const ENTITY_ORDER = 'order';

    protected $fillable = [
        'user_id',
        'session_id',
        'event_type',
        'entity_type',
        'entity_id',
        'occurred_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
