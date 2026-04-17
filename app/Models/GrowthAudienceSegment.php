<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthAudienceSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'segment_key',
        'audience_type',
        'description',
        'filters',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function campaigns()
    {
        return $this->hasMany(GrowthCampaign::class, 'segment_id');
    }

    public function rules()
    {
        return $this->hasMany(GrowthAutomationRule::class, 'segment_id');
    }
}
