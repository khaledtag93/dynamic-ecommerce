@extends('layouts.admin')

@section('title', $returnRequest->reference . ' | Admin')

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
                                <label class="form-label fw-semibold">{{ $item->orderItem?->product_name }} · {{ __('Approved quantity') }}</label>
                                <input type="number" name="approved_quantities[{{ $item->id }}]" min="0" max="{{ $item->requested_quantity }}" value="{{ old('approved_quantities.'.$item->id, $item->requested_quantity) }}" class="form-control" required>
                                <div class="small text-muted">{{ __('Requested') }}: {{ $item->requested_quantity }}</div>
                            </div>
                        @endforeach
                        <textarea name="review_notes" rows="3" maxlength="2000" class="form-control mb-3" placeholder="{{ __('Review notes (optional)') }}">{{ old('review_notes') }}</textarea>
                        <button class="btn btn-primary w-100">{{ __('Approve return') }}</button>
                    </form>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-2">{{ __('Reject return') }}</h4>
                    <form method="POST" action="{{ route('admin.returns.reject', $returnRequest) }}" data-confirm-message="{{ __('Reject this return request?') }}">
                        @csrf
                        @method('PATCH')
                        <textarea name="review_notes" rows="3" minlength="3" maxlength="2000" class="form-control mb-3" placeholder="{{ __('Reason for rejection') }}" required>{{ old('review_notes') }}</textarea>
                        <button class="btn btn-outline-danger w-100">{{ __('Reject return') }}</button>
                    </form>
                </div>
            </div>
        @elseif($returnRequest->status === $returnRequest::STATUS_APPROVED)
            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-2">{{ __('Receive returned items') }}</h4>
                    <p class="text-muted small">{{ __('V1 requires the full approved quantity to be received in one step. Restock is explicit and can be lower than received quantity for damaged or unsellable items.') }}</p>
                    <form method="POST" action="{{ route('admin.returns.receive', $returnRequest) }}" data-submit-loading>
                        @csrf
                        @method('PATCH')
                        @foreach($returnRequest->items as $item)
                            @php($approved = (int)($item->approved_quantity ?? 0))
                            <div class="border rounded-4 p-3 mb-3 {{ $approved < 1 ? 'opacity-75' : '' }}">
                                <div class="fw-bold mb-2">{{ $item->orderItem?->product_name }}</div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">{{ __('Received quantity') }}</label>
                                        <input type="number" name="received_quantities[{{ $item->id }}]" min="0" max="{{ $approved }}" value="{{ $approved }}" class="form-control" readonly>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">{{ __('Restock quantity') }}</label>
                                        <input type="number" name="restock_quantities[{{ $item->id }}]" min="0" max="{{ $approved }}" value="0" class="form-control" {{ $approved < 1 ? 'readonly' : '' }}>
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
                    <h4 class="mb-2">{{ __('Complete return') }}</h4>
                    <p class="text-muted small">{{ __('Refund amount is explicit and uses the canonical order refund ledger. Exchange order is optional and must be a different order.') }}</p>
                    <form method="POST" action="{{ route('admin.returns.complete', $returnRequest) }}" data-submit-loading>
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Refund amount') }}</label>
                            <div class="input-group"><span class="input-group-text">{{ $returnRequest->order?->currency ?? 'EGP' }}</span><input type="number" name="refund_amount" min="0" step="0.01" value="{{ old('refund_amount', 0) }}" class="form-control"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Exchange order ID (optional)') }}</label>
                            <input type="number" name="exchange_order_id" min="1" value="{{ old('exchange_order_id') }}" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Completion notes') }}</label>
                            <textarea name="completion_notes" rows="4" maxlength="2000" class="form-control">{{ old('completion_notes') }}</textarea>
                        </div>
                        <button class="btn btn-primary w-100">{{ __('Complete return') }}</button>
                    </form>
                </div>
            </div>
        @else
            <div class="admin-card"><div class="admin-card-body"><h4>{{ __('Return lifecycle closed') }}</h4><p class="text-muted mb-0">{{ __('This return is read-only in its current status.') }}</p></div></div>
        @endif
    </div>
</div>
@endsection
