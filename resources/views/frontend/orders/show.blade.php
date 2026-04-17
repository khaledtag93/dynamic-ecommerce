@extends('layouts.app')

@section('title', __('Order details') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        @php($latestPayment = $order->latestPayment)
        <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap">
            <div>
                <div class="text-uppercase small text-muted fw-bold mb-1">{{ __('Order details') }}</div>
                <h1 class="fw-bold mb-0">{{ $order->order_number }}</h1>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('orders.index') }}" class="btn lc-btn-soft"><i class="bi bi-arrow-left me-2"></i>{{ __('My Orders') }}</a>
                @if($order->payment_method === \App\Models\Order::PAYMENT_METHOD_ONLINE && $order->payment_status !== \App\Models\Order::PAYMENT_STATUS_PAID && app(\App\Services\Commerce\PaymentService::class)->onlineGatewayConfigured())
                    <a href="{{ route('payments.paymob.redirect', $order) }}" class="btn lc-btn-primary">{{ __('Pay now securely') }}</a>
                @endif
                @if($order->can_user_cancel && (($storeSettings['orders_allow_customer_cancellation'] ?? '1') === '1'))
                    <form method="POST" action="{{ route('orders.cancel', $order) }}" data-submit-loading class="d-flex gap-2 flex-wrap">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="cancelled_reason" value="Cancelled by customer from account area.">
                        <button type="submit" class="btn lc-btn-danger-soft" onclick="return confirm('{{ __('Are you sure you want to cancel this order?') }}')"><i class="bi bi-x-circle me-2"></i>{{ __('Cancel order') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="lc-card p-4 mb-4">
                    <div class="d-flex justify-content-between flex-wrap gap-3 mb-4 align-items-start">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold mb-2">{{ __('Status overview') }}</div>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="lc-status-badge {{ $order->status === \App\Models\Order::STATUS_COMPLETED ? 'lc-badge-success' : ($order->status === \App\Models\Order::STATUS_CANCELLED ? 'lc-badge-danger' : 'lc-badge-processing') }}">{{ $order->status_label }}</span>
                                <span class="lc-status-badge {{ $order->payment_status === \App\Models\Order::PAYMENT_STATUS_PAID ? 'lc-badge-success' : ($order->payment_status === \App\Models\Order::PAYMENT_STATUS_FAILED ? 'lc-badge-danger' : 'lc-badge-unpaid') }}">{{ $order->payment_status_label }}</span>
                                <span class="lc-status-badge {{ $order->delivery_status === \App\Models\Order::DELIVERY_STATUS_DELIVERED ? 'lc-badge-success' : ($order->delivery_status === \App\Models\Order::DELIVERY_STATUS_CANCELLED ? 'lc-badge-danger' : 'lc-badge-processing') }}">{{ $order->delivery_status_label }}</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">{{ __('Placed at') }}</div>
                            <strong>{{ optional($order->placed_at ?? $order->created_at)->format('d M Y, h:i A') }}</strong>
                        </div>
                    </div>

                    @if($order->payment_method === \App\Models\Order::PAYMENT_METHOD_ONLINE && $latestPayment && !empty(data_get($latestPayment->meta, 'checkout_error')))
                        <div class="alert alert-warning rounded-4 mb-4">
                            <div class="fw-semibold mb-1">{{ __('Secure payment session could not be opened.') }}</div>
                            <div class="small mb-0">{{ data_get($latestPayment->meta, 'checkout_error') }}</div>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table align-middle lc-table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Price') }}</th>
                                    <th>{{ __('Qty') }}</th>
                                    <th class="text-end">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->product_name }}</div>
                                            @if($item->sku)
                                                <div class="small text-muted">SKU: {{ $item->sku }}</div>
                                            @endif
                                        </td>
                                        <td>EGP {{ number_format($item->unit_price, 2) }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td class="text-end fw-semibold">EGP {{ number_format($item->total_price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lc-card p-4 mb-4">
                    <h4 class="fw-bold mb-3">{{ __('Order summary') }}</h4>
                    <div class="lc-summary-list">
                        <div class="row-item"><span class="text-muted">{{ __('Subtotal') }}</span><strong>EGP {{ number_format($order->subtotal, 2) }}</strong></div>
                        <div class="row-item"><span class="text-muted">{{ __('Discount') }}</span><strong>- EGP {{ number_format($order->discount_total, 2) }}</strong></div>
                        <div class="row-item"><span class="text-muted">{{ __('Shipping') }}</span><strong>EGP {{ number_format($order->shipping_total, 2) }}</strong></div>
                        <div class="row-item"><span class="text-muted">{{ __('Tax') }}</span><strong>EGP {{ number_format($order->tax_total, 2) }}</strong></div>
                        <hr>
                        <div class="row-item fs-5"><span class="fw-bold">{{ __('Grand total') }}</span><strong>EGP {{ number_format($order->grand_total, 2) }}</strong></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="lc-card p-4 mb-4">
                    <h4 class="fw-bold mb-3">{{ __('Shipping address') }}</h4>
                    <div class="text-muted small">{{ $order->shipping_address_line_1 }}<br>@if($order->shipping_address_line_2){{ $order->shipping_address_line_2 }}<br>@endif{{ $order->shipping_city }} {{ $order->shipping_state }}<br>{{ $order->shipping_country }}</div>
                    @if($order->tracking_number || $order->shipping_provider)
                        <hr>
                        <div class="d-flex justify-content-between flex-wrap gap-2"><span class="text-muted">{{ __('Courier') }}</span><strong>{{ $order->shipping_provider ?: '—' }}</strong></div>
                        <div class="d-flex justify-content-between flex-wrap gap-2"><span class="text-muted">{{ __('Tracking number') }}</span><strong>{{ $order->tracking_number ?: '—' }}</strong></div>
                    @endif
                </div>

                <div class="lc-card p-4 mb-4">
                    <h4 class="fw-bold mb-3">{{ __('Payment instructions') }}</h4>
                    <div class="text-muted small">{{ $paymentInstructions }}</div>
                    @if($order->payment_method === \App\Models\Order::PAYMENT_METHOD_ONLINE && $latestPayment)
                        <hr>
                        <div class="small text-muted mb-2">{{ __('Gateway status') }}</div>
                        <div class="fw-semibold mb-2">{{ $latestPayment->provider_status ?: __('Waiting for secure payment session') }}</div>
                        @if(!empty(data_get($latestPayment->meta, 'paymob_payment_key_created_at')))
                            <div class="small text-muted mb-2">{{ __('Last secure payment session created at') }}: {{ data_get($latestPayment->meta, 'paymob_payment_key_created_at') }}</div>
                        @endif
                        @if(!empty(data_get($latestPayment->meta, 'checkout_error_at')))
                            <div class="small text-danger">{{ __('Last checkout start failure') }}: {{ data_get($latestPayment->meta, 'checkout_error_at') }}</div>
                        @endif
                    @endif
                </div>

                <div class="lc-card p-4">
                    <h4 class="fw-bold mb-3">{{ __('Need help?') }}</h4>
                    <div class="text-muted small">{{ __('You can keep tracking payment and delivery updates from your account notifications.') }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
