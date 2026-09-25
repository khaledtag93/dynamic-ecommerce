<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'country_code',
        'country_name',
        'is_active',
        'priority',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (ShippingZone $zone) {
            $zone->code = Str::upper(trim((string) $zone->code));
            $zone->country_code = Str::upper(trim((string) $zone->country_code));
            $zone->name = trim((string) $zone->name);
            $zone->name_ar = self::nullableTrim($zone->name_ar);
            $zone->country_name = trim((string) $zone->country_name);
        });
    }

    public function cities()
    {
        return $this->hasMany(ShippingZoneCity::class);
    }

    public function rates()
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'ar' && $this->name_ar
            ? $this->name_ar
            : $this->name;
    }

    private static function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
