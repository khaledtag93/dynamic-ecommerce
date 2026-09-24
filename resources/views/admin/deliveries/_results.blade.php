<div data-live-results aria-busy="false">
<div class="admin-card mb-4"><div class="admin-card-body">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div>
            <h4 class="mb-1">{{ __('Delivery operations') }}</h4>
            <p class="text-muted small mb-0">{{ __('Move quickly between orders waiting for dispatch, shipments in transit, and delivery exceptions.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.deliveries.index',['queue'=>'action']) }}" data-live-link class="btn {{ $filters['queue']==='action' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Needs action') }} · {{ $queueStats['action'] }}</a>
            <a href="{{ route('admin.deliveries.index',['queue'=>'transit']) }}" data-live-link class="btn {{ $filters['queue']==='transit' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('In transit') }} · {{ $queueStats['transit'] }}</a>
            <a href="{{ route('admin.deliveries.index',['queue'=>'exceptions']) }}" data-live-link class="btn {{ $filters['queue']==='exceptions' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Exceptions') }} · {{ $queueStats['exceptions'] }}</a>
        </div>
    </div>
</div></div>

<div class="admin-card"><div class="admin-card-body">
    <div class="admin-table-toolbar">
        <div>
            <h4 class="mb-1">{{ __('Delivery queue') }}</h4>
            <div class="text-muted small">{{ __('Showing :count delivery record(s) on this page.', ['count' => $orders->count()]) }}</div>
        </div>
        @if($filters['search'] || $filters['delivery_status'] || $filters['delivery_method'] || $filters['queue'])
            <span class="admin-chip">{{ __('Filtered results') }}</span>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Method') }}</th><th>{{ __('Status') }}</th><th>{{ __('Tracking') }}</th><th>{{ __('Courier') }}</th><th>{{ __('ETA') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold">{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer_name }}</td>
                        <td>{{ $order->delivery_method_label }}</td>
                        <td><span class="badge admin-status-badge {{ $order->delivery_status_badge_class }}">{{ $order->delivery_status_label }}</span></td>
                        <td>{{ $order->tracking_number ?: '—' }}</td>
                        <td>{{ $order->shipping_provider ?: '—' }}</td>
                        <td>{{ optional($order->estimated_delivery_date)->format('d M Y') ?: '—' }}</td>
                        <td class="text-end"><a href="{{ route('admin.orders.show', $order) }}#delivery-card" class="btn btn-sm btn-light border btn-text-icon"><i class="mdi mdi-truck-check-outline"></i><span>{{ __('Manage') }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-5 text-center"><div class="admin-empty-state py-4"><div class="empty-icon"><i class="mdi mdi-truck-outline"></i></div><h5 class="mb-2">{{ __('No delivery records found') }}</h5><p class="text-muted mb-0">{{ __('Try clearing the filters or wait for new delivery activity.') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div></div>
@if($orders->hasPages())<div class="mt-4">{{ $orders->links() }}</div>@endif
</div>