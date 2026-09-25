<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    public const TYPE_SHIPPING = 'shipping';
    public const TYPE_PICKUP = 'pickup';

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'type',
        'is_active',
        'sort_order',
        'eta_min_days',
        'eta_max_days',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'eta_min_days' => 'integer',
        'eta_max_days' => 'integer',
    ];

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

    public function isPickup(): bool
    {
        return $this->type === self::TYPE_PICKUP;
    }

    public function etaLabel(): ?string
    {
        if ($this->eta_min_days === null && $this->eta_max_days === null) {
            return null;
        }

        if ($this->eta_min_days !== null && $this->eta_max_days !== null) {
            return $this->eta_min_days === $this->eta_max_days
                ? __(':count day', ['count' => $this->eta_min_days])
                : __(':min-:max days', ['min' => $this->eta_min_days, 'max' => $this->eta_max_days]);
        }

        return $this->eta_min_days !== null
            ? __('From :count days', ['count' => $this->eta_min_days])
            : __('Up to :count days', ['count' => $this->eta_max_days]);
    }
}
