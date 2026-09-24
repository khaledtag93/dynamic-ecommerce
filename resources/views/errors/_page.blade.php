@php
    $primary = $storeSettings['brand_primary_color'] ?? '#2563eb';
    $secondary = $storeSettings['brand_secondary_color'] ?? '#0f172a';
    $accent = $storeSettings['brand_accent_color'] ?? '#0891b2';
    $background = $storeSettings['brand_background_color'] ?? '#f8fafc';
    $surface = $storeSettings['admin_surface_color'] ?? '#ffffff';
    $border = $storeSettings['admin_card_border_color'] ?? '#e2e8f0';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        :root {
            --error-primary: {{ $primary }};
            --error-secondary: {{ $secondary }};
            --error-accent: {{ $accent }};
            --error-bg: {{ $background }};
            --error-surface: {{ $surface }};
            --error-border: {{ $border }};
        }
        * { box-sizing: border-box; }
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 1rem; background: radial-gradient(circle at top right, color-mix(in srgb, var(--error-primary) 10%, transparent), transparent 32%), linear-gradient(180deg, color-mix(in srgb, var(--error-bg) 92%, white), var(--error-bg)); font-family: Cairo, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: var(--error-secondary); }
        .error-shell { width: min(760px, calc(100% - 2rem)); background: var(--error-surface); border: 1px solid var(--error-border); border-radius: 26px; padding: clamp(1.6rem, 5vw, 3rem); box-shadow: 0 24px 64px color-mix(in srgb, var(--error-secondary) 10%, transparent); text-align: center; }
        .error-mark { display: inline-flex; align-items: center; gap: .65rem; margin-bottom: 1.4rem; }
        .error-icon { min-width: 96px; height: 96px; padding: 0 1rem; border-radius: 28px; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--error-primary); background: color-mix(in srgb, var(--error-primary) 11%, var(--error-surface)); }
        .error-code { display: inline-flex; align-items: center; justify-content: center; padding: .42rem .9rem; border-radius: 999px; background: color-mix(in srgb, var(--error-primary) 8%, var(--error-surface)); color: var(--error-primary); border: 1px solid color-mix(in srgb, var(--error-primary) 28%, var(--error-border)); font-weight: 800; }
        .error-title { font-size: clamp(1.75rem, 4vw, 2.45rem); font-weight: 850; letter-spacing: -.02em; margin: 0 0 .8rem; color: var(--error-secondary); }
        .error-copy { color: color-mix(in srgb, var(--error-secondary) 62%, white); line-height: 1.85; margin: 0 auto 1.7rem; max-width: 58ch; }
        .error-actions { display: flex; justify-content: center; gap: .75rem; flex-wrap: wrap; }
        .error-btn { display: inline-flex; align-items: center; justify-content: center; padding: .9rem 1.35rem; border-radius: 16px; background: linear-gradient(135deg, var(--error-primary), var(--error-accent)); color: #fff; text-decoration: none; font-weight: 800; box-shadow: 0 12px 26px color-mix(in srgb, var(--error-primary) 22%, transparent); }
        .error-btn:hover { filter: brightness(.97); }
        @media (max-width: 520px) { .error-mark { flex-direction: column; } .error-shell { width: 100%; } }
    </style>
</head>
<body>
    <main class="error-shell">
        <div class="error-mark">
            <div class="error-icon">{{ $code }}</div>
            <div class="error-code">HTTP {{ $code }}</div>
        </div>
        <h1 class="error-title">{{ $title }}</h1>
        <p class="error-copy">{{ $copy }}</p>
        <div class="error-actions">
            <a href="{{ url('/') }}" class="error-btn">{{ $action }}</a>
        </div>
    </main>
</body>
</html>
