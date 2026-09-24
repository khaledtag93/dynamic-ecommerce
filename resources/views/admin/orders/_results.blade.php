@php
    $sort = $filters['sort'] ?? 'created_at';
    $direction = $filters['direction'] ?? 'desc';
    $canManageOrders = auth()->user()?->hasPermission('orders.manage') ?? false;
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'payment_status' => $filters['payment_status'] ?? null,
            'payment_method' => $filters['payment_method'] ?? null,
            'queue' => $filters['queue'] ?? null,
            'per_page' => $filters['per_page'] ?? 12,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.orders.index', $query);
    };
@endphp

<div data-live-results aria-busy="false">
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <h4 class="mb-1">{{ __('Order operations') }}</h4>
                <p class="text-muted small mb-0">{{ __('Jump directly into orders that need operational attention.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.orders.index', ['queue' => 'action']) }}" data-live-link class="btn {{ $filters['queue'] === 'action' ? 'btn-primary' : 'btn-light border' }} btn-sm btn-text-icon"><i class="mdi mdi-progress-alert"></i><span>{{ __('Needs action') }} · {{ $queueStats['needs_action'] }}</span></a>
                <a href="{{ route('admin.orders.index', ['queue' => 'unpaid']) }}" data-live-link class="btn {{ $filters['queue'] === 'unpaid' ? 'btn-primary' : 'btn-light border' }} btn-sm btn-text-icon"><i class="mdi mdi-cash-remove"></i><span>{{ __('Payment attention') }} · {{ $queueStats['unpaid'] }}</span></a>
                <a href="{{ route('admin.orders.index', ['queue' => 'refunds']) }}" data-live-link class="btn {{ $filters['queue'] === 'refunds' ? 'btn-primary' : 'btn-light border' }} btn-sm btn-text-icon"><i class="mdi mdi-cash-refund"></i><span>{{ __('Refund activity') }} · {{ $queueStats['with_refunds'] }}</span></a>
            </div>
        </div>
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
                @if($filters['status'] || $filters['payment_status'] || $filters['payment_method'] || $filters['search'] || $filters['queue'])
                    <span class="admin-chip">{{ __('Filtered results') }}</span>
                @endif
                @if(auth()->user()?->hasPermission('promotions.manage'))
                    <a href="{{ route('admin.coupons.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-ticket-percent-outline"></i><span>{{ __('Coupons') }}</span></a>
                @endif
                @if(auth()->user()?->hasPermission('customers.manage'))
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-account-group-outline"></i><span>{{ __('Customers') }}</span></a>
                @endif
            </div>
        </div>

        @if($orders->count())
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('order_number') }}">{{ __('Order') }} @if($sort === 'order_number') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('customer_name') }}">{{ __('Customer') }} @if($sort === 'customer_name') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('status') }}">{{ __('Status') }} @if($sort === 'status') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('payment_status') }}">{{ __('Payment') }} @if($sort === 'payment_status') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('items_count') }}">{{ __('Items') }} @if($sort === 'items_count') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('grand_total') }}">{{ __('Total') }} @if($sort === 'grand_total') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                            <th><a class="table-sort-btn" data-live-link href="{{ $sortLink('created_at') }}">{{ __('Date') }} @if($sort === 'created_at') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
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
                                    @if($canManageOrders)
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
                                    @endif
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
