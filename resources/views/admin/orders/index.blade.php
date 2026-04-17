@extends('layouts.admin')

@section('title', __('Orders Management') . ' | Admin')

@php
    $sort = $filters['sort'] ?? 'created_at';
    $direction = $filters['direction'] ?? 'desc';
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'payment_status' => $filters['payment_status'] ?? null,
            'payment_method' => $filters['payment_method'] ?? null,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.orders.index', $query);
    };
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
        <a href="{{ route('admin.customers.index') }}" class="btn btn-light border btn-text-icon">
            <i class="mdi mdi-account-group-outline"></i><span>{{ __('Customers') }}</span>
        </a>
        <a href="{{ route('admin.notifications.index') }}" class="btn btn-light border btn-text-icon">
            <i class="mdi mdi-bell-outline"></i><span>{{ __('Inbox') }}</span>
        </a>
    </div>
</x-admin.page-header>

<div class="admin-page-shell">
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
        <form method="GET" data-submit-loading class="admin-filter-grid">
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Order #, customer name, email, phone, coupon') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Payment') }}</label>
                <select name="payment_status" class="form-select">
                    <option value="">{{ __('All payment statuses') }}</option>
                    @foreach($paymentStatusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['payment_status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Method') }}</label>
                <select name="payment_method" class="form-select">
                    <option value="">{{ __('All methods') }}</option>
                    @foreach($paymentMethodOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['payment_method'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="admin-filter-actions admin-filter-actions-wide">
                <button type="submit" class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Filtering...') }}"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-1">{{ __('Orders list') }}</h4>
                <div class="text-muted small">{{ __('Showing :count order(s) on this page.', ['count' => $orders->count()]) }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if($filters['status'] || $filters['payment_status'] || $filters['payment_method'] || $filters['search'])
                    <span class="admin-chip">{{ __('Filtered results') }}</span>
                @endif
                <a href="{{ route('admin.coupons.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-ticket-percent-outline"></i><span>{{ __('Coupons') }}</span></a>
                <a href="{{ route('admin.customers.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-account-group-outline"></i><span>{{ __('Customers') }}</span></a>
            </div>
        </div>

        @if($orders->count())
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><a class="table-sort-btn" href="{{ $sortLink('order_number') }}">{{ __('Order') }} @if($sort === 'order_number') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('customer_name') }}">{{ __('Customer') }} @if($sort === 'customer_name') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('status') }}">{{ __('Status') }} @if($sort === 'status') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('payment_status') }}">{{ __('Payment') }} @if($sort === 'payment_status') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('items_count') }}">{{ __('Items') }} @if($sort === 'items_count') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('grand_total') }}">{{ __('Total') }} @if($sort === 'grand_total') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" href="{{ $sortLink('created_at') }}">{{ __('Date') }} @if($sort === 'created_at') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $order->order_number }}</div>
                                    <div class="text-muted small">{{ $order->payment_method_label }}</div>
                                    @if($order->coupon_code)
                                        <div class="mt-2"><span class="admin-coupon-code">{{ $order->coupon_code }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $order->customer_name }}</div>
                                    <div class="text-muted small">{{ $order->customer_email }}</div>
                                    <div class="text-muted small">{{ $order->customer_phone }}</div>
                                </td>
                                <td>
                                    <span class="badge admin-status-badge {{ $order->status_badge_class }} mb-2">{{ $order->status_label }}</span>
                                    <form method="POST" action="{{ route('admin.orders.quick-status', $order) }}" class="d-flex gap-2 flex-wrap align-items-center" data-submit-loading>
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="form-select form-select-sm admin-inline-select">
                                            @foreach($statusOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($order->status === $value) @disabled(!$order->canTransitionTo($value) && $order->status !== $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn-table-icon btn-save" title="{{ __('Save status') }}" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-check"></i></button>
                                    </form>
                                </td>
                                <td>
                                    <span class="badge admin-status-badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span>
                                    @if((float) $order->refund_total > 0)
                                        <div class="text-muted small mt-2">{{ __('Refunded') }}: EGP {{ number_format($order->refund_total, 2) }}</div>
                                    @endif
                                    <div class="text-muted small mt-1">{{ __('Net paid') }}: EGP {{ number_format(max(0, (float) $order->grand_total - (float) $order->refund_total), 2) }}</div>
                                </td>
                                <td>{{ $order->items_count }}</td>
                                <td class="fw-bold">EGP {{ number_format($order->grand_total, 2) }}</td>
                                <td>
                                    <div>{{ optional($order->placed_at)->format('d M Y') ?: $order->created_at->format('d M Y') }}</div>
                                    <div class="text-muted small">{{ $order->created_at->format('h:i A') }}</div>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="btn-table-icon btn-view" title="{{ __('View order') }}"><i class="mdi mdi-eye-outline"></i></a>
                                        @if($order->status === \App\Models\Order::STATUS_CANCELLED)
                                            <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" data-submit-loading data-confirm-message="{{ __('Delete this cancelled order permanently?') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-table-icon btn-delete" title="{{ __('Delete order') }}" data-loading-text="{{ __('Deleting...') }}"><i class="mdi mdi-trash-can-outline"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="admin-empty-state">
                <div class="admin-empty-icon"><i class="mdi mdi-package-variant"></i></div>
                <h4 class="fw-bold mb-2">{{ __('No orders found') }}</h4>
                <p class="text-muted mb-0">{{ __('Try clearing the filters or wait until a new order is placed.') }}</p>
            </div>
        @endif

        @if($orders->hasPages())
            <div class="mt-4 d-flex justify-content-center admin-pagination-wrap">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
</div>
@endsection
