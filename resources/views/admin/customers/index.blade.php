@extends('layouts.admin')

@section('title', __('Customers') . ' | ' . __('Admin Dashboard'))

@section('content')
<x-admin.page-header :kicker="__('People & access')" :title="__('Customers')" :description="__('Review registered users, search quickly, and control who gets admin access with safer role management.')" />

<div class="admin-page-shell" data-live-list>
<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total users'), 'value' => $stats['total'], 'copy' => __('All registered accounts in the platform.'), 'icon' => 'mdi-account-group-outline'],
        ['label' => __('Customers'), 'value' => $stats['customers'], 'copy' => __('Standard customer accounts.'), 'icon' => 'mdi-account-outline'],
        ['label' => __('Admins'), 'value' => $stats['admins'], 'copy' => __('Users with admin dashboard access.'), 'icon' => 'mdi-shield-account-outline'],
        ['label' => __('Buyers'), 'value' => $stats['buyers'], 'copy' => __('Accounts that have at least one order.'), 'icon' => 'mdi-cart-check'],
        ['label' => __('Repeat buyers'), 'value' => $stats['repeat_buyers'], 'copy' => __('Customers with two or more orders.'), 'icon' => 'mdi-account-sync-outline'],
        ['label' => __('Customer revenue'), 'value' => 'EGP '.number_format($stats['revenue'], 2), 'copy' => __('Gross order value linked to registered accounts.'), 'icon' => 'mdi-cash-multiple'],
    ] as $card)
        <div class="col-md-6 col-xl">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                <div class="admin-stat-label">{{ $card['label'] }}</div>
                <div class="admin-stat-value">{{ $card['value'] }}</div>
                <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="admin-filter-grid admin-filter-grid-customers" data-live-filter>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="search" name="search" data-live-search autocomplete="off" value="{{ $search }}" class="form-control" placeholder="{{ __('Customer name or email') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Role') }}</label>
                <select name="role" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All roles') }}</option>
                    <option value="0" @selected($role === '0')>{{ __('Customers') }}</option>
                    <option value="1" @selected($role === '1')>{{ __('Admins') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Order activity') }}</label>
                <select name="activity" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All accounts') }}</option>
                    <option value="buyers" @selected($activity === 'buyers')>{{ __('Buyers') }}</option>
                    <option value="no_orders" @selected($activity === 'no_orders')>{{ __('No orders yet') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Customer value') }}</label>
                <select name="value" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All customer values') }}</option>
                    <option value="repeat" @selected($value === 'repeat')>{{ __('Repeat buyers') }}</option>
                    <option value="high_value" @selected($value === 'high_value')>{{ __('Highest spend first') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                <select name="per_page" class="form-select" data-live-filter-control>
                    @foreach([12,24,48] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-primary btn-text-icon"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.customers.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.customers.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</div>

@include('admin.customers._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
