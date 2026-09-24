@extends('layouts.admin')

@section('title', __('Purchase Details') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Purchase Details')" :description="__('Reference') . ': ' . $purchase->reference">
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.purchases.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to purchases') }}</span></a>
        @if($purchase->status === \App\Models\Purchase::STATUS_ORDERED)
            <a href="{{ route('admin.purchases.receiving', $purchase) }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-barcode-scan"></i><span>{{ __('Receive by barcode') }}</span></a>
            <form method="POST" action="{{ route('admin.purchases.receive', $purchase) }}" data-submit-loading data-confirm-title="{{ __('Confirm stock receipt') }}" data-confirm-message="{{ __('Receive this purchase and add its quantities to inventory without barcode verification?') }}" data-confirm-subtitle="{{ __('Manual receiving remains available. Stock and cost will still be updated once under the protected receipt transaction.') }}" data-confirm-ok="{{ __('Receive manually') }}">
                @csrf
                <button class="btn btn-light border btn-text-icon" data-loading-text="{{ __('Receiving...') }}"><i class="mdi mdi-package-down"></i><span>{{ __('Receive manually') }}</span></button>
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
                            <th>{{ __('Quantity') }}</th>
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
