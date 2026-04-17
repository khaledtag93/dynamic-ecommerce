<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'image_path',
        'is_main',
        'sort_order',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(): string
    {
        return MediaPath::assetUrl($this->image_path ?? null, 'products')
            ?: $this->placeholderImageUrl();
    }

    protected function placeholderImageUrl(): string
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600" fill="none">
  <rect width="600" height="600" rx="32" fill="#FFF7ED"/>
  <rect x="72" y="92" width="456" height="320" rx="24" fill="#FFEDD5"/>
  <path d="M160 372L248 284C257.373 274.627 272.627 274.627 282 284L334 336C343.373 345.373 358.627 345.373 368 336L440 264L512 372V412H88V372H160Z" fill="#FDBA74"/>
  <circle cx="210" cy="210" r="42" fill="#FB923C"/>
  <text x="300" y="494" text-anchor="middle" fill="#9A3412" font-family="Arial, Helvetica, sans-serif" font-size="34" font-weight="700">No Image</text>
</svg>
SVG;

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }
}
