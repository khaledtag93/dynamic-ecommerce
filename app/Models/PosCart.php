<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCart extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'cashier_user_id',
        'order_id',
        'status',
        'open_token',
        'customer_name',
        'notes',
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
