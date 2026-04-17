@extends('layouts.admin')

@section('title', __('Suppliers') . ' | ' . __('Admin Dashboard'))

@php
    $sort = $filters['sort'] ?? 'updated_at';
    $direction = $filters['direction'] ?? 'desc';
    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ], fn ($value) => $value !== null && $value !== '');

        return route('admin.suppliers.index', $query);
    };
@endphp

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Suppliers')" :description="__('Manage vendors, contacts, and sourcing relationships without losing purchasing context.')">
    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Add supplier') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
@if(session('success'))<div class="alert alert-success border-0 shadow-sm rounded-4 mb-0">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger border-0 shadow-sm rounded-4 mb-0">{{ session('error') }}</div>@endif

<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total suppliers'), 'value' => $stats['total'], 'copy' => __('Every supplier profile saved in the system.'), 'icon' => 'mdi-truck-delivery-outline'],
        ['label' => __('Active'), 'value' => $stats['active'], 'copy' => __('Suppliers currently available for purchasing.'), 'icon' => 'mdi-check-decagram-outline'],
        ['label' => __('Inactive'), 'value' => $stats['inactive'], 'copy' => __('Suppliers paused or archived.'), 'icon' => 'mdi-pause-circle-outline'],
        ['label' => __('With purchases'), 'value' => $stats['with_purchases'], 'copy' => __('Suppliers that already have purchase history.'), 'icon' => 'mdi-receipt-text-outline'],
    ] as $card)
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                <div class="admin-stat-label">{{ $card['label'] }}</div>
                <div class="admin-stat-value">{{ $card['value'] }}</div>
                <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card mb-4"><div class="admin-card-body">
    <form method="GET" class="admin-filter-grid" data-submit-loading>
        <div>
            <label class="form-label fw-semibold">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Search suppliers') }}">
        </div>
        <div>
            <label class="form-label fw-semibold">{{ __('Status') }}</label>
            <select name="status" class="form-select">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </div>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <div class="admin-filter-actions">
            <button class="btn btn-primary btn-text-icon" type="submit"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
        </div>
    </form>
</div></div>

<div class="admin-card"><div class="admin-card-body">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">{{ __('Suppliers list') }}</h4>
            <div class="text-muted small">{{ __('Showing :count supplier(s) on this page.', ['count' => $suppliers->count()]) }}</div>
        </div>
    </div>

    @if($suppliers->count())
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th><a class="table-sort-btn" href="{{ $sortLink('name') }}">{{ __('Name') }} @if($sort === 'name') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" href="{{ $sortLink('company') }}">{{ __('Company') }} @if($sort === 'company') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" href="{{ $sortLink('email') }}">{{ __('Contact') }} @if($sort === 'email') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" href="{{ $sortLink('items_count') }}">{{ __('Linked items') }} @if($sort === 'items_count') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" href="{{ $sortLink('is_active') }}">{{ __('Status') }} @if($sort === 'is_active') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th><a class="table-sort-btn" href="{{ $sortLink('updated_at') }}">{{ __('Updated') }} @if($sort === 'updated_at') <i class="mdi {{ $direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i> @endif</a></th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($suppliers as $supplier)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $supplier->name }}</div>
                            <div class="text-muted small">{{ $supplier->contact_name ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $supplier->company ?: '—' }}</div>
                            <div class="text-muted small">{{ $supplier->country ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="small mb-1">{{ $supplier->email ?: '—' }}</div>
                            <div class="small text-muted">{{ $supplier->phone ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $supplier->items_count }}</div>
                            <div class="text-muted small">{{ __('Purchases') }}: {{ $supplier->purchases_count }}</div>
                        </td>
                        <td>
                            <span class="badge admin-status-badge {{ $supplier->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $supplier->is_active ? __('Active') : __('Inactive') }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ optional($supplier->updated_at)->format('d M Y') }}</div>
                            <div class="text-muted small">{{ optional($supplier->updated_at)->format('h:i A') }}</div>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn-table-icon btn-edit" title="{{ __('Edit Supplier') }}"><i class="mdi mdi-pencil-outline"></i></a>
                                <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}" data-confirm-message="{{ __('Delete this supplier?') }}">@csrf @method('DELETE')<button class="btn-table-icon btn-delete" title="{{ __('Delete this supplier?') }}"><i class="mdi mdi-trash-can-outline"></i></button></form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4 d-flex justify-content-center">{{ $suppliers->links() }}</div>
    @else
        <div class="admin-empty-state">
            <div class="admin-empty-icon"><i class="mdi mdi-truck-delivery-outline"></i></div>
            <h4 class="fw-bold mb-2">{{ __('No suppliers found') }}</h4>
            <p class="text-muted mb-3">{{ __('Create your first supplier to organize purchases and sourcing.') }}</p>
            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">{{ __('Add supplier') }}</a>
        </div>
    @endif
</div></div>
</div>
@endsection
