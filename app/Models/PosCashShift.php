<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCashShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashier_user_id', 'opening_cash', 'closing_cash_counted', 'expected_cash',
        'cash_variance', 'opened_at', 'closed_at', 'opening_notes', 'closing_notes',
    ];

    protected $casts = [
        'opening_cash' => 'decimal:2',
        'closing_cash_counted' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'cash_variance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
