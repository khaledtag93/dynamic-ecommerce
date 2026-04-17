@extends('layouts.app')

@section('title', __('Payment status') . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        @php
            $isPaid = $status === \App\Models\Payment::STATUS_PAID;
            $isPending = in_array($status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_AUTHORIZED], true);
            $hasCheckoutFailure = !empty(data_get($payment, 'meta.checkout_error'));
            $icon = $isPaid ? 'bi-check2-circle' : ($isPending ? 'bi-hourglass-split' : 'bi-x-circle');
            $title = $isPaid ? __('Payment completed') : ($isPending ? __('Payment pending') : __('Payment was not completed'));
            $description = $isPaid
                ? __('Your payment was verified and your order has been updated successfully.')
                : ($isPending
                    ? __('Your payment is still being verified by the gateway. Check the order page again in a moment.')
                    : __('No successful payment was confirmed for this order yet. You can retry safely from the order page.'));
        @endphp

        <div class="lc-card p-4 p-lg-5 text-center mx-auto" style="max-width: 820px;">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4 text-white"
                 style="width:84px;height:84px;background:{{ $isPaid ? 'linear-gradient(135deg,#16a34a,#22c55e)' : ($isPending ? 'linear-gradient(135deg,#f59e0b,#f97316)' : 'linear-gradient(135deg,#dc2626,#ef4444)') }};">
                <i class="bi {{ $icon }} fs-1"></i>
            </div>

            <div class="text-uppercase small text-muted fw-bold mb-2">{{ __('Paymob result') }}</div>
            <h1 class="fw-bold mb-2">{{ $title }}</h1>
            <p class="text-muted mx-auto mb-4" style="max-width: 38rem;">{{ $description }}</p>

            <div class="d-flex justify-content-center gap-2 flex-wrap mb-4">
                <span class="lc-status-badge {{ $isPaid ? 'lc-badge-success' : ($isPending ? 'lc-badge-processing' : 'lc-badge-danger') }}">{{ optional($payment)->status_label ?? __('Pending') }}</span>
                <span class="lc-status-badge lc-badge-processing">{{ $order->payment_method_label }}</span>
                <span class="lc-status-badge lc-badge-processing">{{ $order->order_number }}</span>
            </div>

            @if($hasCheckoutFailure)
                <div class="alert alert-warning rounded-4 text-start mb-4">
                    <div class="fw-semibold mb-1">{{ __('Secure payment page could not be opened') }}</div>
                    <div class="small mb-2">{{ data_get($payment, 'meta.checkout_error') }}</div>
                    @if(!empty(data_get($payment, 'meta.checkout_error_at')))
                        <div class="small text-muted">{{ __('Last attempt') }}: {{ data_get($payment, 'meta.checkout_error_at') }}</div>
                    @endif
                </div>
            @endif

            <div class="lc-note-card p-4 text-start mb-4">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Order') }}</span><strong>{{ $order->order_number }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Payment status') }}</span><strong>{{ $order->payment_status_label }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Provider status') }}</span><strong>{{ optional($payment)->provider_status ?: '—' }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Payment key created at') }}</span><strong>{{ data_get($payment, 'meta.paymob_payment_key_created_at') ?: '—' }}</strong></div>
                <div class="d-flex justify-content-between mb-0"><span class="text-muted">{{ __('Total') }}</span><strong>EGP {{ number_format($order->grand_total, 2) }}</strong></div>
            </div>

            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="{{ route('orders.show', $order) }}" class="btn lc-btn-primary">{{ __('Order details') }}</a>
                @if(! $isPaid)
                    <a href="{{ route('payments.paymob.redirect', $order) }}" class="btn lc-btn-soft">{{ __('Retry secure payment') }}</a>
                @endif
                <a href="{{ route('notifications.index') }}" class="btn lc-btn-soft">{{ __('Notifications') }}</a>
                <a href="{{ route('frontend.contact') }}" class="btn lc-btn-soft">{{ __('Contact support') }}</a>
            </div>
        </div>
    </div>
</section>
@endsection
