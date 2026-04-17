@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="notification-safe-shell">
    <style>
        .notification-safe-shell { display:grid; gap:1.5rem; }
        .notification-safe-hero,.notification-safe-card { background: var(--admin-surface); border:1px solid var(--admin-border); border-radius:24px; box-shadow:var(--admin-shadow); }
        .notification-safe-hero,.notification-safe-card { padding:1.35rem; }
        .notification-safe-kicker { color: var(--admin-primary-dark); font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
        .notification-safe-title { margin:.45rem 0 .35rem; font-size:clamp(1.55rem,2vw,2.05rem); font-weight:900; }
        .notification-safe-copy { margin:0; color:var(--admin-muted); line-height:1.8; max-width:860px; }
        .notification-safe-actions,.notification-safe-footer { display:flex; flex-wrap:wrap; gap:.75rem; margin-top:1rem; }
        .notification-safe-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; }
        .notification-safe-stat { padding:1rem 1.1rem; }
        .notification-safe-stat .label { color:var(--admin-muted); font-weight:700; margin-bottom:.45rem; }
        .notification-safe-stat .value { font-size:1.5rem; font-weight:900; }
        .notification-safe-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1.5rem; }
        .notification-safe-card h2 { font-size:1.05rem; font-weight:900; margin-bottom:.35rem; }
        .notification-safe-card p.section-copy { color:var(--admin-muted); margin-bottom:1rem; }
        .notification-safe-list { display:grid; gap:.85rem; }
        .notification-safe-item { padding:.95rem 1rem; border:1px solid var(--admin-border); border-radius:18px; background: color-mix(in srgb, var(--admin-surface) 92%, white); }
        .notification-safe-item__top { display:flex; justify-content:space-between; gap:1rem; margin-bottom:.35rem; }
        .notification-safe-item__title { font-weight:800; }
        .notification-safe-item__meta { color:var(--admin-primary-dark); font-size:.8rem; font-weight:800; }
        .notification-safe-item__desc { color:var(--admin-muted); font-size:.94rem; line-height:1.7; }
        @media (max-width: 991.98px){ .notification-safe-grid{ grid-template-columns:1fr; } }
    </style>

    <div class="notification-safe-hero">
        <div class="notification-safe-kicker">{{ __('Phase 4 · Notification operations') }}</div>
        <h1 class="notification-safe-title">{{ $pageHeading }}</h1>
        <p class="notification-safe-copy">{{ $pageDescription }}</p>
        <div class="notification-safe-actions">
            <a href="{{ route('admin.settings.notifications') }}" class="btn btn-light rounded-pill">{{ __('Overview') }}</a>
            <a href="{{ route('admin.notifications.index') }}" class="btn btn-primary rounded-pill">{{ __('Admin inbox') }}</a>
            @foreach($moduleLinks as $key => $item)
                <a href="{{ $item['route'] }}" class="btn {{ ($currentSection ?? '') === $key ? 'btn-outline-dark' : 'btn-light' }} rounded-pill">{{ $item['label'] }}</a>
            @endforeach
        </div>
    </div>

    <div class="notification-safe-summary">
        @foreach($secondaryCards as $card)
            <div class="notification-safe-stat notification-safe-card">
                <div class="label">{{ $card['label'] }}</div>
                <div class="value">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="notification-safe-grid">
        @foreach($moduleCards as $card)
            <div class="notification-safe-card">
                <h2>{{ $card['title'] }}</h2>
                <p class="section-copy">{{ __('This section is intentionally lighter so it always opens reliably inside the dashboard layout.') }}</p>
                <div class="notification-safe-list">
                    @forelse(($card['items'] ?? []) as $item)
                        <div class="notification-safe-item">
                            <div class="notification-safe-item__top">
                                <div class="notification-safe-item__title">{{ $item['title'] ?? __('Item') }}</div>
                                @if(!empty($item['meta']))
                                    <div class="notification-safe-item__meta">{{ $item['meta'] }}</div>
                                @endif
                            </div>
                            <div class="notification-safe-item__desc">{{ $item['description'] ?? __('No extra details available.') }}</div>
                        </div>
                    @empty
                        <div class="notification-safe-item">
                            <div class="notification-safe-item__title">{{ __('Nothing to show yet') }}</div>
                            <div class="notification-safe-item__desc">{{ __('No records were available for this section right now.') }}</div>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <div class="notification-safe-card">
        <h2>{{ __('Next actions') }}</h2>
        <p class="section-copy">{{ $quickAccessText ?? __('Use the navigation above to move between notification pages without reopening the heavy mixed workspace.') }}</p>
        <div class="notification-safe-footer">
            <a href="{{ route('admin.settings.notifications') }}" class="btn btn-light rounded-pill">{{ __('Back to overview') }}</a>
            <a href="{{ route('admin.notifications.index') }}" class="btn btn-primary rounded-pill">{{ __('Open admin inbox') }}</a>
        </div>
    </div>
</div>
@endsection
