@extends('layouts.admin')

@section('title', __('Purchase Details') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Purchase Details')" :description="__('Reference') . ': ' . $purchase->reference">
    <a href="{{ route('admin.purchases.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to purchases') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="row g-4">
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Supplier') }}</div><div class="admin-stat-value">{{ $purchase->supplier?->name ?: '—' }}</div></div></div>
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Status') }}</div><div class="admin-stat-value">{{ \App\Models\Purchase::statusOptions()[$purchase->status] ?? ucfirst($purchase->status) }}</div></div></div>
        <div class="col-md-4"><div class="admin-card admin-stat-card h-100"><div class="admin-stat-label">{{ __('Grand total') }}</div><div class="admin-stat-value">{{ number_format($purchase->grand_total, 2) }}</div></div></div>
    </div>

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-table-toolbar">
                <div>
                    <h4 class="mb-1">{{ __('Received items') }}</h4>
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
                                <td>{{ number_format($item->unit_cost, 2) }}</td>
                                <td>{{ number_format($item->line_total, 2) }}</td>
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
