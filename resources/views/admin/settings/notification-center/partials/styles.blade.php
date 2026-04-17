<style>

    .notification-page-head {
        background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 96%, white), color-mix(in srgb, var(--admin-primary-soft) 42%, white));
        border: 1px solid color-mix(in srgb, var(--admin-border) 88%, white);
        border-radius: 1.25rem;
        padding: 1.2rem 1.25rem;
        box-shadow: 0 14px 28px color-mix(in srgb, var(--admin-text) 5%, transparent);
    }
    .notification-page-head__meta {
        display: inline-flex;
        margin-bottom: .7rem;
        padding: .3rem .7rem;
        border-radius: 999px;
        background: color-mix(in srgb, var(--admin-primary-soft) 70%, white);
        color: var(--admin-primary-dark);
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .notification-page-head__breadcrumbs {
        display: flex;
        gap: .5rem;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: .45rem;
        font-size: .85rem;
        color: var(--admin-muted);
    }
    .notification-page-head__breadcrumbs a { color: inherit; text-decoration: none; }
    .notification-page-head__title { margin: 0 0 .35rem; font-size: clamp(1.5rem, 2vw, 2rem); font-weight: 800; }
    .notification-page-head__copy { color: var(--admin-muted); max-width: 70rem; }

    .notification-center-nav { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.25rem; }
    .notification-center-nav a {
        text-decoration: none;
        padding: .65rem 1rem;
        border-radius: 999px;
        background: color-mix(in srgb, var(--admin-surface) 96%, white);
        border: 1px solid color-mix(in srgb, var(--admin-border) 88%, white);
        color: inherit;
        font-weight: 700;
        font-size: .92rem;
        transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
    }
    .notification-center-nav a:hover,
    .notification-center-nav a.active {
        background: color-mix(in srgb, var(--admin-primary-soft) 70%, white);
        border-color: color-mix(in srgb, var(--admin-primary) 18%, var(--admin-border));
        color: var(--admin-primary-dark);
        transform: translateY(-1px);
    }
    .notification-section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .notification-soft-card,
    .notification-list-item,
    .notification-collapsible {
        background: color-mix(in srgb, var(--admin-surface) 97%, white);
        border: 1px solid color-mix(in srgb, var(--admin-border) 88%, white);
        border-radius: 1rem;
        box-shadow: 0 10px 24px color-mix(in srgb, var(--admin-text) 4%, transparent);
    }
    .notification-soft-card { padding: 1rem; height: 100%; }
    .notification-list { display: grid; gap: .75rem; }
    .notification-list-item { padding: 1rem; }
    .notification-mini-label { font-size: .8rem; color: var(--admin-muted); margin-bottom: .15rem; }
    .notification-center-page .notification-switch { display: inline-flex; align-items: center; gap: .65rem; row-gap: .35rem; flex-wrap: wrap; padding-left: 0; margin-bottom: 0; min-height: 1.5rem; }
    .notification-center-page .notification-switch .form-check-input { float: none; margin: 0; flex: 0 0 auto; }
    .notification-center-page .notification-switch .form-check-label { margin: 0; line-height: 1.35; }
    .notification-center-page .notification-switch .small { flex-basis: 100%; width: 100%; margin-inline-start: 2.35rem; }
    html[dir="rtl"] .notification-center-page .notification-switch { flex-direction: row-reverse; justify-content: flex-end; }
    html[dir="rtl"] .notification-center-page .notification-switch .small { margin-inline-start: 0; margin-inline-end: 2.35rem; text-align: right; }
    .notification-section-kicker { font-size: .8rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--admin-primary-dark); margin-bottom: .45rem; }

    .notification-center-page .row > [class*='col-'],
    .notification-center-page .admin-card,
    .notification-center-page .admin-card-body,
    .notification-center-page .accordion-item,
    .notification-center-page .accordion-button,
    .notification-center-page .accordion-body { min-width: 0; }
    .notification-center-page [id^="notification-"] { scroll-margin-top: 6rem; }
    .notification-center-page .table-responsive,
    .notification-center-page .admin-table-wrap { -webkit-overflow-scrolling: touch; }
    .notification-center-page .admin-chip,
    .notification-center-page .badge,
    .notification-center-page .btn,
    .notification-center-page .fw-semibold,
    .notification-center-page .admin-helper-text { overflow-wrap: anywhere; }
    .notification-center-page .accordion-button { align-items: flex-start; }
    .notification-center-page .accordion-button::after { margin-top: .1rem; }
    .notification-center-page .btn i { font-size: 1rem; }
    .notification-center-page .notification-workspace-tab { text-decoration:none; }
    .notification-center-page .notification-workspace-tab .copy { display:block; }
    .notification-center-page .admin-chip { white-space: normal; }

    .notification-center-nav.sticky-nav {
        position: sticky;
        top: 5.25rem;
        z-index: 10;
        padding: .85rem;
        background: color-mix(in srgb, var(--admin-bg) 88%, white);
        backdrop-filter: blur(10px);
        border: 1px solid color-mix(in srgb, var(--admin-border) 78%, white);
        border-radius: 1rem;
    }
    .notification-focus-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    .notification-focus-card {
        background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 98%, white), color-mix(in srgb, var(--admin-primary-soft) 28%, white));
        border: 1px solid color-mix(in srgb, var(--admin-primary) 14%, var(--admin-border));
        border-radius: 1rem;
        padding: 1rem;
        min-width: 0;
    }
    .notification-focus-card .title { font-weight: 700; margin-bottom: .3rem; }
    .notification-collapsible { margin-bottom: 1.25rem; overflow: hidden; }
    .notification-collapsible summary {
        list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.1rem; font-weight: 700;
        background: color-mix(in srgb, var(--admin-primary-soft) 42%, white);
        border-bottom: 1px solid color-mix(in srgb, var(--admin-border) 76%, white);
    }
    .notification-collapsible summary::-webkit-details-marker { display: none; }
    .notification-collapsible summary .meta { font-size: .82rem; font-weight: 600; color: var(--admin-muted); }
    .notification-collapsible .notification-collapsible-body { padding: 1rem; background: color-mix(in srgb, var(--admin-surface) 98%, white); }
    .notification-collapsible[open] summary { background: color-mix(in srgb, var(--admin-primary-soft) 64%, white); }

    .notification-workspace-tabs { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin: 1.25rem 0; }
    .notification-workspace-tab {
        display:flex; align-items:flex-start; gap:.9rem; padding:1rem 1.05rem; border-radius:1rem;
        border:1px solid color-mix(in srgb, var(--admin-border) 84%, white);
        background:linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 98%, white), color-mix(in srgb, var(--admin-primary-soft) 24%, white));
        color:inherit; text-decoration:none; min-width:0;
        box-shadow:0 10px 24px color-mix(in srgb, var(--admin-text) 4%, transparent);
        transition: transform .18s ease, background .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .notification-workspace-tab:hover {
        text-decoration:none; border-color:color-mix(in srgb, var(--admin-primary) 18%, var(--admin-border));
        background:color-mix(in srgb, var(--admin-primary-soft) 70%, white); color:var(--admin-primary-dark); transform: translateY(-1px);
    }
    .notification-workspace-tab .icon {
        width:2.6rem; height:2.6rem; border-radius:.85rem; display:inline-flex; align-items:center; justify-content:center;
        background:color-mix(in srgb, var(--admin-surface) 98%, white);
        border:1px solid color-mix(in srgb, var(--admin-primary) 16%, var(--admin-border));
        color:var(--admin-primary); flex:0 0 auto; font-size:1.15rem;
    }
    .notification-workspace-tab .label { display:block; font-weight:800; margin-bottom:.2rem; }
    .notification-workspace-tab .copy { display:block; color:var(--admin-muted); font-size:.88rem; line-height:1.55; }

    @media (max-width: 991.98px) {
        .notification-focus-strip { grid-template-columns: 1fr; }
        .notification-center-nav.sticky-nav { top: 4.75rem; }
        .notification-workspace-tabs { grid-template-columns:1fr; }
    }

    @media (max-width: 767.98px) {
        .notification-center-page .notification-center-nav a { width: 100%; justify-content: center; display: inline-flex; }
        .notification-center-page .table-responsive > .table,
        .notification-center-page .admin-table-wrap > .table,
        .notification-center-page .table-responsive > .admin-table,
        .notification-center-page .admin-table-wrap > .admin-table { min-width: 760px; }
        .notification-center-page .accordion-button { padding: .95rem 1rem; }
        .notification-center-page .accordion-body { padding: 1rem; }
    }
</style>
