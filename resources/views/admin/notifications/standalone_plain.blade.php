<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Notifications') }}</title>
    <link rel="stylesheet" href="{{ asset('admin/vendors/mdi/css/materialdesignicons.min.css') }}">
    <style>
        :root {
            --bg: #f5f2fb;
            --surface: #ffffff;
            --border: #e8def6;
            --text: #1f1630;
            --muted: #6d5f86;
            --primary: #7c3aed;
            --primary-soft: #ede9fe;
            --danger: #dc2626;
            --shadow: 0 14px 32px rgba(31,22,48,.08);
        }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Arial, Helvetica, sans-serif; background: var(--bg); color: var(--text); }
        .page { max-width: 1120px; margin: 0 auto; padding: 28px 18px 42px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom: 22px; }
        .title { font-size: 32px; font-weight: 800; margin: 0 0 6px; }
        .subtitle { margin:0; color: var(--muted); line-height: 1.7; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; }
        .btn { display:inline-flex; align-items:center; gap:8px; min-height: 42px; padding: 0 16px; border-radius: 999px; border:1px solid var(--border); background:#fff; color:var(--text); text-decoration:none; font-weight:700; cursor:pointer; }
        .btn-primary { background: var(--primary); color:#fff; border-color: var(--primary); }
        .panel { background: var(--surface); border:1px solid var(--border); border-radius: 22px; box-shadow: var(--shadow); padding: 18px; }
        .alert { border-radius:16px; padding:14px 16px; margin-bottom:16px; }
        .alert-warning { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; }
        .alert-success { background:#ecfdf5; border:1px solid #a7f3d0; color:#166534; }
        .empty { text-align:center; padding: 48px 18px; }
        .empty i { font-size:56px; color: var(--primary); display:block; margin-bottom: 10px; }
        .list { display:flex; flex-direction:column; gap:14px; }
        .item { display:flex; justify-content:space-between; gap:16px; border:1px solid var(--border); border-radius:18px; padding:16px; background:#fff; }
        .item.unread { background: linear-gradient(180deg, #f6f2ff, #ffffff); }
        .item-main { display:flex; gap:14px; min-width:0; }
        .icon { width:44px; height:44px; flex:0 0 44px; border-radius:14px; background: var(--primary-soft); color: var(--primary); display:flex; align-items:center; justify-content:center; font-size:20px; }
        .content { min-width:0; }
        .row-title { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
        .row-title h3 { margin:0; font-size:18px; }
        .badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:800; background:var(--primary-soft); color:var(--primary); }
        .body { color: var(--muted); line-height:1.8; margin-bottom:10px; word-break:break-word; }
        .meta { display:flex; flex-wrap:wrap; gap:14px; color: var(--muted); font-size:13px; }
        .side { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start; }
        @media (max-width: 768px) {
            .topbar, .item { flex-direction:column; }
            .actions, .side { width:100%; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="topbar">
            <div>
                <h1 class="title">{{ __('Notifications') }}</h1>
                <p class="subtitle">{{ __('A simplified inbox page isolated from the heavy admin layout so notifications can always open reliably.') }}</p>
            </div>
            <div class="actions">
                <a class="btn" href="{{ route('admin.dashboard') }}"><i class="mdi mdi-view-dashboard-outline"></i>{{ __('Dashboard') }}</a>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary"><i class="mdi mdi-check-all"></i>{{ __('Mark all as read') }}</button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($loadError)
            <div class="alert alert-warning">{{ $loadError }}</div>
        @endif

        <div class="panel">
            @if($notifications->isEmpty())
                <div class="empty">
                    <i class="mdi mdi-bell-outline"></i>
                    <h2>{{ __('No notifications yet') }}</h2>
                    <p class="subtitle">{{ __('New admin activity will appear here once events start flowing.') }}</p>
                </div>
            @else
                <div class="list">
                    @foreach($notifications as $notification)
                        <div class="item {{ $notification['is_read'] ? '' : 'unread' }}">
                            <div class="item-main">
                                <div class="icon"><i class="mdi {{ $notification['icon'] ?: 'mdi-bell-outline' }}"></i></div>
                                <div class="content">
                                    <div class="row-title">
                                        <h3>{{ $notification['title'] }}</h3>
                                        @if(!$notification['is_read'])
                                            <span class="badge">{{ __('New') }}</span>
                                        @endif
                                    </div>
                                    <div class="body">{{ $notification['body'] }}</div>
                                    <div class="meta">
                                        <span><i class="mdi mdi-clock-outline"></i> {{ $notification['created_at_human'] }}</span>
                                        <span><i class="mdi mdi-tag-outline"></i> {{ $notification['type'] }}</span>
                                        <span><i class="mdi mdi-broadcast"></i> {{ $notification['channel'] }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="side">
                                @if(!empty($notification['action_url']))
                                    <a class="btn btn-primary" href="{{ $notification['action_url'] }}">{{ __('Open item') }}</a>
                                @endif
                                @if(!$notification['is_read'])
                                    <form method="POST" action="{{ route('admin.notifications.read', $notification['id']) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn">{{ __('Mark read') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</body>
</html>
