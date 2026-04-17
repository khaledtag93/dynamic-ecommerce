<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthCohortSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'cohort_key',
        'cohort_label',
        'cohort_start_date',
        'cohort_end_date',
        'cohort_size',
        'retained_30d',
        'retained_60d',
        'retained_90d',
        'retention_rate_30d',
        'retention_rate_60d',
        'retention_rate_90d',
        'revenue_30d',
        'revenue_60d',
        'revenue_90d',
        'meta',
        'calculated_at',
    ];

    protected $casts = [
        'cohort_start_date' => 'date',
        'cohort_end_date' => 'date',
        'cohort_size' => 'integer',
        'retained_30d' => 'integer',
        'retained_60d' => 'integer',
        'retained_90d' => 'integer',
        'retention_rate_30d' => 'decimal:2',
        'retention_rate_60d' => 'decimal:2',
        'retention_rate_90d' => 'decimal:2',
        'revenue_30d' => 'decimal:2',
        'revenue_60d' => 'decimal:2',
        'revenue_90d' => 'decimal:2',
        'meta' => 'array',
        'calculated_at' => 'datetime',
    ];
}
