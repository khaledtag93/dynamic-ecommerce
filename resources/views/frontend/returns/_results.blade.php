<div data-live-results aria-busy="false">
@if($returns->count())
    <div class="d-flex flex-column gap-3">
        @foreach($returns as $returnRequest)
            <article class="lc-card p-4">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-4">
                        <div class="text-uppercase small text-muted fw-bold mb-1">{{ __('Return request') }}</div>
                        <div class="fw-bold fs-4">{{ $returnRequest->reference }}</div>
                        <div class="text-muted small">{{ __('Requested') }} {{ optional($returnRequest->requested_at)->format('d M Y, h:i A') }}</div>
                    </div>
                    <div class="col-sm-4 col-lg-2">
                        <div class="small text-muted text-uppercase fw-bold mb-1">{{ __('Order') }}</div>
                        <div class="fw-semibold">{{ $returnRequest->order?->order_number ?? '—' }}</div>
                    </div>
                    <div class="col-sm-4 col-lg-2">
                        <div class="small text-muted text-uppercase fw-bold mb-1">{{ __('Items') }}</div>
                        <div class="fw-semibold">{{ $returnRequest->items_count }}</div>
                    </div>
                    <div class="col-sm-4 col-lg-2">
                        <div class="small text-muted text-uppercase fw-bold mb-1">{{ __('Status') }}</div>
                        <span class="lc-status-badge {{ $returnRequest->status === $returnRequest::STATUS_COMPLETED ? 'lc-badge-success' : (in_array($returnRequest->status, [$returnRequest::STATUS_REJECTED, $returnRequest::STATUS_CANCELLED], true) ? 'lc-badge-danger' : 'lc-badge-processing') }}">{{ $returnRequest->status_label }}</span>
                    </div>
                    <div class="col-lg-2 text-lg-end">
                        <a href="{{ route('returns.show', $returnRequest) }}" class="btn lc-btn-primary btn-sm">{{ __('View details') }}</a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@else
    <div class="lc-card lc-empty-state">
        <div class="lc-empty-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
        <h3 class="fw-bold mb-2">{{ __('No return requests yet') }}</h3>
        <p class="text-muted mb-4">{{ __('Eligible delivered or completed orders can start a return from the order details page.') }}</p>
        <a href="{{ route('orders.index') }}" class="btn lc-btn-primary">{{ __('View my orders') }}</a>
    </div>
@endif

@if($returns->hasPages())
    <div class="pt-4 d-flex justify-content-center">{{ $returns->links() }}</div>
@endif
</div>
