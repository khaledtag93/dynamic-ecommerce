<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCart extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_HELD = 'held';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'cashier_user_id',
        'order_id',
        'status',
        'open_token',
        'hold_label',
        'held_at',
        'customer_name',
        'notes',
    ];

    protected $casts = [
        'held_at' => 'datetime',
    ];

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(PosCartItem::class)->orderBy('id');
    }
}
