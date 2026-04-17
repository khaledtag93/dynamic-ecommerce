<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront')</title>
    <meta name="description" content="@yield('meta_description', __('Modern multilingual online store'))">
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
    <style>
        :root {
            --lc-primary: {{ $storeSettings['brand_primary_color'] ?? '#f97316' }};
            --lc-primary-dark: {{ $storeSettings['brand_accent_color'] ?? '#ea580c' }};
            --lc-secondary: {{ $storeSettings['brand_secondary_color'] ?? '#ec4899' }};
            --lc-soft: {{ $storeSettings['brand_soft_color'] ?? '#fff7ed' }};
            --lc-text: #1f2937;
            --lc-muted: #6b7280;
            --lc-border: {{ $storeSettings['brand_border_color'] ?? '#fed7aa' }};
            --lc-bg: {{ $storeSettings['brand_background_color'] ?? '#fffaf5' }};
            --lc-dark: color-mix(in srgb, var(--lc-secondary) 38%, #0f172a);
            --lc-surface: {{ $storeSettings['brand_surface_color'] ?? '#ffffff' }};
            --lc-btn-text: {{ $storeSettings['brand_button_text_color'] ?? '#ffffff' }};
            --lc-card-radius: {{ (int) ($storeSettings['customer_card_radius'] ?? 20) }}px;
            --lc-shadow-color: color-mix(in srgb, var(--lc-primary) 16%, transparent);
            --lc-shadow-soft: 0 10px 30px var(--lc-shadow-color);
            --lc-shadow-card: 0 18px 50px color-mix(in srgb, var(--lc-dark) 10%, transparent);
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
            border-radius: 999px;
            background: var(--lc-surface);
            border: 1px solid var(--lc-border);
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
        .lc-product-card:hover { transform: translateY(-4px); box-shadow: 0 18px 50px color-mix(in srgb, var(--lc-dark) 15%, transparent); border-color: color-mix(in srgb, var(--lc-primary) 24%, white); }
        .lc-product-thumb-wrap { position: relative; display: block; }
        .lc-product-thumb { aspect-ratio: 1/1; object-fit: cover; border-radius: 1rem; background: color-mix(in srgb, var(--lc-soft) 82%, white); }
        .lc-product-badge {
            position: absolute;
            top: .85rem;
            inset-inline-start: .85rem;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .45rem .7rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--lc-primary), var(--lc-secondary));
            box-shadow: 0 12px 24px color-mix(in srgb, var(--lc-primary) 22%, transparent);
        }
        .lc-product-meta { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .85rem; }
        .lc-product-meta__pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .78rem;
            font-weight: 800;
            border-radius: 999px;
            padding: .4rem .65rem;
            background: color-mix(in srgb, var(--lc-soft) 78%, white);
            color: var(--lc-primary-dark);
            border: 1px solid color-mix(in srgb, var(--lc-border) 78%, white);
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
            color: var(--lc-btn-text); border: none; border-radius: .95rem; padding: .8rem 1.1rem; font-weight: 700;
            box-shadow: 0 14px 30px color-mix(in srgb, var(--lc-primary) 18%, transparent);
        }
        .lc-btn-primary:hover { color: #fff; opacity: .95; box-shadow: 0 18px 36px color-mix(in srgb, var(--lc-primary) 24%, transparent); }
        .lc-btn-soft {
            background: var(--lc-soft); color: var(--lc-primary-dark); border: 1px solid var(--lc-border);
            border-radius: .95rem; padding: .8rem 1.1rem; font-weight: 700; box-shadow: 0 12px 26px color-mix(in srgb, var(--lc-primary) 10%, transparent);
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
        .lc-form-control, .lc-form-select { border-radius: .95rem; border: 1px solid color-mix(in srgb, var(--lc-border) 75%, white); padding: .85rem 1rem; background: var(--lc-surface); }
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
        @media (max-width: 991.98px) {
            .lc-progress-strip { grid-template-columns: 1fr; }
            .lc-summary-card-sticky { position: static; }
        }
        @media (max-width: 767.98px) {
            .lc-cart-item { grid-template-columns: 1fr; }
            .lc-cart-item__media img { width: 100%; height: 220px; }
            .lc-cart-item__actions { width: 100%; }
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="lc-topbar py-2">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 small">
            <span class="lc-badge">{{ $storeSettings['project_name'] ?? 'Tag Marketplace' }}</span>
            <span class="d-none d-md-inline">{{ $storeSettings['store_tagline'] ?? __('A clean, scalable storefront built to look production-ready from day one.') }}</span>
        </div>
        @include('layouts.inc.language-switcher')
    </div>
</div>

<nav class="navbar navbar-expand-lg sticky-top lc-navbar">
    <div class="container py-2">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('frontend.home') }}">
            @php($customerLogoPath = \App\Support\AdminBranding::resolveMediaPath($storeSettings['logo_path'] ?? $storeSettings['logo'] ?? null, 'logo'))
            @if($customerLogoPath)
                <span class="d-inline-flex justify-content-center align-items-center rounded-circle overflow-hidden bg-white border" style="width:42px;height:42px;">
                    <img src="{{ \App\Support\AdminBranding::mediaUrl($customerLogoPath, 'logo') }}" alt="{{ $storeSettings['store_name'] ?? 'Storefront' }}" style="width:100%;height:100%;object-fit:cover;">
                </span>
            @else
                <span class="d-inline-flex justify-content-center align-items-center rounded-circle text-white" style="width:42px;height:42px;background:linear-gradient(135deg,var(--lc-primary),var(--lc-primary-dark));">
                    <i class="bi bi-shop"></i>
                </span>
            @endif
            <span>{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront' }}</span>

        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav {{ ($isRtl ?? false) ? 'me-auto' : 'ms-auto' }} align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="{{ route('frontend.home') }}">{{ __('Home') }}</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">{{ __('Categories') }}</a>
                    <ul class="dropdown-menu border-0 shadow-lg rounded-4 p-2 {{ ($isRtl ?? false) ? '' : '' }}">
                        @forelse($layoutCategories as $category)
                            <li><a class="dropdown-item rounded-3 py-2" href="{{ route('category.products', $category->id) }}">{{ $category->name }}</a></li>
                        @empty
                            <li><span class="dropdown-item text-muted">{{ __('No categories yet') }}</span></li>
                        @endforelse
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="{{ route('frontend.contact') }}">{{ __('Contact') }}</a></li>
                @auth
                    <li class="nav-item"><a class="nav-link" href="{{ route('orders.index') }}">{{ __('My Orders') }}</a></li>
                    <li class="nav-item"><a class="nav-link position-relative" href="{{ route('notifications.index') }}"><i class="bi bi-bell fs-5"></i>@if($authNotificationCount > 0)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $authNotificationCount }}</span>@endif</a></li>
                @endauth
                <li class="nav-item">
                    <a class="nav-link position-relative" href="{{ route('cart.index') }}">
                        <i class="bi bi-bag fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-warning">{{ $layoutCartCount }}</span>
                    </a>
                </li>
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">{{ auth()->user()->name }}</a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2">
                            @if((int) auth()->user()->role_as === 1)
                                <li><a class="dropdown-item rounded-3" href="{{ route('admin.dashboard') }}">{{ __('Admin Dashboard') }}</a></li>
                            @endif
                            <li><a class="dropdown-item rounded-3" href="{{ route('orders.index') }}">{{ __('My Orders') }}</a></li>
                            <li><a class="dropdown-item rounded-3" href="{{ route('notifications.index') }}">{{ __('Notifications') }}</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item rounded-3 text-danger" type="submit">{{ __('Logout') }}</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a></li>
                    <li class="nav-item"><a class="btn lc-btn-primary" href="{{ route('register') }}">{{ __('Create account') }}</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

@if (session('success') || session('message') || session('status') || $errors->any())
    <div class="container mt-4">
        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4">{{ session('success') }}</div>
        @endif
        @if (session('message'))
            <div class="alert alert-success border-0 shadow-sm rounded-4">{{ session('message') }}</div>
        @endif
        @if (session('status'))
            <div class="alert alert-info border-0 shadow-sm rounded-4">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-0">
                <div class="fw-bold mb-2">{{ __('Please review the highlighted fields.') }}</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif

<main>
    @yield('hero')
    @yield('content')
</main>

<footer class="lc-footer mt-5 py-5">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-4">
                <div class="lc-footer-card">
                    <h5 class="fw-bold mb-2">{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront' }}</h5>
                    <div class="small text-uppercase text-muted fw-semibold mb-2">{{ $storeSettings['store_name'] ?? 'Storefront' }}</div>
                    <p class="text-muted mb-3">{{ $storeSettings['footer_about'] ?? __('A clean, scalable storefront built to look production-ready from day one.') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="lc-badge"><i class="bi bi-shield-check"></i> {{ __('Trusted checkout') }}</span>
                        <span class="lc-badge"><i class="bi bi-box-seam"></i> {{ __('Live catalog') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="lc-footer-card">
                <h6 class="fw-bold mb-3">{{ __('Quick Links') }}</h6>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('frontend.home') }}">{{ __('Home') }}</a>
                    <a href="{{ route('frontend.contact') }}">{{ __('Contact') }}</a>
                    <a href="{{ route('frontend.privacy') }}">{{ __('Privacy Policy') }}</a>
                    <a href="{{ route('frontend.terms') }}">{{ __('Terms & Conditions') }}</a>
                    <a href="{{ route('frontend.refund') }}">{{ __('Refund Policy') }}</a>
                    <a href="{{ route('frontend.shipping') }}">{{ __('Shipping Policy') }}</a>
                    @auth
                        <a href="{{ route('checkout.index') }}">{{ __('Checkout') }}</a>
                        <a href="{{ route('orders.index') }}">{{ __('My Orders') }}</a>
                    @else
                        <a href="{{ route('login') }}">{{ __('Login') }}</a>
                    @endauth
                </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="lc-footer-card">
                <h6 class="fw-bold mb-3">{{ __('Categories') }}</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($layoutCategories as $category)
                        <a href="{{ route('category.products', $category->id) }}" class="lc-badge">{{ $category->name }}</a>
                    @endforeach
                </div>
                @if(!empty($storeSettings['store_support_email']) || !empty($storeSettings['store_support_phone']) || !empty($storeSettings['store_contact_address']))
                    <div class="text-muted small d-flex flex-column gap-1">
                        @if(!empty($storeSettings['store_support_email']))<span>{{ __('Email') }}: {{ $storeSettings['store_support_email'] }}</span>@endif
                        @if(!empty($storeSettings['store_support_phone']))<span>{{ __('Phone') }}: {{ $storeSettings['store_support_phone'] }}</span>@endif
                        @if(!empty($storeSettings['store_contact_address']))<span>{{ __('Address') }}: {{ $storeSettings['store_contact_address'] }}</span>@endif
                    </div>
                @endif
                </div>
            </div>
        </div>
        <div class="border-top mt-4 pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2 small text-muted">
            <span>© {{ now()->year }} {{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront' }}. {{ $storeSettings['footer_copyright'] ?? __('All rights reserved.') }}</span>
            <span>{{ $storeSettings['store_support_whatsapp'] ?? $storeSettings['store_support_phone'] ?? '' }}</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@livewireScripts
@stack('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
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
