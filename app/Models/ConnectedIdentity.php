<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedIdentity extends Model
{
    use HasFactory;

    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_FACEBOOK = 'facebook';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'provider_email_verified_at',
        'avatar_url',
        'last_login_at',
        'meta',
    ];

    protected $casts = [
        'provider_email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'meta' => 'array',
    ];

    public static function supportedProviders(): array
    {
        return [
            self::PROVIDER_GOOGLE,
            self::PROVIDER_FACEBOOK,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
