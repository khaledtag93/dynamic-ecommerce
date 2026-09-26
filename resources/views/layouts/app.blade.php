<!DOCTYPE html>
@php
    /* Storefront layout safe defaults: keeps the customer layout stable if a view composer value is missing. */
    $storeSettings = $storeSettings ?? [];
    $isRtl = $isRtl ?? app()->getLocale() === 'ar';
    $layoutCategories = collect($layoutCategories ?? []);
    $layoutCartCount = (int) ($layoutCartCount ?? 0);
    $authNotificationCount = (int) ($authNotificationCount ?? 0);
    $customerLogoPath = \App\Support\AdminBranding::resolveMediaPath($storeSettings['logo_path'] ?? $storeSettings['logo'] ?? null, 'logo');
    $localizedStoreTagline = ($isRtl ?? false)
        ? ($storeSettings['store_tagline_ar'] ?? $storeSettings['store_tagline'] ?? __('تجربة تسوق موثوقة بأسعار واضحة ودفع آمن ودعم يمكن الاعتماد عليه.'))
        : ($storeSettings['store_tagline_en'] ?? $storeSettings['store_tagline'] ?? __('A reliable shopping experience with clear prices, secure checkout, and dependable support.'));
    $localizedFooterAbout = ($isRtl ?? false)
        ? ($storeSettings['footer_about_ar'] ?? $storeSettings['footer_about'] ?? __('تصفّح منتجات المتجر بأسعار واضحة ودفع آمن ودعم يساعدك قبل وبعد الشراء.'))
        : ($storeSettings['footer_about_en'] ?? $storeSettings['footer_about'] ?? __('Browse products across the store with clear prices, secure checkout, and helpful support.'));
    $localizedFooterCopyright = ($isRtl ?? false)
        ? ($storeSettings['footer_copyright_ar'] ?? $storeSettings['footer_copyright'] ?? __('جميع الحقوق محفوظة.'))
        : ($storeSettings['footer_copyright_en'] ?? $storeSettings['footer_copyright'] ?? __('All rights reserved.'));
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront')</title>
    <meta name="description" content="@yield('meta_description', __('Browse products, offers, and categories with secure checkout and clear delivery information.'))">
    @php($faviconPath = \App\Support\AdminBranding::resolveMediaPath($storeSettings['favicon_path'] ?? null, 'favicon'))
    @if($faviconPath)
        <link rel="icon" type="image/x-icon" href="{{ \App\Support\AdminBranding::mediaUrl($faviconPath, 'favicon') }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @livewireStyles
    @php
        $storeThemePreset = (string) ($storeSettings['theme_preset'] ?? 'professional_commerce');
        $storeThemeProfile = match ($storeThemePreset) {
            'midnight_luxury', 'luxury_noir', 'desert_gold' => 'refined',
            'tech_neon', 'graphite_modern', 'streetwear_volt' => 'sharp',
            'beauty_blush', 'rose_boutique', 'kids_pop' => 'playful',
            'nordic_home', 'emerald_studio', 'coffee_craft' => 'organic',
            default => 'balanced',
        };
        $themeProfiles = [
            'refined' => ['control_radius' => '8px', 'media_radius' => '6px', 'lift' => '-2px', 'shadow_scale' => '8%'],
            'sharp' => ['control_radius' => '10px', 'media_radius' => '8px', 'lift' => '-3px', 'shadow_scale' => '12%'],
            'playful' => ['control_radius' => '18px', 'media_radius' => '20px', 'lift' => '-5px', 'shadow_scale' => '16%'],
            'organic' => ['control_radius' => '14px', 'media_radius' => '16px', 'lift' => '-3px', 'shadow_scale' => '10%'],
            'balanced' => ['control_radius' => '14px', 'media_radius' => '14px', 'lift' => '-4px', 'shadow_scale' => '12%'],
        ];
        $themeProfile = $themeProfiles[$storeThemeProfile];
    @endphp
    <style>
        :root {
            --lc-primary: {{ $storeSettings['brand_primary_color'] ?? '#2563eb' }};
            --lc-primary-dark: {{ $storeSettings['brand_accent_color'] ?? '#0891b2' }};
            --lc-secondary: {{ $storeSettings['brand_secondary_color'] ?? '#0f172a' }};
            --lc-soft: {{ $storeSettings['brand_soft_color'] ?? '#eff6ff' }};
            --lc-text: #1f2937;
            --lc-muted: #6b7280;
            --lc-border: {{ $storeSettings['brand_border_color'] ?? '#dbe3ef' }};
            --lc-bg: {{ $storeSettings['brand_background_color'] ?? '#f8fafc' }};
            --lc-dark: color-mix(in srgb, var(--lc-secondary) 38%, #0f172a);
            --lc-surface: {{ $storeSettings['brand_surface_color'] ?? '#ffffff' }};
            --lc-btn-text: {{ $storeSettings['brand_button_text_color'] ?? '#ffffff' }};
            --lc-card-radius: {{ (int) ($storeSettings['customer_card_radius'] ?? 18) }}px;
            --lc-badge-radius: {{ ($storeSettings['customer_badge_style'] ?? 'soft') === 'pill' ? '999px' : (($storeSettings['customer_badge_style'] ?? 'soft') === 'outline' ? '6px' : '12px') }};
            --lc-badge-bg: {{ ($storeSettings['customer_badge_style'] ?? 'soft') === 'outline' ? 'transparent' : 'color-mix(in srgb, var(--lc-soft) 82%, white)' }};
            --lc-badge-border: {{ ($storeSettings['customer_badge_style'] ?? 'soft') === 'outline' ? 'var(--lc-primary)' : 'color-mix(in srgb, var(--lc-border) 78%, white)' }};
            --lc-control-radius: {{ $themeProfile['control_radius'] }};
            --lc-media-radius: {{ $themeProfile['media_radius'] }};
            --lc-hover-lift: {{ $themeProfile['lift'] }};
            --lc-theme-shadow-strength: {{ $themeProfile['shadow_scale'] }};
            --lc-shadow-color: color-mix(in srgb, var(--lc-primary) var(--lc-theme-shadow-strength), transparent);
            --lc-shadow-soft: 0 10px 30px var(--lc-shadow-color);
            --lc-shadow-card: 0 18px 50px color-mix(in srgb, var(--lc-dark) var(--lc-theme-shadow-strength), transparent);
            --lc-shadow-strong: 0 20px 55px color-mix(in srgb, var(--lc-dark) 12%, transparent);
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(180deg, var(--lc-bg) 0%, #ffffff 100%);
            color: var(--lc-text);
        }
        body[dir="rtl"] { text-align: right; }
        a { text-decoration: none; }
        .navbar-brand { font-weight: 800; letter-spacing: .3px; }
        .lc-topbar {
            background: linear-gradient(90deg, var(--lc-dark), var(--lc-secondary));
            color: rgba(255,255,255,.88);
            font-size: .92rem;
        }
        .lc-topbar a { color: #fff; }
        .lc-navbar {
            background: color-mix(in srgb, {{ $storeSettings['brand_surface_color'] ?? '#ffffff' }} 92%, transparent);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid color-mix(in srgb, var(--lc-primary) 14%, white);
        }
        .lc-nav-pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            border: 1px solid color-mix(in srgb, var(--lc-primary) 16%, white);
            background: #fff;
            border-radius: 999px;
            padding: .38rem .75rem;
            font-weight: 700;
            color: var(--lc-text);
        }
        .lc-navbar .nav-link {
            color: var(--lc-text);
            font-weight: 700;
            border-radius: 999px;
            padding: .55rem .95rem !important;
            transition: all .2s ease;
        }
        .lc-navbar .nav-link:hover,
        .lc-navbar .nav-link:focus {
            color: var(--lc-primary-dark);
            background: color-mix(in srgb, var(--lc-soft) 82%, white);
        }
        .lc-navbar .dropdown-menu {
            border: 1px solid color-mix(in srgb, var(--lc-primary) 12%, white);
            box-shadow: 0 18px 38px color-mix(in srgb, var(--lc-dark) 10%, transparent) !important;
        }
        .lc-hero {
            background: radial-gradient(circle at top left, var(--lc-soft), color-mix(in srgb, var(--lc-bg) 90%, white) 45%, #ffffff 100%);
            border-bottom: 1px solid color-mix(in srgb, var(--lc-primary) 10%, white);
        }
        .lc-badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .5rem .9rem;
            border-radius: var(--lc-badge-radius);
            background: var(--lc-badge-bg);
            border: 1px solid var(--lc-badge-border);
            color: var(--lc-primary-dark);
            font-weight: 700;
            box-shadow: var(--lc-shadow-soft);
        }
        .lc-card {
            background: var(--lc-surface);
            border: 1px solid var(--lc-border);
            border-radius: var(--lc-card-radius);
            box-shadow: var(--lc-shadow-card);
        }
        .lc-hero-card {
            background: linear-gradient(180deg, color-mix(in srgb, var(--lc-surface) 98%, transparent), color-mix(in srgb, var(--lc-soft) 92%, white));
            border: 1px solid var(--lc-border);
            border-radius: calc(var(--lc-card-radius) + 4px);
            box-shadow: var(--lc-shadow-strong);
        }
        .lc-home-section { padding-block: clamp(3.5rem, 5vw, 5.5rem); position: relative; }
        .lc-home-section--muted { background: linear-gradient(180deg, color-mix(in srgb, var(--lc-soft) 36%, white), transparent 82%); }
        .lc-section-shell { display: grid; gap: clamp(1.4rem, 2.2vw, 2rem); }
        .lc-section-head {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 1rem;
            margin-bottom: clamp(1rem, 2vw, 1.75rem);
        }
        .lc-section-head__copy { max-width: 720px; }
        .lc-section-kicker {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--lc-primary-dark);
            font-weight: 800;
            margin-bottom: .85rem;
        }
        .lc-section-kicker::before {
            content: "";
            width: 2.2rem;
            height: 1px;
            background: color-mix(in srgb, var(--lc-primary) 35%, white);
        }
        .lc-section-title { font-weight: 800; font-size: clamp(1.8rem, 2vw, 2.35rem); line-height: 1.15; margin-bottom: 0; }
        .lc-section-description { color: var(--lc-muted); margin-top: .8rem; margin-bottom: 0; font-size: 1rem; max-width: 62ch; }
        .lc-section-head--center { justify-content: center; text-align: center; }
        .lc-grid { display: grid; gap: 1.25rem; }
        .lc-grid-products { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .lc-grid-categories { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .lc-grid-promos { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .lc-grid-trust { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .lc-section-empty {
            padding: clamp(1.5rem, 3vw, 2rem);
            border-radius: calc(var(--lc-card-radius) + 2px);
            border: 1px dashed color-mix(in srgb, var(--lc-border) 82%, white);
            background: linear-gradient(180deg, color-mix(in srgb, var(--lc-soft) 55%, white), color-mix(in srgb, var(--lc-surface) 96%, transparent));
            text-align: center;
        }
        .lc-section-empty__icon {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.7rem;
            color: var(--lc-primary-dark);
            background: color-mix(in srgb, var(--lc-soft) 76%, white);
            border: 1px solid color-mix(in srgb, var(--lc-border) 82%, white);
            box-shadow: 0 14px 34px color-mix(in srgb, var(--lc-primary) 12%, transparent);
        }
        .lc-skeleton {
            position: relative;
            overflow: hidden;
            background: color-mix(in srgb, var(--lc-soft) 78%, white);
        }
        .lc-skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.55), transparent);
            animation: lcSkeleton 1.35s infinite;
        }
        .lc-product-card { height: 100%; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; position: relative; overflow: hidden; }
        .lc-product-card:hover { transform: translateY(var(--lc-hover-lift)); box-shadow: 0 18px 50px color-mix(in srgb, var(--lc-dark) 15%, transparent); border-color: color-mix(in srgb, var(--lc-primary) 24%, white); }
        .lc-product-thumb-wrap { position: relative; display: block; }
        .lc-product-thumb { aspect-ratio: 1/1; object-fit: cover; border-radius: var(--lc-media-radius); background: color-mix(in srgb, var(--lc-soft) 82%, white); }
        .lc-product-badge {
            position: absolute;
            top: .85rem;
            inset-inline-start: .85rem;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .45rem .7rem;
            border-radius: var(--lc-badge-radius);
            font-size: .78rem;
            font-weight: 800;
            color: {{ ($storeSettings['customer_badge_style'] ?? 'soft') === 'outline' ? 'var(--lc-primary-dark)' : 'var(--lc-btn-text)' }};
            background: {{ ($storeSettings['customer_badge_style'] ?? 'soft') === 'outline' ? 'var(--lc-surface)' : 'linear-gradient(135deg, var(--lc-primary), var(--lc-secondary))' }};
            border: 1px solid {{ ($storeSettings['customer_badge_style'] ?? 'soft') === 'outline' ? 'var(--lc-primary)' : 'transparent' }};
            box-shadow: 0 12px 24px color-mix(in srgb, var(--lc-primary) 22%, transparent);
        }
        .lc-product-meta { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .85rem; }
        .lc-product-meta__pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .78rem;
            font-weight: 800;
            border-radius: var(--lc-badge-radius);
            padding: .4rem .65rem;
            background: var(--lc-badge-bg);
            color: var(--lc-primary-dark);
            border: 1px solid var(--lc-badge-border);
        }
        .lc-product-card__category { font-size: .82rem; font-weight: 700; color: var(--lc-primary-dark); }
        .lc-product-card__title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 3rem;
        }
        .lc-price-stack { display: flex; align-items: baseline; gap: .5rem; flex-wrap: wrap; }
        .lc-price-original { color: var(--lc-muted); text-decoration: line-through; font-size: .9rem; }
        .lc-product-actions { display: grid; gap: .65rem; margin-top: auto; }
        .lc-btn-primary {
            background: linear-gradient(135deg, var(--lc-primary), var(--lc-secondary));
            color: var(--lc-btn-text); border: none; border-radius: var(--lc-control-radius); padding: .8rem 1.1rem; font-weight: 700;
            box-shadow: 0 14px 30px color-mix(in srgb, var(--lc-primary) 18%, transparent);
        }
        .lc-btn-primary:hover { color: #fff; opacity: .95; box-shadow: 0 18px 36px color-mix(in srgb, var(--lc-primary) 24%, transparent); }
        .lc-btn-soft {
            background: var(--lc-soft); color: var(--lc-primary-dark); border: 1px solid var(--lc-border);
            border-radius: var(--lc-control-radius); padding: .8rem 1.1rem; font-weight: 700; box-shadow: 0 12px 26px color-mix(in srgb, var(--lc-primary) 10%, transparent);
        }
        .lc-btn-danger-soft {
            background: linear-gradient(180deg, #fff4f4 0%, #ffffff 100%);
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: .95rem;
            padding: .8rem 1.1rem;
            font-weight: 700;
            box-shadow: 0 12px 26px rgba(220, 38, 38, .08);
        }
        .lc-btn-danger-soft:hover {
            color: #991b1b;
            border-color: #fca5a5;
            background: linear-gradient(180deg, #ffecec 0%, #ffffff 100%);
        }
        .lc-footer { border-top: 1px solid color-mix(in srgb, var(--lc-primary) 14%, white); background: var(--lc-surface); }
        .lc-footer-card {
            height: 100%;
            padding: 1.25rem;
            border-radius: 1.15rem;
            border: 1px solid color-mix(in srgb, var(--lc-primary) 12%, white);
            background: linear-gradient(180deg, color-mix(in srgb, var(--lc-surface) 97%, transparent), color-mix(in srgb, var(--lc-soft) 82%, white));
        }
        .lc-legal-content { line-height: 1.9; }
        .lc-legal-content ul { padding-left: 1.2rem; }
        .lc-legal-content li + li { margin-top: .45rem; }
        .lc-form-control, .lc-form-select { border-radius: var(--lc-control-radius); border: 1px solid color-mix(in srgb, var(--lc-border) 75%, white); padding: .85rem 1rem; background: var(--lc-surface); }
        .lc-form-control:focus, .lc-form-select:focus { border-color: color-mix(in srgb, var(--lc-primary) 55%, white); box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--lc-primary) 14%, transparent); }
        .lc-page-shell { padding-block: 1rem 2rem; }

        .lc-page-hero { padding: clamp(1.35rem, 2vw, 1.8rem); display:grid; gap:1rem; background: linear-gradient(180deg, color-mix(in srgb, var(--lc-surface) 97%, transparent), color-mix(in srgb, var(--lc-soft) 82%, white)); }
        .lc-page-hero__actions { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; justify-content:space-between; }
        .lc-section-stack { display:grid; gap:1.25rem; }

        .lc-stat-card { padding: 1.25rem; height: 100%; background: linear-gradient(180deg, color-mix(in srgb, {{ $storeSettings['brand_surface_color'] ?? '#ffffff' }} 98%, transparent), color-mix(in srgb, var(--lc-soft) 92%, white)); }
        .lc-stat-label { font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; color: var(--lc-muted); font-weight: 800; margin-bottom: .55rem; }
        .lc-stat-value { font-size: 2rem; font-weight: 800; line-height: 1; }
        .lc-order-card { padding: 1.25rem; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
        .lc-order-card:hover { transform: translateY(-3px); box-shadow: 0 20px 44px rgba(17,24,39,.08); border-color: color-mix(in srgb, var(--lc-primary) 24%, white); }
        .lc-status-badge { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; padding: .55rem .95rem; border-radius: 999px; font-size: .82rem; font-weight: 800; border: 1px solid transparent; }
        .lc-status-badge::before { content: ""; width: .5rem; height: .5rem; border-radius: 999px; background: currentColor; opacity: .85; }
        .lc-badge-pending { color: #9a6700; background: #fff5d6; border-color: #ffe3a3; }
        .lc-badge-processing { color: #1d4ed8; background: #dbeafe; border-color: #bfdbfe; }
        .lc-badge-completed { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
        .lc-badge-cancelled { color: #b91c1c; background: #fee2e2; border-color: #fecaca; }
        .lc-badge-paid, .lc-badge-success { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
        .lc-badge-unpaid { color: #475569; background: #e2e8f0; border-color: #cbd5e1; }
        .lc-badge-failed, .lc-badge-danger { color: #b91c1c; background: #fee2e2; border-color: #fecaca; }
        .lc-surface-soft { background: linear-gradient(180deg, var(--lc-soft) 0%, var(--lc-surface) 100%); border: 1px solid color-mix(in srgb, var(--lc-primary) 16%, white); border-radius: 1.15rem; }
        .lc-order-timeline { position: relative; display: grid; gap: 1rem; }
        .lc-order-step { position: relative; padding-left: 3rem; }
        body[dir="rtl"] .lc-order-step { padding-left: 0; padding-right: 3rem; }
        .lc-order-step::before { content: ""; position: absolute; left: 1rem; top: 2rem; bottom: -1.2rem; width: 2px; background: #fde2c3; }
        body[dir="rtl"] .lc-order-step::before { left: auto; right: 1rem; }
        .lc-order-step:last-child::before { display: none; }
        .lc-order-step-dot { position: absolute; left: 0; top: .2rem; width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: #fff; border: 2px solid #fcd6ae; color: var(--lc-muted); font-size: .85rem; font-weight: 800; }
        body[dir="rtl"] .lc-order-step-dot { left: auto; right: 0; }
        .lc-order-step.active .lc-order-step-dot { background: linear-gradient(135deg, var(--lc-primary), var(--lc-primary-dark)); border-color: transparent; color: #fff; box-shadow: 0 12px 24px color-mix(in srgb, var(--lc-primary) 18%, transparent); }
        .lc-order-step.current .lc-order-step-dot { box-shadow: 0 0 0 6px color-mix(in srgb, var(--lc-primary) 14%, transparent); }
        .lc-order-step-title { font-weight: 800; margin-bottom: .15rem; }
        .lc-order-step-copy { color: var(--lc-muted); font-size: .92rem; }
        .lc-summary-list .row-item { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: .8rem; }
        .lc-empty-state { padding: 3rem 1.25rem; text-align: center; }
        .lc-empty-icon { width: 80px; height: 80px; display: inline-flex; align-items: center; justify-content: center; border-radius: 24px; background: linear-gradient(135deg, var(--lc-soft), color-mix(in srgb, var(--lc-bg) 92%, white)); color: var(--lc-primary-dark); font-size: 2rem; margin-bottom: 1rem; box-shadow: 0 18px 36px color-mix(in srgb, var(--lc-primary) 12%, transparent); }
        .page-link { border-radius: .85rem !important; color: var(--lc-primary-dark); border-color: color-mix(in srgb, var(--lc-primary) 18%, white); margin-inline: .18rem; }
        .page-item.active .page-link { background: linear-gradient(135deg, var(--lc-primary), var(--lc-primary-dark)); border-color: transparent; }
        .lc-table-hover tbody tr:hover { background: color-mix(in srgb, var(--lc-primary) 4%, white); }
        .lc-loading { pointer-events:none; opacity:.72; }
        .lc-loading-spinner { width:1rem; height:1rem; border:2px solid rgba(255,255,255,.35); border-top-color:#fff; border-radius:999px; display:inline-block; animation:lcSpin .8s linear infinite; margin-inline-end:.55rem; vertical-align:-2px; }
        @keyframes lcSpin { to { transform: rotate(360deg); } }
        @keyframes lcSkeleton { 100% { transform: translateX(100%); } }
        .lc-btn-soft .lc-loading-spinner { border-color: rgba(234,88,12,.2); border-top-color: var(--lc-primary-dark); }
        .lc-coupon-box { background: linear-gradient(180deg, color-mix(in srgb, {{ $storeSettings['brand_surface_color'] ?? '#ffffff' }} 98%, transparent), color-mix(in srgb, var(--lc-soft) 90%, white)); border: 1px solid var(--lc-border); border-radius: 1rem; }
        .lc-note-card { border:1px dashed var(--lc-border); border-radius:1rem; background:color-mix(in srgb, var(--lc-bg) 85%, white); }
        .lc-feature-strip { display:grid; grid-template-columns: repeat(4, 1fr); gap:1rem; }
        .lc-feature-item { background:{{ $storeSettings['brand_surface_color'] ?? '#ffffff' }}; border:1px solid var(--lc-border); border-radius:1.1rem; padding:1rem; }
        .lc-feature-item i { font-size:1.25rem; color: var(--lc-primary-dark); }
        .language-switcher { display:flex; align-items:center; gap:.8rem; flex-wrap:wrap; }
        .language-switcher__label { font-size:.82rem; font-weight:700; letter-spacing:.03em; color:rgba(255,255,255,.75); text-transform:uppercase; }
        .language-switcher__group { display:inline-flex; align-items:center; gap:.45rem; padding:.35rem; border-radius:999px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.14); box-shadow:0 10px 24px rgba(15,23,42,.12); backdrop-filter:blur(10px); }
        .language-switcher__link { display:inline-flex; align-items:center; gap:.45rem; text-decoration:none; border-radius:999px; padding:.5rem .85rem; color:#fff; font-weight:700; font-size:.88rem; transition:all .2s ease; }
        .language-switcher__link:hover { background:rgba(255,255,255,.12); color:#fff; transform:translateY(-1px); }
        .language-switcher__link.is-active { background:#fff; color:var(--lc-primary-dark); box-shadow:0 10px 20px rgba(15,23,42,.12); border:1px solid color-mix(in srgb, var(--lc-primary) 18%, white); }
        .language-switcher__code { display:inline-flex; width:1.9rem; height:1.9rem; align-items:center; justify-content:center; border-radius:999px; background:rgba(255,255,255,.14); font-size:.72rem; letter-spacing:.04em; }
        .language-switcher__link.is-active .language-switcher__code { background:color-mix(in srgb, var(--lc-primary) 14%, white); }
        .language-switcher__name { line-height:1; }

        .btn-primary,
        .btn-primary:focus,
        .btn-primary:active {
            background: linear-gradient(135deg, var(--lc-primary), var(--lc-secondary)) !important;
            border-color: transparent !important;
            color: var(--lc-button-text, #fff) !important;
        }

        .btn-outline-primary {
            color: var(--lc-primary-dark) !important;
            border-color: color-mix(in srgb, var(--lc-primary) 28%, white) !important;
        }

        .btn-outline-primary:hover {
            background: color-mix(in srgb, var(--lc-primary) 8%, white) !important;
            color: var(--lc-primary-dark) !important;
        }
        body[dir="rtl"] .dropdown-menu { text-align: right; }
        @media (max-width: 1199.98px) {
            .lc-grid-products, .lc-grid-categories { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .lc-grid-trust { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 991.98px) {
            .lc-feature-strip { grid-template-columns: repeat(2, 1fr); }
            .lc-grid-products, .lc-grid-categories, .lc-grid-promos { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .lc-section-head { align-items: start; flex-direction: column; }
        }
        @media (max-width: 767.98px) {
            .lc-home-section { padding-block: 3.25rem; }
            .lc-section-title { font-size: 1.6rem; }
            .lc-order-card { padding: 1rem; }
            .lc-stat-value { font-size: 1.7rem; }
            .lc-feature-strip { grid-template-columns: 1fr; }
            .lc-grid-products, .lc-grid-categories, .lc-grid-promos, .lc-grid-trust { grid-template-columns: 1fr; }
            .lc-section-head__copy, .lc-section-description { max-width: 100%; }
        }

        .lc-pagination-wrap .pagination { gap: .2rem; flex-wrap: wrap; }
        .lc-pagination-wrap .page-link {
            min-width: 2.6rem;
            min-height: 2.6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 22px color-mix(in srgb, var(--lc-primary) 8%, transparent);
        }
        .lc-cart-shell, .lc-checkout-shell { display: grid; gap: 1.5rem; }
        .lc-cart-toolbar, .lc-checkout-toolbar {
            padding: 1.1rem 1.25rem;
            background: linear-gradient(180deg, color-mix(in srgb, var(--lc-soft) 72%, white), color-mix(in srgb, var(--lc-surface) 96%, transparent));
            border: 1px solid var(--lc-border);
            border-radius: calc(var(--lc-card-radius) + 2px);
            box-shadow: var(--lc-shadow-soft);
        }
        .lc-progress-strip { display:grid; grid-template-columns:repeat(3,1fr); gap:.9rem; }
        .lc-progress-step {
            display:flex; align-items:center; gap:.8rem; padding:.9rem 1rem; border-radius:1rem; border:1px solid color-mix(in srgb, var(--lc-border) 76%, white);
            background: color-mix(in srgb, var(--lc-surface) 94%, transparent);
        }
        .lc-progress-step__dot {
            width:2rem; height:2rem; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-weight:800;
            background: color-mix(in srgb, var(--lc-soft) 88%, white); color: var(--lc-primary-dark); border:1px solid color-mix(in srgb, var(--lc-border) 82%, white);
        }
        .lc-progress-step.is-active .lc-progress-step__dot,
        .lc-progress-step.is-complete .lc-progress-step__dot {
            background: linear-gradient(135deg, var(--lc-primary), var(--lc-secondary)); color:#fff; border-color: transparent;
        }
        .lc-progress-step__title { font-weight: 800; line-height: 1.1; }
        .lc-progress-step__copy { color: var(--lc-muted); font-size: .84rem; }
        .lc-cart-item {
            display:grid; grid-template-columns: auto 1fr auto; gap:1rem; align-items:center; padding:1rem; border-radius:1.15rem;
            border:1px solid color-mix(in srgb, var(--lc-border) 70%, white); background:linear-gradient(180deg, color-mix(in srgb, var(--lc-surface) 97%, transparent), color-mix(in srgb, var(--lc-soft) 68%, white));
        }
        .lc-cart-item__media img { width: 84px; height: 84px; border-radius: 1rem; object-fit: cover; }
        .lc-cart-item__meta { display:grid; gap:.35rem; }
        .lc-cart-item__title { font-weight: 800; }
        .lc-cart-item__sub { color: var(--lc-muted); font-size:.9rem; }
        .lc-qty-shell {
            display:inline-flex; align-items:center; gap:.55rem; padding:.35rem; border-radius:999px; border:1px solid color-mix(in srgb, var(--lc-border) 76%, white); background:#fff;
        }
        .lc-qty-shell .form-control {
            width: 86px; border: none; box-shadow:none; background: transparent; font-weight:800;
        }
        .lc-summary-card-sticky { position: sticky; top: 100px; }
        .lc-summary-divider { border-top:1px dashed color-mix(in srgb, var(--lc-border) 82%, white); margin: 1rem 0; }
        .lc-summary-row { display:flex; justify-content:space-between; gap:1rem; margin-bottom:.9rem; }
        .lc-payment-option {
            position: relative; display:block; height:100%; cursor:pointer;
        }
        .lc-payment-option__card {
            height:100%; padding:1rem; border-radius:1.2rem; border:1px solid color-mix(in srgb, var(--lc-border) 76%, white);
            background: linear-gradient(180deg, color-mix(in srgb, var(--lc-surface) 98%, transparent), color-mix(in srgb, var(--lc-soft) 74%, white));
            transition: all .2s ease;
        }
        .lc-payment-option input { position:absolute; opacity:0; pointer-events:none; }
        .lc-payment-option.is-active .lc-payment-option__card {
            border-color: color-mix(in srgb, var(--lc-primary) 42%, white);
            box-shadow: 0 16px 34px color-mix(in srgb, var(--lc-primary) 14%, transparent);
            transform: translateY(-2px);
        }
        .lc-payment-option__header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.75rem; }
        .lc-payment-option__badge { font-size:.72rem; font-weight:800; color: var(--lc-primary-dark); background: color-mix(in srgb, var(--lc-soft) 88%, white); border:1px solid color-mix(in srgb, var(--lc-border) 80%, white); border-radius:999px; padding:.35rem .6rem; }
        .lc-empty-panel {
            padding: 2.5rem 1.5rem; text-align:center; border:1px dashed color-mix(in srgb, var(--lc-border) 76%, white); border-radius: calc(var(--lc-card-radius) + 2px);
            background: linear-gradient(180deg, color-mix(in srgb, var(--lc-soft) 70%, white), color-mix(in srgb, var(--lc-surface) 98%, transparent));
        }
        .lc-inline-note { display:flex; align-items:flex-start; gap:.7rem; color:var(--lc-muted); font-size:.92rem; }
        .lc-inline-note i { color: var(--lc-primary-dark); font-size: 1rem; }
        .lc-state-pulse { position: relative; }
        .lc-state-pulse::after {
            content:""; position:absolute; inset:-6px; border-radius:inherit; border:1px solid color-mix(in srgb, var(--lc-primary) 14%, transparent); animation: lcPulse 1.8s ease-out infinite;
        }
        @keyframes lcPulse { 0% { opacity: .9; transform: scale(1); } 100% { opacity: 0; transform: scale(1.04); } }

        /* Storefront Phase A polish: unified customer design system, cleaner spacing, and retail-focused surfaces. */
        .lc-navbar{box-shadow:0 10px 30px color-mix(in srgb,var(--lc-dark) 7%, transparent)}
        .lc-category-dropdown{min-width:260px;max-height:420px;overflow:auto}.lc-category-dropdown .dropdown-item{font-weight:800;color:var(--lc-text)}.lc-category-dropdown .dropdown-item:hover{background:color-mix(in srgb,var(--lc-soft) 80%, white);color:var(--lc-primary-dark)}
        .lc-btn-primary,.lc-btn-soft,.lc-btn-danger-soft{border-radius:1rem;font-weight:900}.lc-btn-primary{background:linear-gradient(135deg,var(--lc-dark),color-mix(in srgb,var(--lc-primary) 55%, var(--lc-dark)));border:none;color:var(--lc-btn-text);box-shadow:0 16px 32px color-mix(in srgb,var(--lc-dark) 16%, transparent)}.lc-btn-primary:hover{color:var(--lc-btn-text);transform:translateY(-1px);box-shadow:0 20px 42px color-mix(in srgb,var(--lc-dark) 20%, transparent)}.lc-btn-soft{background:#fff;border:1px solid color-mix(in srgb,var(--lc-border) 76%, white);color:var(--lc-primary-dark)}.lc-btn-soft:hover{background:color-mix(in srgb,var(--lc-soft) 80%, white);color:var(--lc-primary-dark);border-color:color-mix(in srgb,var(--lc-primary) 28%, white)}.lc-btn-danger-soft{background:#fff1f2;border:1px solid #fecdd3;color:#be123c}.lc-btn-danger-soft:hover{background:#ffe4e6;color:#9f1239}
        .storefront-footer{background:linear-gradient(135deg,#0f172a,color-mix(in srgb,var(--lc-dark) 74%, #020617));color:#fff;border-top:1px solid rgba(255,255,255,.08)}.storefront-footer__top{display:grid;grid-template-columns:2fr repeat(4,1fr);gap:2rem}.storefront-footer__brand p{color:rgba(255,255,255,.72);line-height:1.9;max-width:520px}.storefront-footer__trust{display:flex;flex-wrap:wrap;gap:.6rem}.storefront-footer__trust span{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem .75rem;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);font-weight:800;font-size:.84rem}.storefront-footer__trust i{color:var(--lc-primary)}.storefront-footer__social{display:flex;flex-wrap:wrap;gap:.55rem;margin-top:1rem}.storefront-footer__social a{width:38px;height:38px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:#fff;text-decoration:none;transition:transform .18s ease,background .18s ease}.storefront-footer__social a:hover{background:rgba(255,255,255,.15);transform:translateY(-2px)}.storefront-footer__social a:focus-visible{outline:3px solid var(--lc-primary);outline-offset:3px}.storefront-footer__column{display:flex;flex-direction:column;gap:.6rem}.storefront-footer__column h6{font-weight:900;margin-bottom:.35rem;color:#fff}.storefront-footer__column a,.storefront-footer__column span{color:rgba(255,255,255,.68);font-size:.94rem}.storefront-footer__column a{width:fit-content;text-decoration:none;transition:color .18s ease,transform .18s ease}.storefront-footer__column a:hover{color:#fff;transform:translateX(2px)}body[dir="rtl"] .storefront-footer__column a:hover{transform:translateX(-2px)}.storefront-footer__contact a,.storefront-footer__contact span{display:inline-flex;align-items:flex-start;gap:.55rem;line-height:1.6;overflow-wrap:anywhere}.storefront-footer__contact i{color:var(--lc-primary);margin-top:.18rem;flex:0 0 auto}.storefront-footer__column a:focus-visible{color:#fff;outline:3px solid var(--lc-primary);outline-offset:4px;border-radius:.3rem}.storefront-footer__column a[aria-current="page"]{color:#fff;font-weight:850}.storefront-footer__bottom{border-top:1px solid rgba(255,255,255,.1);margin-top:2rem;padding-top:1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;color:rgba(255,255,255,.58);font-size:.9rem}.lc-page-shell{background:linear-gradient(180deg,#fff,color-mix(in srgb,var(--lc-soft) 24%, #fff))}@media(max-width:991.98px){.storefront-footer__top{grid-template-columns:1fr 1fr}.storefront-footer__brand{grid-column:1/-1}}@media(max-width:575.98px){.storefront-footer{padding-block:2.5rem!important}.storefront-footer__top{grid-template-columns:1fr;gap:1.5rem}.storefront-footer__brand p{line-height:1.75}.storefront-footer__trust{gap:.45rem}.storefront-footer__trust span{font-size:.8rem;padding:.5rem .65rem}.storefront-footer__bottom{align-items:flex-start;flex-direction:column;margin-top:1.5rem}.storefront-footer__top{grid-template-columns:1fr}.lc-section-head{align-items:flex-start;flex-direction:column}.lc-grid-products,.lc-grid-categories,.lc-grid-promos,.lc-grid-trust{grid-template-columns:1fr!important}}


        /* Storefront Phase B — Retail UX Foundation */
        .retail-header{z-index:1030;background:rgba(255,255,255,.92);backdrop-filter:blur(18px);box-shadow:0 12px 34px rgba(15,23,42,.08)}
        .retail-topbar{background:linear-gradient(135deg,#111827,color-mix(in srgb,var(--lc-primary) 48%,#111827));color:#fff;font-size:.9rem}
        .retail-topbar__inner{min-height:42px;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}.retail-topbar__message,.retail-topbar__actions,.retail-topbar__actions span{display:flex;align-items:center;gap:.55rem}.retail-topbar__message i,.retail-topbar__actions i{color:#fde68a}.retail-topbar .language-switcher__label{display:none}.retail-topbar .language-switcher__group{padding:.18rem;background:rgba(255,255,255,.1)}.retail-topbar .language-switcher__link{padding:.35rem .65rem;font-size:.8rem}.retail-topbar .language-switcher__code{width:1.55rem;height:1.55rem}.retail-navbar{background:rgba(255,255,255,.92);border-bottom:1px solid color-mix(in srgb,var(--lc-border) 55%, white)}.retail-navbar__grid{display:grid;grid-template-columns:minmax(230px,310px) minmax(280px,1fr) auto;gap:1.1rem;align-items:center;padding:1rem 0}.retail-brand{display:flex;align-items:center;gap:.85rem;color:var(--lc-text)}.retail-brand:hover{color:var(--lc-text)}.retail-brand__logo{width:54px;height:54px;border-radius:18px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff;box-shadow:0 16px 28px color-mix(in srgb,var(--lc-primary) 18%, transparent);flex:0 0 auto}.retail-brand__logo img{width:100%;height:100%;object-fit:cover}.retail-brand__copy{display:grid;gap:.12rem}.retail-brand__copy strong{font-size:1.08rem;font-weight:950;letter-spacing:-.02em}.retail-brand__copy small{font-size:.78rem;color:var(--lc-muted);line-height:1.35;max-width:250px}.retail-search{height:52px;border:1px solid color-mix(in srgb,var(--lc-border) 70%, white);background:#fff;border-radius:18px;align-items:center;gap:.65rem;padding:.35rem .45rem .35rem 1rem;box-shadow:0 16px 38px rgba(15,23,42,.05)}body[dir="rtl"] .retail-search{padding:.35rem 1rem .35rem .45rem}.retail-search i{color:var(--lc-primary-dark);font-size:1.05rem}.retail-search input{border:0;outline:0;box-shadow:none;background:transparent;flex:1;min-width:0;color:var(--lc-text);font-weight:700}.retail-search button{border:0;border-radius:var(--lc-control-radius);background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff;padding:.72rem 1.15rem;font-weight:900}.retail-actions{display:flex;align-items:center;justify-content:flex-end;gap:.55rem}.retail-action,.retail-menu-toggle{position:relative;min-height:46px;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:var(--lc-control-radius);border:1px solid color-mix(in srgb,var(--lc-border) 65%, white);background:#fff;color:var(--lc-text);font-weight:900;padding:.65rem .8rem;box-shadow:0 12px 28px rgba(15,23,42,.05)}.retail-action:hover,.retail-menu-toggle:hover{color:var(--lc-primary-dark);background:color-mix(in srgb,var(--lc-soft) 80%, white)}.retail-action i,.retail-menu-toggle i{font-size:1.15rem}.retail-action em{position:absolute;top:-.45rem;inset-inline-end:-.35rem;min-width:1.35rem;height:1.35rem;padding:0 .25rem;border-radius:999px;background:var(--lc-primary);color:#fff;font-style:normal;font-size:.72rem;font-weight:950;display:flex;align-items:center;justify-content:center;border:2px solid #fff}.retail-action--cart{background:linear-gradient(135deg,var(--lc-dark),color-mix(in srgb,var(--lc-primary) 52%,var(--lc-dark)));color:#fff;border:0;padding-inline:1rem}.retail-action--cart:hover{color:#fff;filter:brightness(1.04)}.retail-menu-toggle{display:none}.retail-nav-collapse{border-top:1px solid color-mix(in srgb,var(--lc-border) 50%, white)}.retail-nav-row{min-height:56px;display:flex;align-items:center;justify-content:space-between;gap:1rem}.retail-category-button{border:0;border-radius:16px;background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff;font-weight:950;padding:.8rem 1rem;display:inline-flex;align-items:center;gap:.65rem;box-shadow:0 14px 30px color-mix(in srgb,var(--lc-primary) 18%, transparent)}.retail-category-button__chevron{font-size:.8rem;opacity:.8}.retail-mega-menu{width:min(720px,calc(100vw - 2rem));border:0;border-radius:24px;padding:1rem;margin-top:.65rem;box-shadow:0 30px 70px rgba(15,23,42,.16);background:rgba(255,255,255,.98);backdrop-filter:blur(20px)}.retail-mega-menu__head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:.4rem .4rem 1rem;border-bottom:1px solid color-mix(in srgb,var(--lc-border) 55%, white);margin-bottom:1rem}.retail-mega-menu__head strong{display:block;font-weight:950}.retail-mega-menu__head span{color:var(--lc-muted);font-size:.9rem}.retail-mega-menu__head a{color:var(--lc-primary-dark);font-weight:900}.retail-mega-menu__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.retail-mega-category{display:flex;align-items:center;gap:.75rem;padding:.75rem;border-radius:18px;color:var(--lc-text);border:1px solid transparent}.retail-mega-category:hover{background:color-mix(in srgb,var(--lc-soft) 80%, white);border-color:color-mix(in srgb,var(--lc-border) 60%, white);color:var(--lc-primary-dark)}.retail-mega-category__icon{width:48px;height:48px;border-radius:16px;background:linear-gradient(135deg,color-mix(in srgb,var(--lc-soft) 80%, white),#fff);display:flex;align-items:center;justify-content:center;overflow:hidden;color:var(--lc-primary-dark);flex:0 0 auto}.retail-mega-category__icon img{width:100%;height:100%;object-fit:cover}.retail-mega-category strong{display:block;font-weight:950}.retail-mega-category small{display:block;color:var(--lc-muted);font-size:.82rem;margin-top:.08rem}.retail-mega-empty{padding:1.25rem;color:var(--lc-muted)}.retail-links{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}.retail-links a,.retail-link-button{border:0;background:transparent;color:var(--lc-text);font-weight:900;padding:.65rem .8rem;border-radius:14px}.retail-links a:hover,.retail-link-button:hover{background:color-mix(in srgb,var(--lc-soft) 80%, white);color:var(--lc-primary-dark)}.retail-search--mobile{margin:0 0 1rem;display:flex}.retail-quick-strip{padding:1rem 0;background:linear-gradient(90deg,#fff,color-mix(in srgb,var(--lc-soft) 62%,#fff),#fff);border-bottom:1px solid color-mix(in srgb,var(--lc-border) 46%, white)}.retail-quick-strip__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}.retail-quick-tile{display:flex;align-items:center;gap:.75rem;padding:1rem;border-radius:20px;background:#fff;border:1px solid color-mix(in srgb,var(--lc-border) 58%, white);box-shadow:0 14px 30px rgba(15,23,42,.05);color:var(--lc-text)}.retail-quick-tile i{width:42px;height:42px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff;font-size:1.15rem}.retail-quick-tile strong{display:block;font-weight:950}.retail-quick-tile span{display:block;color:var(--lc-muted);font-size:.86rem}.retail-section-band{background:linear-gradient(180deg,#fff,color-mix(in srgb,var(--lc-soft) 28%,#fff))}.lc-home-section:nth-of-type(even){background:linear-gradient(180deg,color-mix(in srgb,var(--lc-soft) 34%,#fff),#fff)}.lc-section-title{letter-spacing:-.03em}.lc-section-kicker{text-transform:none;letter-spacing:0;font-size:.9rem}.lc-grid-products,.row.g-4{row-gap:1.45rem!important}.storefront-footer{margin-top:0!important}.storefront-footer__column a{padding:.12rem 0}.storefront-footer__bottom span:last-child{color:rgba(255,255,255,.76)}@media(max-width:1199.98px){.retail-navbar__grid{grid-template-columns:minmax(220px,1fr) auto}.retail-search.d-lg-flex{display:none!important}.retail-menu-toggle{display:inline-flex}.retail-nav-row{align-items:flex-start;flex-direction:column;padding:1rem 0}.retail-links{width:100%;display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.retail-links a,.retail-link-button{text-align:start;background:#fff;border:1px solid color-mix(in srgb,var(--lc-border) 55%, white)}}@media(max-width:991.98px){.retail-topbar__inner{justify-content:center;text-align:center}.retail-brand__copy small{display:none}.retail-mega-menu{width:100%;box-shadow:none;border:1px solid color-mix(in srgb,var(--lc-border) 55%, white)}.retail-quick-strip__grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:575.98px){.retail-navbar__grid{grid-template-columns:1fr auto;gap:.7rem}.retail-brand__logo{width:46px;height:46px}.retail-brand__copy strong{font-size:.95rem}.retail-action span{display:none!important}.retail-mega-menu__grid,.retail-links,.retail-quick-strip__grid{grid-template-columns:1fr}.retail-topbar__message{font-size:.8rem}.retail-topbar__actions{width:100%;justify-content:center}}

        @media (max-width: 991.98px) {
            .lc-progress-strip { grid-template-columns: 1fr; }
            .lc-summary-card-sticky { position: static; }
        }
        @media (max-width: 767.98px) {
            .lc-cart-item { grid-template-columns: 1fr; }
            .lc-cart-item__media img { width: 100%; height: 220px; }
            .lc-cart-item__actions { width: 100%; }
        }
        /* Retail header hotfixes: keep cart readable on hover and restore customer account dropdown. */
        .retail-action--cart,
        .retail-action--cart:hover,
        .retail-action--cart:focus {
            color: #fff !important;
            background: linear-gradient(135deg, var(--lc-dark), color-mix(in srgb, var(--lc-primary) 52%, var(--lc-dark))) !important;
        }
        .retail-account-dropdown { position: relative; }
        .retail-action--account { max-width: 190px; }
        .retail-action--account span { max-width: 115px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .retail-account-menu {
            min-width: 260px;
            border: 0;
            border-radius: 22px;
            padding: .65rem;
            box-shadow: 0 28px 65px rgba(15, 23, 42, .18);
            margin-top: .65rem;
        }
        .retail-account-menu__head {
            padding: .85rem .95rem;
            border-radius: 16px;
            background: color-mix(in srgb, var(--lc-soft) 80%, white);
            margin-bottom: .45rem;
        }
        .retail-account-menu__head strong,
        .retail-account-menu__head small { display: block; }
        .retail-account-menu__head small { color: var(--lc-muted); font-size: .82rem; margin-top: .1rem; word-break: break-all; }
        .retail-account-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: .6rem;
            border-radius: 14px;
            padding: .7rem .85rem;
            font-weight: 800;
        }
        .retail-account-menu .dropdown-item:hover { background: color-mix(in srgb, var(--lc-soft) 82%, white); color: var(--lc-primary-dark); }
        .retail-account-menu__logout { color: #dc2626; width: 100%; text-align: inherit; }

        /* V37.1: hero/quick-entry RTL polish. Keep icons away from text and avoid first-screen clutter. */
        .retail-quick-tile {
            min-width: 0;
            justify-content: space-between;
            overflow: hidden;
        }
        .retail-quick-tile i {
            flex: 0 0 42px;
            order: 2;
        }
        .retail-quick-tile > div {
            min-width: 0;
            flex: 1 1 auto;
            order: 1;
        }
        .retail-quick-tile strong,
        .retail-quick-tile span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        body[dir="ltr"] .retail-quick-tile i { order: 1; }
        body[dir="ltr"] .retail-quick-tile > div { order: 2; }
        @media (max-width: 575.98px) {
            .retail-quick-tile strong,
            .retail-quick-tile span { white-space: normal; }
        }

        /* V37.3: quick entry icons must never cover text in RTL/LTR. */
        .retail-quick-tile {
            display: grid !important;
            grid-template-columns: 48px minmax(0, 1fr);
            align-items: center;
            gap: .9rem !important;
            justify-content: initial !important;
        }
        .retail-quick-tile i {
            position: static !important;
            grid-column: 1;
            grid-row: 1;
            width: 44px !important;
            height: 44px !important;
            order: initial !important;
            flex: 0 0 44px !important;
        }
        .retail-quick-tile > div {
            grid-column: 2;
            grid-row: 1;
            min-width: 0;
            order: initial !important;
        }
        body[dir="rtl"] .retail-quick-tile {
            grid-template-columns: minmax(0, 1fr) 48px;
            text-align: right;
        }
        body[dir="rtl"] .retail-quick-tile i { grid-column: 2; }
        body[dir="rtl"] .retail-quick-tile > div { grid-column: 1; }
        body[dir="ltr"] .retail-quick-tile { text-align: left; }
        @media (max-width: 991.98px) {
            .retail-quick-tile { grid-template-columns: 44px minmax(0, 1fr); }
            body[dir="rtl"] .retail-quick-tile { grid-template-columns: minmax(0, 1fr) 44px; }
        }

        .lc-toast-stack { position:fixed; top:1rem; inset-inline-end:1rem; z-index:1090; display:grid; gap:.65rem; width:min(420px,calc(100vw - 2rem)); pointer-events:none; }
        .lc-toast-stack--live { top:auto; bottom:1rem; }
        .lc-flash-toast { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:.75rem; padding:.85rem 1rem; border:1px solid var(--lc-border); border-radius:1rem; background:var(--lc-surface); box-shadow:var(--lc-shadow-strong); pointer-events:auto; transition:opacity .18s ease, transform .18s ease; }
        .lc-flash-toast--success > i { color:#15803d; } .lc-flash-toast--info > i { color:var(--lc-primary-dark); } .lc-flash-toast--danger > i { color:#dc2626; }
        .lc-flash-toast__close { border:0; background:transparent; color:var(--lc-muted); padding:.25rem; line-height:1; border-radius:.5rem; }
        .lc-flash-toast__close:focus-visible { outline:3px solid var(--lc-primary); outline-offset:2px; }
        .lc-flash-toast.is-leaving { opacity:0; transform:translateY(-8px); }
        @media (max-width:575.98px) { .lc-toast-stack { top:.75rem; inset-inline:.75rem; width:auto; } }

        .retail-action[aria-current="page"] { box-shadow:0 0 0 3px color-mix(in srgb, var(--lc-primary) 24%, transparent); border-color:color-mix(in srgb, var(--lc-primary) 45%, white); }
        .retail-links a[aria-current="page"] { color:var(--lc-primary-dark); background:color-mix(in srgb, var(--lc-soft) 82%, white); }
        .retail-links a:focus-visible, .retail-link-button:focus-visible, .retail-menu-toggle:focus-visible, .retail-category-button:focus-visible, .retail-action:focus-visible { outline:3px solid var(--lc-primary); outline-offset:3px; }

        .lc-account-nav { display:flex; gap:.55rem; overflow-x:auto; padding:.35rem 0 .75rem; scrollbar-width:thin; }
        .lc-account-nav a { flex:none; border:1px solid var(--lc-border); border-radius:999px; padding:.6rem 1rem; background:var(--lc-surface); color:var(--lc-text); font-weight:700; }
        .lc-account-nav a:hover, .lc-account-nav a[aria-current="page"] { background:var(--lc-primary); border-color:var(--lc-primary); color:var(--lc-btn-text); }
        .lc-account-nav a:focus-visible, .lc-account-shortcut:focus-visible, .lc-account-order:focus-visible { outline:3px solid var(--lc-primary); outline-offset:3px; }
        @media (max-width:767.98px) { .lc-account-nav { scroll-snap-type:inline proximity; scroll-padding-inline:.5rem; } .lc-account-nav a { scroll-snap-align:start; } }
        .lc-account-shortcut { display:flex; align-items:center; gap:1rem; padding:1.35rem; color:var(--lc-text); }
        .lc-account-shortcut:hover { color:var(--lc-primary-dark); border-color:var(--lc-primary); }
        .lc-account-shortcut > i:first-child { display:grid; place-items:center; flex:none; width:2.8rem; height:2.8rem; border-radius:.9rem; background:var(--lc-soft); color:var(--lc-primary-dark); font-size:1.2rem; }
        .lc-account-shortcut span { display:grid; gap:.15rem; min-width:0; flex:1; }
        .lc-account-shortcut small, .lc-account-order small { color:var(--lc-muted); }
        .lc-account-order { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:1rem 0; border-top:1px solid var(--lc-border); color:var(--lc-text); }
        .lc-account-order span:first-child { display:grid; gap:.2rem; min-width:0; overflow-wrap:anywhere; }
        @media (max-width:575.98px) { .lc-account-shortcut { padding:1rem; } }

    </style>
    @stack('styles')
</head>
<body>
<header class="retail-header sticky-top">
    <div class="retail-topbar">
        <div class="container retail-topbar__inner">
            <div class="retail-topbar__message">
                <i class="bi bi-lightning-charge-fill"></i>
                <span>{{ __('Current offers, dependable delivery, and trusted support.') }}</span>
            </div>
            <div class="retail-topbar__actions">
                <span class="d-none d-lg-inline-flex"><i class="bi bi-shield-check"></i>{{ __('Secure checkout') }}</span>
                <span class="d-none d-lg-inline-flex"><i class="bi bi-truck"></i>{{ __('Fast delivery') }}</span>
                @include('layouts.inc.language-switcher')
            </div>
        </div>
    </div>

    <nav class="retail-navbar">
        <div class="container">
            <div class="retail-navbar__grid">
                <a class="retail-brand" href="{{ route('frontend.home') }}" aria-label="{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Tag Marketplace' }}">
                    <span class="retail-brand__logo">
                        @if(!empty($customerLogoPath ?? null))
                            <img src="{{ \App\Support\AdminBranding::mediaUrl($customerLogoPath ?? null, 'logo') }}" alt="{{ $storeSettings['store_name'] ?? 'Tag Marketplace' }}">
                        @else
                            <i class="bi bi-lightning-charge-fill"></i>
                        @endif
                    </span>
                    <span class="retail-brand__copy">
                        <strong>{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Tag Marketplace' }}</strong>
                        <small>{{ $localizedStoreTagline }}</small>
                    </span>
                </a>

                <form class="retail-search d-none d-lg-flex" action="{{ route('frontend.search') }}" method="GET" role="search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search products, categories, and offers') }}" aria-label="{{ __('Search products, categories, and offers') }}" data-storefront-search>
                    <button type="submit">{{ __('Search') }}</button>
                </form>

                <div class="retail-actions">
                    @auth
                        <div class="dropdown retail-account-dropdown d-none d-md-inline-flex">
                            <button class="retail-action retail-action--account dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="menu">
                                <i class="bi bi-person-circle"></i>
                                <span>{{ auth()->user()->name ?? __('My account') }}</span>
                            </button>
                            <div class="dropdown-menu retail-account-menu dropdown-menu-end" role="menu">
                                <div class="retail-account-menu__head">
                                    <strong>{{ auth()->user()->name ?? __('My account') }}</strong>
                                    <small>{{ auth()->user()->email ?? '' }}</small>
                                </div>
                                <a class="dropdown-item" role="menuitem" href="{{ route('account.index') }}"><i class="bi bi-person"></i>{{ __('My account') }}</a>
                                <a class="dropdown-item" role="menuitem" href="{{ route('orders.index') }}"><i class="bi bi-receipt"></i>{{ __('My Orders') }}</a>
                                <a class="dropdown-item" role="menuitem" href="{{ route('account.addresses.index') }}"><i class="bi bi-geo-alt"></i>{{ __('Address book') }}</a>
                                <a class="dropdown-item" role="menuitem" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i>{{ __('Notifications') }}</a>
                                @if((int) auth()->user()->role_as === 1)
                                    <a class="dropdown-item" role="menuitem" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i>{{ __('Admin Dashboard') }}</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item retail-account-menu__logout" role="menuitem" type="submit"><i class="bi bi-box-arrow-right"></i>{{ __('Logout') }}</button>
                                </form>
                            </div>
                        </div>
                        <a class="retail-action retail-action--icon" href="{{ route('notifications.index') }}" aria-label="{{ __('Notifications') }}" @if(request()->routeIs('notifications.*')) aria-current="page" @endif>
                            <i class="bi bi-bell"></i>
                            @if($authNotificationCount > 0)<em>{{ $authNotificationCount }}</em>@endif
                        </a>
                    @else
                        <a class="retail-action d-none d-md-inline-flex" href="{{ route('login') }}">
                            <i class="bi bi-person"></i><span>{{ __('Login') }}</span>
                        </a>
                    @endauth
                    <a class="retail-action retail-action--cart" href="{{ route('cart.index') }}" @if(request()->routeIs('cart.*')) aria-current="page" @endif>
                        <i class="bi bi-bag"></i><span class="d-none d-sm-inline">{{ __('Cart') }}</span><em data-layout-cart-count>{{ $layoutCartCount }}</em>
                    </a>
                    <button class="retail-menu-toggle d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#retailNav" aria-controls="retailNav" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
            </div>

            <div class="collapse retail-nav-collapse" id="retailNav">
                <div class="retail-nav-row">
                    <div class="dropdown retail-mega-dropdown">
                        <button class="retail-category-button" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="menu">
                            <i class="bi bi-grid-3x3-gap-fill"></i>
                            <span>{{ __('All categories') }}</span>
                            <i class="bi bi-chevron-down retail-category-button__chevron"></i>
                        </button>
                        <div class="dropdown-menu retail-mega-menu" role="menu">
                            <div class="retail-mega-menu__head">
                                <div>
                                    <strong>{{ __('Shop by department') }}</strong>
                                    <span>{{ __('Choose a category and start browsing faster.') }}</span>
                                </div>
                                <a href="#categories" role="menuitem">{{ __('View all') }} <i class="bi bi-arrow-up-right"></i></a>
                            </div>
                            <div class="retail-mega-menu__grid">
                                @forelse($layoutCategories as $category)
                                    <a class="retail-mega-category" role="menuitem" href="{{ route('category.products', $category->id) }}">
                                        <span class="retail-mega-category__icon">
                                            @if($category->image_url)
                                                <img src="{{ $category->image_url }}" alt="{{ $category->name }}">
                                            @else
                                                <i class="bi bi-grid"></i>
                                            @endif
                                        </span>
                                        <span>
                                            <strong>{{ $category->name }}</strong>
                                            <small>{{ __('Browse products') }}</small>
                                        </span>
                                    </a>
                                @empty
                                    <div class="retail-mega-empty">{{ __('No categories yet') }}</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="retail-links">
                        <a href="{{ route('frontend.home') }}" @if(request()->routeIs('frontend.home')) aria-current="page" @endif>{{ __('Home') }}</a>
                        <a href="#on-sale-products">{{ __('Offers') }}</a>
                        <a href="#best-sellers">{{ __('Best sellers') }}</a>
                        <a href="#latest-products">{{ __('New arrivals') }}</a>
                        <a href="{{ route('frontend.contact') }}" @if(request()->routeIs('frontend.contact')) aria-current="page" @endif>{{ __('Contact') }}</a>
                        @auth
                            <a class="d-md-none" href="{{ route('account.index') }}" @if(request()->routeIs('account.*')) aria-current="page" @endif><i class="bi bi-person me-1"></i>{{ __('My account') }}</a>
                            <a class="d-md-none" href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif><i class="bi bi-receipt me-1"></i>{{ __('My Orders') }}</a>
                            <a class="d-md-none" href="{{ route('account.addresses.index') }}" @if(request()->routeIs('account.addresses.*')) aria-current="page" @endif><i class="bi bi-geo-alt me-1"></i>{{ __('Address book') }}</a>
                            <a class="d-md-none" href="{{ route('notifications.index') }}" @if(request()->routeIs('notifications.*')) aria-current="page" @endif><i class="bi bi-bell me-1"></i>{{ __('Notifications') }}</a>
                            @if((int) auth()->user()->role_as === 1)
                                <a href="{{ route('admin.dashboard') }}">{{ __('Admin Dashboard') }}</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button class="retail-link-button" type="submit">{{ __('Logout') }}</button>
                            </form>
                        @else
                            <a class="d-md-none" href="{{ route('login') }}">{{ __('Login') }}</a>
                            <a href="{{ route('register') }}">{{ __('Create account') }}</a>
                        @endauth
                    </div>
                </div>

                <form class="retail-search retail-search--mobile d-lg-none" action="{{ route('frontend.search') }}" method="GET" role="search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search products') }}" aria-label="{{ __('Search products') }}" data-storefront-search>
                    <button type="submit">{{ __('Search') }}</button>
                </form>
            </div>
        </div>
    </nav>
</header>

@if (session('success') || session('message') || session('status'))
    <div class="lc-toast-stack" id="storefrontToastStack" aria-live="polite" aria-atomic="true">
        @foreach (['success' => 'success', 'message' => 'success', 'status' => 'info'] as $flashKey => $flashTone)
            @if (session($flashKey))
                <div class="lc-flash-toast lc-flash-toast--{{ $flashTone }}" role="status" data-storefront-toast>
                    <i class="bi {{ $flashTone === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill' }}" aria-hidden="true"></i>
                    <span>{{ session($flashKey) }}</span>
                    <button type="button" class="lc-flash-toast__close" data-storefront-toast-close aria-label="{{ __('Dismiss notification') }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
            @endif
        @endforeach
    </div>
@endif
@if ($errors->any())
    <div class="container mt-4">
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-0" role="alert">
            <div class="fw-bold mb-2">{{ __('Please review the highlighted fields.') }}</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const stack = document.getElementById('storefrontToastStack');
    if (stack) {
        const dismiss = (toast) => {
            if (!toast || toast.classList.contains('is-leaving')) return;
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 180);
        };
        stack.addEventListener('click', (event) => {
            const close = event.target.closest('[data-storefront-toast-close]');
            if (close) dismiss(close.closest('[data-storefront-toast]'));
        });
        stack.querySelectorAll('[data-storefront-toast]').forEach((toast) => {
            window.setTimeout(() => dismiss(toast), 4200);
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
        const target = event.target;
        if (target && (target.matches('input, textarea, select') || target.isContentEditable)) return;
        document.querySelectorAll('.lc-account-nav [aria-current="page"]').forEach((current) => {
            if (!window.matchMedia('(max-width: 767.98px)').matches) return;
            window.requestAnimationFrame(() => current.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' }));
        });

        const searches = Array.from(document.querySelectorAll('[data-storefront-search]'));
        const search = searches.find((input) => input.offsetParent !== null);
        if (!search) return;
        event.preventDefault();
        search.focus();
        search.select();
    });

    document.querySelectorAll('.retail-account-dropdown, .retail-mega-dropdown').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-bs-toggle="dropdown"]');
        const menu = dropdown.querySelector('[role="menu"]');
        if (!trigger || !menu) return;

        dropdown.addEventListener('shown.bs.dropdown', () => {
            const firstItem = menu.querySelector('[role="menuitem"]:not([disabled])');
            if (firstItem) firstItem.focus();
        });

        menu.addEventListener('keydown', (event) => {
            const items = Array.from(menu.querySelectorAll('[role="menuitem"]:not([disabled])'));
            if (!items.length) return;
            const currentIndex = items.indexOf(document.activeElement);

            if (event.key === 'Escape') {
                event.preventDefault();
                bootstrap.Dropdown.getOrCreateInstance(trigger).hide();
                trigger.focus();
                return;
            }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            const nextIndex = currentIndex < 0 ? 0 : (currentIndex + step + items.length) % items.length;
            items[nextIndex].focus();
        });
    });

    const retailNav = document.getElementById('retailNav');
    if (retailNav) {
        retailNav.addEventListener('shown.bs.collapse', () => {
            const firstAction = retailNav.querySelector('a, button, input');
            if (firstAction && window.matchMedia('(max-width: 991.98px)').matches) firstAction.focus();
        });
        retailNav.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !retailNav.classList.contains('show')) return;
            const toggle = document.querySelector('[data-bs-target="#retailNav"]');
            bootstrap.Collapse.getOrCreateInstance(retailNav).hide();
            if (toggle) toggle.focus();
        });
    }
});
</script>

<div class="lc-toast-stack lc-toast-stack--live" aria-live="polite" aria-atomic="true">
    <div class="lc-flash-toast lc-flash-toast--success d-none"
         role="status"
         data-live-cart-feedback
         data-error="{{ __('Could not update the cart right now.') }}">
        <i class="bi bi-check-circle-fill" aria-hidden="true" data-live-cart-feedback-icon></i>
        <div class="lc-flash-toast__content" data-live-cart-feedback-message></div>
        <button type="button" class="lc-flash-toast__close" aria-label="{{ __('Dismiss notification') }}" data-live-cart-feedback-close>
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
</div>

<main>
    @yield('hero')
    @yield('content')
</main>

<footer class="lc-footer storefront-footer mt-5 py-5">
    <div class="container">
        <div class="storefront-footer__top">
            <div class="storefront-footer__brand">
                <h5 class="fw-bold mb-2">{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Tag Marketplace' }}</h5>
                <p class="mb-3">{{ $localizedFooterAbout }}</p>
                @if(($storeSettings['footer_show_trust'] ?? '1') === '1')
                <div class="storefront-footer__trust">
                    @if(filled($storeSettings['footer_trust_1_text'] ?? null))<span><i class="bi bi-shield-check"></i>{{ $storeSettings['footer_trust_1_text'] }}</span>@endif
                    @if(filled($storeSettings['footer_trust_2_text'] ?? null))<span><i class="bi bi-stars"></i>{{ $storeSettings['footer_trust_2_text'] }}</span>@endif
                    @if(filled($storeSettings['footer_trust_3_text'] ?? null))<span><i class="bi bi-card-checklist"></i>{{ $storeSettings['footer_trust_3_text'] }}</span>@endif
                </div>
                @endif
                @php($footerSocialChannels = [
                    ['key' => 'store_social_facebook', 'icon' => 'bi-facebook', 'label' => 'Facebook'],
                    ['key' => 'store_social_instagram', 'icon' => 'bi-instagram', 'label' => 'Instagram'],
                    ['key' => 'store_social_tiktok', 'icon' => 'bi-tiktok', 'label' => 'TikTok'],
                    ['key' => 'store_social_youtube', 'icon' => 'bi-youtube', 'label' => 'YouTube'],
                    ['key' => 'store_social_linkedin', 'icon' => 'bi-linkedin', 'label' => 'LinkedIn'],
                ])
                @if(($storeSettings['footer_show_social'] ?? '1') === '1' && collect($footerSocialChannels)->contains(fn ($channel) => filled($storeSettings[$channel['key']] ?? null)))
                    <div class="storefront-footer__social" aria-label="{{ __('Social channels') }}">
                        @foreach($footerSocialChannels as $channel)
                            @if(filled($storeSettings[$channel['key']] ?? null))
                                <a href="{{ $storeSettings[$channel['key']] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $channel['label'] }}"><i class="bi {{ $channel['icon'] }}" aria-hidden="true"></i></a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            @if(($storeSettings['footer_show_shop'] ?? '1') === '1')
            <div class="storefront-footer__column">
                <h6>{{ __('Shop') }}</h6>
                <a href="{{ route('frontend.home') }}" @if(request()->routeIs('frontend.home')) aria-current="page" @endif>{{ __('Home') }}</a>
                <a href="{{ route('frontend.contact') }}" @if(request()->routeIs('frontend.contact')) aria-current="page" @endif>{{ __('Contact') }}</a>
                @auth
                    <a href="{{ route('account.index') }}" @if(request()->routeIs('account.*')) aria-current="page" @endif>{{ __('My account') }}</a>
                    <a href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif>{{ __('My Orders') }}</a>
                    <a href="{{ route('checkout.index') }}" @if(request()->routeIs('checkout.*')) aria-current="page" @endif>{{ __('Checkout') }}</a>
                @else
                    <a href="{{ route('login') }}">{{ __('Login') }}</a>
                @endauth
            </div>
            @endif

            @if(($storeSettings['footer_show_policies'] ?? '1') === '1')
            <div class="storefront-footer__column">
                <h6>{{ __('Policies') }}</h6>
                <a href="{{ route('frontend.privacy') }}" @if(request()->routeIs('frontend.privacy')) aria-current="page" @endif>{{ __('Privacy Policy') }}</a>
                <a href="{{ route('frontend.terms') }}" @if(request()->routeIs('frontend.terms')) aria-current="page" @endif>{{ __('Terms & Conditions') }}</a>
                <a href="{{ route('frontend.refund') }}" @if(request()->routeIs('frontend.refund')) aria-current="page" @endif>{{ __('Refund Policy') }}</a>
                <a href="{{ route('frontend.shipping') }}" @if(request()->routeIs('frontend.shipping')) aria-current="page" @endif>{{ __('Shipping Policy') }}</a>
            </div>
            @endif

            @if(($storeSettings['footer_show_categories'] ?? '1') === '1')
            <div class="storefront-footer__column">
                <h6>{{ __('Categories') }}</h6>
                @forelse($layoutCategories->take(6) as $category)
                    <a href="{{ route('category.products', $category->id) }}">{{ $category->name }}</a>
                @empty
                    <span class="text-white-50 small">{{ __('No categories yet') }}</span>
                @endforelse
            </div>
            @endif

            @if(($storeSettings['footer_show_support'] ?? '1') === '1' && (!empty($storeSettings['store_support_email']) || !empty($storeSettings['store_support_phone']) || !empty($storeSettings['store_contact_address']) || (($storeSettings['footer_show_whatsapp'] ?? '1') === '1' && !empty($storeSettings['store_support_whatsapp'])) || (($storeSettings['footer_show_website'] ?? '1') === '1' && !empty($storeSettings['store_business_website']))))
            <div class="storefront-footer__column storefront-footer__contact">
                <h6>{{ __('Support') }}</h6>
                @if(!empty($storeSettings['store_support_email']))<a href="mailto:{{ $storeSettings['store_support_email'] }}"><i class="bi bi-envelope"></i><span>{{ $storeSettings['store_support_email'] }}</span></a>@endif
                @if(!empty($storeSettings['store_support_phone']))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $storeSettings['store_support_phone']) }}"><i class="bi bi-telephone"></i><span>{{ $storeSettings['store_support_phone'] }}</span></a>@endif
                @if(($storeSettings['footer_show_whatsapp'] ?? '1') === '1' && !empty($storeSettings['store_support_whatsapp']))<a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $storeSettings['store_support_whatsapp']) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp"></i><span>{{ __('WhatsApp') }}</span></a>@endif
                @if(($storeSettings['footer_show_website'] ?? '1') === '1' && !empty($storeSettings['store_business_website']))<a href="{{ $storeSettings['store_business_website'] }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-globe2"></i><span>{{ __('Website') }}</span></a>@endif
                @if(!empty($storeSettings['store_contact_address']))<span><i class="bi bi-geo-alt"></i>{{ $storeSettings['store_contact_address'] }}</span>@endif
            </div>
            @endif
        </div>
        <div class="storefront-footer__bottom">
            <span>© {{ now()->year }} {{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Tag Marketplace' }}. {{ $localizedFooterCopyright }}</span>
            @if(($storeSettings['footer_show_experience_note'] ?? '1') === '1')
            <span>{{ __('Built for a clear and dependable shopping experience.') }}</span>
            @endif
        </div>
    </div>
</footer>

<div class="modal fade" id="storefrontConfirmModal" tabindex="-1" aria-labelledby="storefrontConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-body p-4 p-lg-5">
                <div class="d-flex align-items-start gap-3 mb-4">
                    <span class="lc-section-empty__icon flex-shrink-0 mb-0" style="width:58px;height:58px;font-size:1.35rem;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </span>
                    <div>
                        <h3 class="h5 fw-bold mb-2" id="storefrontConfirmTitle">{{ __('Confirm action') }}</h3>
                        <p class="mb-1" id="storefrontConfirmMessage">{{ __('Are you sure you want to continue?') }}</p>
                        <p class="text-muted small mb-0" id="storefrontConfirmSubtitle"></p>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                    <button type="button" class="btn lc-btn-soft" data-bs-dismiss="modal" id="storefrontConfirmCancel">{{ __('Cancel') }}</button>
                    <button type="button" class="btn lc-btn-danger-soft" id="storefrontConfirmOk">{{ __('Confirm') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="{{ asset('js/storefront-cart-actions.js') }}"></script>
@livewireScripts
@stack('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const confirmModalElement = document.getElementById('storefrontConfirmModal');
  const confirmModal = confirmModalElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(confirmModalElement) : null;
  const confirmTitle = document.getElementById('storefrontConfirmTitle');
  const confirmMessage = document.getElementById('storefrontConfirmMessage');
  const confirmSubtitle = document.getElementById('storefrontConfirmSubtitle');
  const confirmOk = document.getElementById('storefrontConfirmOk');
  const confirmCancel = document.getElementById('storefrontConfirmCancel');
  let pendingConfirm = null;

  document.querySelectorAll('form[data-confirm-message]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;
        return;
      }

      if (!confirmModal) return;

      event.preventDefault();
      event.stopImmediatePropagation();
      pendingConfirm = { form: form, submitter: event.submitter || null };

      confirmTitle.textContent = form.dataset.confirmTitle || @json(__('Confirm action'));
      confirmMessage.textContent = form.dataset.confirmMessage || @json(__('Are you sure you want to continue?'));
      confirmSubtitle.textContent = form.dataset.confirmSubtitle || '';
      confirmOk.textContent = form.dataset.confirmOk || @json(__('Confirm'));
      confirmCancel.textContent = form.dataset.confirmCancel || @json(__('Cancel'));
      confirmModal.show();
    }, true);
  });

  confirmOk?.addEventListener('click', function () {
    if (!pendingConfirm) return;

    const target = pendingConfirm;
    pendingConfirm = null;
    target.form.dataset.confirmed = '1';
    confirmModal?.hide();

    if (target.submitter && typeof target.form.requestSubmit === 'function') {
      target.form.requestSubmit(target.submitter);
    } else {
      target.form.submit();
    }
  });

  confirmModalElement?.addEventListener('hidden.bs.modal', function () {
    pendingConfirm = null;
  });

  document.querySelectorAll('form[data-submit-loading]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      const button = event.submitter || form.querySelector('[data-loading-text]');
      if (!button) return;
      button.dataset.originalText = button.innerHTML;
      button.innerHTML = '<span class="lc-loading-spinner"></span>' + button.getAttribute('data-loading-text');
      button.disabled = true;
      form.classList.add('lc-loading');
    });
  });

  const syncPaymentCards = function () {
    document.querySelectorAll('[data-payment-card]').forEach(function (card) {
      const cardInput = card.querySelector('input[type="radio"]');
      card.classList.toggle('is-active', !!cardInput && cardInput.checked);
    });
  };

  document.querySelectorAll('[data-payment-card] input[type="radio"]').forEach(function (input) {
    input.addEventListener('change', syncPaymentCards);
  });

  syncPaymentCards();
});
</script>
</body>
</html>
