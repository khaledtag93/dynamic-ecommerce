<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZoneCity extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_zone_id',
        'country_code',
        'name',
        'normalized_name',
    ];

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
