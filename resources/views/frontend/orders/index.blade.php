@extends('layouts.app')

@section('title', __('My Orders') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell" data-live-list>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
            <div>
                <div class="text-uppercase small text-muted fw-bold">{{ __('Account') }}</div>
                <h1 class="lc-section-title mb-1">{{ __('My Orders') }}</h1>
                <p class="text-muted mb-0">{{ __('Track every order, check payment progress, and follow delivery updates from one place.') }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('notifications.index') }}" class="btn lc-btn-soft"><i class="bi bi-bell me-2"></i>{{ __('Notifications') }}</a>
                <a href="{{ route('frontend.home') }}" class="btn lc-btn-soft"><i class="bi bi-bag me-2"></i>{{ __('Continue shopping') }}</a>
            </div>
        </div>

        @include('frontend.account.partials.navigation')
        <form method="GET" action="{{ route('orders.index') }}" data-live-filter class="d-none"></form>
        @include('frontend.orders._results')
        <div class="small mt-2 text-center" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('orders.index') }}" class="small d-block text-center" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</section>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
