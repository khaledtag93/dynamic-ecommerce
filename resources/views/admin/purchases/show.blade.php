@extends('layouts.admin')

@section('title', __('Purchase Details') . ' | ' . __('Admin'))

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Purchase Details')" :description="__('Reference') . ': ' . $purchase->reference">
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.purchases.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to purchases') }}</span></a>
        @if(in_array($purchase->status, [\App\Models\Purchase::STATUS_ORDERED, \App\Models\Purchase::STATUS_PARTIALLY_RECEIVED], true))
            @if($purchase->status === \App\Models\Purchase::STATUS_ORDERED)
                <a href="{{ route('admin.purchases.receiving', $purchase) }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-barcode-scan"></i><span>{{ __('Receive by barcode') }}</span></a>
            @endif
            <form method="POST" action="{{ route('admin.purchases.receive', $purchase) }}" data-submit-loading data-confirm-title="{{ __('Confirm remaining stock receipt') }}" data-confirm-message="{{ __('Receive every remaining unit on this purchase now?') }}" data-confirm-subtitle="{{ __('Only quantities not already received will be added to inventory.') }}" data-confirm-ok="{{ __('Receive all remaining') }}">
                @csrf
                <button class="btn btn-light border btn-text-icon" data-loading-text="{{ __('Receiving...') }}"><i class="mdi mdi-package-down"></i><span>{{ __('Receive all remaining') }}</span></button>
            </form>
        @elseif($purchase->status === \App\Models\Purchase::STATUS_RECEIVED && $purchase->receivingProgress->isNotEmpty())
            <a href="{{ route('admin.purchases.receiving', $purchase) }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-barcode-scan"></i><span>{{ __('View barcode receiving') }}</span></a>
        @endif
    </div>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="row g-4">
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Supplier') }}</div><div class="admin-stat-value">{{ $purchase->supplier?->name ?: '—' }}</div></div></div>
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Status') }}</div><div class="admin-stat-value">{{ \App\Models\Purchase::statusOptions()[$purchase->status] ?? ucfirst($purchase->status) }}</div></div></div>
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Grand total') }}</div><div class="admin-stat-value">EGP {{ number_format($purchase->grand_total, 2) }}</div><div class="text-muted small mt-2">{{ __('Including shipping and tax.') }}</div></div></div>
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

    @if(in_array($purchase->status, [\App\Models\Purchase::STATUS_ORDERED, \App\Models\Purchase::STATUS_PARTIALLY_RECEIVED], true))
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
                                <td>EGP {{ number_format($item->unit_cost, 2) }}</td>
                                <td class="fw-semibold">EGP {{ number_format($item->line_total, 2) }}</td>
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
