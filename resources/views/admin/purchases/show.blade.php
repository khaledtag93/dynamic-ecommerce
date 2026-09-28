@extends('layouts.admin')

@section('title', __('Purchase Details') . ' | ' . __('Admin'))

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Purchase Details')" :description="__('Reference') . ': ' . $purchase->reference">
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.purchases.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to purchases') }}</span></a>
        @if($canManageInventory && in_array($purchase->status, [\App\Models\Purchase::STATUS_ORDERED, \App\Models\Purchase::STATUS_PARTIALLY_RECEIVED], true))
            @if($purchase->status === \App\Models\Purchase::STATUS_ORDERED)
                <a href="{{ route('admin.purchases.receiving', $purchase) }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-barcode-scan"></i><span>{{ __('Receive by barcode') }}</span></a>
            @endif
            <form method="POST" action="{{ route('admin.purchases.receive', $purchase) }}" data-submit-loading data-confirm-title="{{ __('Confirm remaining stock receipt') }}" data-confirm-message="{{ __('Receive every remaining unit on this purchase now?') }}" data-confirm-subtitle="{{ __('Only quantities not already received will be added to inventory.') }}" data-confirm-ok="{{ __('Receive all remaining') }}">
                @csrf
                <button class="btn btn-light border btn-text-icon" data-loading-text="{{ __('Receiving...') }}"><i class="mdi mdi-package-down"></i><span>{{ __('Receive all remaining') }}</span></button>
            </form>
        @elseif($canManageInventory && $purchase->status === \App\Models\Purchase::STATUS_RECEIVED && $purchase->receivingProgress->isNotEmpty())
            <a href="{{ route('admin.purchases.receiving', $purchase) }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-barcode-scan"></i><span>{{ __('View barcode receiving') }}</span></a>
        @endif
    </div>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="row g-4">
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Supplier') }}</div><div class="admin-stat-value">{{ $purchase->supplier?->name ?: '—' }}</div></div></div>
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Status') }}</div><div class="admin-stat-value">{{ \App\Models\Purchase::statusOptions()[$purchase->status] ?? ucfirst($purchase->status) }}</div></div></div>
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Grand total') }}</div><div class="admin-stat-value">{{ $purchase->currency ?: 'EGP' }} {{ number_format($purchase->grand_total, 2) }}</div><div class="text-muted small mt-2">{{ __('Including shipping and tax.') }}</div></div></div>
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-4"><div class="admin-inline-label">{{ __('Purchase date') }}</div><div class="fw-semibold">{{ optional($purchase->purchase_date)->format('d M Y') ?: '—' }}</div></div>
                <div class="col-md-4"><div class="admin-inline-label">{{ __('Received date') }}</div><div class="fw-semibold">{{ optional($purchase->received_date)->format('d M Y') ?: __('Not received yet') }}</div></div>
                <div class="col-md-4"><div class="admin-inline-label">{{ __('Items') }}</div><div class="fw-semibold">{{ $purchase->items->count() }}</div></div>
                @if($purchase->notes)<div class="col-12"><div class="admin-inline-label">{{ __('Notes') }}</div><div>{{ $purchase->notes }}</div></div>@endif
            </div>
        </div>
    </div>

    @include('admin.purchases._settlement')

    @if($canManageInventory && in_array($purchase->status, [\App\Models\Purchase::STATUS_DRAFT, \App\Models\Purchase::STATUS_ORDERED], true))
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="admin-table-toolbar">
                    <div>
                        <h4 class="mb-1">{{ __('Cancel purchase') }}</h4>
                        <div class="text-muted small">{{ __('Cancel only while no stock has been received. A reason is required for the audit trail.') }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.purchases.cancel', $purchase) }}" data-submit-loading data-confirm-title="{{ __('Confirm purchase cancellation') }}" data-confirm-message="{{ __('Cancel this purchase order?') }}" data-confirm-subtitle="{{ __('No stock will be changed. Purchases with received stock require receipt reversal instead.') }}" data-confirm-ok="{{ __('Cancel purchase') }}">
                    @csrf
                    <label for="purchaseCancellationReason" class="form-label fw-semibold">{{ __('Cancellation reason') }}</label>
                    <textarea id="purchaseCancellationReason" name="cancellation_reason" maxlength="1000" rows="3" class="form-control @error('cancellation_reason') is-invalid @enderror" required aria-required="true">{{ old('cancellation_reason') }}</textarea>
                    @error('cancellation_reason')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('purchase')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-danger btn-text-icon mt-3" data-loading-text="{{ __('Cancelling...') }}"><i class="mdi mdi-close-circle-outline"></i><span>{{ __('Cancel purchase') }}</span></button>
                </form>
            </div>
        </div>
    @elseif($purchase->status === \App\Models\Purchase::STATUS_CANCELLED)
        <div class="alert alert-secondary mb-4">
            <div class="fw-semibold">{{ __('Purchase cancelled') }}</div>
            <div class="small">{{ $purchase->cancellation_reason ?: '—' }}</div>
            @if($purchase->cancelled_at)
                <div class="small text-muted mt-1">{{ $purchase->cancelled_at->format('d M Y, H:i') }} · {{ $purchase->cancelledBy?->name ?: '—' }}</div>
            @endif
        </div>
    @endif

    @if($canManageInventory && in_array($purchase->status, [\App\Models\Purchase::STATUS_ORDERED, \App\Models\Purchase::STATUS_PARTIALLY_RECEIVED], true))
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="admin-table-toolbar">
                    <div>
                        <h4 class="mb-1">{{ __('Partial receipt') }}</h4>
                        <div class="text-muted small">{{ __('Record only the units physically received now. Repeated submission of the same receipt is protected from adding stock twice.') }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.purchases.receive-partial', $purchase) }}" data-submit-loading>
                    @csrf
                    <input type="hidden" name="receipt_key" value="{{ $receiptKey }}">
                    <div class="table-responsive">
                        <table class="table admin-table align-middle mb-3">
                            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Ordered') }}</th><th>{{ __('Received') }}</th><th>{{ __('Remaining') }}</th><th>{{ __('Receive now') }}</th></tr></thead>
                            <tbody>
                                @foreach($purchase->items as $item)
                                    @php($remaining = max(0, (int) $item->quantity - (int) $item->received_quantity))
                                    <tr>
                                        <td><div class="fw-semibold">{{ $item->product_name }}</div><div class="text-muted small">{{ $item->variant_name ?: ($item->sku ?: '—') }}</div></td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ $item->received_quantity }}</td>
                                        <td class="fw-semibold">{{ $remaining }}</td>
                                        <td>
                                            <input type="number" class="form-control" name="items[{{ $item->id }}]" min="0" max="{{ $remaining }}" value="0" {{ $remaining < 1 ? 'disabled' : '' }} aria-label="{{ __('Receive now') }}">
                                            @if($errors->has("items.{$item->id}"))
                                                <div class="text-danger small mt-1">{{ $errors->first("items.{$item->id}") }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('items')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
                    @error('receipt_key')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
                    <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Receiving...') }}"><i class="mdi mdi-package-down"></i><span>{{ __('Record partial receipt') }}</span></button>
                </form>
            </div>
        </div>
    @endif

    @if($purchase->receipts->isNotEmpty())
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="admin-table-toolbar">
                    <div>
                        <h4 class="mb-1">{{ __('Receiving history') }}</h4>
                        <div class="text-muted small">{{ __('Each stock receipt remains auditable. Safe reversals are allowed only while no newer inventory activity exists for the affected stock.') }}</div>
                    </div>
                </div>

                @error('receipt')<div class="alert alert-danger">{{ $message }}</div>@enderror
                @foreach($purchase->receipts as $receipt)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <div class="fw-semibold">{{ __('Receipt') }} #{{ $receipt->id }}</div>
                                <div class="text-muted small">
                                    {{ \App\Models\PurchaseReceipt::methodOptions()[$receipt->receipt_method] ?? $receipt->receipt_method }}
                                    · {{ $receipt->received_at?->format('d M Y, H:i') }}
                                    · {{ $receipt->receivedBy?->name ?: '—' }}
                                </div>
                            </div>
                            <span class="badge admin-status-badge {{ $receipt->reversed_at ? 'badge-soft-danger' : 'badge-soft-success' }}">
                                {{ $receipt->reversed_at ? __('Reversed') : __('Active') }}
                            </span>
                        </div>

                        <div class="row g-2 mt-2">
                            @foreach($receipt->items as $receiptItem)
                                <div class="col-md-6">
                                    <div class="admin-section-card h-100">
                                        <div class="fw-semibold">{{ $receiptItem->purchaseItem?->product_name ?: __('Purchase item') }}</div>
                                        <div class="text-muted small">{{ __('Quantity') }}: {{ $receiptItem->quantity }} · {{ __('Unit cost') }}: {{ $purchase->currency ?: 'EGP' }} {{ number_format($receiptItem->unit_cost, 2) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($receipt->reversed_at)
                            <div class="alert alert-light border mt-3 mb-0">
                                <div class="fw-semibold">{{ __('Reversal') }} · {{ $receipt->reversed_at->format('d M Y, H:i') }}</div>
                                <div class="small">{{ $receipt->reversal_reason }}</div>
                                <div class="text-muted small">{{ $receipt->reversedBy?->name ?: '—' }}</div>
                            </div>
                        @elseif($canManageInventory && $receipt->receipt_method !== 'legacy_unknown')
                            <form method="POST" action="{{ route('admin.purchases.receipts.reverse', ['purchase' => $purchase->id, 'purchaseReceipt' => $receipt->id]) }}" class="mt-3" data-submit-loading data-confirm-title="{{ __('Confirm receipt reversal') }}" data-confirm-message="{{ __('Reverse this stock receipt?') }}" data-confirm-subtitle="{{ __('Reversal is allowed only when these receipt movements are still the latest inventory activity for every affected item.') }}" data-confirm-ok="{{ __('Reverse receipt') }}">
                                @csrf
                                <input type="hidden" name="reversal_receipt_id" value="{{ $receipt->id }}">
                                <label for="receiptReversalReason{{ $receipt->id }}" class="form-label fw-semibold">{{ __('Reversal reason') }}</label>
                                <textarea id="receiptReversalReason{{ $receipt->id }}" name="reversal_reason" maxlength="1000" rows="2" class="form-control" required aria-required="true"></textarea>
                                @if((int) old('reversal_receipt_id') === (int) $receipt->id)
                                    @error('reversal_reason')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                @endif
                                <button class="btn btn-outline-danger btn-sm mt-2" data-loading-text="{{ __('Reversing...') }}">{{ __('Reverse receipt') }}</button>
                            </form>
                        @elseif($canManageInventory)
                            <div class="text-muted small mt-3">{{ __('This legacy receipt cannot be reversed automatically because its receiving method is unknown.') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-table-toolbar">
                <div>
                    <h4 class="mb-1">{{ __('Purchase items') }}</h4>
                    <div class="text-muted small">{{ __('Review purchased products, variants, cost lines, and expiration dates in one place.') }}</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Variant') }}</th>
                            <th>{{ __('SKU') }}</th>
                            <th>{{ __('Ordered') }}</th>
                            <th>{{ __('Received') }}</th>
                            <th>{{ __('Remaining') }}</th>
                            <th>{{ __('Unit cost') }}</th>
                            <th>{{ __('Line total') }}</th>
                            <th>{{ __('Expiration date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $item)
                            <tr>
                                <td>{{ $item->product_name }}</td>
                                <td>{{ $item->variant_name ?: '—' }}</td>
                                <td>{{ $item->sku ?: '—' }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ $item->received_quantity }}</td>
                                <td>{{ max(0, (int) $item->quantity - (int) $item->received_quantity) }}</td>
                                <td>{{ $purchase->currency ?: 'EGP' }} {{ number_format($item->unit_cost, 2) }}</td>
                                <td class="fw-semibold">{{ $purchase->currency ?: 'EGP' }} {{ number_format($item->line_total, 2) }}</td>
                                <td>{{ $item->expiration_date ? \Illuminate\Support\Carbon::parse($item->expiration_date)->format('d M Y') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
