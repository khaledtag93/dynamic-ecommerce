@extends('layouts.app')

@section('title', $returnRequest->reference . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 lc-page-shell">
    <div class="container">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <div class="text-uppercase small text-muted fw-bold">{{ __('Return request') }}</div>
                <h1 class="lc-section-title mb-1">{{ $returnRequest->reference }}</h1>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <span data-return-status class="lc-status-badge {{ $returnRequest->status === $returnRequest::STATUS_COMPLETED ? 'lc-badge-success' : (in_array($returnRequest->status, [$returnRequest::STATUS_REJECTED, $returnRequest::STATUS_CANCELLED], true) ? 'lc-badge-danger' : 'lc-badge-processing') }}">{{ $returnRequest->status_label }}</span>
                    <span class="text-muted">{{ __('Order') }} {{ $returnRequest->order?->order_number }}</span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('returns.index') }}" class="btn lc-btn-soft">{{ __('My Returns') }}</a>
                @if($returnRequest->order)<a href="{{ route('orders.show', $returnRequest->order) }}" class="btn lc-btn-soft">{{ __('Order details') }}</a>@endif
            </div>
        </div>

        <div class="lc-card p-4 mb-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3"><div class="small text-muted text-uppercase fw-bold">{{ __('Requested') }}</div><div class="fw-semibold">{{ optional($returnRequest->requested_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
                <div class="col-sm-6 col-lg-3"><div class="small text-muted text-uppercase fw-bold">{{ __('Reviewed') }}</div><div class="fw-semibold">{{ optional($returnRequest->reviewed_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
                <div class="col-sm-6 col-lg-3"><div class="small text-muted text-uppercase fw-bold">{{ __('Received') }}</div><div class="fw-semibold">{{ optional($returnRequest->received_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
                <div class="col-sm-6 col-lg-3"><div class="small text-muted text-uppercase fw-bold">{{ __('Completed') }}</div><div class="fw-semibold">{{ optional($returnRequest->completed_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
            </div>
        </div>

        <div class="lc-card p-4 mb-4">
            <h4 class="fw-bold mb-3">{{ __('Return items') }}</h4>
            <div class="table-responsive">
                <table class="table align-middle lc-table-hover mb-0">
                    <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Resolution') }}</th><th>{{ __('Requested') }}</th><th>{{ __('Approved') }}</th><th>{{ __('Received') }}</th><th>{{ __('Restocked') }}</th></tr></thead>
                    <tbody>
                        @foreach($returnRequest->items as $item)
                            <tr>
                                <td><div class="fw-semibold">{{ $item->orderItem?->product_name ?? __('Order item') }}</div>@if($item->orderItem?->variant_name)<div class="text-muted small">{{ $item->orderItem->variant_name }}</div>@endif</td>
                                <td>{{ $item->reasonLabel() }}@if($item->reason_details)<div class="text-muted small">{{ $item->reason_details }}</div>@endif</td>
                                <td>{{ $item->resolutionLabel() }}</td>
                                <td>{{ $item->requested_quantity }}</td>
                                <td>{{ $item->approved_quantity ?? '—' }}</td>
                                <td>{{ $item->received_quantity }}</td>
                                <td>{{ $item->restock_quantity }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($returnRequest->customer_notes || $returnRequest->review_notes || $returnRequest->completion_notes)
            <div class="lc-card p-4 mb-4">
                <h4 class="fw-bold mb-3">{{ __('Notes') }}</h4>
                @if($returnRequest->customer_notes)<div class="mb-3"><div class="small text-muted fw-bold">{{ __('Your notes') }}</div><div>{{ $returnRequest->customer_notes }}</div></div>@endif
                @if($returnRequest->review_notes)<div class="mb-3"><div class="small text-muted fw-bold">{{ __('Store review') }}</div><div>{{ $returnRequest->review_notes }}</div></div>@endif
                @if($returnRequest->completion_notes)<div><div class="small text-muted fw-bold">{{ __('Completion notes') }}</div><div>{{ $returnRequest->completion_notes }}</div></div>@endif
            </div>
        @endif

        @if($returnRequest->refunds->isNotEmpty() || $returnRequest->exchangeOrder)
            <div class="lc-card p-4 mb-4">
                <h4 class="fw-bold mb-3">{{ __('Resolution outcome') }}</h4>
                @foreach($returnRequest->refunds as $refund)
                    <div class="d-flex justify-content-between gap-3 border rounded-4 p-3 mb-2"><span>{{ __('Refund recorded') }}</span><strong>{{ $refund->order?->currency ?? 'EGP' }} {{ number_format((float)$refund->amount, 2) }}</strong></div>
                @endforeach
                @if($returnRequest->exchangeOrder)
                    <div class="d-flex justify-content-between gap-3 border rounded-4 p-3"><span>{{ __('Exchange order') }}</span><a href="{{ route('orders.show', $returnRequest->exchangeOrder) }}" class="fw-bold">{{ $returnRequest->exchangeOrder->order_number }}</a></div>
                @endif
            </div>
        @endif

        <div class="small text-muted mb-3 d-none" data-return-live-status role="status" aria-live="polite"></div>

        @if($returnRequest->status === $returnRequest::STATUS_REQUESTED)
            <form method="POST" action="{{ route('returns.cancel', $returnRequest) }}" data-confirm-message="{{ __('Cancel this return request?') }}" data-return-cancel-live>
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-danger">{{ __('Cancel return request') }}</button>
            </form>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[data-return-cancel-live]');
    if (!form) return;

    form.addEventListener('submit', async function (event) {
        if (form.dataset.confirmed !== '1') return;

        event.preventDefault();
        event.stopImmediatePropagation();

        const button = event.submitter || form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'X-Return-Cancel-Live': '1',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error('return-cancel-failed');

            const payload = await response.json();
            const status = document.querySelector('[data-return-status]');
            const liveStatus = document.querySelector('[data-return-live-status]');

            if (status) {
                status.textContent = payload.return?.status_label || status.textContent;
                status.className = 'lc-status-badge lc-badge-danger';
            }
            if (liveStatus) {
                liveStatus.textContent = payload.message || @json(__('Return request cancelled.'));
                liveStatus.classList.remove('d-none');
            }

            form.remove();
        } catch (error) {
            form.submit();
        }
    }, true);
});
</script>
@endpush
