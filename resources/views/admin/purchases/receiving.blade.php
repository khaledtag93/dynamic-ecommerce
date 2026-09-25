@extends('layouts.admin')

@section('title', __('Barcode receiving') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Barcode receiving')" :description="__('Verify each physical unit before the final protected stock receipt.')">
    <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to purchase') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <div class="admin-stat-label">{{ __('Ordered units') }}</div>
                <div class="admin-stat-value">{{ $receivingStats['ordered_units'] }}</div>
                <div class="text-muted small mt-2">{{ __('Units expected across all purchase lines.') }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <div class="admin-stat-label">{{ __('Verified units') }}</div>
                <div class="admin-stat-value">{{ $receivingStats['verified_units'] }}</div>
                <div class="text-muted small mt-2">{{ __('Units counted through barcode verification.') }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <div class="admin-stat-label">{{ __('Remaining') }}</div>
                <div class="admin-stat-value">{{ $receivingStats['remaining_units'] }}</div>
                <div class="text-muted small mt-2">{{ __('Units still waiting for verification.') }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <div class="admin-stat-label">{{ __('Verification status') }}</div>
                <div class="admin-stat-value fs-5">{{ $receivingStats['complete'] ? __('Complete') : __('In progress') }}</div>
                <div class="text-muted small mt-2">{{ __('Final receipt stays separate from scanning.') }}</div>
            </div>
        </div>
    </div>

    @if($purchase->status === \App\Models\Purchase::STATUS_ORDERED)
        <div class="row g-4 mb-4">
            <div class="col-xl-5">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <div class="d-flex align-items-start gap-3 mb-4">
                            <span class="admin-stat-icon"><i class="mdi mdi-barcode-scan"></i></span>
                            <div>
                                <h4 class="mb-1">{{ __('Scan received unit') }}</h4>
                                <p class="text-muted small mb-0">{{ __('Each accepted scan verifies one unit only. Inventory is not changed until final receipt.') }}</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.purchases.receiving.scan', $purchase) }}" data-submit-loading>
                            @csrf
                            <label for="purchaseReceivingBarcode" class="form-label fw-semibold">{{ __('Barcode') }}</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i class="mdi mdi-barcode"></i></span>
                                <input
                                    id="purchaseReceivingBarcode"
                                    name="barcode"
                                    type="text"
                                    value="{{ old('barcode') }}"
                                    class="form-control @error('barcode') is-invalid @enderror"
                                    placeholder="{{ __('Scan the physical item barcode') }}"
                                    maxlength="255"
                                    autocomplete="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    enterkeyhint="done"
                                    autofocus
                                >
                                <button class="btn btn-primary" data-loading-text="{{ __('Verifying...') }}">{{ __('Verify unit') }}</button>
                            </div>
                            @error('barcode')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            @error('purchase')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            @error('purchase_item_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </form>

                        <div class="d-flex align-items-center gap-2 flex-wrap mt-3">
                            <span class="admin-chip"><i class="mdi mdi-keyboard-outline me-1"></i>{{ __('HID keyboard scanners supported') }}</span>
                            <span class="text-muted small">{{ __('One scan equals one verified physical unit.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <h4 class="mb-1">{{ __('Final receipt') }}</h4>
                        <p class="text-muted small mb-4">{{ __('Complete every line first. The final action rechecks verification under database locks before adding any stock.') }}</p>

                        @if($receivingStats['complete'])
                            <div class="alert alert-success">
                                <div class="fw-semibold">{{ __('All ordered units are verified.') }}</div>
                                <div class="small">{{ __('You can now receive the purchase into inventory once.') }}</div>
                            </div>

                            <form
                                method="POST"
                                action="{{ route('admin.purchases.receive-verified', $purchase) }}"
                                data-submit-loading
                                data-confirm-title="{{ __('Confirm barcode-verified receipt') }}"
                                data-confirm-message="{{ __('Add all verified purchase quantities to inventory now?') }}"
                                data-confirm-subtitle="{{ __('The server will lock and recheck the purchase, verification counts, catalog records, and variants before writing stock.') }}"
                                data-confirm-ok="{{ __('Receive verified stock') }}"
                            >
                                @csrf
                                <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Receiving...') }}">
                                    <i class="mdi mdi-package-down"></i><span>{{ __('Receive verified stock') }}</span>
                                </button>
                            </form>
                        @else
                            <div class="admin-section-card">
                                <div class="fw-semibold mb-1">{{ __('Receiving is not complete yet') }}</div>
                                <div class="text-muted small">{{ __('Scan the remaining units or correct a previous scan before final receipt.') }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info mb-4">
            <div class="fw-semibold">{{ __('This purchase is no longer open for barcode verification.') }}</div>
            <div class="small">{{ __('The verification history remains visible below for review.') }}</div>
        </div>
    @endif

    @if(session('receiving_choices'))
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-1">{{ __('Choose the purchase line') }}</h4>
                <p class="text-muted small mb-3">{{ __('This same catalog item appears on multiple purchase lines. Choose the exact cost / expiry line for this scanned unit.') }}</p>

                <div class="row g-3">
                    @foreach(session('receiving_choices') as $choice)
                        <div class="col-lg-6">
                            <div class="border rounded-4 p-3 h-100">
                                <div class="fw-semibold mb-1">{{ $choice['product_name'] }}</div>
                                <div class="text-muted small mb-3">{{ $choice['variant_name'] ?: ($choice['sku'] ?: __('No variant')) }}</div>
                                <div class="row g-2 small mb-3">
                                    <div class="col-4"><span class="text-muted d-block">{{ __('Ordered') }}</span><span class="fw-semibold">{{ $choice['quantity'] }}</span></div>
                                    <div class="col-4"><span class="text-muted d-block">{{ __('Unit cost') }}</span><span class="fw-semibold">{{ number_format((float) $choice['unit_cost'], 2) }}</span></div>
                                    <div class="col-4"><span class="text-muted d-block">{{ __('Expiry') }}</span><span class="fw-semibold">{{ $choice['expiration_date'] ?: '—' }}</span></div>
                                </div>
                                <form method="POST" action="{{ route('admin.purchases.receiving.scan', $purchase) }}" data-submit-loading>
                                    @csrf
                                    <input type="hidden" name="barcode" value="{{ session('receiving_barcode') }}">
                                    <input type="hidden" name="purchase_item_id" value="{{ $choice['id'] }}">
                                    <button class="btn btn-outline-primary btn-sm">{{ __('Count this scan on this line') }}</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-table-toolbar">
                <div>
                    <h4 class="mb-1">{{ __('Receiving progress') }}</h4>
                    <div class="text-muted small">{{ __('Barcode verification is tracked per purchase line and can be corrected before final receipt.') }}</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('Barcode') }}</th>
                            <th>{{ __('Ordered') }}</th>
                            <th>{{ __('Verified') }}</th>
                            <th>{{ __('Remaining') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Last scan') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $item)
                            @php
                                $progress = $progressByItem->get($item->id);
                                $verified = (int) optional($progress)->verified_quantity;
                                $ordered = (int) $item->quantity;
                                $remaining = max(0, $ordered - $verified);
                                $lineBarcode = $item->variant?->barcode ?: ($item->product?->barcode ?: null);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->product_name }}</div>
                                    <div class="text-muted small">{{ $item->variant_name ?: ($item->sku ?: __('Simple product')) }}</div>
                                </td>
                                <td>
                                    @if($lineBarcode)
                                        <span class="font-monospace small">{{ $lineBarcode }}</span>
                                    @else
                                        <span class="badge admin-status-badge badge-soft-warning">{{ __('No scannable barcode') }}</span>
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $ordered }}</td>
                                <td class="fw-semibold">{{ $verified }}</td>
                                <td>{{ $remaining }}</td>
                                <td>
                                    @if($verified === $ordered && $ordered > 0)
                                        <span class="badge admin-status-badge badge-soft-success">{{ __('Verified') }}</span>
                                    @elseif($verified > 0)
                                        <span class="badge admin-status-badge badge-soft-warning">{{ __('In progress') }}</span>
                                    @else
                                        <span class="badge admin-status-badge badge-soft-secondary">{{ __('Waiting') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($progress?->last_scanned_at)
                                        <div class="small">{{ $progress->last_scanned_at->format('M d, Y H:i') }}</div>
                                        <div class="text-muted small">{{ $progress->lastScannedBy?->name ?: '—' }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($purchase->status === \App\Models\Purchase::STATUS_ORDERED && $verified > 0)
                                        <form method="POST" action="{{ route('admin.purchases.receiving.undo', ['purchase' => $purchase->id, 'purchaseItem' => $item->id]) }}" class="d-inline" data-submit-loading data-confirm-title="{{ __('Undo verified unit') }}" data-confirm-message="{{ __('Remove one verified unit from this purchase line?') }}" data-confirm-subtitle="{{ __('Inventory has not been changed yet. This only reduces the barcode verification count by one unit before final receipt.') }}" data-confirm-ok="{{ __('Undo one') }}">
                                            @csrf
                                            <button class="btn btn-sm btn-light border">{{ __('Undo one') }}</button>
                                        </form>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('purchaseReceivingBarcode');

    if (input) {
        input.focus();
        input.select();
    }
});
</script>
@endsection
