<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

class LocalizedStoreSettings
{
    public static function apply(array $settings): array
    {
        $locale = App::getLocale();
        $fallback = config('app.fallback_locale', 'en');

        foreach (self::translatableKeys() as $key) {
            $settings[$key] = self::resolve($settings, $key, $locale, $fallback);
        }

        $settings['supported_locales'] = config('store.supported_locales', ['ar', 'en']);
        $settings['current_locale'] = $locale;
        $settings['fallback_locale'] = $fallback;

        return $settings;
    }

    public static function translatableKeys(): array
    {
        return config('store.translatable_settings', []);
    }

    public static function resolve(array $settings, string $key, ?string $locale = null, ?string $fallback = null): mixed
    {
        $locale ??= App::getLocale();
        $fallback ??= config('app.fallback_locale', 'en');

        $localizedKey = self::localizedKey($key, $locale);
        $fallbackKey = self::localizedKey($key, $fallback);

        if (array_key_exists($localizedKey, $settings) && filled($settings[$localizedKey])) {
            return $settings[$localizedKey];
        }

        if (array_key_exists($fallbackKey, $settings) && filled($settings[$fallbackKey])) {
            return $settings[$fallbackKey];
        }

        return $settings[$key] ?? null;
    }

    public static function localizedKey(string $key, string $locale): string
    {
        return "{$key}_{$locale}";
    }
}
