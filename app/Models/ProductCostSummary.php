<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCostSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'materials_cost',
        'extra_cost',
        'total_cost',
        'selling_price',
        'profit',
        'profit_margin',
    ];

    protected $casts = [
        'materials_cost' => 'decimal:2',
        'extra_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'profit' => 'decimal:2',
        'profit_margin' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
