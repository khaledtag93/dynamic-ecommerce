@extends('layouts.app')

@section('title', __('Order placed successfully') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        @php($latestPayment = $order->payments->sortByDesc('id')->first())
        <div class="lc-card p-4 p-lg-5 mb-4 overflow-hidden" style="background:radial-gradient(circle at top right, rgba(249,115,22,.16), transparent 28%), linear-gradient(180deg, #fff9f4 0%, #ffffff 62%);">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle text-white mb-4" style="width:78px;height:78px;background:linear-gradient(135deg,var(--lc-primary),var(--lc-primary-dark)); box-shadow:0 20px 40px color-mix(in srgb, var(--lc-primary) 22%, transparent);">
                        <i class="bi bi-check2-circle fs-1"></i>
                    </div>
                    <div class="text-uppercase small text-muted fw-bold mb-2">{{ __('Order placed successfully') }}</div>
                    <h1 class="fw-bold mb-2" style="font-size:clamp(2rem,4vw,3rem);">{{ __('Your order has been received and is now part of your account history.') }}</h1>
                    <p class="text-muted mb-4" style="max-width: 38rem;">{{ __('Track payment, delivery, and future order updates from your account.') }}</p>
                    <div class="d-flex gap-2 flex-wrap mb-4">
                        <span class="lc-status-badge lc-badge-processing">{{ $order->status_label }}</span>
                        <span class="lc-status-badge {{ $order->payment_status === \App\Models\Order::PAYMENT_STATUS_PAID ? 'lc-badge-success' : ($order->payment_status === \App\Models\Order::PAYMENT_STATUS_FAILED ? 'lc-badge-danger' : 'lc-badge-unpaid') }}">{{ $order->payment_status_label }}</span>
                        <span class="lc-status-badge lc-badge-processing">{{ $order->delivery_method_label }}</span>
                    </div>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="{{ route('orders.show', $order) }}" class="btn lc-btn-primary">{{ __('Order details') }}</a>
                        @if($order->payment_method === \App\Models\Order::PAYMENT_METHOD_ONLINE && $order->payment_status !== \App\Models\Order::PAYMENT_STATUS_PAID && app(\App\Services\Commerce\PaymentService::class)->onlineGatewayConfigured())
                            <a href="{{ route('payments.paymob.redirect', $order) }}" class="btn lc-btn-soft">{{ __('Pay now securely') }}</a>
                        @endif
                        <a href="{{ route('notifications.index') }}" class="btn lc-btn-soft">{{ __('Notifications') }}</a>
                <a href="{{ route('frontend.contact') }}" class="btn lc-btn-soft">{{ __('Contact support') }}</a>
                        <a href="{{ route('frontend.home') }}" class="btn lc-btn-soft">{{ __('Home') }}</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="lc-note-card p-4 h-100">
                        <div class="lc-stat-label">{{ __('Quick snapshot') }}</div>
                        <div class="d-flex justify-content-between mb-3"><span class="text-muted">{{ __('Order') }}</span><strong>{{ $order->order_number }}</strong></div>
                        <div class="d-flex justify-content-between mb-3"><span class="text-muted">{{ __('Payment method') }}</span><strong>{{ $order->payment_method_label }}</strong></div>
                        <div class="d-flex justify-content-between mb-3"><span class="text-muted">{{ __('Delivery method') }}</span><strong>{{ $order->delivery_method_label }}</strong></div>
                        <div class="d-flex justify-content-between mb-3"><span class="text-muted">{{ __('Grand total') }}</span><strong>EGP {{ number_format($order->grand_total, 2) }}</strong></div>
                        <hr>
                        <div class="small text-muted">{{ $paymentInstructions }}</div>
                        @if(!empty($storeSettings['store_support_email']) || !empty($storeSettings['store_support_phone']))
                            <div class="small text-muted mt-3">{{ __('Need help?') }} {{ $storeSettings['store_support_email'] ?? $storeSettings['store_support_phone'] }}</div>
                        @endif
                        @if($order->payment_method === \App\Models\Order::PAYMENT_METHOD_ONLINE && $order->payment_status !== \App\Models\Order::PAYMENT_STATUS_PAID)
                            <div class="alert alert-warning mt-3 mb-0 rounded-4 small">
                                {{ __('Online payment is not completed yet. You can safely reopen the secure payment page without creating duplicate paid orders.') }}
                                @if(!empty(data_get($latestPayment, 'meta.checkout_error')))
                                    <div class="mt-2 fw-semibold">{{ __('Latest gateway start issue') }}: {{ data_get($latestPayment, 'meta.checkout_error') }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
