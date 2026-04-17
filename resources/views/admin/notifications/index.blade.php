@extends('layouts.admin')

@section('title', __('Admin Notifications'))

@section('content')
<div class="admin-page-shell admin-inbox-shell">
    <x-admin.page-header
        :kicker="__('Team & alerts')"
        :title="__('Notifications')"
        :description="__('Review unread admin updates, jump into the linked item quickly, and keep inbox-driven work inside the main dashboard experience.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Team & alerts')],
            ['label' => __('Notifications'), 'current' => true],
        ]"
    >
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.settings.notifications') }}" class="btn btn-light border btn-text-icon">
                <i class="mdi mdi-bell-cog-outline"></i><span>{{ __('Notification Center') }}</span>
            </a>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-primary btn-text-icon">
                        <i class="mdi mdi-check-all"></i><span>{{ __('Mark all as read') }}</span>
                    </button>
                </form>
            @endif
        </div>
    </x-admin.page-header>

    <style>
        .admin-inbox-shell { display:grid; gap:1.5rem; }
        .admin-inbox-panel { background: var(--admin-surface); border:1px solid var(--admin-border); border-radius:24px; box-shadow: var(--admin-shadow); }
        .admin-inbox-panel { padding:1rem; }
        .admin-inbox-list { display:grid; gap:1rem; }
        .admin-inbox-item { display:grid; grid-template-columns: 1fr auto; gap:1rem; padding:1rem; border:1px solid var(--admin-border); border-radius:20px; background: color-mix(in srgb, var(--admin-surface) 93%, white); }
        .admin-inbox-item.unread { background: linear-gradient(180deg, color-mix(in srgb, var(--admin-primary-soft) 55%, white), #fff); }
        .admin-inbox-main { display:flex; gap:.9rem; min-width:0; }
        .admin-inbox-icon { width:46px; height:46px; flex:0 0 46px; border-radius:15px; display:flex; align-items:center; justify-content:center; background: var(--admin-primary-soft); color: var(--admin-primary-dark); font-size:1.2rem; }
        .admin-inbox-text { min-width:0; }
        .admin-inbox-row { display:flex; flex-wrap:wrap; gap:.65rem; align-items:center; margin-bottom:.45rem; }
        .admin-inbox-row h3 { margin:0; font-size:1.02rem; font-weight:900; }
        .admin-inbox-badge { display:inline-flex; align-items:center; padding:.3rem .7rem; border-radius:999px; font-size:.75rem; font-weight:800; background:var(--admin-primary-soft); color:var(--admin-primary-dark); }
        .admin-inbox-body { color:var(--admin-muted); line-height:1.8; margin-bottom:.5rem; word-break:break-word; }
        .admin-inbox-meta { display:flex; flex-wrap:wrap; gap:1rem; color:var(--admin-muted); font-size:.85rem; }
        .admin-inbox-side { display:flex; flex-wrap:wrap; gap:.65rem; align-items:flex-start; }
        .admin-inbox-empty { text-align:center; padding:3.5rem 1rem; }
        .admin-inbox-empty i { font-size:3rem; color: var(--admin-primary); display:block; margin-bottom:.7rem; }
        .admin-inbox-summary-card { height: 100%; }
        @media (max-width: 991.98px) { .admin-inbox-item { grid-template-columns: 1fr; } .admin-inbox-side { width:100%; } }
    </style>

    <div class="row g-3">
        @foreach([
            ['label' => __('Unread'), 'value' => $unreadCount, 'copy' => __('Items still waiting for your review.'), 'icon' => 'mdi-bell-badge-outline'],
            ['label' => __('Read'), 'value' => $readCount, 'copy' => __('Items already acknowledged by the admin.'), 'icon' => 'mdi-check-decagram-outline'],
            ['label' => __('Action links'), 'value' => $actionableCount, 'copy' => __('Notifications with a direct jump into the related page.'), 'icon' => 'mdi-open-in-new'],
            ['label' => __('Latest update'), 'value' => $latestNotification['created_at_human'] ?? __('No updates yet'), 'copy' => $latestNotification['title'] ?? __('Waiting for the first notification to arrive.'), 'icon' => 'mdi-timeline-clock-outline'],
        ] as $card)
            <div class="col-12 col-md-6 col-xl-3">
                <div class="admin-card admin-stat-card admin-inbox-summary-card">
                    <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                    <div class="admin-stat-label">{{ $card['label'] }}</div>
                    <div class="admin-stat-value">{{ $card['value'] }}</div>
                    <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if(session('success'))
        <div class="alert alert-success rounded-4 mb-0">{{ session('success') }}</div>
    @endif

    @if($loadError)
        <div class="alert alert-warning rounded-4 mb-0">{{ $loadError }}</div>
    @endif

    <div class="admin-card">
        <div class="admin-card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h4 class="mb-1">{{ __('Inbox workspace') }}</h4>
                <div class="text-muted small">{{ __('Stay in the dashboard layout while reviewing admin activity, then open the linked item only when you need deeper action.') }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($unreadCount > 0)
                    <span class="admin-chip">{{ __('Unread items') }}: {{ $unreadCount }}</span>
                @endif
                @if($actionableCount > 0)
                    <span class="admin-chip">{{ __('Direct actions') }}: {{ $actionableCount }}</span>
                @endif
                <a href="{{ route('admin.dashboard') }}" class="btn btn-light border btn-sm btn-text-icon">
                    <i class="mdi mdi-view-dashboard-outline"></i><span>{{ __('Dashboard') }}</span>
                </a>
            </div>
        </div>
    </div>

    <div class="admin-inbox-panel">
        @if($notifications->isEmpty())
            <div class="admin-inbox-empty">
                <i class="mdi mdi-bell-outline"></i>
                <h2>{{ __('No notifications yet') }}</h2>
                <p class="text-muted mb-0">{{ __('New admin activity will appear here once events start flowing.') }}</p>
            </div>
        @else
            <div class="admin-inbox-list">
                @foreach($notifications as $notification)
                    <div class="admin-inbox-item {{ $notification['is_read'] ? '' : 'unread' }}">
                        <div class="admin-inbox-main">
                            <div class="admin-inbox-icon"><i class="mdi {{ $notification['icon'] ?: 'mdi-bell-outline' }}"></i></div>
                            <div class="admin-inbox-text">
                                <div class="admin-inbox-row">
                                    <h3>{{ $notification['title'] }}</h3>
                                    @if(! $notification['is_read'])
                                        <span class="admin-inbox-badge">{{ __('New') }}</span>
                                    @endif
                                </div>
                                <div class="admin-inbox-body">{{ $notification['body'] }}</div>
                                <div class="admin-inbox-meta">
                                    <span><i class="mdi mdi-clock-outline"></i> {{ $notification['created_at_human'] }}</span>
                                    <span><i class="mdi mdi-tag-outline"></i> {{ $notification['type'] }}</span>
                                    <span><i class="mdi mdi-broadcast"></i> {{ $notification['channel'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="admin-inbox-side">
                            @if(!empty($notification['action_url']))
                                <a class="btn btn-primary rounded-pill" href="{{ $notification['action_url'] }}">{{ __('Open item') }}</a>
                            @endif
                            @if(! $notification['is_read'])
                                <form method="POST" action="{{ route('admin.notifications.read', $notification['id']) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-light rounded-pill">{{ __('Mark read') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
