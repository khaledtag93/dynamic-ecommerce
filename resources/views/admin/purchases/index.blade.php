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
                                <td>{{ number_format($purchase->grand_total, 2) }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                        <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn-table-icon btn-edit" title="{{ __('View purchase') }}"><i class="mdi mdi-eye-outline"></i></a>
                                        @if($purchase->status !== \App\Models\Purchase::STATUS_RECEIVED)
                                            <form method="POST" action="{{ route('admin.purchases.receive', $purchase) }}">@csrf <button class="btn btn-sm btn-primary">{{ __('Receive stock') }}</button></form>
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
