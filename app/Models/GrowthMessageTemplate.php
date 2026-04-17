<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrowthMessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'template_key',
        'channel',
        'locale',
        'subject',
        'body',
        'tokens',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'tokens' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];
}
