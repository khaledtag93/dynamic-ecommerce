@extends('admin.settings.notification-center.layout')

@section('notification-module-content')

    <div class="notification-workspace-tabs">
        <a href="#notification-overview" class="notification-workspace-tab">
            <span class="icon"><i class="mdi mdi-view-dashboard-outline"></i></span>
            <span>
                <span class="label">{{ __('Overview') }}</span>
                <span class="copy">{{ __('Read the current health snapshot first before changing settings or opening recovery tools.') }}</span>
            </span>
        </a>
        <a href="#notification-settings" class="notification-workspace-tab">
            <span class="icon"><i class="mdi mdi-tune-variant"></i></span>
            <span>
                <span class="label">{{ __('Settings & channels') }}</span>
                <span class="copy">{{ __('Control the master switch, active channels, and event routing from one consistent control area.') }}</span>
            </span>
        </a>
        <a href="{{ route('admin.settings.notifications.logs') }}" class="notification-workspace-tab">
            <span class="icon"><i class="mdi mdi-history"></i></span>
            <span>
                <span class="label">{{ __('Logs & retry') }}</span>
                <span class="copy">{{ __('Move directly into recovery work whenever a dispatch, provider, or queue issue needs action.') }}</span>
            </span>
        </a>
    </div>

    @include('admin.settings.notification-center.partials.overview-main')

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="notification-soft-card">
                <div class="fw-semibold mb-2">{{ __('Next step: Logs & retry') }}</div>
                <div class="admin-helper-text mb-3">{{ __('Open the recovery workspace whenever a failed dispatch or WhatsApp provider issue needs action.') }}</div>
                <a href="{{ route('admin.settings.notifications.logs') }}" class="btn btn-outline-dark btn-sm">{{ __('Open logs & retry') }}</a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="notification-soft-card">
                <div class="fw-semibold mb-2">{{ __('Next step: Templates & test send') }}</div>
                <div class="admin-helper-text mb-3">{{ __('Keep customer-facing content edits separate from live recovery work and preview every change safely.') }}</div>
                <a href="{{ route('admin.settings.notifications.templates') }}" class="btn btn-outline-primary btn-sm">{{ __('Open templates') }}</a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="notification-soft-card">
                <div class="fw-semibold mb-2">{{ __('Next step: Automation & diagnostics') }}</div>
                <div class="admin-helper-text mb-3">{{ __('Use automation for policy design and diagnostics only when queue health or provider behavior needs deeper review.') }}</div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.settings.notifications.automation') }}" class="btn btn-outline-success btn-sm">{{ __('Open automation') }}</a>
                    <a href="{{ route('admin.settings.notifications.diagnostics') }}" class="btn btn-outline-warning btn-sm">{{ __('Open diagnostics') }}</a>
                </div>
            </div>
        </div>
    </div>

    @include('admin.settings.notification-center.partials.settings')
@endsection
