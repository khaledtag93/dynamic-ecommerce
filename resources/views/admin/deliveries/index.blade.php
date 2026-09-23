@extends('layouts.admin')

@section('title', __('Deliveries') . ' | Admin')

@section('content')
<div class="admin-page-shell">
<x-admin.page-header :kicker="__('Operations')" :title="__('Deliveries')" :description="__('Delivery tracking foundation with status, courier, ETA, and tracking number support.')" />

<div class="row g-3 mb-4">@foreach([['label'=>__('Total deliveries'),'value'=>$stats['total'],'icon'=>'mdi-truck-outline'],['label'=>__('Needs action'),'value'=>$stats['action'],'icon'=>'mdi-alert-circle-outline'],['label'=>__('In transit'),'value'=>$stats['transit'],'icon'=>'mdi-truck-fast-outline'],['label'=>__('Delivered'),'value'=>$stats['delivered'],'icon'=>'mdi-package-variant-closed-check'],['label'=>__('Exceptions'),'value'=>$stats['exceptions'],'icon'=>'mdi-alert-octagon-outline']] as $card)<div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span><div class="admin-stat-label">{{ $card['label'] }}</div><div class="admin-stat-value">{{ $card['value'] }}</div></div></div>@endforeach</div>

<div class="admin-card mb-4"><div class="admin-card-body">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-3"><div><h4 class="mb-1">{{ __('Delivery operations') }}</h4><p class="text-muted small mb-0">{{ __('Move quickly between orders waiting for dispatch, shipments in transit, and delivery exceptions.') }}</p></div><div class="d-flex flex-wrap gap-2"><a href="{{ route('admin.deliveries.index',['queue'=>'action']) }}" class="btn {{ $filters['queue']==='action' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Needs action') }} · {{ $stats['action'] }}</a><a href="{{ route('admin.deliveries.index',['queue'=>'transit']) }}" class="btn {{ $filters['queue']==='transit' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('In transit') }} · {{ $stats['transit'] }}</a><a href="{{ route('admin.deliveries.index',['queue'=>'exceptions']) }}" class="btn {{ $filters['queue']==='exceptions' ? 'btn-primary':'btn-light border' }} btn-sm">{{ __('Exceptions') }} · {{ $stats['exceptions'] }}</a></div></div>
    <form method="GET" class="row g-3 align-items-end" data-submit-loading>
        <div class="col-lg-4"><label class="form-label fw-semibold">{{ __('Search') }}</label><input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Order, customer, tracking, courier') }}"></div>
        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Delivery status') }}</label><select name="delivery_status" class="form-select"><option value="">{{ __('All statuses') }}</option>@foreach($deliveryStatusOptions as $value => $label)<option value="{{ $value }}" @selected($filters['delivery_status'] === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Delivery method') }}</label><select name="delivery_method" class="form-select"><option value="">{{ __('All methods') }}</option>@foreach($deliveryMethodOptions as $value => $label)<option value="{{ $value }}" @selected($filters['delivery_method'] === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Per page') }}</label><select name="per_page" class="form-select">@foreach([15,30,60] as $size)<option value="{{ $size }}" @selected((int)$filters['per_page']===$size)>{{ $size }}</option>@endforeach</select></div><input type="hidden" name="queue" value="{{ $filters['queue'] }}"><div class="col-12 d-flex gap-2 flex-wrap justify-content-end"><button type="submit" class="btn btn-primary flex-fill" data-loading-text="{{ __('Filtering...') }}">{{ __('Filter') }}</button><a href="{{ route('admin.deliveries.index') }}" class="btn btn-light border flex-fill">{{ __('Reset') }}</a></div>
    </form>
</div></div>

<div class="admin-card"><div class="admin-card-body">
    <div class="admin-table-toolbar">
        <div>
            <h4 class="mb-1">{{ __('Delivery queue') }}</h4>
            <div class="text-muted small">{{ __('Showing :count delivery record(s) on this page.', ['count' => $orders->count()]) }}</div>
        </div>
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
                    <tr><td colspan="8" class="py-5 text-center"><div class="admin-empty-state py-4"><div class="empty-icon"><i class="mdi mdi-truck-outline"></i></div><h5 class="mb-2">{{ __('No delivery records yet') }}</h5><p class="text-muted mb-0">{{ __('Orders will appear here as soon as checkout starts creating shipments.') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div></div>
<div class="mt-4">{{ $orders->links() }}</div>
</div>
@endsection
