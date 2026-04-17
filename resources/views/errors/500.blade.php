<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Something went wrong') }}</title>
    <style>
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 1rem; background: linear-gradient(180deg, #fff8f1 0%, #ffffff 100%); font-family: Cairo, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1f2937; }
        .error-shell { width: min(720px, calc(100% - 2rem)); background: #fff; border: 1px solid #f2dac8; border-radius: 28px; padding: 2.5rem; box-shadow: 0 24px 60px rgba(15, 23, 42, .08); text-align: center; }
        .error-icon { width: 96px; height: 96px; border-radius: 28px; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #ea580c; background: #fff2e7; margin-bottom: 1.25rem; }
        .error-code { display: inline-flex; align-items: center; justify-content: center; padding: .4rem .9rem; border-radius: 999px; background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; font-weight: 800; margin-bottom: 1rem; }
        .error-title { font-size: clamp(1.7rem, 4vw, 2.3rem); font-weight: 800; margin: 0 0 .75rem; }
        .error-copy { color: #6b7280; line-height: 1.9; margin: 0 auto 1.5rem; max-width: 52ch; }
        .error-actions { display: flex; justify-content: center; gap: .75rem; flex-wrap: wrap; }
        .error-btn { display: inline-flex; align-items: center; justify-content: center; padding: .9rem 1.3rem; border-radius: 18px; background: linear-gradient(135deg, #f97316, #ec4899); color: #fff; text-decoration: none; font-weight: 800; }
    </style>
</head>
<body>
    <div class="error-shell">
        <div class="error-icon">500</div>
        <div class="error-code">HTTP 500</div>
        <h1 class="error-title">{{ __('Something went wrong') }}</h1>
        <p class="error-copy">{{ __('We hit an unexpected problem while loading this page. Please try again, or return to a safer starting point.') }}</p>
        <div class="error-actions">
            <a href="{{ url('/') }}" class="error-btn">{{ __('Back to home') }}</a>
        </div>
    </div>
</body>
</html>
