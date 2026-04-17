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

    public static function status(): array
    {
        return [
            'enabled' => self::enabled(),
            'environment' => app()->environment(),
            'db_connection' => (string) config('database.default'),
            'app_url' => (string) config('app.url'),
        ];
    }
}
