@extends('layouts.admin')

@section('title', __('Notifications') . ' | Admin')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 24px; overflow: hidden;">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-uppercase fw-bold mb-2" style="font-size:.8rem; letter-spacing:.08em; color: var(--admin-primary-dark);">{{ __('Admin Inbox') }}</div>
                            <h1 class="mb-2" style="font-size:2rem; font-weight:800; color:var(--admin-text);">{{ __('Notifications') }}</h1>
                            <p class="mb-0" style="color:var(--admin-muted); max-width:780px; line-height:1.8;">
                                {{ __('Review new admin updates, order changes, payment activity, and delivery events from one simple inbox.') }}
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-light border">{{ __('Dashboard') }}</a>
                            @if($unreadCount > 0)
                                <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-primary">{{ __('Mark all as read') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success rounded-4 mb-4">{{ session('success') }}</div>
    @endif

    @if($loadError)
        <div class="alert alert-warning rounded-4 mb-4">{{ $loadError }}</div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 24px; overflow: hidden;">
                <div class="card-body p-3 p-lg-4">
                    @if(empty($notifications))
                        <div class="text-center py-5 px-3">
                            <div class="mb-3" style="font-size:3rem; color:var(--admin-primary-dark);">
                                <i class="mdi mdi-bell-outline"></i>
                            </div>
                            <h4 class="fw-bold mb-2">{{ __('No notifications yet') }}</h4>
                            <p class="mb-0" style="color:var(--admin-muted);">
                                {{ __('New payment, delivery, and order updates will appear here once activity starts.') }}
                            </p>
                        </div>
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach($notifications as $notification)
                                <div class="border rounded-4 p-3 p-lg-4 {{ $notification['is_read'] ? 'bg-white' : '' }}"
                                     style="border-color: var(--admin-border) !important; background: {{ $notification['is_read'] ? 'var(--admin-surface)' : 'linear-gradient(180deg, color-mix(in srgb, var(--admin-primary-soft) 25%, white), color-mix(in srgb, var(--admin-surface) 94%, white))' }};">
                                    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-start">
                                        <div class="d-flex gap-3 align-items-start flex-grow-1" style="min-width:0;">
                                            <div class="rounded-4 d-inline-flex align-items-center justify-content-center"
                                                 style="width:46px; height:46px; flex:0 0 46px; background: color-mix(in srgb, var(--admin-primary) 12%, white); color: var(--admin-primary-dark); font-size:1.2rem;">
                                                <i class="mdi {{ $notification['icon'] }}"></i>
                                            </div>

                                            <div class="flex-grow-1" style="min-width:0;">
                                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                    <h5 class="mb-0 fw-bold">{{ $notification['title'] }}</h5>
                                                    @if(!$notification['is_read'])
                                                        <span class="badge rounded-pill text-dark" style="background: var(--admin-primary-soft);">{{ __('New') }}</span>
                                                    @endif
                                                </div>

                                                <div class="mb-2" style="color:var(--admin-muted); line-height:1.85; word-break:break-word;">
                                                    {{ $notification['body'] }}
                                                </div>

                                                <div class="d-flex flex-wrap gap-3 small" style="color:var(--admin-muted);">
                                                    <span><i class="mdi mdi-clock-outline me-1"></i>{{ $notification['created_at_human'] }}</span>
                                                    <span><i class="mdi mdi-tag-outline me-1"></i>{{ $notification['type'] }}</span>
                                                    <span><i class="mdi mdi-broadcast me-1"></i>{{ $notification['channel'] }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            @if(!empty($notification['action_url']))
                                                <a href="{{ $notification['action_url'] }}" class="btn btn-sm btn-primary">{{ __('Open item') }}</a>
                                            @endif
                                            @if(!$notification['is_read'])
                                                <form method="POST" action="{{ route('admin.notifications.read', $notification['id']) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-light border">{{ __('Mark read') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
