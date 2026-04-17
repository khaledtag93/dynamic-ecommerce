<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use App\Models\WebsiteSetting;
use App\Support\LocalSafeBoot;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = ['en', 'ar'];
        $defaultLocale = config('app.locale', 'en');

        if (! LocalSafeBoot::shouldSkipBootDatabaseTouches()) {
            try {
                if (Schema::hasTable('website_settings')) {
                    $defaultLocale = WebsiteSetting::getValue('default_locale', $defaultLocale) ?: $defaultLocale;
                }
            } catch (\Throwable $e) {
                // Keep locale middleware safe while the database is unavailable or not migrated yet.
            }
        }

        $locale = Session::get('locale', $defaultLocale);

        if (! in_array($locale, $supported, true)) {
            $locale = $defaultLocale;
        }

        App::setLocale($locale);
        date_default_timezone_set(config('app.timezone', 'UTC'));

        view()->share('currentLocale', $locale);
        view()->share('isRtl', $locale === 'ar');

        return $next($request);
    }
}
