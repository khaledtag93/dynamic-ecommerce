@extends('layouts.admin')

@section('title', ($payment->transaction_reference ?: __('Payment')) . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Payment details')" :title="($payment->transaction_reference ?: __('Payment record'))" :description="__('Linked order: :order', ['order' => optional($payment->order)->order_number ?: __('Missing order')])">
    <a href="{{ route('admin.payments.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to payments') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card mb-4"><div class="admin-card-body">
            <div class="row g-4">
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Method') }}</div><div class="fw-semibold">{{ $payment->method_label }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Status') }}</div><span class="badge admin-status-badge {{ $payment->status_badge_class }}">{{ $payment->status_label }}</span></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Provider') }}</div><div>{{ $payment->provider ?: '—' }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Provider status') }}</div><div>{{ $payment->provider_status ?: '—' }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Amount') }}</div><div class="fw-semibold">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Created') }}</div><div>{{ optional($payment->created_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Paid at') }}</div><div>{{ optional($payment->paid_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Reference') }}</div><div>{{ $payment->transaction_reference ?: '—' }}</div></div>
            </div>
            @if($payment->notes)
                <hr>
                <div class="admin-inline-label">{{ __('Notes') }}</div>
                <div>{{ $payment->notes }}</div>
            @endif
        </div></div>

        @if($payment->order)
            <div class="admin-card mb-4"><div class="admin-card-body">
                <h4 class="mb-3">{{ __('Order snapshot') }}</h4>
                <div class="row g-4">
                    <div class="col-md-6"><div class="admin-inline-label">{{ __('Order') }}</div><a href="{{ route('admin.orders.show', $payment->order) }}" class="fw-semibold">{{ $payment->order->order_number }}</a></div>
                    <div class="col-md-6"><div class="admin-inline-label">{{ __('Customer') }}</div><div>{{ $payment->order->customer_name }}</div></div>
                    <div class="col-md-6"><div class="admin-inline-label">{{ __('Order status') }}</div><span class="badge admin-status-badge {{ $payment->order->status_badge_class }}">{{ $payment->order->status_label }}</span></div>
                    <div class="col-md-6"><div class="admin-inline-label">{{ __('Payment status') }}</div><span class="badge admin-status-badge {{ $payment->order->payment_status_badge_class }}">{{ $payment->order->payment_status_label }}</span></div>
                </div>
            </div></div>
        @endif

        <div class="admin-card"><div class="admin-card-body">
            <h4 class="mb-3">{{ __('Gateway logs') }}</h4>
            <div class="row g-4 mb-4">
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Paymob order id') }}</div><div>{{ data_get($payment->meta, 'paymob_order_id') ?: '—' }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Paymob transaction id') }}</div><div>{{ data_get($payment->meta, 'paymob_transaction_id') ?: '—' }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('Payment key created at') }}</div><div>{{ data_get($payment->meta, 'paymob_payment_key_created_at') ?: '—' }}</div></div>
                <div class="col-md-6"><div class="admin-inline-label">{{ __('HMAC valid') }}</div><div>{{ data_get($payment->meta, 'paymob_hmac_valid') === true ? __('Yes') : (data_get($payment->meta, 'paymob_hmac_valid') === false ? __('No') : '—') }}</div></div>
            </div>

            <h5 class="mb-3">{{ __('Payment events timeline') }}</h5>
            <div class="d-flex flex-column gap-3">
                @forelse(data_get($payment->meta, 'events', []) as $event)
                    <div class="border rounded-4 p-3 bg-light-subtle">
                        <div class="fw-semibold">{{ $event['message'] ?? ($event['event'] ?? __('Payment event')) }}</div>
                        <div class="small text-muted mt-1">{{ $event['event'] ?? __('Event') }} • {{ $event['at'] ?? '—' }}</div>
                    </div>
                @empty
                    <div class="text-muted small">{{ __('No gateway events were recorded yet.') }}</div>
                @endforelse
            </div>

            @if(!empty(data_get($payment->meta, 'paymob_callback')))
                <hr>
                <h5 class="mb-3">{{ __('Last callback payload') }}</h5>
                <pre class="small admin-code-block mb-0" style="max-height: 420px;">{{ json_encode(data_get($payment->meta, 'paymob_callback'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </div></div>
    </div>

    <div class="col-xl-4">
        <div class="admin-card admin-card-sticky"><div class="admin-card-body">
            <h4 class="mb-3">{{ __('Update payment status') }}</h4>
            <form method="POST" action="{{ route('admin.payments.update-status', $payment) }}" data-submit-loading>
                @csrf
                @method('PATCH')
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('Status') }}</label>
                    <select class="form-select" name="status">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($payment->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('Provider status') }}</label>
                    <input type="text" name="provider_status" class="form-control" value="{{ old('provider_status', $payment->provider_status) }}" placeholder="{{ __('Optional gateway status') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="4" class="form-control" placeholder="{{ __('Optional internal payment notes') }}">{{ old('notes', $payment->notes) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('Save payment update') }}</button>
            </form>
        </div></div>
    </div>
</div>
</div>
@endsection
