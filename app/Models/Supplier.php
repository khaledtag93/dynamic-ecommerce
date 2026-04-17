<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'contact_name', 'email', 'phone', 'company', 'country', 'address', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $supplier) {
            if (! $supplier->slug) {
                $supplier->slug = Str::slug($supplier->name) . '-' . Str::lower(Str::random(5));
            }
        });
    }

    public function items() { return $this->hasMany(SupplierItem::class); }
    public function purchases() { return $this->hasMany(Purchase::class)->latest('id'); }
}
