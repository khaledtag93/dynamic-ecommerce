<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReplyTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'body',
        'body_ar',
        'visibility',
        'sort_order',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'ar' && filled($this->name_ar)
            ? (string) $this->name_ar
            : (string) $this->name;
    }

    public function displayBody(): string
    {
        return app()->getLocale() === 'ar' && filled($this->body_ar)
            ? (string) $this->body_ar
            : (string) $this->body;
    }
}
