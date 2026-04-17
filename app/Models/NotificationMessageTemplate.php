<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationMessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event',
        'channel',
        'locale',
        'name',
        'title',
        'subject',
        'body',
        'tokens',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'tokens' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
