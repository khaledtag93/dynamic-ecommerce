<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportCaseMessage extends Model
{
    use HasFactory;

    public const AUTHOR_CUSTOMER = 'customer';
    public const AUTHOR_STAFF = 'staff';
    public const AUTHOR_SYSTEM = 'system';

    public const VISIBILITY_CUSTOMER = 'customer';
    public const VISIBILITY_INTERNAL = 'internal';

    protected $fillable = [
        'support_case_id',
        'author_user_id',
        'author_type',
        'visibility',
        'body',
    ];

    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function isInternal(): bool
    {
        return $this->visibility === self::VISIBILITY_INTERNAL;
    }
}
