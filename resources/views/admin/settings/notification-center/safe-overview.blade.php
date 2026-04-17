@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="notification-safe-shell">
    <style>
        .notification-safe-shell { display: grid; gap: 1.5rem; }
        .notification-safe-hero,
        .notification-safe-card,
        .notification-safe-table { background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: 24px; box-shadow: var(--admin-shadow); }
        .notification-safe-hero { padding: 1.5rem; }
        .notification-safe-kicker { color: var(--admin-primary-dark); font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
        .notification-safe-title { margin: .45rem 0 .4rem; font-size: clamp(1.65rem, 2vw, 2.15rem); font-weight: 900; }
        .notification-safe-copy { margin: 0; color: var(--admin-muted); max-width: 860px; line-height: 1.8; }
        .notification-safe-actions { display:flex; flex-wrap:wrap; gap:.75rem; margin-top: 1rem; }
        .notification-safe-summary { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1rem; }
        .notification-safe-stat { padding: 1.15rem; }
        .notification-safe-stat .label { color: var(--admin-muted); font-weight: 700; margin-bottom: .45rem; }
        .notification-safe-stat .value { font-size: 1.8rem; font-weight: 900; color: var(--admin-text); }
        .notification-safe-grid { display:grid; grid-template-columns: 1.05fr .95fr; gap: 1.5rem; }
        .notification-safe-card { padding: 1.25rem; }
        .notification-safe-card h2 { font-size: 1.05rem; font-weight: 900; margin-bottom: .35rem; }
        .notification-safe-card p.section-copy { color: var(--admin-muted); margin-bottom: 1rem; }
        .notification-safe-list { display:grid; gap: .85rem; }
        .notification-safe-item { padding: .95rem 1rem; border: 1px solid var(--admin-border); border-radius: 18px; background: color-mix(in srgb, var(--admin-surface) 92%, white); }
        .notification-safe-item__top { display:flex; align-items:center; justify-content:space-between; gap: 1rem; margin-bottom: .35rem; }
        .notification-safe-item__title { font-weight: 800; }
        .notification-safe-item__desc { color: var(--admin-muted); font-size: .94rem; line-height: 1.7; }
        .notification-safe-badge { display:inline-flex; align-items:center; border-radius: 999px; padding:.32rem .75rem; font-weight:800; font-size:.78rem; }
        .notification-safe-badge.enabled { background: color-mix(in srgb, #10b981 14%, white); color: #047857; }
        .notification-safe-badge.disabled { background: color-mix(in srgb, #ef4444 12%, white); color: #b91c1c; }
        .notification-safe-tags { display:flex; flex-wrap:wrap; gap:.45rem; margin-top:.65rem; }
        .notification-safe-tag { background: var(--admin-primary-soft); color: var(--admin-primary-dark); border-radius:999px; padding:.28rem .65rem; font-size:.78rem; font-weight:800; }
        .notification-safe-footer { display:flex; flex-wrap:wrap; gap:.75rem; }
        .notification-safe-footer a { text-decoration:none; }
        @media (max-width: 991.98px) { .notification-safe-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="notification-safe-hero">
        <div class="notification-safe-kicker">{{ __('Phase 4 · Notification operations') }}</div>
        <h1 class="notification-safe-title">{{ __('Notification Center') }}</h1>
        <p class="notification-safe-copy">{{ __('This overview page is intentionally lighter so the notification workspace opens reliably inside the dashboard layout. Use it as the main control hub, then jump into logs, templates, automation, or diagnostics only when needed.') }}</p>

        <div class="notification-safe-actions">
            @foreach($moduleLinks as $link)
                <a href="{{ $link['route'] }}" class="btn btn-{{ $link['style'] === 'primary' ? 'primary' : ($link['style'] === 'dark' ? 'outline-dark' : 'light') }} rounded-pill">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>

    @if($successMessage)
        <div class="alert alert-success rounded-4">{{ $successMessage }}</div>
    @endif

    <div class="notification-safe-summary">
        <div class="notification-safe-stat notification-safe-card">
            <div class="label">{{ __('Admin inbox') }}</div>
            <div class="value">{{ $summary['database_total'] ?? 0 }}</div>
            <div class="section-copy mb-0">{{ __('Unread: :count', ['count' => $summary['database_unread'] ?? 0]) }}</div>
        </div>
        <div class="notification-safe-stat notification-safe-card">
            <div class="label">{{ __('Dispatch logs') }}</div>
            <div class="value">{{ $summary['dispatch_total'] ?? 0 }}</div>
            <div class="section-copy mb-0">{{ __('Pending: :count', ['count' => $summary['dispatch_pending'] ?? 0]) }}</div>
        </div>
        <div class="notification-safe-stat notification-safe-card">
            <div class="label">{{ __('Failed dispatches') }}</div>
            <div class="value">{{ $summary['dispatch_failed'] ?? 0 }}</div>
            <div class="section-copy mb-0">{{ __('Logs that need review or retry.') }}</div>
        </div>
        <div class="notification-safe-stat notification-safe-card">
            <div class="label">{{ __('WhatsApp log volume') }}</div>
            <div class="value">{{ $summary['whatsapp_total'] ?? 0 }}</div>
            <div class="section-copy mb-0">{{ __('Failed: :count', ['count' => $summary['whatsapp_failed'] ?? 0]) }}</div>
        </div>
    </div>

    <div class="notification-safe-grid">
        <div class="notification-safe-card">
            <h2>{{ __('Channel status') }}</h2>
            <p class="section-copy">{{ __('Quick channel snapshot so the team can confirm what is enabled before opening the heavier settings pages.') }}</p>
            <div class="notification-safe-list">
                @foreach($channelCards as $channel)
                    <div class="notification-safe-item">
                        <div class="notification-safe-item__top">
                            <div class="notification-safe-item__title">{{ $channel['label'] }}</div>
                            <span class="notification-safe-badge {{ $channel['enabled'] ? 'enabled' : 'disabled' }}">{{ $channel['enabled'] ? __('Enabled') : __('Disabled') }}</span>
                        </div>
                        <div class="notification-safe-item__desc">{{ $channel['description'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="notification-safe-card">
            <h2>{{ __('Event coverage') }}</h2>
            <p class="section-copy">{{ __('Each event below shows which channels are currently active for it. This keeps the overview fast and avoids the heavy mixed workspace that was opening blank before.') }}</p>
            <div class="notification-safe-list">
                @foreach($eventCards as $event)
                    <div class="notification-safe-item">
                        <div class="notification-safe-item__top">
                            <div class="notification-safe-item__title">{{ $event['label'] }}</div>
                            <span class="notification-safe-badge {{ count($event['enabled_channels']) ? 'enabled' : 'disabled' }}">{{ count($event['enabled_channels']) ? __('Live') : __('No channels') }}</span>
                        </div>
                        <div class="notification-safe-item__desc">{{ $event['description'] }}</div>
                        <div class="notification-safe-tags">
                            @forelse($event['enabled_channels'] as $channelLabel)
                                <span class="notification-safe-tag">{{ $channelLabel }}</span>
                            @empty
                                <span class="notification-safe-tag">{{ __('Nothing enabled yet') }}</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="notification-safe-card">
        <h2>{{ __('Where to go next') }}</h2>
        <p class="section-copy">{{ __('Keep this page as the stable hub. Open the inbox when you want admin notifications, logs when something failed, templates for content work, and automation or diagnostics only for deeper operational changes.') }}</p>
        <div class="notification-safe-footer">
            <a href="{{ route('admin.notifications.index') }}" class="btn btn-primary rounded-pill">{{ __('Open admin inbox') }}</a>
            <a href="{{ route('admin.settings.notifications.logs') }}" class="btn btn-outline-dark rounded-pill">{{ __('Open logs & retry') }}</a>
            <a href="{{ route('admin.settings.notifications.templates') }}" class="btn btn-light rounded-pill">{{ __('Open templates') }}</a>
            <a href="{{ route('admin.settings.notifications.automation') }}" class="btn btn-light rounded-pill">{{ __('Open automation') }}</a>
            <a href="{{ route('admin.settings.notifications.diagnostics') }}" class="btn btn-light rounded-pill">{{ __('Open diagnostics') }}</a>
        </div>
    </div>
</div>
@endsection
