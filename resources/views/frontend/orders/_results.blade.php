<div data-live-results aria-busy="false">
@if($orders->count())
    <div class="d-flex flex-column gap-3">
        @foreach($orders as $order)
            <div class="lc-card lc-order-card">
                <div class="row g-3 align-items-center">
                    <div class="col-xl-4 col-lg-5">
                        <div class="fw-bold fs-4 mb-1">{{ $order->order_number }}</div>
                        <div class="text-muted small mb-2">{{ __('Placed') }} {{ optional($order->placed_at)->format('d M Y, h:i A') ?: $order->created_at->format('d M Y, h:i A') }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="lc-status-badge {{ $order->status === AppModelsOrder::STATUS_COMPLETED ? 'lc-badge-success' : ($order->status === AppModelsOrder::STATUS_CANCELLED ? 'lc-badge-danger' : 'lc-badge-processing') }}">{{ $order->status_label }}</span>
                            <span class="lc-status-badge {{ $order->payment_status === AppModelsOrder::PAYMENT_STATUS_PAID ? 'lc-badge-success' : ($order->payment_status === AppModelsOrder::PAYMENT_STATUS_FAILED ? 'lc-badge-danger' : 'lc-badge-unpaid') }}">{{ $order->payment_status_label }}</span>
                            <span class="lc-status-badge {{ $order->delivery_status === AppModelsOrder::DELIVERY_STATUS_DELIVERED ? 'lc-badge-success' : ($order->delivery_status === AppModelsOrder::DELIVERY_STATUS_CANCELLED ? 'lc-badge-danger' : 'lc-badge-processing') }}">{{ $order->delivery_status_label }}</span>
                            @if($order->sales_channel === $order::SALES_CHANNEL_POS)<span class="lc-status-badge lc-badge-processing">{{ __('In-store') }}</span>@endif
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-2 col-sm-4 col-6"><div class="small text-muted text-uppercase fw-bold mb-1">{{ __('Items') }}</div><div class="fw-bold fs-5">{{ $order->items_count }}</div></div>
                    <div class="col-xl-2 col-lg-2 col-sm-4 col-6"><div class="small text-muted text-uppercase fw-bold mb-1">{{ __('Payment method') }}</div><div class="fw-semibold">{{ $order->payment_method_label }}</div></div>
                    <div class="col-xl-2 col-lg-2 col-sm-4 col-6"><div class="small text-muted text-uppercase fw-bold mb-1">{{ __('Delivery') }}</div><div class="fw-semibold">{{ $order->delivery_method_label }}</div></div>
                    <div class="col-xl-2 col-lg-1 col-sm-12 text-lg-end"><a href="{{ route('orders.show', $order) }}" class="btn lc-btn-primary btn-sm">{{ __('View details') }}</a></div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="lc-card lc-empty-state">
        <div class="lc-empty-icon"><i class="bi bi-receipt"></i></div>
        <h3 class="fw-bold mb-2">{{ __('No orders yet') }}</h3>
        <p class="text-muted mb-4">{{ __('Once you place your first order, it will appear here with full status tracking.') }}</p>
        <a href="{{ route('frontend.home') }}" class="btn lc-btn-primary">{{ __('Shop now') }}</a>
    </div>
@endif
@if($orders->hasPages())<div class="pt-4 d-flex justify-content-center">{{ $orders->links() }}</div>@endif
</div>