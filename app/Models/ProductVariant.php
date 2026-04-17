<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'sale_price',
        'cost_price',
        'stock',
        'expiration_date',
        'reorder_point',
        'image',
        'is_default',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'expiration_date' => 'date',
        'reorder_point' => 'integer',
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    // ❌ هنوقف auto appends (مهم جدًا)
    protected $appends = [];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes()
    {
        return $this->hasMany(ProductVariantAttribute::class, 'variant_id');
    }

    public function attributeValues()
    {
        return $this->hasMany(ProductVariantAttribute::class, 'variant_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors (Manual use only)
    |--------------------------------------------------------------------------
    */

    public function getCurrentPriceAttribute()
    {
        if (!is_null($this->sale_price) && (float) $this->sale_price > 0) {
            return (float) $this->sale_price;
        }

        if (!is_null($this->price) && (float) $this->price > 0) {
            return (float) $this->price;
        }

        return 0;
    }

    public function getImageUrlAttribute()
    {
        if (!empty($this->image)) {
            return MediaPath::assetUrl($this->image, 'products');
        }

        // ❌ ما نعملش query
        if ($this->relationLoaded('product') && $this->product) {
            return $this->product->main_image_url;
        }

        return null;
    }

    public function getVariantNameAttribute()
    {
        // ❌ ممنوع queries هنا
        if (!$this->relationLoaded('attributes')) {
            return $this->sku ?: 'Default Variant';
        }

        // مهم: $this->attributes في Eloquent هو raw attributes array بتاع الموديل نفسه
        // وليس relation collection، لذلك لازم نجيب الـ relation بشكل صريح.
        $attributes = collect($this->getRelation('attributes'));

        if ($attributes->isEmpty()) {
            return $this->sku ?: 'Default Variant';
        }

        return $attributes
            ->map(function ($item) {
                $name = $item->attribute?->name ?? '';
                $value = $item->attribute_value ?? '';

                return trim($name !== '' ? ($name . ': ' . $value) : $value);
            })
            ->filter()
            ->implode(' / ');
    }
}