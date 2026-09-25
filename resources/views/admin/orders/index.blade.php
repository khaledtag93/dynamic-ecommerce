@extends('layouts.admin')

@section('title', __('Orders Management') . ' | Admin')

@php
    $sort = $filters['sort'] ?? 'created_at';
    $direction = $filters['direction'] ?? 'desc';
@endphp

@section('content')
<x-admin.page-header
    :kicker="__('Store operations')"
    :title="__('Orders Management')"
    :description="__('Review every order, filter by status or payment, update order flow fast, and keep revenue visibility in one place.')"
    :breadcrumbs="[
        ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('Operations')],
        ['label' => __('Orders'), 'current' => true],
    ]"
>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.returns.index') }}" class="btn btn-light border btn-text-icon">
            <i class="mdi mdi-keyboard-return"></i><span>{{ __('Returns & RMA') }}</span>
        </a>
        @if(auth()->user()?->hasPermission('customers.manage'))
        <a href="{{ route('admin.customers.index') }}" class="btn btn-light border btn-text-icon">
            <i class="mdi mdi-account-group-outline"></i><span>{{ __('Customers') }}</span>
        </a>
        @endif
        @if(auth()->user()?->hasPermission('notifications.view'))
        <a href="{{ route('admin.notifications.index') }}" class="btn btn-light border btn-text-icon">
            <i class="mdi mdi-bell-outline"></i><span>{{ __('Inbox') }}</span>
        </a>
        @endif
    </div>
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Pending'), 'value' => $stats['pending'], 'copy' => __('Orders waiting for processing.'), 'icon' => 'mdi-timer-sand'],
        ['label' => __('Processing'), 'value' => $stats['processing'], 'copy' => __('Orders currently being handled.'), 'icon' => 'mdi-progress-clock'],
        ['label' => __('Completed'), 'value' => $stats['completed'], 'copy' => __('Orders successfully completed.'), 'icon' => 'mdi-check-decagram-outline'],
        ['label' => __('Cancelled'), 'value' => $stats['cancelled'], 'copy' => __('Orders cancelled before completion.'), 'icon' => 'mdi-close-circle-outline'],
        ['label' => __('Paid total'), 'value' => 'EGP ' . number_format($stats['paid_total'], 2), 'copy' => __('Net collected after refunds.'), 'icon' => 'mdi-cash-check'],
        ['label' => __('Refunded total'), 'value' => 'EGP ' . number_format($stats['refunds_total'], 2), 'copy' => __('Recorded refunds across all orders.'), 'icon' => 'mdi-cash-refund'],
    ] as $card)
        <div class="col-md-6 col-xl-4">
            <x-admin.stat-card
                :label="$card['label']"
                :value="$card['value']"
                :icon="$card['icon']"
                :help="$card['copy']"
                class="h-100"
            />
        </div>
    @endforeach
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="{{ route('admin.orders.index') }}" data-live-filter class="admin-filter-grid">
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="search" name="search" data-live-search autocomplete="off" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Order #, customer name, email, phone, coupon') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Status') }}</label>
                <select name="status" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Payment') }}</label>
                <select name="payment_status" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All payment statuses') }}</option>
                    @foreach($paymentStatusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['payment_status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Method') }}</label>
                <select name="payment_method" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All methods') }}</option>
                    @foreach($paymentMethodOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['payment_method'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="queue" value="{{ $filters['queue'] }}">
            <div>
                <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                <select name="per_page" class="form-select" data-live-filter-control>
                    @foreach([12, 24, 48] as $size)
                        <option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $sort }}" data-live-default="created_at">
            <input type="hidden" name="direction" value="{{ $direction }}" data-live-default="desc">
            <div class="admin-filter-actions admin-filter-actions-wide">
                <button type="submit" class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Filtering...') }}"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.orders.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</div>

@include('admin.orders._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
