<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthExperiment extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'experiment_key',
        'description',
        'variants',
        'stats',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'variants' => 'array',
        'stats' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }

    public function deliveries()
    {
        return $this->hasMany(GrowthDelivery::class, 'experiment_id');
    }
}
