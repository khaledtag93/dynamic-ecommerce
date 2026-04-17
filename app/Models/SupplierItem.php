<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id', 'product_id', 'product_variant_id', 'supplier_sku', 'cost_price', 'lead_time_days', 'minimum_order_quantity', 'is_preferred',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'lead_time_days' => 'integer',
        'minimum_order_quantity' => 'integer',
        'is_preferred' => 'boolean',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
