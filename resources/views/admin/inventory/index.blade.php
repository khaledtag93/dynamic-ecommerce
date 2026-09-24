@extends('layouts.admin')

@section('title', __('Inventory') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Operations')" :title="__('Inventory')" :description="__('Monitor stock movement, low stock alerts, and expiration risks with safer fallbacks.')">
    <a href="{{ route('admin.inventory.scan') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-barcode-scan"></i><span>{{ __('Scan barcode') }}</span></a>
    <a href="{{ route('admin.inventory.adjust') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-clipboard-edit-outline"></i><span>{{ __('Adjust stock') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-archive-outline"></i></span>
            <div class="admin-stat-label">{{ __('Movements') }}</div>
            <div class="admin-stat-value">{{ $inventoryStats['total_movements'] }}</div>
            <div class="text-muted small mt-2">{{ __('Recorded stock movement entries.') }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-alert-outline"></i></span>
            <div class="admin-stat-label">{{ __('Low stock') }}</div>
            <div class="admin-stat-value">{{ $lowStockProducts->count() }}</div>
            <div class="text-muted small mt-2">{{ __('Products that need replenishment attention.') }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-calendar-clock-outline"></i></span>
            <div class="admin-stat-label">{{ __('Near expiry') }}</div>
            <div class="admin-stat-value">{{ $nearExpiryProducts->count() }}</div>
            <div class="text-muted small mt-2">{{ __('Products expiring within the next 30 days.') }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-format-list-bulleted-type"></i></span>
            <div class="admin-stat-label">{{ __('Movement types') }}</div>
            <div class="admin-stat-value">{{ $inventoryStats['movement_types'] }}</div>
            <div class="text-muted small mt-2">{{ __('Movement categories currently recorded in the inventory ledger.') }}</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <h4 class="mb-0">{{ __('Low stock attention') }}</h4>
                    <span class="badge admin-status-badge badge-soft-warning">{{ $lowStockProducts->count() }} {{ __('items') }}</span>
                </div>

                @forelse($lowStockProducts as $product)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                        <div>
                            <div class="fw-semibold">{{ $product->name ?: __('Unnamed product') }}</div>
                            <div class="text-muted small">{{ $product->category?->name ?? __('No category') }}</div>
                            <a href="{{ route('admin.products.edit', $product) }}" class="small text-decoration-none">{{ __('Open product') }} <i class="mdi mdi-arrow-top-right"></i></a>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">{{ $product->quantity_value ?? $product->quantity ?? 0 }}</div>
                            <div class="text-muted small">{{ __('available') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="admin-empty-state py-4">
                        <div class="empty-icon"><i class="mdi mdi-check-circle-outline"></i></div>
                        <h5 class="mb-2">{{ __('No low stock items right now') }}</h5>
                        <p class="text-muted mb-0">{{ __('Inventory looks healthy at the moment.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <h4 class="mb-0">{{ __('Near expiry') }}</h4>
                    <span class="badge admin-status-badge badge-soft-secondary">{{ $nearExpiryProducts->count() }} {{ __('items') }}</span>
                </div>

                @forelse($nearExpiryProducts as $product)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                        <div>
                            <div class="fw-semibold">{{ $product->name ?: __('Unnamed product') }}</div>
                            <div class="text-muted small">{{ $product->category?->name ?? __('No category') }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold">{{ optional($product->expiration_date)->format('M d, Y') ?: __('Not set') }}</div>
                            <div class="text-muted small">{{ __('expiry date') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="admin-empty-state py-4">
                        <div class="empty-icon"><i class="mdi mdi-calendar-check-outline"></i></div>
                        <h5 class="mb-2">{{ __('No products near expiry') }}</h5>
                        <p class="text-muted mb-0">{{ __('Nothing is expiring in the next 30 days.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="mb-3">
            <h4 class="mb-1">{{ __('Movement explorer') }}</h4>
            <p class="text-muted small mb-0">{{ __('Trace stock changes by product, SKU, order, movement type, or source.') }}</p>
        </div>
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="row g-3 align-items-end" data-live-filter>
            <div class="col-lg-4">
                <label class="form-label fw-semibold">{{ __('Search movements') }}</label>
                <input type="search" name="search" data-live-search autocomplete="off" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Product, SKU, order number, or reason') }}">
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold">{{ __('Movement type') }}</label>
                <select name="type" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All types') }}</option>
                    @foreach($movementTypes as $type)
                        <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ __(str_replace('_', ' ', IlluminateSupportStr::headline($type))) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold">{{ __('Source') }}</label>
                <select name="reference" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All sources') }}</option>
                    <option value="order" @selected($filters['reference'] === 'order')>{{ __('Orders') }}</option>
                    <option value="purchase" @selected($filters['reference'] === 'purchase')>{{ __('Purchases') }}</option>
                    <option value="manual" @selected($filters['reference'] === 'manual')>{{ __('Manual / system') }}</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                <select name="per_page" class="form-select" data-live-filter-control>
                    @foreach([20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill">{{ __('Apply') }}</button>
                <a href="{{ route('admin.inventory.index') }}" class="btn btn-light border" data-live-reset>{{ __('Reset') }}</a>
            </div>
        </form>
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.inventory.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</div>

@include('admin.inventory._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
