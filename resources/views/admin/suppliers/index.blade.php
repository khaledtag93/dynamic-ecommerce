@extends('layouts.admin')

@section('title', __('Suppliers') . ' | ' . __('Admin Dashboard'))

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Suppliers')" :description="__('Manage vendors, contacts, and sourcing relationships without losing purchasing context.')">
    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Add supplier') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
@if(session('success'))<div class="alert alert-success border-0 shadow-sm rounded-4 mb-0">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger border-0 shadow-sm rounded-4 mb-0">{{ session('error') }}</div>@endif

<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total suppliers'), 'value' => $stats['total'], 'copy' => __('Every supplier profile saved in the system.'), 'icon' => 'mdi-truck-delivery-outline'],
        ['label' => __('Active'), 'value' => $stats['active'], 'copy' => __('Suppliers currently available for purchasing.'), 'icon' => 'mdi-check-decagram-outline'],
        ['label' => __('Inactive'), 'value' => $stats['inactive'], 'copy' => __('Suppliers paused or archived.'), 'icon' => 'mdi-pause-circle-outline'],
        ['label' => __('With purchases'), 'value' => $stats['with_purchases'], 'copy' => __('Suppliers that already have purchase history.'), 'icon' => 'mdi-receipt-text-outline'],
        ['label' => __('Unused suppliers'), 'value' => $stats['unused'], 'copy' => __('Suppliers without purchase history yet.'), 'icon' => 'mdi-link-variant-off'],
    ] as $card)
        <div class="col-md-6 col-xl">
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
    <form method="GET" action="{{ route('admin.suppliers.index') }}" class="admin-filter-grid" data-live-filter>
        <div>
            <label class="form-label fw-semibold">{{ __('Search') }}</label>
            <input type="search" name="search" data-live-search autocomplete="off" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Search suppliers') }}">
        </div>
        <div>
            <label class="form-label fw-semibold">{{ __('Status') }}</label>
            <select name="status" class="form-select" data-live-filter-control>
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-semibold">{{ __('Purchase usage') }}</label>
            <select name="usage" class="form-select" data-live-filter-control>
                <option value="">{{ __('All suppliers') }}</option>
                <option value="with_purchases" @selected(($filters['usage'] ?? '') === 'with_purchases')>{{ __('With purchases') }}</option>
                <option value="unused" @selected(($filters['usage'] ?? '') === 'unused')>{{ __('Unused') }}</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-semibold">{{ __('Per page') }}</label>
            <select name="per_page" class="form-select" data-live-filter-control>
                @foreach([12,24,48] as $size)
                    <option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'updated_at' }}" data-live-default="updated_at">
        <input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'desc' }}" data-live-default="desc">
        <div class="admin-filter-actions">
            <button class="btn btn-primary btn-text-icon" type="submit"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
        </div>
    </form>
    <div class="small mt-2" role="status" aria-live="polite" data-live-status
         data-loading="{{ __('Updating results...') }}"
         data-updated="{{ __('Results updated.') }}"
         data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
    <a href="{{ route('admin.suppliers.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
</div></div>

@include('admin.suppliers._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
