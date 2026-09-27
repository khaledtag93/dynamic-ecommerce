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
        <x-admin.stat-card
            :label="__('Movements')"
            :value="$inventoryStats['total_movements']"
            icon="mdi-archive-outline"
            :help="__('Recorded stock movement entries.')"
            class="h-100"
        />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-admin.stat-card
            :label="__('Low stock')"
            :value="$lowStockItems->count()"
            icon="mdi-alert-outline"
            tone="warning"
            :help="__('Products that need replenishment attention.')"
            class="h-100"
        />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-admin.stat-card
            :label="__('Expiry attention')"
            :value="$expiryRiskItems->count()"
            icon="mdi-calendar-clock-outline"
            tone="warning"
            :help="__('Expired or expiring within the next 30 days.')"
            class="h-100"
        />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-admin.stat-card
            :label="__('Movement types')"
            :value="$inventoryStats['movement_types']"
            icon="mdi-format-list-bulleted-type"
            :help="__('Movement categories currently recorded in the inventory ledger.')"
            class="h-100"
        />
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <h4 class="mb-0">{{ __('Low stock attention') }}</h4>
                    <span class="badge admin-status-badge badge-soft-warning">{{ $lowStockItems->count() }} {{ __('items') }}</span>
                </div>

                @forelse($lowStockItems as $item)
                    @php($product = $item['product'])
                    @php($variant = $item['variant'])
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                        <div>
                            <div class="fw-semibold">{{ $product?->name ?: __('Unnamed product') }}</div>
                            @if($variant)
                                <div class="text-muted small font-monospace">{{ $variant->sku ?: ('#' . $variant->id) }}</div>
                            @endif
                            <div class="text-muted small">{{ $product?->category?->name ?? __('No category') }}</div>
                            @if($product)
                                <a href="{{ route('admin.products.edit', $product) }}" class="small text-decoration-none">{{ __('Open product') }} <i class="mdi mdi-arrow-top-right"></i></a>
                            @endif
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">{{ $item['stock'] }}</div>
                            <div class="text-muted small">{{ __('available') }}</div>
                            <div class="text-muted small">{{ __('Reorder point') }}: {{ $item['threshold'] }}</div>
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
                    <h4 class="mb-0">{{ __('Expiry attention') }}</h4>
                    <span class="badge admin-status-badge badge-soft-secondary">{{ $expiryRiskItems->count() }} {{ __('items') }}</span>
                </div>

                @forelse($expiryRiskItems as $item)
                    @php($product = $item['product'])
                    @php($variant = $item['variant'])
                    @php($expiryDate = $item['expiration_date'])
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                        <div>
                            <div class="fw-semibold">{{ $product?->name ?: __('Unnamed product') }}</div>
                            @if($variant)
                                <div class="text-muted small font-monospace">{{ $variant->sku ?: ('#' . $variant->id) }}</div>
                            @endif
                            <div class="text-muted small">{{ $product?->category?->name ?? __('No category') }}</div>
                            @if($product)
                                <a href="{{ route('admin.products.edit', $product) }}" class="small text-decoration-none">{{ __('Open product') }} <i class="mdi mdi-arrow-top-right"></i></a>
                            @endif
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold {{ $expiryDate?->lt(today()) ? 'text-danger' : '' }}">{{ optional($expiryDate)->format('M d, Y') ?: __('Not set') }}</div>
                            <div class="text-muted small">{{ __('expiry date') }}</div>
                            <span class="badge admin-status-badge {{ $expiryDate?->lt(today()) ? 'badge-soft-danger' : 'badge-soft-warning' }}">
                                {{ $expiryDate?->lt(today()) ? __('Expired') : __('Upcoming') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="admin-empty-state py-4">
                        <div class="empty-icon"><i class="mdi mdi-calendar-check-outline"></i></div>
                        <h5 class="mb-2">{{ __('No products near expiry') }}</h5>
                        <p class="text-muted mb-0">{{ __('No expired products or items expiring in the next 30 days.') }}</p>
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
                <label class="form-label fw-semibold" for="inventorySearch">{{ __('Search movements') }}</label>
                <input id="inventorySearch" type="search" name="search" maxlength="100" data-live-search autocomplete="off" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Product, SKU, order number, or reason') }}">
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="inventoryMovementType">{{ __('Movement type') }}</label>
                <select id="inventoryMovementType" name="type" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All types') }}</option>
                    @foreach($movementTypes as $type)
                        <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ __(str_replace('_', ' ', \Illuminate\Support\Str::headline($type))) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="inventorySource">{{ __('Source') }}</label>
                <select id="inventorySource" name="reference" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All sources') }}</option>
                    <option value="order" @selected($filters['reference'] === 'order')>{{ __('Orders') }}</option>
                    <option value="purchase" @selected($filters['reference'] === 'purchase')>{{ __('Purchases') }}</option>
                    <option value="manual" @selected($filters['reference'] === 'manual')>{{ __('Manual / system') }}</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label fw-semibold" for="inventoryPerPage">{{ __('Per page') }}</label>
                <select id="inventoryPerPage" name="per_page" class="form-select" data-live-filter-control>
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
