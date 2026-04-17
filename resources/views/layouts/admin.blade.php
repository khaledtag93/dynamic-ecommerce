<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Laravel') . ' Admin')</title>

    <link rel="stylesheet" href="{{ asset('admin/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/css/style.css') }}">
    <link rel="shortcut icon" href="{{ asset('admin/images/favicon.ico') }}" />

    <style>
        :root {
            --admin-bg: {{ $storeSettings['brand_background_color'] ?? '#fff8f1' }};
            --admin-surface: {{ $storeSettings['admin_surface_color'] ?? '#ffffff' }};
            --admin-surface-alt: {{ $storeSettings['admin_header_color'] ?? '#fff2e7' }};
            --admin-border: {{ $storeSettings['admin_card_border_color'] ?? '#f2dac8' }};
            --admin-text: color-mix(in srgb, var(--admin-sidebar) 18%, #2a1b14);
            --admin-muted: color-mix(in srgb, var(--admin-sidebar) 12%, #7b6354);
            --admin-primary: {{ $storeSettings['brand_primary_color'] ?? '#f97316' }};
            --admin-primary-dark: {{ $storeSettings['brand_accent_color'] ?? '#d35400' }};
            --admin-accent: {{ $storeSettings['brand_secondary_color'] ?? '#ec4899' }};
            --admin-accent-soft: {{ $storeSettings['admin_accent_soft_color'] ?? '#fff1f2' }};
            --admin-primary-soft: {{ $storeSettings['admin_primary_soft_color'] ?? '#ffedd5' }};
            --admin-sidebar: {{ $storeSettings['admin_sidebar_color'] ?? '#0f172a' }};
            --admin-sidebar-2: {{ $storeSettings['admin_sidebar_color'] ?? '#111827' }};
            --admin-success-bg: color-mix(in srgb, var(--admin-primary) 12%, white);
            --admin-success-text: color-mix(in srgb, var(--admin-sidebar) 32%, var(--admin-primary));
            --admin-warning-bg: color-mix(in srgb, var(--admin-primary-soft) 72%, white);
            --admin-warning-text: color-mix(in srgb, var(--admin-primary-dark) 82%, #4b2e14);
            --admin-danger-bg: color-mix(in srgb, var(--admin-accent) 14%, white);
            --admin-danger-text: color-mix(in srgb, var(--admin-accent) 64%, #7f1d1d);
            --admin-shadow-color: color-mix(in srgb, var(--admin-primary) 16%, transparent);
            --admin-shadow-color-strong: color-mix(in srgb, var(--admin-primary) 22%, transparent);
            --admin-shadow-soft: 0 18px 40px var(--admin-shadow-color);
            --admin-input-bg: color-mix(in srgb, var(--admin-surface) 92%, white);
            --admin-table-head-bg: {{ $storeSettings['brand_table_head_color'] ?? $storeSettings['admin_header_color'] ?? '#fff2e7' }};
            --admin-row-hover-bg: {{ $storeSettings['brand_row_hover_color'] ?? '#fffaf6' }};
            --admin-shadow: var(--admin-shadow-soft);
        }

        body {
            background: linear-gradient(180deg, color-mix(in srgb, var(--admin-bg) 88%, white) 0%, var(--admin-surface) 100%);
            color: var(--admin-text);
        }

        html {
            scroll-behavior: smooth;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        img,
        svg,
        video,
        canvas,
        iframe {
            max-width: 100%;
        }

        body,
        .wrapper,
        .container-scroller,
        .page-body-wrapper,
        .main-panel,
        .content-wrapper,
        .content-wrapper > *,
        .row,
        .row > [class*='col-'],
        .card,
        .card-body,
        .card-header,
        .card-footer,
        .admin-card,
        .admin-card-body,
        .admin-page-header,
        .admin-page-actions,
        form,
        fieldset,
        .tab-content,
        .tab-pane {
            min-width: 0;
        }

        body[dir='rtl'] { text-align: right; }
        body[dir='rtl'] .sidebar-icon-only .custom-sidebar .nav .nav-item .nav-link,
        body[dir='rtl'] .custom-sidebar .nav .nav-item .nav-link,
        body[dir='rtl'] .admin-page-header,
        body[dir='rtl'] .card-header,
        body[dir='rtl'] .dropdown-menu { text-align: right; }

        .container-scroller {
            background: transparent;
        }

        .content-wrapper {
            background:
                radial-gradient(circle at top right, color-mix(in srgb, var(--admin-accent) 12%, transparent), transparent 24%),
                radial-gradient(circle at top left, color-mix(in srgb, var(--admin-primary) 12%, transparent), transparent 28%),
                color-mix(in srgb, var(--admin-bg) 88%, white);
            min-height: calc(100vh - 70px);
            padding: 1.5rem;
        }


        .content-wrapper > .row,
        .content-wrapper > .admin-page-shell,
        .content-wrapper > .product-admin-page,
        .content-wrapper > .gx-shell,
        .content-wrapper > .analytics-shell,
        .content-wrapper > .phase4-page,
        .content-wrapper > .notification-center-page,
        .content-wrapper > .whatsapp-page {
            width: 100%;
        }

        .main-panel {
            transition: all .3s ease;
        }

        /* =========================
           TOPBAR
        ========================= */
        .admin-topbar,
        .navbar .navbar-menu-wrapper,
        .navbar .navbar-brand-wrapper {
            background: linear-gradient(100deg, color-mix(in srgb, var(--admin-surface-alt) 92%, white) 0%, var(--admin-surface-alt) 35%, color-mix(in srgb, var(--admin-surface-alt) 78%, var(--admin-accent-soft)) 100%) !important;
            border-bottom: 1px solid color-mix(in srgb, var(--admin-border) 94%, transparent);
            backdrop-filter: blur(14px);
        }

        .admin-brand {
            display: inline-flex;
            align-items: center;
            gap: .9rem;
            text-decoration: none;
        }

        .admin-brand:hover {
            text-decoration: none;
        }

        .admin-brand-mark {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent));
            color: #fff;
            box-shadow: 0 10px 24px var(--admin-shadow-color-strong);
            font-size: 1.25rem;
        }

        .admin-brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
            color: var(--admin-text);
        }

        .admin-brand-text strong {
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: .01em;
        }

        .admin-brand-text small {
            color: var(--admin-muted);
            font-size: .78rem;
            font-weight: 700;
        }

        .admin-brand-mini {
            display: none;
            text-decoration: none;
        }

        .admin-topbar-search {
            width: min(460px, 100%);
            border: 1px solid var(--admin-border);
            border-radius: 999px;
            background: color-mix(in srgb, var(--admin-surface) 88%, white);
            padding: .45rem .9rem;
            gap: .55rem;
            color: var(--admin-muted);
        }

        .admin-topbar-search input {
            min-height: auto;
        }

        .admin-topbar-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .55rem .9rem;
            border-radius: 999px;
            background: var(--admin-primary-soft);
            color: var(--admin-primary-dark);
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .admin-topbar-shell {
            gap: 1rem;
            min-height: 70px;
        }

        .admin-topbar-start {
            min-width: 0;
        }

        .admin-topbar-search {
            width: min(560px, 100%);
            margin-inline: auto;
        }

        .admin-topbar-action {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            min-height: 44px;
            padding: .62rem .95rem;
            border-radius: 999px;
            text-decoration: none;
            color: var(--admin-text);
            background: color-mix(in srgb, var(--admin-surface) 86%, white);
            border: 1px solid var(--admin-border);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--admin-text) 6%, transparent);
            font-weight: 700;
        }

        .admin-topbar-action:hover {
            color: var(--admin-primary-dark);
            text-decoration: none;
            background: color-mix(in srgb, var(--admin-primary) 8%, white);
            transform: translateY(-1px);
        }

        .admin-topbar-action--icon {
            width: 2.85rem;
            height: 2.85rem;
            justify-content: center;
            padding: 0 !important;
            position: relative;
        }

        .admin-profile-menu {
            min-width: 16rem;
            padding: .4rem;
        }

        .admin-profile-menu__header {
            padding: .8rem .85rem .55rem;
        }

        .admin-profile-menu__name {
            font-weight: 800;
            color: var(--admin-text);
            margin-bottom: .2rem;
        }

        .admin-profile-menu__email {
            color: var(--admin-muted);
            font-size: .82rem;
            word-break: break-word;
        }

        .admin-topbar-shell {
            gap: 1rem;
        }

        .admin-topbar-search {
            max-width: 38rem;
            width: 100%;
            border-radius: 999px;
            padding-inline: .95rem;
            background: color-mix(in srgb, var(--admin-surface) 88%, white);
            border: 1px solid var(--admin-border);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--admin-text) 6%, transparent);
        }

        .admin-topbar-search .form-control {
            min-height: 2.8rem;
        }

        .admin-topbar-action--notifications {
            background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 90%, white), #fff);
            border-color: color-mix(in srgb, var(--admin-primary) 16%, var(--admin-border));
            box-shadow: 0 12px 26px color-mix(in srgb, var(--admin-primary) 10%, transparent);
        }

        .admin-topbar-action--notifications i {
            font-size: 1.2rem;
        }

        .admin-topbar-count {
            position: absolute;
            top: -.22rem;
            inset-inline-end: -.2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.4rem;
            height: 1.4rem;
            padding: 0 .34rem;
            border-radius: 999px;
            background: linear-gradient(180deg, #fb7185, #ef4444);
            color: #fff;
            font-size: .67rem;
            font-weight: 800;
            line-height: 1;
            border: 2px solid #fff;
            box-shadow: 0 6px 14px rgba(239, 68, 68, .28);
        }

        .admin-topbar-dot {
            position: absolute;
            top: .45rem;
            inset-inline-end: .48rem;
            width: .42rem;
            height: .42rem;
            border-radius: 999px;
            background: #ef4444;
            box-shadow: 0 0 0 0 rgba(239, 68, 68, .35);
            animation: adminTopbarPulse 1.8s infinite;
        }

        @keyframes adminTopbarPulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, .35); }
            70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .admin-profile-trigger {
            display: inline-flex !important;
            align-items: center;
            gap: .7rem;
            border-radius: 999px;
            padding: .35rem .5rem .35rem .35rem !important;
            background: color-mix(in srgb, var(--admin-surface) 84%, white);
            border: 1px solid var(--admin-border);
        }


        .admin-brand-mark-image { background:var(--admin-surface); overflow:hidden; padding:0; }
        .admin-brand-mark-image img { width:100%; height:100%; object-fit:cover; }
        .language-switcher-admin { display:flex; align-items:center; }
        .language-switcher-admin.language-switcher--admin-compact .language-switcher__group { gap: .3rem; }
        .language-switcher-admin.language-switcher--admin-compact .language-switcher__link { padding: .4rem .55rem; }
        .language-switcher-admin.language-switcher--admin-compact .language-switcher__code { width: 1.8rem; height: 1.8rem; }
        .language-switcher-admin .language-switcher__label { color: var(--admin-muted); text-transform:none; font-size:.82rem; }
        .language-switcher-admin .language-switcher__group { display:inline-flex; align-items:center; gap:.45rem; padding:.35rem; border-radius:999px; background:color-mix(in srgb, var(--admin-surface) 88%, white); border:1px solid var(--admin-border); box-shadow:0 10px 24px color-mix(in srgb, var(--admin-text) 8%, transparent); backdrop-filter:blur(10px); }
        .language-switcher-admin .language-switcher__link { display:inline-flex; align-items:center; gap:.45rem; text-decoration:none; border-radius:999px; padding:.48rem .82rem; color:var(--admin-text); font-weight:700; font-size:.88rem; transition:all .2s ease; }
        .language-switcher-admin .language-switcher__link:hover { background:color-mix(in srgb, var(--admin-primary) 10%, white); color:var(--admin-primary-dark); transform:translateY(-1px); }
        .language-switcher-admin .language-switcher__link.is-active { background:linear-gradient(135deg, color-mix(in srgb, var(--admin-primary) 16%, white), color-mix(in srgb, var(--admin-accent) 14%, white)); color:var(--admin-primary-dark); box-shadow:0 10px 20px rgba(15,23,42,.08); }
        .language-switcher-admin .language-switcher__code { display:inline-flex; width:1.9rem; height:1.9rem; align-items:center; justify-content:center; border-radius:999px; background:var(--admin-surface); border:1px solid color-mix(in srgb, var(--admin-primary) 16%, white); font-size:.72rem; letter-spacing:.04em; }
        .language-switcher-admin .language-switcher__link.is-active .language-switcher__code { background:color-mix(in srgb, var(--admin-primary-soft) 82%, white); border-color:color-mix(in srgb, var(--admin-primary) 24%, white); }
        .language-switcher-admin .language-switcher__name { line-height:1; }
        .table-sort-btn { display:inline-flex; align-items:center; gap:.35rem; color:inherit; font-weight:700; text-decoration:none; }
        .table-sort-btn:hover { color:var(--admin-primary-dark); text-decoration:none; }

        
        .admin-page-shell { display:grid; gap:1.5rem; }
        :root {
            --admin-space-section: clamp(1rem, 1.2vw + .85rem, 1.5rem);
            --admin-space-card: clamp(1rem, 1vw + .8rem, 1.3rem);
            --admin-table-cell-y: .88rem;
            --admin-table-cell-x: .95rem;
            --admin-table-font: .92rem;
            --admin-radius-lg: 1.25rem;
            --admin-radius-md: 1rem;
        }

        .phase4-page,
        .notification-center-page,
        .whatsapp-page,
        .admin-page-shell,
        .growth-form-page,
        .template-form-page,
        .gx-shell {
            --page-block-gap: var(--admin-space-section);
        }

        .admin-section-card { background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 96%, white), color-mix(in srgb, var(--admin-primary-soft) 18%, white)); border: 1px solid var(--admin-border); border-radius: 1.4rem; box-shadow: 0 14px 34px color-mix(in srgb, var(--admin-text) 6%, transparent); }
        .admin-section-card__head { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
        .admin-section-card__title { font-size:1.05rem; font-weight:800; margin:0; }
        .admin-section-card__copy { color:var(--admin-muted); margin:0; font-size:.9rem; }
        .admin-list-item { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.95rem 0; border-bottom:1px solid color-mix(in srgb, var(--admin-border) 78%, white); }
        .admin-list-item:last-child { border-bottom:none; padding-bottom:0; }
        .admin-list-item:first-child { padding-top:0; }

        .admin-card,
        .card {
            background: color-mix(in srgb, var(--admin-surface) 96%, white);
            border: 1px solid var(--admin-border);
            box-shadow: 0 14px 32px color-mix(in srgb, var(--admin-text) 6%, transparent);
        }

        .card-header,
        .table thead th {
            background: color-mix(in srgb, var(--admin-table-head-bg) 92%, white);
            color: var(--admin-text);
            border-color: var(--admin-border) !important;
        }

        .table {
            --bs-table-bg: transparent;
            --bs-table-striped-bg: color-mix(in srgb, var(--admin-surface) 96%, white);
            --bs-table-hover-bg: var(--admin-row-hover-bg);
            --bs-table-border-color: var(--admin-border);
            color: var(--admin-text);
        }

        .table tbody tr:hover,
        .table-hover > tbody > tr:hover > * {
            background: var(--admin-row-hover-bg) !important;
        }

        .form-control,
        .form-select,
        textarea,
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="search"],
        input[type="password"] {
            background: var(--admin-input-bg) !important;
            border-color: var(--admin-border) !important;
            color: var(--admin-text) !important;
            box-shadow: none;
        }

        .form-control::placeholder,
        .form-select::placeholder,
        textarea::placeholder {
            color: color-mix(in srgb, var(--admin-muted) 82%, white);
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            border-color: color-mix(in srgb, var(--admin-primary) 42%, white) !important;
            box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--admin-primary) 16%, transparent) !important;
            background: color-mix(in srgb, var(--admin-surface) 98%, white) !important;
        }

        .alert-success {
            background: var(--admin-success-bg);
            color: var(--admin-success-text);
            border-color: color-mix(in srgb, var(--admin-primary) 22%, white);
        }

        .alert-danger,
        .invalid-feedback.d-block,
        .text-danger {
            color: var(--admin-danger-text) !important;
        }

        .alert-danger {
            background: var(--admin-danger-bg);
            border-color: color-mix(in srgb, var(--admin-accent) 18%, white);
        }

        .alert-warning {
            background: var(--admin-warning-bg);
            color: var(--admin-warning-text);
            border-color: color-mix(in srgb, var(--admin-primary) 18%, white);
        }

        .admin-flash {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-radius: 1.15rem;
            border: 1px solid transparent;
            box-shadow: 0 14px 28px color-mix(in srgb, var(--admin-primary) 8%, transparent);
        }

        .admin-flash-icon {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex: 0 0 auto;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.28);
        }

        .admin-flash-content {
            min-width: 0;
            flex: 1 1 auto;
        }

        .admin-flash-title {
            font-size: .98rem;
            font-weight: 800;
            line-height: 1.35;
            margin-bottom: .2rem;
            color: inherit;
        }

        .admin-flash-subtitle {
            font-size: .92rem;
            line-height: 1.65;
            opacity: .96;
            color: inherit;
        }

        .admin-flash-subtitle:last-child {
            margin-bottom: 0;
        }

        .admin-flash-list {
            margin: .55rem 0 0;
            padding-inline-start: 1.1rem;
            display: grid;
            gap: .28rem;
        }

        .admin-flash-list li {
            line-height: 1.55;
        }

        .admin-flash--success .admin-flash-icon {
            background: color-mix(in srgb, var(--admin-primary) 20%, white);
            color: var(--admin-success-text);
        }

        .admin-flash--danger .admin-flash-icon {
            background: color-mix(in srgb, var(--admin-accent) 16%, white);
            color: var(--admin-danger-text);
        }

        .admin-flash--warning .admin-flash-icon {
            background: color-mix(in srgb, var(--admin-primary-soft) 55%, white);
            color: var(--admin-warning-text);
        }

        .badge,
        .status-badge {
            box-shadow: none;
        }

        .btn-primary,
        .btn-primary:focus,
        .btn-primary:active {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent)) !important;
            border-color: transparent !important;
            color: #fff !important;
            box-shadow: 0 14px 28px color-mix(in srgb, var(--admin-primary) 24%, transparent);
        }

        .btn-primary:hover {
            filter: brightness(.98);
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            color: var(--admin-primary-dark) !important;
            border-color: color-mix(in srgb, var(--admin-primary) 30%, white) !important;
        }

        .btn-outline-primary:hover {
            background: color-mix(in srgb, var(--admin-primary) 10%, white) !important;
            color: var(--admin-primary-dark) !important;
        }

        .admin-profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent));
            color: #fff;
            font-weight: 800;
        }

        /* =========================
           SIDEBAR
        ========================= */
        .custom-sidebar {
            background: linear-gradient(180deg, var(--admin-sidebar) 0%, var(--admin-sidebar-2) 100%) !important;
            min-height: 100vh;
            border-right: 1px solid rgba(255,255,255,.06);
            transition: all .3s ease;
            box-shadow: 0 12px 30px color-mix(in srgb, var(--admin-sidebar) 28%, transparent);
        }

        .sidebar-header {
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .sidebar-store-card {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding: .9rem 1rem;
            border-radius: 18px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--admin-primary) 24%, transparent), color-mix(in srgb, var(--admin-accent) 22%, transparent));
            color: #fff;
            min-height: 72px;
            overflow: hidden;
        }

        .sidebar-store-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(255,255,255,.14);
            font-size: 1.15rem;
            color: #fff;
        }

        .sidebar-store-title {
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
        }

        .sidebar-store-text small {
            color: rgba(255,255,255,.75);
            display: block;
            margin-top: 2px;
        }

        .sidebar-section {
            padding: 12px 0 6px;
        }

        .sidebar-section-label {
            display: block;
            padding: 0 1.2rem .55rem;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .16em;
            font-weight: 800;
            color: rgba(255,255,255,.45) !important;
        }

        .custom-sidebar .nav {
            margin: 0;
        }

        .custom-sidebar .nav .nav-item .nav-link {
            display: flex;
            align-items: center;
            gap: .8rem;
            border-radius: 14px;
            margin: 0 .9rem .48rem;
            color: rgba(255,255,255,.78) !important;
            font-weight: 700;
            transition: all .25s ease;
            padding: .9rem 1rem;
            white-space: nowrap;
            min-height: 50px;
        }

        .custom-sidebar .nav .nav-item .nav-link .menu-icon,
        .custom-sidebar .nav .nav-item .nav-link i {
            color: rgba(255,255,255,.72) !important;
            font-size: 1.1rem;
            transition: all .25s ease;
            min-width: 20px;
            text-align: center;
        }

        .custom-sidebar .nav .nav-item .nav-link:hover {
            background: linear-gradient(135deg, rgba(249,115,22,.18), rgba(236,72,153,.14));
            color: #fff !important;
            transform: translateX(3px);
        }

        .custom-sidebar .nav .nav-item .nav-link:hover .menu-icon,
        .custom-sidebar .nav .nav-item .nav-link:hover i {
            color: #fff !important;
        }

        .custom-sidebar .nav .nav-item.active > .nav-link {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent));
            color: #fff !important;
            box-shadow: 0 12px 24px rgba(249,115,22,.22);
        }

        .custom-sidebar .nav .nav-item.active > .nav-link .menu-icon,
        .custom-sidebar .nav .nav-item.active > .nav-link i {
            color: #fff !important;
        }

        /* =========================
           MINI SIDEBAR / COLLAPSED
        ========================= */
        .sidebar-icon-only .custom-sidebar {
            width: 70px;
        }

        .sidebar-icon-only .custom-sidebar .sidebar-store-text,
        .sidebar-icon-only .custom-sidebar .menu-title,
        .sidebar-icon-only .custom-sidebar .sidebar-section-label {
            display: none !important;
        }

        .sidebar-icon-only .custom-sidebar .sidebar-header {
            padding-left: .55rem !important;
            padding-right: .55rem !important;
        }

        .sidebar-icon-only .custom-sidebar .sidebar-store-card {
            justify-content: center;
            padding: .8rem .5rem;
        }

        .sidebar-icon-only .custom-sidebar .sidebar-store-icon {
            margin: 0;
        }

        .sidebar-icon-only .custom-sidebar .nav .nav-item .nav-link {
            justify-content: center;
            gap: 0;
            padding: .95rem .5rem;
            margin-left: .5rem;
            margin-right: .5rem;
        }

        .sidebar-icon-only .custom-sidebar .nav .nav-item .nav-link i,
        .sidebar-icon-only .custom-sidebar .nav .nav-item .nav-link .menu-icon {
            margin: 0 !important;
            font-size: 1.2rem;
        }

        .sidebar-icon-only .main-panel {
            width: calc(100% - 70px);
        }

        /* =========================
           PAGE UI
        ========================= */
        .admin-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .admin-breadcrumbs {
            margin-bottom: .75rem;
        }

        .admin-breadcrumb-list {
            list-style: none;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .55rem;
            padding: 0;
            margin: 0;
            color: var(--admin-muted);
            font-size: .84rem;
            font-weight: 700;
        }

        .admin-breadcrumb-item {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
        }

        .admin-breadcrumb-item:not(:last-child)::after {
            content: '›';
            color: color-mix(in srgb, var(--admin-muted) 75%, white);
            font-size: .95rem;
            font-weight: 900;
        }

        html[dir="rtl"] .admin-breadcrumb-item:not(:last-child)::after {
            content: '‹';
        }

        .admin-breadcrumb-item a {
            color: var(--admin-primary-dark);
            text-decoration: none;
        }

        .admin-breadcrumb-item a:hover {
            color: var(--admin-accent-dark, var(--admin-primary-dark));
            text-decoration: underline;
        }

        .admin-breadcrumb-item.is-current span {
            color: var(--admin-text);
        }

        .admin-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .65rem;
            padding: .35rem .75rem;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--admin-primary-soft), var(--admin-accent-soft));
            color: var(--admin-primary-dark);
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .admin-page-title {
            margin: 0;
            font-size: 2rem;
            font-weight: 900;
            color: var(--admin-text);
        }

        .admin-page-description {
            margin: .45rem 0 0;
            color: var(--admin-muted);
            font-size: .98rem;
            max-width: 760px;
        }

        .admin-page-actions {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }


        .admin-page-actions > *,
        .admin-page-actions .btn,
        .gx-actions .btn,
        .analytics-actions .btn,
        .analytics-form .btn,
        .d-flex.gap-2 > .btn,
        .d-flex.gap-3 > .btn,
        .btn-group > .btn {
            max-width: 100%;
        }

        .admin-page-actions .btn,
        .gx-actions .btn,
        .analytics-actions .btn,
        .analytics-form .btn,
        .admin-form-actions-buttons .btn {
            white-space: normal;
        }

        .admin-section-title {
            margin: 0;
            font-size: 1.02rem;
            font-weight: 800;
            color: var(--admin-text);
        }

        .admin-section-subtitle {
            margin: .3rem 0 0;
            color: var(--admin-muted);
            font-size: .9rem;
            line-height: 1.7;
        }

        .admin-inline-title {
            margin: 0;
            font-size: .98rem;
            font-weight: 800;
            color: var(--admin-text);
        }

        .admin-helper-text {
            margin: .35rem 0 0;
            color: var(--admin-muted) !important;
            font-size: .84rem;
            line-height: 1.7;
        }

        .admin-empty-title {
            margin: 0 0 .35rem;
            font-size: 1rem;
            font-weight: 800;
            color: var(--admin-text);
        }

        .admin-empty-subtitle {
            margin: 0;
            color: var(--admin-muted);
            font-size: .92rem;
            line-height: 1.7;
        }

        .admin-soft-note {
            padding: .95rem 1rem;
            border: 1px solid var(--admin-border);
            border-radius: 1rem;
            background: color-mix(in srgb, var(--admin-surface) 94%, white);
        }

        .btn-primary {
            border-color: transparent;
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent));
            box-shadow: 0 12px 26px rgba(236, 72, 153, .22);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            border-color: transparent;
            background: linear-gradient(135deg, var(--admin-primary-dark), color-mix(in srgb, var(--admin-accent) 82%, white));
        }

        .btn-icon,
        .btn-action {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 0;
        }

        .btn-action.btn-outline-primary:hover,
        .btn-action.btn-outline-secondary:hover,
        .btn-action.btn-outline-danger:hover,
        .btn-action.btn-outline-info:hover {
            color: #fff;
        }

        .admin-btn-soft,
        .btn-light {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            color: var(--admin-text);
        }

        .admin-card {
            border: 1px solid rgba(242, 218, 200, .95);
            background: color-mix(in srgb, var(--admin-surface) 95%, white);
            border-radius: 1.2rem;
            box-shadow: var(--admin-shadow);
            overflow: visible;
        }

        .admin-card .card-header {
            background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface-alt) 55%, white) 0%, var(--admin-surface) 100%);
            border-bottom: 1px solid color-mix(in srgb, var(--admin-border) 94%, transparent);
            padding: 1rem 1.25rem;
        }

        .admin-card .card-body,
        .admin-card .card-footer {
            padding: 1.25rem;
        }

        .admin-card-sticky {
            position: sticky;
            top: 95px;
        }

        .admin-table thead th {
            border-top: 0;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--admin-muted);
            background: linear-gradient(180deg, var(--admin-table-head-bg) 0%, color-mix(in srgb, var(--admin-table-head-bg) 72%, var(--admin-surface)) 100%);
            white-space: nowrap;
            min-height: 50px;
        }

        .admin-table tbody tr:hover {
            background: color-mix(in srgb, var(--admin-row-hover-bg) 78%, var(--admin-surface));
        }

        .admin-table td {
            vertical-align: middle;
        }

        .admin-thumb {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid var(--admin-border);
            background: color-mix(in srgb, var(--admin-primary-soft) 72%, var(--admin-surface));
        }

        .admin-thumb-lg {
            width: 100%;
            height: 240px;
            object-fit: cover;
            border-radius: 18px;
            border: 1px solid var(--admin-border);
            background: color-mix(in srgb, var(--admin-primary-soft) 72%, var(--admin-surface));
        }

        .badge-soft-success {
            color: var(--admin-success-text);
            background: var(--admin-success-bg);
        }

        .badge-soft-secondary {
            color: var(--admin-muted);
            background: color-mix(in srgb, var(--admin-primary-soft) 64%, white);
        }

        .badge-soft-warning {
            color: var(--admin-warning-text);
            background: var(--admin-warning-bg);
        }

        .badge-soft-info {
            color: color-mix(in srgb, var(--admin-accent) 72%, var(--admin-sidebar));
            background: color-mix(in srgb, var(--admin-accent) 16%, white);
        }

        .badge-soft-danger {
            color: var(--admin-danger-text);
            background: var(--admin-danger-bg);
        }

        .admin-chip {
            display: inline-flex;
            align-items: center;
            padding: .45rem .85rem;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--admin-primary-soft), var(--admin-accent-soft));
            color: var(--admin-primary-dark);
            font-weight: 700;
            font-size: .85rem;
        }

        .form-control,
        .form-select {
            border-color: var(--admin-border);
            border-radius: .9rem;
            min-height: 48px;
            color: var(--admin-text);
            background-color: var(--admin-input-bg);
        }

        textarea.form-control {
            min-height: 120px;
        }

        .admin-card-body {
            padding: 1.25rem;
        }

        .admin-card-body > :first-child,
        .admin-page-header > :first-child,
        .admin-page-header h1,
        .admin-page-header p,
        .admin-card h1,
        .admin-card h2,
        .admin-card h3,
        .admin-card h4,
        .admin-card h5,
        .admin-card h6 {
            margin-top: 0 !important;
        }

        .admin-kicker,
        .admin-inline-label,
        .admin-page-title,
        .admin-page-description,
        .form-label,
        h1, h2, h3, h4, h5, h6,
        label,
        .btn,
        .text-muted,
        .small {
            line-height: 1.35;
        }

        .admin-kicker {
            display: inline-block;
            margin-bottom: .45rem;
            color: var(--admin-primary-dark);
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: .78rem;
            font-weight: 800;
        }

        .table-responsive,
        .admin-table-wrap {
            overflow-y: hidden;
            border: 1px solid color-mix(in srgb, var(--admin-border) 88%, white);
            border-radius: var(--admin-radius-lg);
            background: color-mix(in srgb, var(--admin-surface) 97%, white);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--admin-text) 5%, transparent);
        }


        .analytics-table-wrap,
        .gx-table-wrap {
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            border: 1px solid color-mix(in srgb, var(--admin-border) 88%, white);
            border-radius: var(--admin-radius-lg);
            background: color-mix(in srgb, var(--admin-surface) 97%, white);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--admin-text) 5%, transparent);
        }

        .analytics-table-wrap > .analytics-table,
        .gx-table-wrap > .gx-table {
            min-width: 720px;
            margin-bottom: 0;
        }

        .table-responsive > .table,
        .admin-table-wrap > .table,
        .table-responsive > .admin-table,
        .admin-table-wrap > .admin-table {
            margin-bottom: 0;
            min-width: max-content;
        }

        .table-responsive,
        .admin-table-wrap {
            -webkit-overflow-scrolling: touch;
        }

        .table thead th,
        .table tbody td,
        .admin-table thead th,
        .admin-table tbody td {
            padding: var(--admin-table-cell-y) var(--admin-table-cell-x);
            font-size: var(--admin-table-font);
        }

        .table tbody td,
        .admin-table tbody td {
            line-height: 1.55;
        }

        .table thead th,
        .admin-table thead th {
            position: relative;
            vertical-align: middle;
        }

        .table tbody tr:last-child td,
        .admin-table tbody tr:last-child td {
            border-bottom-width: 0;
        }

        .table td .btn,
        .table td .badge,
        .admin-table td .btn,
        .admin-table td .badge {
            margin-bottom: .2rem;
        }

        .table td .btn:last-child,
        .table td .badge:last-child,
        .admin-table td .btn:last-child,
        .admin-table td .badge:last-child {
            margin-bottom: 0;
        }

        .admin-card,
        .gx-card,
        .gx-panel,
        .gx-mini-panel,
        .gx-hero,
        .notification-soft-card,
        .notification-list-item,
        .rounded-4.border,
        .rounded-4.border.bg-white,
        .rounded-4.border.p-4,
        .rounded-4.border.p-3 {
            border-radius: var(--admin-radius-lg) !important;
        }

        .admin-card .card-body,
        .admin-card .card-footer,
        .gx-card,
        .gx-panel,
        .gx-mini-panel,
        .notification-soft-card,
        .notification-list-item {
            padding: var(--admin-space-card);
        }

        .admin-page-shell,
        .growth-form-page,
        .template-form-page,
        .notification-center-page,
        .whatsapp-page,
        .gx-shell {
            display: grid;
            gap: var(--page-block-gap);
        }

        .admin-page-header {
            margin-bottom: 0;
        }

        .admin-page-header + *,
        .notification-center-page > *,
        .whatsapp-page > *,
        .gx-shell > * {
            min-width: 0;
        }

        .row > [class*='col-'],
        .gx-grid > *,
        .gx-three > *,
        .gx-two > *,
        .gx-subgrid > *,
        .gx-hero-grid > *,
        .gx-quick-actions > *,
        .gx-inline-metrics > *,
        .notification-center-page .accordion-item,
        .notification-center-page .accordion-body,
        .notification-center-page .accordion-button,
        .notification-center-page .accordion-button > div {
            min-width: 0;
        }

        .admin-card,
        .admin-card .card-body,
        .admin-card .card-footer,
        .gx-card,
        .gx-panel,
        .gx-mini-panel,
        .gx-hero,
        .notification-soft-card,
        .notification-list-item {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .admin-page-description,
        .admin-section-subtitle,
        .admin-helper-text,
        .gx-subtitle,
        .gx-caption,
        .gx-help,
        .gx-mini,
        .notification-mini-label {
            max-width: 78ch;
        }

        .admin-stat-card,
        .gx-card,
        .gx-panel,
        .gx-mini-panel,
        .notification-soft-card,
        .notification-list-item {
            height: 100%;
        }

        .admin-form-actions {
            gap: 1rem;
            border-radius: var(--admin-radius-lg);
        }

        @media (max-width: 1199.98px) {
            .content-wrapper {
                padding: 1.15rem;
            }

            .admin-page-title {
                font-size: 1.75rem;
            }

            .table thead th,
            .table tbody td,
            .admin-table thead th,
            .admin-table tbody td {
                padding: .78rem .82rem;
            }
        }

        @media (max-width: 991.98px) {
            :root {
                --admin-space-section: 1rem;
                --admin-space-card: 1rem;
                --admin-table-cell-y: .76rem;
                --admin-table-cell-x: .78rem;
                --admin-table-font: .88rem;
            }

            .content-wrapper {
                padding: 1rem;
            }

            .admin-page-header {
                gap: .85rem;
            }

            .admin-page-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .admin-page-actions .btn,
            .gx-toolbar .btn,
            .gx-runbar .btn {
                width: 100%;
            }

            .gx-title {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 767.98px) {
            .content-wrapper {
                padding: .85rem;
            }

            .admin-page-title {
                font-size: 1.45rem;
            }

            .admin-page-description,
            .admin-section-subtitle,
            .admin-helper-text,
            .gx-subtitle,
            .gx-caption,
            .gx-help,
            .gx-mini,
            .admin-empty-subtitle {
                font-size: .88rem;
                line-height: 1.65;
            }

            .admin-chip,
            .notification-center-nav a,
            .gx-tab-link {
                font-size: .8rem;
            }

            .admin-form-actions,
            .admin-section-card__head,
            .notification-section-title {
                align-items: flex-start;
            }

            .admin-form-actions-buttons,
            .admin-page-actions,
            .gx-toolbar,
            .gx-runbar {
                width: 100%;
            }

            .admin-form-actions-buttons .btn,
            .admin-page-actions .btn,
            .gx-toolbar .btn,
            .gx-runbar .btn,
            .notification-section-title .btn {
                width: 100%;
            }

            .table-responsive,
            .admin-table-wrap {
                border-radius: 1rem;
                margin-inline: -.15rem;
            }

            .table thead th,
            .table tbody td,
            .admin-table thead th,
            .admin-table tbody td {
                white-space: nowrap;
                font-size: .84rem;
            }

            .table td .text-muted,
            .admin-table td .text-muted,
            .table td small,
            .admin-table td small {
                white-space: normal;
            }
        }

        .admin-filter-actions-wide {
            grid-column: span 2;
        }

        .admin-sort-link {
            color: inherit;
            text-decoration: none;
            font-weight: inherit;
        }

        .admin-sort-link:hover {
            color: var(--admin-primary-dark);
            text-decoration: none;
        }

        .admin-inline-select {
            min-width: 145px;
            min-height: 38px;
            border-radius: .75rem;
        }

        .form-control:focus,
        .form-select:focus,
        .form-check-input:focus {
            border-color: color-mix(in srgb, var(--admin-primary) 45%, white);
            box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--admin-primary) 12%, transparent);
        }

        select,
.form-select {
    color: var(--admin-text) !important;
}

select option {
    color: var(--admin-text) !important;
    background: var(--admin-surface);
}
        .form-check-input:checked {
            background-color: var(--admin-primary);
            border-color: var(--admin-primary);
        }

        .required-star {
            color: var(--admin-danger-text);
        }

        .alert-success {
            background: linear-gradient(135deg, color-mix(in srgb, var(--admin-primary) 12%, white), color-mix(in srgb, var(--admin-primary) 20%, white));
            color: var(--admin-success-text);
            border: 0;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .gallery-item {
            display: block;
            cursor: pointer;
            margin: 0;
            position: relative;
        }

        .gallery-item input[type='radio'] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .gallery-item-inner {
            display: flex;
            gap: .9rem;
            padding: .95rem;
            border-radius: 1rem;
            border: 1px solid var(--admin-border);
            background: color-mix(in srgb, var(--admin-primary-soft) 52%, var(--admin-surface));
            transition: all .2s ease;
            min-height: 116px;
        }

        .gallery-item img {
            width: 84px;
            height: 84px;
            object-fit: cover;
            border-radius: .9rem;
            border: 1px solid var(--admin-border);
        }

        .gallery-item input[type='radio']:checked + .gallery-item-inner {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--admin-primary) 12%, transparent);
            background: var(--admin-surface);
        }

        .gallery-main-label,
        .gallery-secondary-label {
            display: inline-flex;
            padding: .3rem .6rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
        }

        .gallery-main-label {
            background: var(--admin-primary-soft);
            color: var(--admin-primary-dark);
        }

        .gallery-secondary-label {
            background: color-mix(in srgb, var(--admin-surface-alt) 44%, white);
            color: var(--admin-muted);
        }

        .upload-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: .9rem;
            margin-top: 1rem;
        }

        .upload-preview-card {
            border: 1px solid var(--admin-border);
            border-radius: 1rem;
            background: color-mix(in srgb, var(--admin-primary-soft) 52%, var(--admin-surface));
            overflow: hidden;
        }

        .upload-preview-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }

        .upload-preview-card .meta {
            padding: .7rem .8rem;
            font-size: .8rem;
            color: var(--admin-muted);
        }

        .variant-row {
            padding: .95rem;
            border: 1px dashed var(--admin-border);
            border-radius: 1rem;
            background: linear-gradient(180deg, var(--admin-surface) 0%, color-mix(in srgb, var(--admin-surface-alt) 32%, white) 100%);
        }

        .section-note {
            color: var(--admin-muted);
            font-size: .88rem;
            margin-top: .2rem;
        }

        /* Product Status Switch */
        .product-status-switch {
            width: 2.9rem !important;
            height: 1.5rem;
            cursor: pointer;
            border-radius: 2rem;
        }

        .product-status-switch:checked {
            background-color: var(--admin-primary) !important;
            border-color: var(--admin-primary) !important;
        }

        .product-status-switch:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--admin-primary) 22%, transparent);
        }

        @media (max-width: 991px) {
            .admin-page-title {
                font-size: 1.6rem;
            }

            .admin-brand-text {
                display: none;
            }

            .admin-topbar-search {
                display: none !important;
            }

            .admin-card-sticky {
                position: static;
            }
        }
    
        .admin-kicker {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .45rem;
            padding: .35rem .7rem;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--admin-primary-soft), var(--admin-accent-soft));
            color: var(--admin-primary-dark);
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .custom-sidebar .nav .nav-item {
            width: 100%;
        }
        .custom-sidebar .nav .nav-item .nav-link,
        .custom-sidebar .nav .nav-item.active > .nav-link {
            min-height: 52px;
            position: relative;
            overflow: hidden;
        }
        .custom-sidebar .nav .nav-item .menu-title {
            color: inherit !important;
            position: relative;
            z-index: 1;
        }
        .custom-sidebar .nav .nav-item .nav-link::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: transparent;
            z-index: 0;
        }
        .custom-sidebar .nav .nav-item.active > .nav-link::after {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent));
        }
        .admin-stat-card {
            position: relative;
            padding: 1.25rem;
            min-height: 145px;
            isolation: isolate;
        }
        .admin-stat-card::before {
            content: "";
            position: absolute;
            right: -18px;
            top: -18px;
            width: 90px;
            height: 90px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--admin-primary) 9%, transparent);
            z-index: -1;
        }
        .admin-stat-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            margin-bottom: .9rem;
            font-size: 1.2rem;
            background: var(--admin-primary-soft);
            color: var(--admin-primary-dark);
        }
        .admin-stat-label {
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--admin-muted);
            font-weight: 800;
            margin-bottom: .55rem;
        }
        .admin-stat-value {
            font-size: 2rem;
            font-weight: 900;
            line-height: 1;
            color: var(--admin-text);
        }
        .admin-filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        .admin-filter-actions {
            display: flex;
            gap: .75rem;
        }
        .admin-filter-actions-wide {
            width: 100%;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .admin-table-toolbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .admin-table-toolbar-actions {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            flex-wrap: wrap;
        }
        .admin-section-card {
            border: 1px solid color-mix(in srgb, var(--admin-border) 88%, white);
            border-radius: var(--admin-radius-lg);
            background: color-mix(in srgb, var(--admin-surface) 96%, white);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--admin-text) 4%, transparent);
            padding: clamp(1rem, 1.2vw + .8rem, 1.35rem);
        }
        .admin-section-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .admin-section-title {
            margin: 0;
            font-size: 1.02rem;
            font-weight: 800;
            color: var(--admin-text);
        }
        .admin-section-subtitle {
            margin: .35rem 0 0;
            color: var(--admin-muted);
            font-size: .92rem;
        }
        .admin-form-shell {
            display: grid;
            gap: 1rem;
        }
        .admin-form-shell > .row,
        .admin-form-shell > .admin-card,
        .admin-form-shell > .admin-section-card {
            margin-bottom: 0 !important;
        }
        .admin-actions-stack {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .admin-code-block {
            max-width: 100%;
            overflow: auto;
            border-radius: 1rem;
            padding: 1rem;
            background: #111827;
            color: #f9fafb;
        }
        .admin-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            padding: .55rem .95rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 800;
            border: 1px solid transparent;
        }
        .admin-status-badge::before {
            content: "";
            width: .5rem;
            height: .5rem;
            border-radius: 999px;
            background: currentColor;
            opacity: .9;
        }
        .admin-empty-state {
            padding: 3rem 1.25rem;
            text-align: center;
        }
        .admin-empty-icon,
        .empty-icon {
            width: 74px;
            height: 74px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--admin-primary-soft), var(--admin-accent-soft));
            color: var(--admin-primary-dark);
            font-size: 1.9rem;
            flex-shrink: 0;
        }
        .admin-order-flow {
            display: grid;
            gap: 1rem;
        }
        .admin-order-step {
            position: relative;
            padding-left: 3rem;
        }
        .admin-order-step::before {
            content: "";
            position: absolute;
            left: .95rem;
            top: 2.05rem;
            bottom: -1.2rem;
            width: 2px;
            background: var(--admin-border);
        }
        .admin-order-step:last-child::before { display: none; }
        .admin-order-step-dot {
            position: absolute;
            left: 0;
            top: .15rem;
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--admin-surface);
            border: 2px solid color-mix(in srgb, var(--admin-border) 90%, white);
            color: var(--admin-muted);
            font-size: .85rem;
            font-weight: 800;
        }
        .admin-order-step.active .admin-order-step-dot {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent));
            border-color: transparent;
            color: #fff;
        }
        .admin-order-step.current .admin-order-step-dot {
            box-shadow: 0 0 0 6px color-mix(in srgb, var(--admin-primary) 12%, transparent);
        }
        .admin-order-step-title { font-weight: 800; margin-bottom: .15rem; }
        .admin-order-step-copy { color: var(--admin-muted); font-size: .92rem; }
        .admin-order-header-card {
            padding: 1.25rem;
            background: linear-gradient(135deg, color-mix(in srgb, var(--admin-surface) 98%, white), color-mix(in srgb, var(--admin-surface-alt) 92%, var(--admin-surface)));
        }
        .admin-inline-label {
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--admin-muted);
            font-weight: 800;
            margin-bottom: .35rem;
        }
        .admin-summary-list .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .8rem;
        }
        .admin-summary-list .summary-row:last-child { margin-bottom: 0; }
        .page-link {
            border-radius: .85rem !important;
            border-color: rgba(242,218,200,.95);
            color: var(--admin-primary-dark);
            margin-inline: .18rem;
        }
        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent));
            border-color: transparent;
        }
        .admin-pagination-wrap .pagination {
            gap: .25rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .admin-pagination-wrap .page-link {
            min-width: 2.5rem;
            min-height: 2.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 24px color-mix(in srgb, var(--admin-primary) 10%, transparent);
        }
        @media (max-width: 1199.98px) {
            .admin-filter-grid { grid-template-columns: 1fr 1fr; }
            .admin-filter-actions { grid-column: 1 / -1; }
            .admin-card-sticky { position: static; }
        }
        @media (max-width: 767.98px) {
            .admin-filter-grid { grid-template-columns: 1fr; }
            .admin-page-title { font-size: 1.55rem; }
        }


        @media (max-width: 991.98px) {
            .content-wrapper {
                padding: 1rem;
            }

            .admin-card-body,
            .card-body,
            .gx-card,
            .gx-panel,
            .gx-mini-panel,
            .gx-hero {
                padding-inline: 1rem;
            }
        }

        @media (max-width: 767.98px) {
            .content-wrapper {
                padding: .85rem;
            }

            .admin-page-header,
            .admin-table-toolbar,
            .admin-section-heading {
                gap: .75rem;
            }

            .admin-table-toolbar-actions,
            .admin-actions-stack,
            .admin-filter-actions {
                width: 100%;
            }

            .admin-table-toolbar-actions > *,
            .admin-actions-stack > *,
            .admin-filter-actions > * {
                flex: 1 1 180px;
            }

            .admin-page-actions,
            .gx-actions,
            .analytics-actions,
            .analytics-form,
            .admin-form-actions,
            .admin-form-actions-buttons,
            .d-flex.justify-content-between,
            .d-flex.align-items-center {
                gap: .75rem;
            }

            .admin-table-toolbar,
            .admin-section-heading {
                gap: .75rem;
            }

            .admin-table-toolbar-actions,
            .admin-actions-stack,
            .admin-filter-actions {
                width: 100%;
            }

            .admin-table-toolbar-actions > *,
            .admin-actions-stack > *,
            .admin-filter-actions > * {
                flex: 1 1 180px;
            }

            .admin-page-actions,
            .gx-actions,
            .analytics-actions,
            .analytics-form,
            .admin-form-actions-buttons {
                width: 100%;
            }

            .admin-page-actions .btn,
            .gx-actions .btn,
            .analytics-actions .btn,
            .analytics-form .btn,
            .admin-form-actions-buttons .btn,
            .btn-group.mobile-stack > .btn {
                width: 100%;
                justify-content: center;
            }

            .table-responsive,
            .admin-table-wrap,
            .analytics-table-wrap,
            .gx-table-wrap {
                border-radius: 1rem;
            }
        }

    
        .badge-soft-purple { color: color-mix(in srgb, var(--admin-accent) 78%, var(--admin-sidebar)); background: color-mix(in srgb, var(--admin-accent) 14%, white); border-color: color-mix(in srgb, var(--admin-accent) 22%, white); }
        .sidebar-utility-links { display: grid; gap: .65rem; }
        .sidebar-utility-link { display: flex; align-items: center; gap: .7rem; min-height: 42px; padding: .8rem .95rem; border-radius: 14px; background: rgba(255,255,255,.04); color: rgba(255,255,255,.86); text-decoration: none; transition: all .18s ease; }
        .sidebar-utility-link:hover { background: rgba(255,255,255,.09); color: #fff; transform: translateY(-1px); }
        .sidebar-utility-link i { font-size: 1rem; }
        .admin-navigation-card__icon { width: 48px; height: 48px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--admin-accent) 14%, white); color: color-mix(in srgb, var(--admin-accent) 70%, var(--admin-primary)); font-size: 1.25rem; }
        .admin-navigation-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .admin-navigation-item { display: flex; gap: .9rem; min-height: 132px; padding: 1rem; border: 1px solid var(--admin-border); border-radius: 18px; background: #fff; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
        .admin-navigation-item:hover { transform: translateY(-2px); box-shadow: 0 18px 35px rgba(15, 23, 42, .08); border-color: color-mix(in srgb, var(--admin-accent) 22%, var(--admin-border)); }
        .admin-navigation-item__icon { width: 44px; height: 44px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--admin-accent) 12%, white); color: var(--admin-primary); font-size: 1.15rem; flex: 0 0 auto; }
        .admin-navigation-item__body { min-width: 0; display: flex; flex-direction: column; gap: .55rem; }
        .admin-navigation-item__head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; color: #0f172a; }
        .admin-navigation-item__head span { font-weight: 800; color: color-mix(in srgb, var(--admin-accent) 75%, var(--admin-primary)); }
        .admin-navigation-item__meta { margin-top: auto; color: var(--admin-muted); font-size: .78rem; }
        .admin-route-list { display: grid; gap: .9rem; }
        .admin-route-item { display: grid; grid-template-columns: 44px minmax(0, 1fr) 18px; align-items: center; gap: .85rem; padding: .9rem 1rem; border-radius: 16px; border: 1px solid var(--admin-border); color: inherit; background: #fff; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
        .admin-route-item:hover { transform: translateY(-2px); box-shadow: 0 18px 35px rgba(15, 23, 42, .08); border-color: color-mix(in srgb, var(--admin-accent) 22%, var(--admin-border)); }
        .admin-route-item__icon { width: 44px; height: 44px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--admin-accent) 12%, white); color: var(--admin-primary); font-size: 1.1rem; }
        @media (max-width: 1199.98px) { .admin-navigation-grid { grid-template-columns: 1fr; } }
        @media (max-width: 991.98px) { .sidebar-utility-links { padding-top: .5rem; } }
        .custom-sidebar .nav { display: flex; flex-direction: column; gap: 0; padding: 0 .75rem; }
        .custom-sidebar .nav .nav-item { margin: 0 0 .35rem 0 !important; }
        .custom-sidebar .nav .nav-item .nav-link,
        .custom-sidebar .nav .nav-item.active > .nav-link {
            margin: 0 !important;
            min-height: 50px;
            padding: .82rem 1rem !important;
            border-radius: 14px !important;
            position: relative;
            display: flex !important;
            align-items: center;
            overflow: hidden;
        }
        .custom-sidebar .nav .nav-item .nav-link .menu-icon,
        .custom-sidebar .nav .nav-item .nav-link i { position: relative; z-index: 2; }
        .custom-sidebar .nav .nav-item .nav-link .menu-title { position: relative; z-index: 2; }
        .custom-sidebar .nav .nav-item .nav-link::after { z-index: 1; }
        .custom-sidebar .nav .nav-item.active > .nav-link { box-shadow: 0 14px 30px color-mix(in srgb, var(--admin-primary) 18%, transparent); }
        .custom-sidebar .nav .nav-item .nav-link:hover { transform: translateX(0); }
        .admin-table tbody tr { transition: background-color .2s ease, transform .2s ease; }
        .admin-table tbody tr:hover { background: color-mix(in srgb, var(--admin-row-hover-bg) 72%, var(--admin-surface)); }
        .admin-table tbody td { padding-top: 1rem; padding-bottom: 1rem; }
        .admin-panel-loading { position: relative; pointer-events: none; opacity: .7; }
        .admin-loading-spinner { width: 1rem; height: 1rem; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 999px; display: inline-block; animation: adminSpin .8s linear infinite; margin-inline-end: .55rem; vertical-align: -2px; }
        @keyframes adminSpin { to { transform: rotate(360deg); } }
        .admin-refund-item { padding: .9rem 1rem; border: 1px dashed var(--admin-border); border-radius: 16px; background: color-mix(in srgb, var(--admin-surface) 75%, white); }
        .admin-coupon-code { display:inline-flex; padding:.45rem .7rem; border-radius:12px; background:color-mix(in srgb, var(--admin-primary-soft) 75%, white); color:var(--admin-primary-dark); font-weight:800; letter-spacing:.04em; }

        .alert-danger {
            background: linear-gradient(135deg, color-mix(in srgb, var(--admin-accent-soft) 90%, white), color-mix(in srgb, var(--admin-accent) 14%, white));
            color: var(--admin-danger-text);
            border: 0;
        }
        .custom-sidebar .nav .nav-item.active:not(.sidebar-current) > .nav-link,
        .custom-sidebar .nav .nav-item.active:not(.sidebar-current) > .nav-link::after {
            background: transparent !important;
            box-shadow: none !important;
            color: rgba(255,255,255,.78) !important;
        }
        .custom-sidebar .nav .nav-item.sidebar-current > .nav-link {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent)) !important;
            color: #fff !important;
            box-shadow: 0 14px 30px color-mix(in srgb, var(--admin-primary) 18%, transparent);
        }
        .custom-sidebar .nav .nav-item.sidebar-current > .nav-link::after {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent)) !important;
        }
        .custom-sidebar .nav .nav-item:not(.sidebar-current) > .nav-link,
        .custom-sidebar .nav .nav-item:not(.sidebar-current) > .nav-link::after {
            background: transparent !important;
            box-shadow: none !important;
        }
        .custom-sidebar .nav .nav-item.sidebar-current > .nav-link .menu-icon,
        .custom-sidebar .nav .nav-item.sidebar-current > .nav-link i,
        .custom-sidebar .nav .nav-item.sidebar-current > .nav-link .menu-title {
            color: #fff !important;
        }
        .sidebar-mini-list {
            padding-left: 1rem;
            color: rgba(255,255,255,.78);
            line-height: 1.55;
        }
        .sidebar-mini-list li + li { margin-top: .45rem; }
        .sidebar-quick-access { border-bottom: 1px solid rgba(255,255,255,.05); }
        .sidebar-focus-card {
            border-radius: 18px;
            padding: .95rem 1rem;
            background: linear-gradient(145deg, rgba(249,115,22,.18), rgba(236,72,153,.12));
            border: 1px solid rgba(255,255,255,.07);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
        }
        .sidebar-focus-card__label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(255,255,255,.66);
            margin-bottom: .35rem;
            font-weight: 800;
        }
        .sidebar-focus-card__title {
            color: #fff;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: .35rem;
        }
        .sidebar-focus-card__meta {
            color: rgba(255,255,255,.74);
            font-size: .82rem;
            line-height: 1.45;
        }
        .sidebar-quick-grid { display: grid; gap: .55rem; }
        .sidebar-quick-chip {
            display: flex; align-items: center; gap: .55rem; padding: .72rem .85rem;
            border-radius: 14px; text-decoration: none; color: rgba(255,255,255,.82);
            background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.05);
            transition: all .2s ease; font-weight: 700;
        }
        .sidebar-quick-chip strong {
            margin-inline-start: auto; min-width: 24px; height: 24px; border-radius: 999px;
            display: inline-flex; align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--admin-primary) 22%, transparent); color: #fff; font-size: .72rem;
        }
        .sidebar-quick-chip i { color: rgba(255,255,255,.72); font-size: 1rem; }
        .sidebar-quick-chip:hover, .sidebar-quick-chip.active {
            background: linear-gradient(135deg, rgba(249,115,22,.18), rgba(236,72,153,.14));
            color: #fff; border-color: rgba(255,255,255,.08);
        }
        .sidebar-quick-chip:hover i, .sidebar-quick-chip.active i { color: #fff; }
        .sidebar-group { padding: .7rem .75rem 0; }
        .sidebar-group:last-of-type { padding-bottom: 1rem; }
        .sidebar-group[open] .sidebar-group-arrow { transform: rotate(180deg); }
        .sidebar-group-summary {
            list-style: none; display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            cursor: pointer; padding: .8rem .95rem; border-radius: 16px; color: rgba(255,255,255,.92);
            background: rgba(255,255,255,.035); border: 1px solid rgba(255,255,255,.045);
            transition: all .2s ease;
        }
        .sidebar-group-summary::-webkit-details-marker { display: none; }
        .sidebar-group-summary:hover { background: rgba(255,255,255,.055); }
        .sidebar-group-title-wrap { display: flex; align-items: center; gap: .8rem; min-width: 0; }
        .sidebar-group-icon {
            width: 36px; height: 36px; min-width: 36px; border-radius: 12px; display: inline-flex;
            align-items: center; justify-content: center; background: rgba(255,255,255,.08); color: #fff;
        }
        .sidebar-group-title { display: block; color: #fff; font-weight: 800; line-height: 1.05; }
        .sidebar-group-count { display: inline-flex; align-items: center; justify-content: center; min-width: 1.65rem; height: 1.65rem; margin-top: .45rem; padding: 0 .45rem; border-radius: 999px; background: rgba(255,255,255,.09); color: rgba(255,255,255,.82); font-size: .72rem; font-weight: 700; }
        .sidebar-group-summary small { display: block; color: rgba(255,255,255,.55); margin-top: .15rem; white-space: normal; }
        .sidebar-group-arrow { color: rgba(255,255,255,.55); font-size: 1.05rem; transition: transform .2s ease; }
        .sidebar-group-body { padding-top: .55rem; }
        .sidebar-group .nav { padding: 0 .15rem 0 .15rem; }
        .sidebar-group .nav .nav-item .nav-link { min-height: 46px; }
        .sidebar-group .nav .nav-item .nav-link .menu-title { flex: 1 1 auto; }
        .sidebar-group .nav .nav-item .nav-link .sidebar-inline-badge { margin-inline-start: auto; }
        .sidebar-group-last .sidebar-group-body { padding-bottom: .25rem; }
        .sidebar-mini-note { border-radius: 16px; background: linear-gradient(135deg, rgba(255,255,255,.08), rgba(255,255,255,.03)); padding: .95rem 1rem; border: 1px solid rgba(255,255,255,.06); }
        .sidebar-icon-only .sidebar-quick-access,
        .sidebar-icon-only .sidebar-group-summary small,
        .sidebar-icon-only .sidebar-group-title,
        .sidebar-icon-only .sidebar-group-arrow,
        .sidebar-icon-only .sidebar-mini-note { display: none !important; }
        .sidebar-icon-only .sidebar-group { padding-left: .45rem; padding-right: .45rem; }
        .sidebar-icon-only .sidebar-group-summary { justify-content: center; padding: .7rem .4rem; }
        .sidebar-icon-only .sidebar-group-icon { margin: 0; }

        .btn-admin-icon, .btn-table-icon {
            width: 38px; height: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 12px; border: 1px solid var(--admin-border); background: var(--admin-surface); color: var(--admin-text);
            transition: all .2s ease;
        }
        .btn-admin-icon:hover, .btn-table-icon:hover { transform: translateY(-1px); text-decoration:none; }
        .btn-table-icon.btn-view:hover { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
        .btn-table-icon.btn-edit:hover { background:color-mix(in srgb, var(--admin-primary-soft) 72%, white); border-color:var(--admin-border); color:var(--admin-primary-dark); }
        .btn-table-icon.btn-delete:hover { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }
        .btn-table-icon.btn-values:hover { background:#eef2ff; border-color:#c7d2fe; color:#4338ca; }
        .btn-table-icon.btn-save:hover { background:#ecfdf5; border-color:#bbf7d0; color:#166534; }
        .btn-text-icon { display:inline-flex; align-items:center; gap:.45rem; }
        .table-action-group { display:inline-flex; gap:.45rem; flex-wrap:wrap; justify-content:flex-end; }
        .table-sort-btn { display:inline-flex; align-items:center; gap:.35rem; border:0; background:transparent; padding:0; font:inherit; color:inherit; font-weight:700; }
        .table-sort-btn:hover { color: var(--admin-primary-dark); }
        .admin-filter-actions { display:flex; gap:.75rem; align-items:flex-end; flex-wrap:nowrap; }
        .admin-filter-actions .btn { white-space: nowrap; min-height: 48px; }
        .admin-inline-update { display:flex; gap:.5rem; flex-wrap:nowrap; align-items:center; }
        .admin-inline-update .form-select { min-width: 150px; }
        .admin-wide-modal .modal-dialog { max-width: 760px; }
        .admin-wide-modal .modal-content { border: 1px solid var(--admin-border); border-radius: 1.25rem; box-shadow: var(--admin-shadow); overflow:hidden; }
        .admin-wide-modal .modal-header { background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface-alt) 55%, white) 0%, var(--admin-surface) 100%); border-bottom:1px solid var(--admin-border); }
        .admin-wide-modal .modal-footer { background:var(--admin-surface); border-top:1px solid color-mix(in srgb, var(--admin-border) 65%, white); }
        .admin-confirm-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter: blur(3px); z-index:1050; display:none; }
        .admin-confirm-backdrop.show { display:block; }
        .admin-confirm-modal { position:fixed; inset:0; z-index:1055; display:none; align-items:center; justify-content:center; padding:1rem; }
        .admin-confirm-modal.show { display:flex; }
        .admin-confirm-card { width:min(520px, 100%); background:var(--admin-surface); border-radius:1.25rem; border:1px solid var(--admin-border); box-shadow:var(--admin-shadow); overflow:hidden; }
        .admin-confirm-head { padding:1.15rem 1.25rem; border-bottom:1px solid var(--admin-border); background:linear-gradient(180deg, color-mix(in srgb, var(--admin-primary-soft) 72%, white) 0%, var(--admin-surface) 100%); }
        .admin-confirm-body { padding:1.25rem; color:var(--admin-muted); }
        .admin-confirm-actions { padding:1rem 1.25rem 1.25rem; display:flex; justify-content:flex-end; gap:.75rem; }
        .admin-inline-kpi-grid { display:grid; grid-template-columns: repeat(12,1fr); gap:1rem; }
        .admin-kpi-span-8 { grid-column: span 8; } .admin-kpi-span-4 { grid-column: span 4; }
        .admin-chart-card { padding:1.25rem; }
        .admin-mini-chart { display:grid; gap:.85rem; }
        .admin-bar-row { display:grid; grid-template-columns: 96px 1fr auto; gap:.75rem; align-items:center; }
        .admin-bar-track { height:12px; border-radius:999px; background: color-mix(in srgb, var(--admin-primary-soft) 65%, var(--admin-surface)); overflow:hidden; }
        .admin-bar-fill { height:100%; border-radius:999px; background:linear-gradient(135deg, var(--admin-primary), var(--admin-accent)); }
        .admin-donut-legend { display:grid; gap:.65rem; }
        .admin-donut-item { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
        .admin-donut-label { display:inline-flex; align-items:center; gap:.5rem; font-weight:700; }
        .admin-dot { width:.75rem; height:.75rem; border-radius:999px; display:inline-block; }
        .admin-dot.pending { background:color-mix(in srgb, var(--admin-primary-dark) 72%, white); } .admin-dot.processing { background:color-mix(in srgb, var(--admin-accent) 72%, white); } .admin-dot.completed { background:color-mix(in srgb, var(--admin-primary) 76%, white); } .admin-dot.cancelled { background:color-mix(in srgb, var(--admin-accent) 82%, #ef4444); }
        .admin-kpi-card { padding:1.25rem; min-height:unset; }
        .admin-form-grid-tight { row-gap:.85rem; }
        .admin-form-actions { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-top:1.25rem; padding:1rem 1.1rem; border:1px solid var(--admin-border); border-radius:1rem; background:linear-gradient(180deg, color-mix(in srgb, var(--admin-surface-alt) 48%, white) 0%, var(--admin-surface) 100%); }
        .admin-form-actions-copy { display:grid; gap:.25rem; }
        .admin-form-actions-title { font-weight:800; color:var(--admin-text); }
        .admin-form-actions-subtitle { color:var(--admin-muted); font-size:.84rem; line-height:1.5; }
        .admin-form-actions-buttons { display:flex; align-items:center; justify-content:flex-end; gap:.75rem; flex-wrap:wrap; }
        .admin-form-actions-buttons .btn { min-width: 160px; }
        @media (max-width: 767.98px) { .admin-form-actions { padding: .95rem 1rem; } .admin-form-actions-buttons { width:100%; } .admin-form-actions-buttons .btn { flex:1 1 220px; } }
        @media (max-width: 991.98px) { .admin-inline-kpi-grid { grid-template-columns: 1fr; } .admin-kpi-span-8, .admin-kpi-span-4 { grid-column: span 1; } }

        .form-text {
            color: var(--admin-muted);
            font-size: .82rem;
            line-height: 1.45;
            margin-top: .45rem;
        }
        .admin-filter-grid-categories { grid-template-columns: 2fr 1fr 1fr 1fr auto; }
        .admin-filter-grid-coupons { grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; }
        .admin-filter-grid-customers { grid-template-columns: 2fr 1fr auto; }

        .alert:not(.admin-flash) {
            border: 1px solid color-mix(in srgb, var(--admin-border) 86%, white);
            border-radius: 1rem;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--admin-text) 5%, transparent);
        }
        .alert:not(.admin-flash) a {
            color: inherit;
            font-weight: 700;
        }
        .admin-inline-actions {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
        }
        .admin-form-actions-compact {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .admin-form-actions-compact .btn {
            min-width: 160px;
            white-space: normal;
        }
        .admin-card .table-responsive {
            scrollbar-width: thin;
        }
        .admin-card .table-responsive + .pagination,
        .admin-card .table-responsive + nav {
            margin-top: 1rem;
        }
        @media (max-width: 767.98px) {
            .admin-form-actions-compact {
                width: 100%;
            }
            .admin-form-actions-compact .btn {
                flex: 1 1 220px;
            }
        }

        .admin-stat-value-sm { font-size: 1.4rem; line-height: 1.2; }
        .sidebar-inline-badge {
            margin-left: auto;
            min-width: 24px;
            height: 24px;
            padding: 0 .45rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            color: #fff;
            font-size: .72rem;
            font-weight: 800;
        }
        .sidebar-inline-badge-warn { background: color-mix(in srgb, var(--admin-primary) 18%, transparent); color: color-mix(in srgb, var(--admin-primary-soft) 72%, white); }
        .sidebar-inline-badge-success { background: color-mix(in srgb, var(--admin-primary) 24%, transparent); color: color-mix(in srgb, var(--admin-primary-soft) 78%, white); }
        .sidebar-mini-note {
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            padding: .9rem 1rem;
            color: rgba(255,255,255,.7);
            font-size: .84rem;
            line-height: 1.55;
            background: rgba(255,255,255,.03);
        }
        .admin-topbar-dropdown {
            border: 1px solid var(--admin-border);
            border-radius: 1rem;
            box-shadow: var(--admin-shadow);
            overflow: hidden;
        }
        .admin-search-panel,
        .admin-preview-box {
            border: 1px solid var(--admin-border);
            border-radius: 1rem;
            padding: 1rem;
            background: linear-gradient(180deg, var(--admin-surface), color-mix(in srgb, var(--admin-surface-alt) 22%, white));
            height: 100%;
        }
        .admin-search-item,
        .admin-list-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .8rem 0;
            border-bottom: 1px solid rgba(242,218,200,.8);
        }
        .admin-search-item:last-child,
        .admin-list-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .admin-search-item:first-child,
        .admin-list-row:first-child { padding-top: 0; }
        .admin-search-item {
            color: var(--admin-text);
            text-decoration: none;
            font-weight: 700;
        }
        .admin-search-item:hover { color: var(--admin-primary-dark); text-decoration: none; }
        .admin-bullet-list { padding-left: 1rem; }
        .admin-bullet-list li { margin-bottom: .55rem; color: var(--admin-muted); }
        .admin-bullet-list li:last-child { margin-bottom: 0; }
        .admin-search-inline { width: min(280px, 100%); }
        
        .table,
        .table td,
        .table th,
        .table thead th,
        .table tbody tr,
        .dataTables_wrapper .dataTables_paginate .paginate_button,
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input,
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple,
        .modal-content,
        .dropdown-menu {
            background-color: var(--admin-surface);
            border-color: var(--admin-border);
        }

        .table thead th,
        .admin-table thead th {
            background: linear-gradient(180deg, var(--admin-table-head-bg) 0%, color-mix(in srgb, var(--admin-table-head-bg) 72%, var(--admin-surface)) 100%);
        }

        .table-hover tbody tr:hover,
        .admin-table.table-hover tbody tr:hover,
        .table tbody tr:hover {
            background: color-mix(in srgb, var(--admin-row-hover-bg) 72%, var(--admin-surface));
        }

        .shadow,
        .shadow-sm,
        .shadow-lg,
        .card,
        .admin-card,
        .modal-content {
            box-shadow: var(--admin-shadow) !important;
        }

        .form-control,
        .form-select,
        .input-group-text {
            background: var(--admin-input-bg);
            border-color: var(--admin-border);
            color: var(--admin-text);
        }

.admin-card, .admin-card .card-body, .admin-card .card-header, .content-wrapper, .table-responsive, .table, .table td, .table th {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .table td .btn,
        .table th .btn,
        .admin-page-actions .btn,
        .d-inline-flex .btn,
        .d-flex .btn {
            white-space: nowrap;
        }
        .form-check-label,
        .admin-page-description,
        .text-muted,
        .small,
        .form-label,
        .card-title,
        .card-body p,
        .card-body small {
            overflow-wrap: anywhere;
        }
        @media (max-width: 1399.98px) {
            .admin-filter-grid-coupons { grid-template-columns: 1fr 1fr 1fr; }
            .admin-filter-grid-categories { grid-template-columns: 1fr 1fr 1fr; }
            .admin-filter-grid-customers { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 991.98px) {
            .admin-filter-grid-coupons,
            .admin-filter-grid-categories,
            .admin-filter-grid-customers { grid-template-columns: 1fr; }
        }

    
        .admin-confirm-kicker {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .45rem;
            padding: .35rem .7rem;
            border-radius: 999px;
            background: color-mix(in srgb, var(--admin-primary-soft) 72%, white);
            color: var(--admin-primary-dark);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .admin-confirm-subtitle {
            margin-top: .75rem;
            color: var(--admin-muted);
            font-size: .92rem;
            line-height: 1.65;
        }

        .admin-loading-state {
            position: relative;
            pointer-events: none;
        }

        .admin-loading-state::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: color-mix(in srgb, var(--admin-surface) 68%, transparent);
        }

        .admin-loading-inline {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }


        .settings-page .admin-card,
        .settings-page .admin-card-body,
        .settings-page .row,
        .settings-page [class*='col-'],
        .settings-page .rounded-4,
        .product-admin-page .card,
        .product-admin-page .card-body,
        .product-form-root .card,
        .product-form-root .card-body {
            min-width: 0;
        }

        .settings-page .admin-settings-section {
            padding: 1.1rem 1.15rem;
            border: 1px solid color-mix(in srgb, var(--admin-border) 82%, white);
            border-radius: 1.15rem;
            background: color-mix(in srgb, var(--admin-surface) 96%, white);
        }

        .settings-page .admin-settings-section h4 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--admin-text);
        }

        .settings-page .admin-switch-card {
            background: color-mix(in srgb, var(--admin-surface) 95%, white);
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .settings-page .admin-switch-card:hover {
            border-color: color-mix(in srgb, var(--admin-primary) 28%, white);
            box-shadow: 0 12px 26px color-mix(in srgb, var(--admin-primary) 7%, transparent);
            transform: translateY(-1px);
        }

        .settings-page .admin-switch-card .form-check-input {
            float: none;
        }

        .settings-page .admin-page-shell > .admin-card + .admin-card,
        .settings-page .admin-card + .admin-card {
            margin-top: 1.25rem;
        }

        .settings-page .form-control[readonly] {
            background: color-mix(in srgb, var(--admin-surface-alt) 30%, white);
        }

        .product-admin-page .table-responsive,
        .product-form-root .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .product-admin-page .admin-page-header {
            margin-bottom: 1.25rem;
        }

        .product-admin-page .product-toolbar-card,
        .product-form-root .product-form-header-card {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 1.15rem;
            box-shadow: 0 12px 30px color-mix(in srgb, var(--admin-text) 4%, transparent);
        }

        .product-admin-page .product-filter-heading,
        .product-admin-page .product-selection-heading,
        .product-form-root .product-form-header-copy {
            min-width: 0;
        }

        .product-form-root .product-form-header-card {
            padding: 1rem 1.1rem;
            margin-bottom: 1.25rem;
        }

        .product-form-root .product-form-header-card h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--admin-text);
        }


        /* Final QA polish pass: dashboard, notifications, analytics, growth, settings */
        .dashboard-overview-page .admin-jump-chip,
        .dashboard-overview-page .admin-overview-item,
        .dashboard-overview-page .admin-focus-pill,
        .dashboard-overview-page .admin-exec-stat,
        .dashboard-overview-page .admin-context-card,
        .dashboard-overview-page .admin-pulse-card,
        .dashboard-overview-page .admin-status-note,
        .dashboard-overview-page .admin-watch-item,
        .dashboard-overview-page .admin-insight-item,
        .dashboard-overview-page .admin-quick-action,
        .dashboard-overview-page .admin-depth-highlight,
        .dashboard-overview-page .admin-signal-card,
        .dashboard-overview-page .admin-smart-item,
        .dashboard-overview-page .admin-priority-card,
        .admin-inbox-panel,
        .admin-inbox-item,
        .gm-card,
        .gm-panel,
        .gm-soft,
        .growth-card,
        .growth-panel,
        .growth-focus,
        .growth-signal-card,
        .growth-lane-card,
        .growth-story-mini,
        .analytics-trust-card,
        .notification-page-head,
        .notification-soft-card,
        .notification-list-item,
        .notification-focus-card,
        .notification-collapsible,
        .notification-workspace-tab {
            background: color-mix(in srgb, var(--admin-surface) 96%, white);
            border-color: color-mix(in srgb, var(--admin-border) 88%, white);
        }

        .dashboard-overview-page .admin-dashboard-hero,
        .gm-hero,
        .growth-focus,
        .notification-page-head,
        .notification-focus-card,
        .settings-page .admin-page-header,
        .settings-page .admin-card:first-child {
            background: linear-gradient(
                135deg,
                color-mix(in srgb, var(--admin-primary-soft) 70%, white),
                color-mix(in srgb, var(--admin-surface) 96%, white)
            );
            border-color: color-mix(in srgb, var(--admin-primary) 16%, var(--admin-border));
        }

        .dashboard-overview-page .admin-priority-card.tone-danger,
        .dashboard-overview-page .admin-smart-item.tone-danger,
        .dashboard-overview-page .admin-watch-item.tone-danger,
        .dashboard-overview-page .admin-insight-item.danger,
        .growth-signal-card.danger,
        .analytics-trust-card.is-risk {
            background: linear-gradient(180deg, color-mix(in srgb, var(--admin-danger-soft) 72%, white), color-mix(in srgb, var(--admin-surface) 98%, white));
            border-color: color-mix(in srgb, var(--admin-danger) 18%, var(--admin-border));
        }

        .dashboard-overview-page .admin-priority-card.tone-warning,
        .dashboard-overview-page .admin-smart-item.tone-warning,
        .dashboard-overview-page .admin-watch-item.tone-warning,
        .dashboard-overview-page .admin-pulse-card.warning,
        .growth-signal-card.warn,
        .analytics-trust-card.is-warn {
            background: linear-gradient(180deg, color-mix(in srgb, var(--admin-warning-soft) 76%, white), color-mix(in srgb, var(--admin-surface) 98%, white));
            border-color: color-mix(in srgb, var(--admin-primary-dark) 18%, var(--admin-border));
        }

        .dashboard-overview-page .admin-priority-card.tone-success,
        .dashboard-overview-page .admin-smart-item.tone-success,
        .dashboard-overview-page .admin-watch-item.tone-success,
        .dashboard-overview-page .admin-pulse-card.success,
        .dashboard-overview-page .admin-signal-card.tone-success,
        .dashboard-overview-page .admin-depth-highlight.tone-success,
        .growth-signal-card.success,
        .analytics-trust-card.is-good {
            background: linear-gradient(180deg, color-mix(in srgb, var(--admin-success-soft) 82%, white), color-mix(in srgb, var(--admin-surface) 98%, white));
            border-color: color-mix(in srgb, var(--admin-success) 18%, var(--admin-border));
        }

        .dashboard-overview-page .admin-priority-card.tone-info,
        .dashboard-overview-page .admin-smart-item.tone-info,
        .dashboard-overview-page .admin-pulse-card.info,
        .dashboard-overview-page .admin-signal-card.tone-info,
        .dashboard-overview-page .admin-depth-highlight.tone-info,
        .analytics-trust-card.is-neutral {
            background: linear-gradient(180deg, color-mix(in srgb, var(--admin-info-soft) 82%, white), color-mix(in srgb, var(--admin-surface) 98%, white));
            border-color: color-mix(in srgb, var(--admin-info) 18%, var(--admin-border));
        }

        .dashboard-overview-page .admin-command-card__icon,
        .dashboard-overview-page .admin-reading-step strong,
        .dashboard-overview-page .admin-depth-highlight-icon,
        .dashboard-overview-page .admin-context-icon,
        .dashboard-overview-page .admin-watch-icon,
        .dashboard-overview-page .admin-insight-icon,
        .dashboard-overview-page .admin-quick-action-icon,
        .dashboard-overview-page .admin-priority-icon,
        .dashboard-overview-page .admin-smart-item-icon,
        .admin-inbox-icon,
        .notification-workspace-tab .icon {
            background: color-mix(in srgb, var(--admin-primary-soft) 68%, white);
            color: var(--admin-primary-dark);
        }

        .dashboard-overview-page .admin-depth-track,
        .growth-meter {
            background: color-mix(in srgb, var(--admin-surface-alt) 72%, white);
        }

        .dashboard-overview-page .admin-depth-fill.revenue,
        .growth-meter-fill {
            background: linear-gradient(90deg, var(--admin-primary), color-mix(in srgb, var(--admin-primary) 55%, white));
        }

        .dashboard-overview-page .admin-depth-fill.orders,
        .growth-story-card {
            background: linear-gradient(135deg, var(--admin-sidebar), color-mix(in srgb, var(--admin-sidebar) 76%, var(--admin-primary)));
        }

        .growth-kicker,
        .gm-kicker,
        .notification-page-head__meta,
        .notification-section-kicker {
            background: color-mix(in srgb, var(--admin-primary-soft) 70%, white);
            color: var(--admin-primary-dark);
        }

        .gm-nav a.active,
        .notification-center-nav a.active,
        .notification-center-nav a:hover,
        .notification-workspace-tab:hover {
            background: color-mix(in srgb, var(--admin-primary-soft) 68%, white);
            color: var(--admin-primary-dark);
            border-color: color-mix(in srgb, var(--admin-primary) 18%, var(--admin-border));
        }

        .gm-chip.on { background: color-mix(in srgb, var(--admin-success-soft) 86%, white); color: var(--admin-success); border-color: color-mix(in srgb, var(--admin-success) 22%, var(--admin-border)); }
        .gm-chip.off { background: color-mix(in srgb, var(--admin-danger-soft) 86%, white); color: var(--admin-danger); border-color: color-mix(in srgb, var(--admin-danger) 22%, var(--admin-border)); }
        .gm-chip.soft { background: color-mix(in srgb, var(--admin-surface-alt) 70%, white); color: var(--admin-text); border-color: color-mix(in srgb, var(--admin-border) 82%, white); }

        .analytics-empty,
        .gm-empty,
        .notification-collapsible .notification-collapsible-body {
            background: color-mix(in srgb, var(--admin-surface) 97%, white);
            border-color: color-mix(in srgb, var(--admin-border) 76%, white);
        }

        .settings-page .form-control,
        .settings-page .form-select,
        .settings-page .input-group-text {
            border-color: color-mix(in srgb, var(--admin-border) 92%, white);
        }

        .settings-page .form-control:focus,
        .settings-page .form-select:focus {
            border-color: color-mix(in srgb, var(--admin-primary) 32%, white);
            box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--admin-primary) 16%, transparent);
        }

        @media (max-width: 991.98px) {
            .settings-page .admin-settings-section {
                padding: 1rem;
            }

            .product-admin-page .product-toolbar-card .card-body,
            .product-form-root .product-form-header-card {
                padding: 1rem !important;
            }
        }

    
        @media (max-width: 1199.98px) {
            .admin-topbar-search {
                width: min(420px, 100%);
            }
        }

        @media (max-width: 991.98px) {
            .admin-topbar-shell {
                padding-inline: .9rem !important;
            }
        }
</style>

    @livewireStyles
    @stack('styles')
</head>

<body>
    <div class="container-scroller">
        @include('layouts.inc.admin.navbar')

        <div class="container-fluid page-body-wrapper">
            @include('layouts.inc.admin.sidebar')

            <div class="main-panel">
                <div class="content-wrapper">
                    @if (session('message'))
                        <div class="alert alert-success admin-flash admin-flash--success mb-4">
                            <div class="admin-flash-icon"><i class="mdi mdi-check-circle-outline"></i></div>
                            <div class="admin-flash-content">
                                <div class="admin-flash-title">{{ __('Update completed') }}</div>
                                <div class="admin-flash-subtitle">{{ session('message') }}</div>
                            </div>
                        </div>
                    @endif
                    @if (session('success'))
                        <div class="alert alert-success admin-flash admin-flash--success mb-4">
                            <div class="admin-flash-icon"><i class="mdi mdi-check-circle-outline"></i></div>
                            <div class="admin-flash-content">
                                <div class="admin-flash-title">{{ __('Changes saved successfully') }}</div>
                                <div class="admin-flash-subtitle">{{ session('success') }}</div>
                            </div>
                        </div>
                    @endif
                    @if (session('warning'))
                        <div class="alert alert-warning admin-flash admin-flash--warning mb-4">
                            <div class="admin-flash-icon"><i class="mdi mdi-alert-outline"></i></div>
                            <div class="admin-flash-content">
                                <div class="admin-flash-title">{{ __('Please review this note') }}</div>
                                <div class="admin-flash-subtitle">{{ session('warning') }}</div>
                            </div>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger admin-flash admin-flash--danger mb-4">
                            <div class="admin-flash-icon"><i class="mdi mdi-alert-circle-outline"></i></div>
                            <div class="admin-flash-content">
                                <div class="admin-flash-title">{{ __('Action needs attention') }}</div>
                                <div class="admin-flash-subtitle">{{ session('error') }}</div>
                            </div>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger admin-flash admin-flash--danger mb-4">
                            <div class="admin-flash-icon"><i class="mdi mdi-alert-circle-outline"></i></div>
                            <div class="admin-flash-content">
                                <div class="admin-flash-title">{{ __('Please review the highlighted fields') }}</div>
                                <div class="admin-flash-subtitle">{{ __('A few entries still need attention before this form can be saved.') }}</div>
                                <ul class="admin-flash-list">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @hasSection('content')
                        @yield('content')
                    @else
                        {{ $slot ?? '' }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('admin/vendors/js/vendor.bundle.base.js') }}"></script>
    <script src="{{ asset('admin/vendors/datatables.net-bs4/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('admin/js/off-canvas.js') }}"></script>
    <script src="{{ asset('admin/js/hoverable-collapse.js') }}"></script>
    <script src="{{ asset('admin/js/template.js') }}"></script>
    <script src="{{ asset('admin/js/settings.js') }}"></script>
    <script src="{{ asset('admin/js/todolist.js') }}"></script>
    <script src="{{ asset('admin/js/dashboard.js') }}"></script>
    <script src="{{ asset('admin/js/proBanner.js') }}"></script>

    @livewireScripts
    @stack('scripts')

    <div class="admin-confirm-backdrop" id="adminConfirmBackdrop"></div>
    <div class="admin-confirm-modal" id="adminConfirmModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="adminConfirmTitle" aria-describedby="adminConfirmMessage">
        <div class="admin-confirm-card">
            <div class="admin-confirm-head">
                <div>
                    <div class="admin-confirm-kicker">{{ __('Please review') }}</div>
                    <div class="fw-bold fs-5" id="adminConfirmTitle">{{ __('Confirm this action') }}</div>
                </div>
            </div>
            <div class="admin-confirm-body">
                <div id="adminConfirmMessage">{{ __('Are you sure you want to continue?') }}</div>
                <div class="admin-confirm-subtitle" id="adminConfirmSubtitle">{{ __('This change will be applied immediately after confirmation.') }}</div>
                <div class="admin-confirm-input-wrap" id="adminConfirmInputWrap" hidden>
                    <label class="form-label fw-semibold mt-3 mb-2" for="adminConfirmInput" id="adminConfirmInputLabel">{{ __('Type the required phrase to continue') }}</label>
                    <input type="text" class="form-control" id="adminConfirmInput" autocomplete="off">
                    <div class="small text-danger mt-2 d-none" id="adminConfirmInputError">{{ __('The typed phrase does not match the required value yet.') }}</div>
                </div>
            </div>
            <div class="admin-confirm-actions">
                <button type="button" class="btn btn-light border" id="adminConfirmCancel">{{ __('Keep editing') }}</button>
                <button type="button" class="btn btn-primary" id="adminConfirmOk">{{ __('Confirm and continue') }}</button>
            </div>
        </div>
    </div>

<script>

function adminConfirmAction(callback, options = {}) {
  const modal = document.getElementById('adminConfirmModal');
  const backdrop = document.getElementById('adminConfirmBackdrop');
  const ok = document.getElementById('adminConfirmOk');
  const cancel = document.getElementById('adminConfirmCancel');
  const title = document.getElementById('adminConfirmTitle');
  const body = document.getElementById('adminConfirmMessage');
  const subtitle = document.getElementById('adminConfirmSubtitle');
  const inputWrap = document.getElementById('adminConfirmInputWrap');
  const inputLabel = document.getElementById('adminConfirmInputLabel');
  const input = document.getElementById('adminConfirmInput');
  const inputError = document.getElementById('adminConfirmInputError');
  const expectedValue = (options.requiredInput || '').trim();

  title.textContent = options.title || '{{ __('Confirm this action') }}';
  body.textContent = options.message || '{{ __('Are you sure you want to continue?') }}';
  subtitle.textContent = options.subtitle || '{{ __('This change will be applied immediately after confirmation.') }}';
  ok.textContent = options.confirmLabel || '{{ __('Confirm and continue') }}';
  cancel.textContent = options.cancelLabel || '{{ __('Keep editing') }}';

  if (expectedValue) {
    inputWrap.hidden = false;
    inputLabel.textContent = options.requiredInputLabel || '{{ __('Type the required phrase to continue') }}';
    input.value = '';
    input.placeholder = options.requiredInputPlaceholder || expectedValue;
    inputError.classList.add('d-none');
  } else {
    inputWrap.hidden = true;
    input.value = '';
    input.placeholder = '';
    inputError.classList.add('d-none');
  }

  modal.classList.add('show');
  modal.setAttribute('aria-hidden', 'false');
  backdrop.classList.add('show');

  const cleanup = () => {
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    backdrop.classList.remove('show');
    ok.onclick = null;
    cancel.onclick = null;
    backdrop.onclick = null;
    document.removeEventListener('keydown', escapeHandler);
    input.removeEventListener('input', handleInput);
  };

  const handleInput = () => {
    if (!expectedValue) return;
    inputError.classList.toggle('d-none', input.value.trim() === expectedValue);
  };

  const escapeHandler = (event) => {
    if (event.key === 'Escape') cleanup();
  };

  cancel.onclick = cleanup;
  backdrop.onclick = cleanup;
  ok.onclick = function(){
    if (expectedValue && input.value.trim() !== expectedValue) {
      inputError.classList.remove('d-none');
      input.focus();
      return;
    }
    cleanup();
    callback && callback(input.value.trim());
  };

  input.addEventListener('input', handleInput);
  document.addEventListener('keydown', escapeHandler);

  if (expectedValue) {
    setTimeout(() => input.focus(), 0);
  }
}

document.addEventListener('submit', function (event) {
  const form = event.target;
  if (!form.matches('[data-confirm-message]')) return;
  if (form.dataset.confirmed === '1') { form.dataset.confirmed = '0'; return; }
  event.preventDefault();
  adminConfirmAction((typedValue) => {
    const targetSelector = form.getAttribute('data-confirm-input-target');
    if (targetSelector) {
      const targetField = form.querySelector(targetSelector);
      if (targetField) targetField.value = typedValue || '';
    }
    form.dataset.confirmed = '1';
    form.requestSubmit();
  }, {
    title: form.getAttribute('data-confirm-title'),
    message: form.getAttribute('data-confirm-message'),
    subtitle: form.getAttribute('data-confirm-subtitle'),
    confirmLabel: form.getAttribute('data-confirm-ok'),
    cancelLabel: form.getAttribute('data-confirm-cancel'),
    requiredInput: form.getAttribute('data-confirm-input-expected'),
    requiredInputLabel: form.getAttribute('data-confirm-input-label'),
    requiredInputPlaceholder: form.getAttribute('data-confirm-input-placeholder')
  });
});

@php
    $adminTranslations = [
        'Products' => __('Products'),
        'Manage products with fast search, bulk actions, inline updates, and CSV export.' => __('Manage products with fast search, bulk actions, inline updates, and CSV export.'),
        'Export CSV' => __('Export CSV'),
        'Add Product' => __('Add Product'),
        'Per Page' => __('Per Page'),
        'On this page' => __('On this page'),
        'Selected' => __('Selected'),
        'Filtered results' => __('Filtered results'),
        'Rows per page' => __('Rows per page'),
        'Currently visible rows' => __('Currently visible rows'),
        'Bulk action ready' => __('Bulk action ready'),
        'Products matching current filters' => __('Products matching current filters'),
        'Brand' => __('Brand'),
        'All Brands' => __('All Brands'),
        'Category' => __('Category'),
        'All Categories' => __('All Categories'),
        'Search' => __('Search'),
        'Reset' => __('Reset'),
        'Bulk Delete' => __('Bulk Delete'),
        'clear Selection' => __('Clear selection'),
        'Clear Selection' => __('Clear selection'),
        'Select this page' => __('Select this page'),
        'Actions' => __('Actions'),
        'Updated' => __('Updated'),
        'Status' => __('Status'),
        'Type' => __('Type'),
        'Quantity' => __('Quantity'),
        'Sale Price' => __('Sale Price'),
        'Base Price' => __('Base Price'),
        'Product' => __('Product'),
        'IMAGE' => __('Image'),
        'Image' => __('Image'),
        'Edit' => __('Edit'),
        'Delete' => __('Delete'),
        'Direction' => __('Direction'),
        'Sort by' => __('Sort by'),
        'Descending' => __('Descending'),
        'Ascending' => __('Ascending'),
        'Category name, slug, or description' => __('Category name, slug, or description'),
        'Customer-facing categories that can be browsed.' => __('Customer-facing categories that can be browsed.'),
        'Categories already linked to products.' => __('Categories already linked to products.'),
        'Temporarily hidden categories.' => __('Temporarily hidden categories.'),
        'All category records in the store.' => __('All category records in the store.'),
        'Products count' => __('Products count'),
        'No description yet.' => __('No description yet.'),
        'Order #, customer name, email, phone, coupon' => __('Order #, customer name, email, phone, coupon'),
        'Refunded' => __('Refunded'),
        'Net paid' => __('Net paid'),
        'Catalog / Attributes' => __('Catalog / Attributes'),
        'Product Attributes' => __('Product Attributes'),
        'Create and manage reusable product attributes and their values with cleaner search and coverage stats.' => __('Create and manage reusable product attributes and their values with cleaner search and coverage stats.'),
        'Attributes' => __('Attributes'),
        'With values' => __('With values'),
        'Values total' => __('Values total'),
        'Reusable attribute groups.' => __('Reusable attribute groups.'),
        'Attributes already filled with values.' => __('Attributes already filled with values.'),
        'All attribute values stored in the catalog.' => __('All attribute values stored in the catalog.'),
        'Add or Edit Attribute' => __('Add or Edit Attribute'),
        'Keep labels reusable for product variants or specification sections.' => __('Keep labels reusable for product variants or specification sections.'),
        'Search attributes' => __('Search attributes'),
        'Attribute Name' => __('Attribute Name'),
        'Update Attribute' => __('Update Attribute'),
        'Add Attribute' => __('Add Attribute'),
        'Values' => __('Values'),
        'Manage values' => __('Manage values'),
        'No attributes found.' => __('No attributes found.'),
        'Method' => __('Method'),
        'Payment' => __('Payment'),
        'Items' => __('Items'),
        'Total' => __('Total'),
        'Date' => __('Date'),
        'Customer' => __('Customer'),
        'Order' => __('Order'),
        'Promotion name' => __('Promotion name'),
        'Inactive' => __('Inactive'),
        'Active' => __('Active'),

        'Create Product' => __('Create Product'),
        'Edit Product' => __('Edit Product'),
        'Save Product' => __('Save Product'),
        'Saving product...' => __('Saving product...'),
        'Please wait while your changes are being processed.' => __('Please wait while your changes are being processed.'),
        'Ready to save' => __('Ready to save'),
        'Saved #' => __('Saved #'),
        'Last saved:' => __('Last saved:'),
        'Back' => __('Back'),
        'Save failed' => __('Save failed'),
        'Basic Information' => __('Basic Information'),
        'Main product data and description.' => __('Main product data and description.'),
        'Product Name' => __('Product Name'),
        'Barcode' => __('Barcode'),
        'Video URL' => __('Video URL'),
        'Category' => __('Category'),
        'Select Category' => __('Select Category'),
        'Select Brand' => __('Select Brand'),
        'Description' => __('Description'),
        'Pricing & Inventory' => __('Pricing & Inventory'),
        'Price, quantity, and stock settings.' => __('Price, quantity, and stock settings.'),
        'Base Price' => __('Base Price'),
        'Sale Price' => __('Sale Price'),
        'Quantity' => __('Quantity'),
        'Low Stock Threshold' => __('Low Stock Threshold'),
        'Stock Status' => __('Stock Status'),
        'Featured' => __('Featured'),
        'Images Manager' => __('Images Manager'),
        'Upload, preview, reorder, set main, and delete.' => __('Upload, preview, reorder, set main, and delete.'),
        'Product Images' => __('Product Images'),
        'Drag & drop images here' => __('Drag & drop images here'),
        'or click to browse multiple files' => __('or click to browse multiple files'),
        'Choose Images' => __('Choose Images'),
        'Existing Images' => __('Existing Images'),
        'Set Main' => __('Set Main'),
        'Main' => __('Main'),
        'Move' => __('Move'),
        'Drag cards to reorder' => __('Drag cards to reorder'),
        'Create Category' => __('Create Category'),
        'Edit Category' => __('Edit Category'),
        'Back to Categories' => __('Back to Categories'),
        'Save Category' => __('Save Category'),
        'Update Category' => __('Update Category'),
        'Cancel' => __('Cancel'),
        'Category name' => __('Category name'),
        'Category slug' => __('Category slug'),
        'Write a short description' => __('Write a short description'),
        'Media & Visibility' => __('Media & Visibility'),
        'Image' => __('Image'),
        'Choose File' => __('Choose File'),
        'No file chosen' => __('No file chosen'),
        'Preview' => __('Preview'),
        'Hide category' => __('Hide category'),
        'Meta Title' => __('Meta Title'),
        'Meta Keyword' => __('Meta Keyword'),
        'Meta Description' => __('Meta Description'),
        'Search attributes' => __('Search attributes'),
        'Add or Edit Value' => __('Add or Edit Value'),
        'Add Attribute' => __('Add Attribute'),
        'Update Attribute' => __('Update Attribute'),
        'Attribute Name' => __('Attribute Name'),
        'Color Values' => __('Color Values'),
        'Value' => __('Value'),
        'Enter value (e.g. Red)' => __('Enter value (e.g. Red)'),
        'No values found yet' => __('No values found yet'),
        'Save' => __('Save'),
        'Suppliers' => __('Suppliers'),
        'Create Supplier' => __('Create Supplier'),
        'Add supplier' => __('Add supplier'),
        'Update supplier details without affecting existing orders.' => __('Update supplier details without affecting existing orders.'),
        'Add a supplier for sourcing and purchase orders.' => __('Add a supplier for sourcing and purchase orders.'),
        'Search suppliers' => __('Search suppliers'),
        'Independent supplier' => __('Independent supplier'),
        'Contact' => __('Contact'),
        'linked items' => __('linked items'),
        'purchases' => __('purchases'),
        'Company' => __('Company'),
        'Email' => __('Email'),
        'Phone' => __('Phone'),
        'Address' => __('Address'),
        'Notes' => __('Notes'),
        'Country' => __('Country'),
        'Active supplier' => __('Active supplier'),
        'Save supplier' => __('Save supplier'),
        'Total users' => __('Total users'),
        'Customers' => __('Customers'),
        'Admins' => __('Admins'),
        'Buyers' => __('Buyers'),
        'Customer name or email' => __('Customer name or email'),
        'Role' => __('Role'),
        'All roles' => __('All roles'),
        'Showing :count user(s) on this page' => __('Showing :count user(s) on this page'),
        'Purchases' => __('Purchases'),
        'Create purchase' => __('Create purchase'),
        'Reference' => __('Reference'),
        'Supplier' => __('Supplier'),
        'Date' => __('Date'),
        'Total' => __('Total'),
        'Received' => __('Received'),
        'Inventory' => __('Inventory'),
        'Near expiry' => __('Near expiry'),
        'Low stock alert' => __('Low stock alert'),
        'Time' => __('Time'),
        'Item' => __('Item'),
        'Type' => __('Type'),
        'Change' => __('Change'),
        'Balance' => __('Balance'),
        'Reason' => __('Reason'),
        'Purchase in' => __('Purchase in'),
        'Order out' => __('Order out'),
        'Payments' => __('Payments'),
        'Created' => __('Created'),
        'Reference' => __('Reference'),
        'Amount' => __('Amount'),
        'Method' => __('Method'),
        'Bank Transfer' => __('Bank Transfer'),
        'Promotions' => __('Promotions'),
        'Add promotion' => __('Add promotion'),
        'All types' => __('All types'),
        'Apply filters' => __('Apply filters'),
        'Name' => __('Name'),
        'Priority' => __('Priority'),
        'Discount' => __('Discount'),
        'Create Promotion' => __('Create Promotion'),
        'Edit Promotion' => __('Edit Promotion'),
        'Type' => __('Type'),
        'Min. order' => __('Min. order'),
        'Max discount' => __('Max discount'),
        'Usage limit' => __('Usage limit'),
        'Starts at' => __('Starts at'),
        'Ends at' => __('Ends at'),
        'Coupon is active' => __('Coupon is active'),
        'How this coupon will behave' => __('How this coupon will behave'),
        'Optional internal label' => __('Optional internal label'),
        'Customers will enter this code during cart or checkout.' => __('Customers will enter this code during cart or checkout.'),
        'Useful when your team wants a readable internal title for the promotion.' => __('Useful when your team wants a readable internal title for the promotion.'),
        'Edit Coupon' => __('Edit Coupon'),
        'Create Coupon' => __('Create Coupon'),
        'Back to coupons' => __('Back to coupons'),
        'Search by name, slug, SKU, or barcode...' => __('Search by name, slug, SKU, or barcode...'),
        'Catalog / Attributes / Values' => __('Catalog / Attributes / Values'),
        'Order status' => __('Order status'),
        'Order Summary' => __('Order Summary'),
        'Subtotal' => __('Subtotal'),
        'Tax' => __('Tax'),
        'Coupon' => __('Coupon'),
        'Grand Total' => __('Grand Total'),
        'Customer notes' => __('Customer notes'),
        'Update Status' => __('Update Status'),
        'Allowed flow: Pending → Processing → Completed. Cancel can happen before completion.' => __('Allowed flow: Pending → Processing → Completed. Cancel can happen before completion.'),
        'Save status' => __('Save status'),
        'Refund' => __('Refund'),
        'Refundable balance:' => __('Refundable balance:'),
        'Refund reason' => __('Refund reason'),
        'Optional refund notes' => __('Optional refund notes'),
        'Record refund' => __('Record refund'),
        'This order is not currently eligible for a refund. Mark it paid/completed first, or it may already be fully refunded.' => __('This order is not currently eligible for a refund. Mark it paid/completed first, or it may already be fully refunded.'),
        'Pending' => __('Pending'),
        'Processing' => __('Processing'),
        'Completed' => __('Completed'),
        'Cancelled' => __('Cancelled'),
        'Order flow' => __('Order flow'),
        'Billing' => __('Billing'),
        'Shipping address' => __('Shipping address'),
        'Billing address is the same as the shipping address.' => __('Billing address is the same as the shipping address.'),
        'Refund History' => __('Refund History'),
        'Processed' => __('Processed'),
        'Branding' => __('Branding'),
        'White-label Settings' => __('White-label Settings'),
        'Store name' => __('Store name'),
        'Tagline' => __('Tagline'),
        'Hero title' => __('Hero title'),
        'Hero subtitle' => __('Hero subtitle'),
        'Brand colors' => __('Brand colors'),
        'Primary color' => __('Primary color'),
        'Accent color' => __('Accent color'),
        'Live preview ready' => __('Live preview ready'),
        'Primary action' => __('Primary action'),
        'Logo' => __('Logo'),
        'No logo uploaded yet.' => __('No logo uploaded yet.'),
        'Upload new logo' => __('Upload new logo'),
        'Logo path' => __('Logo path'),
        'Hero banner' => __('Hero banner'),
        'No hero banner uploaded yet.' => __('No hero banner uploaded yet.'),
        'Upload new banner' => __('Upload new banner'),
        'Hero banner path' => __('Hero banner path'),
        'Save branding' => __('Save branding'),
        'Shared theme' => __('Shared theme'),
        'Customer pages' => __('Customer pages'),
        'Admin pages' => __('Admin pages'),
        'Show current logo' => __('Show current logo'),
        'Show current banner' => __('Show current banner'),
        'Choose a color' => __('Choose a color'),

    ];
@endphp

const adminLocale = @json(app()->getLocale());
const adminTranslations = @json($adminTranslations ?? []);

function adminTranslateValue(value) {
  if (!value || adminLocale !== 'ar') return value;
  let result = String(value);
  const entries = Object.entries(adminTranslations).sort((a, b) => b[0].length - a[0].length);
  for (const [source, target] of entries) {
    if (!source || source === target) continue;
    if (result === source) return target;
    result = result.split(source).join(target);
  }
  return result;
}

function adminTranslateNodeText(node) {
  if (!node || !node.nodeValue || adminLocale !== 'ar') return;
  const original = node.nodeValue;
  const translated = adminTranslateValue(original);
  if (translated !== original) node.nodeValue = translated;
}

function adminTranslateElement(el) {
  if (!el || adminLocale !== 'ar') return;
  ['placeholder', 'title', 'aria-label', 'data-loading-text', 'data-confirm-message'].forEach((attr) => {
    if (el.hasAttribute && el.hasAttribute(attr)) {
      const translated = adminTranslateValue(el.getAttribute(attr));
      if (translated) el.setAttribute(attr, translated);
    }
  });
  if (el.tagName === 'INPUT' && ['button', 'submit'].includes((el.type || '').toLowerCase()) && el.value) {
    const translated = adminTranslateValue(el.value);
    if (translated) el.value = translated;
  }
  if (el.tagName === 'OPTION' && el.textContent) {
    const translated = adminTranslateValue(el.textContent);
    if (translated) el.textContent = translated;
  }
}

function adminTranslateContent(root = document.body) {
  if (adminLocale !== 'ar' || !root) return;
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      if (!node.parentElement) return NodeFilter.FILTER_REJECT;
      const tag = node.parentElement.tagName;
      if (['SCRIPT', 'STYLE', 'TEXTAREA'].includes(tag)) return NodeFilter.FILTER_REJECT;
      if (!node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
      return NodeFilter.FILTER_ACCEPT;
    }
  });
  const textNodes = [];
  while (walker.nextNode()) textNodes.push(walker.currentNode);
  textNodes.forEach(adminTranslateNodeText);
  root.querySelectorAll?.('*').forEach(adminTranslateElement);
}

document.addEventListener('DOMContentLoaded', () => {
  adminTranslateContent(document.body);
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === 1) adminTranslateContent(node);
        if (node.nodeType === 3) adminTranslateNodeText(node);
      });
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });
});

function adminApplyResidualTranslations() {
  if (adminLocale !== 'ar') return;
  const replaceString = (value) => {
    const trimmed = value.trim();
    if (!trimmed || !adminTranslations[trimmed]) return value;
    return value.replace(trimmed, adminTranslations[trimmed]);
  };
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
  const textNodes = [];
  while (walker.nextNode()) textNodes.push(walker.currentNode);
  textNodes.forEach((node) => {
    if (!node.parentElement) return;
    const tag = node.parentElement.tagName;
    if (['SCRIPT', 'STYLE'].includes(tag)) return;
    node.textContent = replaceString(node.textContent);
  });
  document.querySelectorAll('[placeholder],[title],[data-loading-text],[data-confirm-message]').forEach((el) => {
    ['placeholder', 'title', 'data-loading-text', 'data-confirm-message'].forEach((attr) => {
      const value = el.getAttribute(attr);
      if (value) el.setAttribute(attr, replaceString(value));
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  adminApplyResidualTranslations();

  if (window.bootstrap && bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"], [data-admin-tooltip]').forEach((element) => {
      if (!element.getAttribute('title') && element.dataset.adminTooltip) {
        element.setAttribute('title', element.dataset.adminTooltip);
      }
      bootstrap.Tooltip.getOrCreateInstance(element);
    });
  }

  document.querySelectorAll('form[data-submit-loading]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      const button = event.submitter || form.querySelector('[data-loading-text]');
      if (!button || button.dataset.loadingApplied === '1') return;

      const loadingText = button.getAttribute('data-loading-text') || '{{ __('Working...') }}';
      button.dataset.loadingApplied = '1';
      button.dataset.originalText = button.innerHTML;
      button.innerHTML = '<span class="admin-loading-inline"><span class="admin-loading-spinner"></span><span>' + loadingText + '</span></span>';
      button.disabled = true;
      form.classList.add('admin-panel-loading');
      const card = form.closest('.admin-card, .gx-card, .gx-panel, .gx-mini-panel, .rounded-4, .accordion-item');
      if (card) card.classList.add('admin-loading-state');
      Array.from(form.querySelectorAll('button, input, select, textarea')).forEach((field) => {
        if (field !== button) field.setAttribute('aria-disabled', 'true');
      });
    });
  });
});
</script>

</body>

</html>