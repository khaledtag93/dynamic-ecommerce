<?php

namespace App\Models;

use App\Support\MediaPath;
use App\Support\TranslatableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    use TranslatableModel;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'barcode',
        'category_id',
        'brand_id',
        'description',
        'base_price',
        'sale_price',
        'cost_price',
        'quantity',
        'expiration_date',
        'stock_status',
        'low_stock_threshold',
        'status',
        'is_featured',
        'has_variants',
        'meta_title',
        'meta_description',
        'video_url',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'quantity' => 'integer',
        'expiration_date' => 'date',
        'low_stock_threshold' => 'integer',
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'has_variants' => 'boolean',
    ];

    protected $appends = [
        'current_price',
        'quantity_value',
        'main_image_url',
        'has_active_variants',
        'in_stock',
    ];


    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function images()
    {
        return $this->productImages();
    }

    public function mainImage()
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_main', true);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class)
            ->where('is_default', true);
    }

    public function relatedProducts()
    {
        return $this->belongsToMany(self::class, 'product_related', 'product_id', 'related_product_id')
            ->withPivot(['relation_type', 'sort_order', 'is_active'])
            ->wherePivot('relation_type', 'related')
            ->wherePivot('is_active', true);
    }

    public function addonProducts()
    {
        return $this->belongsToMany(self::class, 'product_related', 'product_id', 'related_product_id')
            ->withPivot(['relation_type', 'sort_order', 'is_active'])
            ->wherePivot('relation_type', 'addon')
            ->wherePivot('is_active', true);
    }

    public function bundleProducts()
    {
        return $this->belongsToMany(self::class, 'product_related', 'product_id', 'related_product_id')
            ->withPivot(['relation_type', 'sort_order', 'is_active'])
            ->wherePivot('relation_type', 'bundle')
            ->wherePivot('is_active', true);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field !== 'slug') {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::query()
            ->with(['translations'])
            ->where('slug', $value)
            ->orWhereHas('translations', fn ($query) => $query->where('slug', $value))
            ->firstOrFail();
    }

    public function getNameAttribute($value): string
    {
        return (string) $this->translatedAttribute('name', $value);
    }

    public function getSlugAttribute($value): string
    {
        return (string) $this->translatedAttribute('slug', $value);
    }

    public function getDescriptionAttribute($value): ?string
    {
        return $this->translatedAttribute('description', $value);
    }

    public function getCurrentPriceAttribute(): float
    {
        $defaultVariant = $this->relationLoaded('defaultVariant')
            ? $this->defaultVariant
            : null;

        if ($defaultVariant) {
            return (float) (
                $defaultVariant->current_price
                ?? $defaultVariant->sale_price
                ?? $defaultVariant->price
                ?? 0
            );
        }

        if (! is_null($this->sale_price) && (float) $this->sale_price > 0) {
            return (float) $this->sale_price;
        }

        if (! is_null($this->base_price) && (float) $this->base_price > 0) {
            return (float) $this->base_price;
        }

        return 0.0;
    }

  public function getQuantityValueAttribute(): int
{
    if ($this->has_variants) {
        if ($this->relationLoaded('variants')) {
            return (int) $this->variants
                ->where('status', true)
                ->sum(fn ($variant) => (int) ($variant->stock ?? 0));
        }

        if ($this->relationLoaded('activeVariants')) {
            return (int) $this->activeVariants
                ->sum(fn ($variant) => (int) ($variant->stock ?? 0));
        }

        if ($this->relationLoaded('defaultVariant') && $this->defaultVariant) {
            return (int) ($this->defaultVariant->stock ?? 0);
        }
    }

    return (int) ($this->quantity ?? 0);
}

    public function getMainImageUrlAttribute(): ?string
    {
        $mainImage = $this->relationLoaded('mainImage')
            ? $this->mainImage
            : null;

        if ($mainImage && ! empty($mainImage->image_path)) {
            return MediaPath::assetUrl($mainImage->image_path, 'products');
        }

        $firstImage = $this->relationLoaded('productImages')
            ? $this->productImages->first()
            : null;

        if ($firstImage && ! empty($firstImage->image_path)) {
            return MediaPath::assetUrl($firstImage->image_path, 'products');
        }

        $defaultVariant = $this->relationLoaded('defaultVariant')
            ? $this->defaultVariant
            : null;

        if ($defaultVariant && ! empty($defaultVariant->image)) {
            return MediaPath::assetUrl($defaultVariant->image, 'products');
        }

        return null;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->main_image_url;
    }

    public function getInStockAttribute(): bool
    {
        return $this->quantity_value > 0;
    }

    public function getHasActiveVariantsAttribute(): bool
    {
        if ($this->relationLoaded('activeVariants')) {
            return $this->activeVariants->isNotEmpty();
        }

        if ($this->relationLoaded('variants')) {
            return $this->variants->contains(
                fn ($variant) => (bool) ($variant->status ?? false)
            );
        }

        return false;
    }
}