<?php

namespace App\Models;

use App\Support\MediaPath;
use App\Support\TranslatableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;
    use TranslatableModel;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'meta_title',
        'meta_keyword',
        'meta_description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = [
        'image_url',
        'image_relative_path',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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

    public function getMetaTitleAttribute($value): ?string
    {
        return $this->translatedAttribute('meta_title', $value);
    }

    public function getMetaKeywordAttribute($value): ?string
    {
        return $this->translatedAttribute('meta_keyword', $value);
    }

    public function getMetaDescriptionAttribute($value): ?string
    {
        return $this->translatedAttribute('meta_description', $value);
    }

    public function getImageRelativePathAttribute(): ?string
    {
        return MediaPath::normalizeRelative($this->image, 'category');
    }

    public function getImageUrlAttribute(): ?string
    {
        return MediaPath::assetUrl($this->image, 'category');
    }
}