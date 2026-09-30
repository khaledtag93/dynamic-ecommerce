@extends('layouts.admin')

@section('title', $returnRequest->reference . ' | ' . __('Admin'))

@section('content')
@php
    $canManage = auth()->user()?->hasPermission('orders.manage') ?? false;
@endphp

<x-admin.page-header :kicker="__('Return request')" :title="$returnRequest->reference" :description="__('Order :order', ['order' => $returnRequest->order?->order_number ?? '—'])">
    <a href="{{ route('admin.returns.index') }}" class="btn btn-light border">{{ __('Back to returns') }}</a>
    @if($returnRequest->order)<a href="{{ route('admin.orders.show', $returnRequest->order) }}" class="btn btn-light border">{{ __('Open order') }}</a>@endif
</x-admin.page-header>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Status') }}</div><div class="mt-2"><span class="badge admin-status-badge {{ $returnRequest->status_badge_class }}">{{ $returnRequest->status_label }}</span></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Requested') }}</div><div class="fw-bold mt-2">{{ optional($returnRequest->requested_at)->format('d M Y, H:i') ?: '—' }}</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Received') }}</div><div class="fw-bold mt-2">{{ optional($returnRequest->received_at)->format('d M Y, H:i') ?: '—' }}</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Completed') }}</div><div class="fw-bold mt-2">{{ optional($returnRequest->completed_at)->format('d M Y, H:i') ?: '—' }}</div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Return items') }}</h4>
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Resolution') }}</th><th>{{ __('Requested') }}</th><th>{{ __('Approved') }}</th><th>{{ __('Received') }}</th><th>{{ __('Restocked') }}</th></tr></thead>
                        <tbody>
                            @foreach($returnRequest->items as $item)
                                <tr>
                                    <td><div class="fw-bold">{{ $item->orderItem?->product_name ?? __('Order item') }}</div>@if($item->orderItem?->variant_name)<div class="text-muted small">{{ $item->orderItem->variant_name }}</div>@endif</td>
                                    <td>{{ $item->reasonLabel() }}@if($item->reason_details)<div class="small text-muted">{{ $item->reason_details }}</div>@endif</td>
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
        </div>

        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Notes & audit context') }}</h4>
                <div class="row g-3">
                    <div class="col-md-4"><div class="admin-inline-label">{{ __('Customer notes') }}</div><div>{{ $returnRequest->customer_notes ?: '—' }}</div></div>
                    <div class="col-md-4"><div class="admin-inline-label">{{ __('Review notes') }}</div><div>{{ $returnRequest->review_notes ?: '—' }}</div></div>
                    <div class="col-md-4"><div class="admin-inline-label">{{ __('Completion notes') }}</div><div>{{ $returnRequest->completion_notes ?: '—' }}</div></div>
                </div>
                <hr>
                <div class="row g-3 small text-muted">
                    <div class="col-md-4">{{ __('Reviewed by') }}: {{ $returnRequest->reviewedBy?->name ?? '—' }}</div>
                    <div class="col-md-4">{{ __('Received by') }}: {{ $returnRequest->receivedBy?->name ?? '—' }}</div>
                    <div class="col-md-4">{{ __('Completed by') }}: {{ $returnRequest->completedBy?->name ?? '—' }}</div>
                </div>
            </div>
        </div>

        @if($returnRequest->refunds->isNotEmpty() || $returnRequest->exchangeOrder)
            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Resolution outcome') }}</h4>
                    @foreach($returnRequest->refunds as $refund)
                        <div class="admin-refund-item mb-2 d-flex justify-content-between gap-3 flex-wrap">
                            <div><strong>{{ __('Refund') }}</strong><div class="small text-muted">{{ optional($refund->processed_at)->format('d M Y, H:i') }}</div></div>
                            <strong>{{ $returnRequest->order?->currency ?? 'EGP' }} {{ number_format((float)$refund->amount, 2) }}</strong>
                        </div>
                    @endforeach
                    @if($returnRequest->exchangeOrder)
                        <div class="admin-refund-item d-flex justify-content-between gap-3 flex-wrap"><span>{{ __('Exchange order') }}</span><a href="{{ route('admin.orders.show', $returnRequest->exchangeOrder) }}" class="fw-bold">{{ $returnRequest->exchangeOrder->order_number }}</a></div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-4">
        @if(!$canManage)
            <div class="admin-card"><div class="admin-card-body"><h4>{{ __('Read-only review') }}</h4><p class="text-muted mb-0">{{ __('You can review this return, but orders.manage permission is required for lifecycle changes.') }}</p></div></div>
        @elseif($returnRequest->status === $returnRequest::STATUS_REQUESTED)
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <h4 class="mb-2">{{ __('Approve return') }}</h4>
                    <p class="text-muted small">{{ __('Approve only the quantities the store is willing to accept. No inventory is restored at approval.') }}</p>
                    <form method="POST" action="{{ route('admin.returns.approve', $returnRequest) }}" data-submit-loading>
                        @csrf
                        @method('PATCH')
                        @foreach($returnRequest->items as $item)
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="approvedQuantity-{{ $item->id }}">{{ $item->orderItem?->product_name }} · {{ __('Approved quantity') }}</label>
                                <input id="approvedQuantity-{{ $item->id }}" type="number" name="approved_quantities[{{ $item->id }}]" aria-required="true" min="0" max="{{ $item->requested_quantity }}" value="{{ old('approved_quantities.'.$item->id, $item->requested_quantity) }}" class="form-control" required>
                                <div class="small text-muted">{{ __('Requested') }}: {{ $item->requested_quantity }}</div>
                            </div>
                        @endforeach
                        <label class="visually-hidden" for="approveReviewNotes">{{ __('Review notes (optional)') }}</label>
                        <textarea id="approveReviewNotes" name="review_notes" rows="3" maxlength="2000" class="form-control mb-3" placeholder="{{ __('Review notes (optional)') }}">{{ old('review_notes') }}</textarea>
                        <button class="btn btn-primary w-100">{{ __('Approve return') }}</button>
                    </form>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-2">{{ __('Reject return') }}</h4>
                    <form method="POST" action="{{ route('admin.returns.reject', $returnRequest) }}" data-confirm-message="{{ __('Reject this return request?') }}" data-submit-loading>
                        @csrf
                        @method('PATCH')
                        <label class="visually-hidden" for="rejectReviewNotes">{{ __('Reason for rejection') }}</label>
                        <textarea id="rejectReviewNotes" name="review_notes" rows="3" minlength="3" maxlength="2000" class="form-control mb-3" placeholder="{{ __('Reason for rejection') }}" required aria-required="true">{{ old('review_notes') }}</textarea>
                        <button class="btn btn-outline-danger w-100">{{ __('Reject return') }}</button>
                    </form>
                </div>
            </div>
        @elseif($returnRequest->status === $returnRequest::STATUS_APPROVED)
            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-2">{{ __('Receive returned items') }}</h4>
                    <p class="text-muted small">{{ __('V1 requires the full approved quantity to be received in one step. Restock is explicit and can be lower than received quantity for damaged or unsellable items.') }}</p>
                    <form method="POST" action="{{ route('admin.returns.receive', $returnRequest) }}" data-submit-loading data-confirm-message="{{ __('Mark these items received and apply the entered restock quantities? Inventory will increase for every unit marked for restock.') }}">
                        @csrf
                        @method('PATCH')
                        @foreach($returnRequest->items as $item)
                            @php($approved = (int)($item->approved_quantity ?? 0))
                            <div class="border rounded-4 p-3 mb-3 {{ $approved < 1 ? 'opacity-75' : '' }}">
                                <div class="fw-bold mb-2">{{ $item->orderItem?->product_name }}</div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold" for="receivedQuantity-{{ $item->id }}">{{ __('Received quantity') }}</label>
                                        <input id="receivedQuantity-{{ $item->id }}" type="number" name="received_quantities[{{ $item->id }}]" min="0" max="{{ $approved }}" value="{{ $approved }}" class="form-control" readonly>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold" for="restockQuantity-{{ $item->id }}">{{ __('Restock quantity') }}</label>
                                        <input id="restockQuantity-{{ $item->id }}" type="number" name="restock_quantities[{{ $item->id }}]" min="0" max="{{ $approved }}" value="0" class="form-control" {{ $approved < 1 ? 'readonly' : '' }}>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <button class="btn btn-primary w-100">{{ __('Mark received') }}</button>
                    </form>
                </div>
            </div>
        @elseif($returnRequest->status === $returnRequest::STATUS_RECEIVED)
            <div class="admin-card">
                <div class="admin-card-body">
                    @php($hasExchangeItems = $returnRequest->items->contains(fn ($item) => $item->requested_resolution === \App\Models\ReturnRequestItem::RESOLUTION_EXCHANGE && (int) $item->received_quantity > 0))
                    <h4 class="mb-2">{{ __('Complete return') }}</h4>
                    <p class="text-muted small mb-1">{{ __('Refund amount is explicit and uses the canonical order refund ledger. An exchange order is required when received items are approved for exchange.') }}</p>
                    <p class="text-muted small">{{ __('Linking an exchange order records the replacement linkage only. Settle any price difference through explicit payment or refund workflows.') }}</p>
                    <form method="POST" action="{{ route('admin.returns.complete', $returnRequest) }}" data-submit-loading data-return-complete-form data-return-currency="{{ $returnRequest->order?->currency ?? 'EGP' }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="returnRefundAmount">{{ __('Refund amount') }}</label>
                            <div class="input-group"><span class="input-group-text">{{ $returnRequest->order?->currency ?? 'EGP' }}</span><input id="returnRefundAmount" type="number" name="refund_amount" min="0" step="0.01" value="{{ old('refund_amount', 0) }}" class="form-control"></div>
                        </div>
                        @if($hasExchangeItems)
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="returnExchangeOrderId">{{ __('Exchange order') }} <span class="text-danger">*</span></label>
                                <input id="returnExchangeOrderId" type="number" name="exchange_order_id" min="1" value="{{ old('exchange_order_id') }}" class="form-control" required>
                            </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="returnCompletionNotes">{{ __('Completion notes') }}</label>
                            <textarea id="returnCompletionNotes" name="completion_notes" rows="4" maxlength="2000" class="form-control">{{ old('completion_notes') }}</textarea>
                        </div>
                        <button class="btn btn-primary w-100">{{ __('Complete return') }}</button>
                    </form>
                </div>
            </div>
        @if($returnRequest->status === $returnRequest::STATUS_RECEIVED)
            <div class="modal fade" id="returnCompleteConfirmModal" tabindex="-1" aria-labelledby="returnCompleteConfirmTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="returnCompleteConfirmTitle">{{ __('Confirm return completion') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2" data-return-complete-copy></p>
                            <p class="text-muted small mb-0">{{ __('Completing the return closes its lifecycle. Any refund entered here is recorded through the canonical order refund ledger.') }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('Review return') }}</button>
                            <button type="button" class="btn btn-primary" data-confirm-return-complete>{{ __('Complete return') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @else
            <div class="admin-card"><div class="admin-card-body"><h4>{{ __('Return lifecycle closed') }}</h4><p class="text-muted mb-0">{{ __('This return is read-only in its current status.') }}</p></div></div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-return-complete-form]');
    const modalElement = document.getElementById('returnCompleteConfirmModal');
    if (!form || !modalElement || typeof bootstrap === 'undefined') return;

    const amountInput = form.querySelector('[name="refund_amount"]');
    const copy = modalElement.querySelector('[data-return-complete-copy]');
    const confirmButton = modalElement.querySelector('[data-confirm-return-complete]');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    let confirmed = false;

    form.addEventListener('submit', function (event) {
        if (confirmed) return;
        event.preventDefault();
        const amount = amountInput?.value || '0';
        if (copy) {
            copy.textContent = @json(__('Complete this return with a refund of :amount?', ['amount' => '__AMOUNT__']))
                .replace('__AMOUNT__', form.dataset.returnCurrency + ' ' + amount);
        }
        modal.show();
    });

    confirmButton?.addEventListener('click', function () {
        confirmed = true;
        modal.hide();
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    });
});
</script>
@endpush
