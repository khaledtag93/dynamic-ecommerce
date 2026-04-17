<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthOfferLearningSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'experiment_id',
        'campaign_key',
        'retention_stage',
        'offer_bias',
        'offer_key',
        'experiment_variant',
        'deliveries',
        'converted',
        'conversion_rate',
        'revenue',
        'learning_score',
        'is_recommended',
        'calculated_at',
        'meta',
    ];

    protected $casts = [
        'deliveries' => 'integer',
        'converted' => 'integer',
        'conversion_rate' => 'decimal:2',
        'revenue' => 'decimal:2',
        'learning_score' => 'decimal:2',
        'is_recommended' => 'boolean',
        'calculated_at' => 'datetime',
        'meta' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }

    public function experiment()
    {
        return $this->belongsTo(GrowthExperiment::class, 'experiment_id');
    }
}
