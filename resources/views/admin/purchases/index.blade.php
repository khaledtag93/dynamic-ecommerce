@extends('layouts.admin')

@section('title', __('Purchases') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Purchases')" :description="__('Track procurement activity, receive stock safely, and keep supplier purchasing history clear.')">
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('New purchase') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-0">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Purchase orders'), 'value' => $stats['total'], 'copy' => __('All procurement records.'), 'icon' => 'mdi-clipboard-text-outline'],
            ['label' => __('Awaiting receipt'), 'value' => $stats['awaiting'], 'copy' => __('Orders that still need stock receiving.'), 'icon' => 'mdi-truck-clock-outline'],
            ['label' => __('Received'), 'value' => $stats['received'], 'copy' => __('Purchases already added to inventory.'), 'icon' => 'mdi-package-check'],
            ['label' => __('Procurement value'), 'value' => 'EGP ' . number_format($stats['value'], 2), 'copy' => __('Total recorded purchase value.'), 'icon' => 'mdi-cash-multiple'],
        ] as $card)
            <div class="col-md-6 col-xl-3"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span><div class="admin-stat-label">{{ $card['label'] }}</div><div class="admin-stat-value">{{ $card['value'] }}</div><div class="text-muted small mt-2">{{ $card['copy'] }}</div></div></div>
        @endforeach
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                <div><h4 class="mb-1">{{ __('Procurement workspace') }}</h4><p class="text-muted small mb-0">{{ __('Find purchase orders quickly and focus on stock that still needs to be received.') }}</p></div>
                @if($stats['awaiting'] > 0)<a href="{{ route('admin.purchases.index', ['status' => \App\Models\Purchase::STATUS_ORDERED]) }}" class="btn {{ $filters['status'] === \App\Models\Purchase::STATUS_ORDERED ? 'btn-primary' : 'btn-light border' }} btn-sm">{{ __('Awaiting receipt') }} · {{ $stats['awaiting'] }}</a>@endif
            </div>
            <form method="GET" class="row g-3 align-items-end" data-submit-loading>
                <div class="col-lg-4"><label class="form-label fw-semibold">{{ __('Search purchases') }}</label><input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Reference, supplier, or company') }}"></div>
                <div class="col-md-4 col-lg-2"><label class="form-label fw-semibold">{{ __('Status') }}</label><select name="status" class="form-select"><option value="">{{ __('All statuses') }}</option>@foreach(\App\Models\Purchase::statusOptions() as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ __($label) }}</option>@endforeach</select></div>
                <div class="col-md-4 col-lg-3"><label class="form-label fw-semibold">{{ __('Supplier') }}</label><select name="supplier_id" class="form-select"><option value="">{{ __('All suppliers') }}</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)$filters['supplier_id'] === (string)$supplier->id)>{{ $supplier->name }}{{ $supplier->company ? ' · '.$supplier->company : '' }}</option>@endforeach</select></div>
                <div class="col-md-4 col-lg-1"><label class="form-label fw-semibold">{{ __('Per page') }}</label><select name="per_page" class="form-select">@foreach([15,30,60] as $size)<option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>@endforeach</select></div>
                <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-fill" data-loading-text="{{ __('Filtering...') }}">{{ __('Apply') }}</button><a href="{{ route('admin.purchases.index') }}" class="btn btn-light border">{{ __('Reset') }}</a></div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="admin-table-toolbar">
                <div>
                    <h4 class="mb-1">{{ __('Purchase list') }}</h4>
                    <div class="text-muted small">{{ __('Showing :count purchase record(s) on this page.', ['count' => $purchases->count()]) }}</div>
                </div>
                <div class="admin-table-toolbar-actions">
                    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-truck-delivery-outline"></i><span>{{ __('Suppliers') }}</span></a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Supplier') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Total') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchases as $purchase)
                            <tr>
                                <td class="fw-semibold">{{ $purchase->reference }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $purchase->supplier?->name ?: '—' }}</div>
                                    <div class="text-muted small">{{ $purchase->supplier?->company ?: __('No company assigned') }}</div>
                                </td>
                                <td><span class="badge admin-status-badge badge-soft-info">{{ \App\Models\Purchase::statusOptions()[$purchase->status] ?? ucfirst($purchase->status) }}</span></td>
                                <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                                <td class="fw-bold">EGP {{ number_format($purchase->grand_total, 2) }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                        <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn-table-icon btn-edit" title="{{ __('View purchase') }}"><i class="mdi mdi-eye-outline"></i></a>
                                        @if($purchase->status !== \App\Models\Purchase::STATUS_RECEIVED)
                                            <form method="POST" action="{{ route('admin.purchases.receive', $purchase) }}" data-submit-loading>@csrf <button class="btn btn-sm btn-primary btn-text-icon" data-loading-text="{{ __('Receiving...') }}"><i class="mdi mdi-package-down"></i><span>{{ __('Receive stock') }}</span></button></form>
                                        @else
                                            <span class="text-success small">{{ __('Received on :date', ['date' => optional($purchase->received_date)->format('d M Y')]) }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-5 text-center">
                                    <div class="admin-empty-state py-4">
                                        <div class="admin-empty-icon"><i class="mdi mdi-cart-off"></i></div>
                                        <h5 class="mb-2">{{ __('No purchases yet') }}</h5>
                                        <p class="text-muted mb-0">{{ __('Create the first purchase order to start receiving stock into inventory.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 admin-pagination-wrap">{{ $purchases->links() }}</div>
</div>
@endsection
