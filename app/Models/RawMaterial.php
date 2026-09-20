<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'unit',
        'unit_price',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];
}
