<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMaterialCostItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'raw_material_id',
        'material_name',
        'unit',
        'quantity',
        'unit_price',
        'total_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
