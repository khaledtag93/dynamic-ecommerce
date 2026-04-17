@extends('layouts.admin')

@section('title', $pageTitle ?? __('Notification Center'))

@section('content')
@include('admin.settings.notification-center.partials.styles')

<div class="notification-center-page">
    <div class="notification-page-head mb-4">
        <div class="notification-page-head__meta">{{ __('Phase 4') }}</div>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="notification-page-head__breadcrumbs">
                    <a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                    <span>/</span>
                    <a href="{{ route('admin.settings.notifications') }}">{{ __('Notification center') }}</a>
                    <span>/</span>
                    <strong>{{ $pageTitle ?? __('Overview') }}</strong>
                </div>
                <h1 class="notification-page-head__title">{{ $pageTitle ?? __('Notification Center') }}</h1>
                <p class="notification-page-head__copy mb-0">{{ $pageDescription ?? __('Monitor channel health, templates, automation rules, logs, and escalation recovery from a cleaner notification workspace.') }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.settings.whatsapp') }}" class="btn btn-light border">{{ __('WhatsApp channel') }}</a>
                <a href="{{ route('admin.settings.notifications.logs') }}" class="btn btn-outline-dark">{{ __('Logs & Retry') }}</a>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <div class="notification-section-kicker">{{ __('Notification operations hub') }}</div>
            <h2 class="admin-section-title mb-1">{{ $pageHeading ?? ($pageTitle ?? __('Notification Center')) }}</h2>
            <p class="admin-section-subtitle mb-0">{{ $pageIntro ?? __('Keep the notification workspace organized by task: overview, recovery, templates, automation, and diagnostics.') }}</p>
        </div>
        <span class="admin-chip">{{ __('Phase 4.7') }}</span>
    </div>

    <div class="notification-focus-strip">
        <div class="notification-focus-card">
            <div class="title">{{ __('Module flow') }}</div>
            <div class="admin-helper-text">{{ __('Open overview for the current status, move to logs for active recovery, edit templates separately, then use automation and diagnostics only when needed.') }}</div>
        </div>
        <div class="notification-focus-card">
            <div class="title">{{ __('Operator rule') }}</div>
            <div class="admin-helper-text">{{ __('Each page now serves one job only, so the team reads less, decides faster, and touches fewer heavy sections per task.') }}</div>
        </div>
        <div class="notification-focus-card">
            <div class="title">{{ __('Quick access') }}</div>
            <div class="admin-helper-text">{{ $quickAccessText ?? __('Use the sub-navigation below to jump directly into the exact notification task you want.') }}</div>
        </div>
    </div>

    <div class="notification-center-nav sticky-nav">
        @foreach($moduleLinks as $key => $item)
            <a href="{{ $item['route'] }}" @class(['active' => ($currentSection ?? 'overview') === $key])>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>

    @yield('notification-module-content')
</div>
@endsection
