<?php

namespace App\Support;

class LocalSafeBoot
{
    public static function enabled(): bool
    {
        $configured = env('LOCAL_SAFE_BOOT');

        if ($configured === null) {
            return app()->environment('local');
        }

        return filter_var($configured, FILTER_VALIDATE_BOOL);
    }

    public static function shouldSkipBootDatabaseTouches(): bool
    {
        return self::enabled() && app()->environment('local');
    }
}
