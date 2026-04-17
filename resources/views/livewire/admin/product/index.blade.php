<div class="product-admin-page">

    {{-- Header --}}
    <x-admin.page-header
        :kicker="__('Catalog management')"
        :title="__('Products')"
        :description="__('Manage products with fast search, bulk actions, inline updates, and CSV export.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Catalog')],
            ['label' => __('Products'), 'current' => true],
        ]"
        class="mb-4"
    >
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-text-icon">
                <i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Add product') }}</span>
            </a>

            <button
                type="button"
                class="btn btn-outline-success btn-text-icon"
                wire:click="exportCsv"
                wire:loading.attr="disabled"
            >
                <i class="mdi mdi-file-delimited-outline"></i>
                <span wire:loading.remove wire:target="exportCsv">{{ __('Export CSV') }}</span>
                <span wire:loading wire:target="exportCsv">{{ __('Exporting...') }}</span>
            </button>

            <a href="{{ route('admin.categories.index') }}" class="btn btn-light border btn-text-icon">
                <i class="mdi mdi-shape-outline"></i><span>{{ __('Categories') }}</span>
            </a>
        </div>
    </x-admin.page-header>

    {{-- Alerts --}}
    @if (session('message'))
        <div class="alert alert-success alert-modern mb-3">
            <i class="mdi mdi-check-circle-outline me-2"></i>
            {{ session('message') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-modern mb-3">
            <i class="mdi mdi-alert-circle-outline me-2"></i>
            {{ session('error') }}
        </div>
    @endif

    <div class="admin-card mb-4 product-toolbar-card">
        <div class="card-body p-3 p-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <h5 class="mb-1">{{ __('Catalog workspace') }}</h5>
                    <p class="text-muted mb-0 small">{{ __('Use this page for fast catalog operations, then jump into create, categories, or bulk cleanup from the same workspace.') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="admin-chip">{{ __('Filtered') }}: {{ $products->total() }}</span>
                    @if($this->selectedCount > 0)
                        <span class="admin-chip">{{ __('Selected') }}: {{ $this->selectedCount }}</span>
                    @endif
                    <a href="{{ route('admin.notifications.index') }}" class="btn btn-light border btn-sm btn-text-icon">
                        <i class="mdi mdi-bell-outline"></i><span>{{ __('Inbox') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card admin-card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label">{{ __('Filtered results') }}</div>
                    <div class="stat-value">{{ $products->total() }}</div>
                    <div class="stat-note">{{ __('Products matching current filters') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card admin-card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label">{{ __('Selected') }}</div>
                    <div class="stat-value">{{ $this->selectedCount }}</div>
                    <div class="stat-note">{{ __('Bulk action ready') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card admin-card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label">{{ __('On this page') }}</div>
                    <div class="stat-value">{{ $products->count() }}</div>
                    <div class="stat-note">{{ __('Currently visible rows') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card admin-card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label">{{ __('Per Page') }}</div>
                    <div class="stat-value">{{ $perPage }}</div>
                    <div class="stat-note">{{ __('Rows per page') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card admin-card mb-4 product-toolbar-card">
        <div class="card-body p-3 p-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div class="product-filter-heading">
                    <h5 class="mb-1">{{ __('Filter workspace') }}</h5>
                    <p class="text-muted mb-0 small">{{ __('Search, narrow, and reset the catalog list without leaving the page.') }}</p>
                </div>
                <div class="text-muted small">{{ __('Livewire updates these results automatically as you work.') }}</div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label filter-label">{{ __('Search') }}</label>
                    <div class="input-group modern-input-group">
                        <span class="input-group-text">
                            <i class="mdi mdi-magnify"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control"
                            placeholder="{{ __('Search by name, slug, SKU, or barcode...') }}"
                            wire:model.debounce.400ms="search"
                        >
                    </div>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label filter-label">{{ __('Status') }}</label>
                    <select class="form-select" wire:model="statusFilter">
                        <option value="">{{ __('All') }}</option>
                        <option value="1">{{ __('Active') }}</option>
                        <option value="0">{{ __('Hidden') }}</option>
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label filter-label">{{ __('Category') }}</label>
                    <select class="form-select" wire:model="categoryFilter">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label filter-label">{{ __('Brand') }}</label>
                    <select class="form-select" wire:model="brandFilter">
                        <option value="">{{ __('All Brands') }}</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-1">
                    <label class="form-label filter-label">{{ __('Per Page') }}</label>
                    <select class="form-select" wire:model="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                <div class="col-12 col-lg-1">
                    <button
                        type="button"
                        class="btn btn-light w-100 btn-modern"
                        wire:click="resetFilters"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Reset') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk Bar --}}
    <div class="card admin-card mb-3 product-toolbar-card">
        <div class="card-body py-3 px-3 px-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="product-selection-heading">
                    <div class="fw-semibold mb-1">{{ __('Bulk selection') }}</div>
                    <div class="text-muted small">{{ __('Select rows on this page or across filtered results, then run safe bulk actions.') }}</div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 ms-lg-auto">
                    <div class="form-check m-0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="selectPage"
                            wire:model="selectPage"
                        >
                        <label class="form-check-label fw-semibold" for="selectPage">
                            {{ __('Select this page') }}
                        </label>
                    </div>

                    @if ($selectPage && !$selectAll && $products->count())
                        <button
                            type="button"
                            class="btn btn-link p-0 text-decoration-none fw-semibold"
                            wire:click="selectAllMatching"
                        >
                            {{ __('Select all') }} {{ $this->totalFilteredCount }} {{ __('filtered products') }}
                        </button>
                    @endif

                    @if ($this->selectedCount > 0)
                        <span class="selection-badge">
                            {{ $this->selectedCount }} {{ __('Selected') }}
                        </span>
                    @endif
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-modern"
                        wire:click="resetSelection"
                        wire:loading.attr="disabled"
                        @disabled($this->selectedCount === 0)
                    >
                        {{ __('Clear Selection') }}
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline-danger btn-modern"
                        wire:click="bulkDelete"
                        wire:confirm="{{ __('Are you sure you want to delete the selected products?') }}"
                        wire:loading.attr="disabled"
                        @disabled($this->selectedCount === 0)
                    >
                        <i class="mdi mdi-trash-can-outline me-1"></i>
                        <span wire:loading.remove wire:target="bulkDelete">{{ __('Bulk delete') }}</span>
                        <span wire:loading wire:target="bulkDelete">{{ __('Deleting...') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card admin-card overflow-hidden position-relative">
        <div class="table-loading-overlay" wire:loading.flex wire:target="search,statusFilter,categoryFilter,brandFilter,perPage,sortBy,resetFilters,toggleStatus,saveInlineBasePrice,saveInlineSalePrice,saveInlineQty,bulkDelete,deleteSingle,duplicate">
            <div class="loading-box">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                {{ __('Loading...') }}
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive admin-table-wrap">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 52px;">#</th>

                            <th style="width: 72px;">{{ __('Image') }}</th>

                            <th>
                                <button type="button" class="sort-btn" wire:click="sortBy('name')">
                                    {{ __('Product') }}
                                    @if ($sortField === 'name')
                                        <i class="mdi {{ $sortDirection === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                    @endif
                                </button>
                            </th>

                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Brand') }}</th>

                            <th style="width: 180px;">
                                <button type="button" class="sort-btn" wire:click="sortBy('base_price')">
                                    {{ __('Base Price') }}
                                    @if ($sortField === 'base_price')
                                        <i class="mdi {{ $sortDirection === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                    @endif
                                </button>
                            </th>

                            <th style="width: 180px;">
                                <button type="button" class="sort-btn" wire:click="sortBy('sale_price')">
                                    {{ __('Sale Price') }}
                                    @if ($sortField === 'sale_price')
                                        <i class="mdi {{ $sortDirection === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                    @endif
                                </button>
                            </th>

                            <th style="width: 150px;">
                                <button type="button" class="sort-btn" wire:click="sortBy('quantity')">
                                    {{ __('Quantity') }}
                                    @if ($sortField === 'quantity')
                                        <i class="mdi {{ $sortDirection === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                    @endif
                                </button>
                            </th>

                            <th style="width: 140px;">{{ __('Type') }}</th>

                            <th style="width: 140px;">
                                <button type="button" class="sort-btn" wire:click="sortBy('status')">
                                    {{ __('Status') }}
                                    @if ($sortField === 'status')
                                        <i class="mdi {{ $sortDirection === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                    @endif
                                </button>
                            </th>

                            <th style="width: 160px;">
                                <button type="button" class="sort-btn" wire:click="sortBy('updated_at')">
                                    {{ __('Updated') }}
                                    @if ($sortField === 'updated_at')
                                        <i class="mdi {{ $sortDirection === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}"></i>
                                    @endif
                                </button>
                            </th>

                            <th style="width: 190px;" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($products as $product)
                            <tr wire:key="product-row-{{ $product->id }}">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <input
                                            type="checkbox"
                                            class="form-check-input row-checkbox"
                                            value="{{ $product->id }}"
                                            wire:model="selectedProducts"
                                        >
                                        <span class="text-muted small">#{{ $product->id }}</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="product-thumb-wrap">
                                        @if ($product->main_image_url)
                                            <img
                                                src="{{ $product->main_image_url }}"
                                                alt="{{ $product->name }}"
                                                class="product-thumb"
                                            >
                                        @else
                                            <div class="product-thumb product-thumb-placeholder">
                                                <i class="mdi mdi-image-outline"></i>
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <div class="product-main-cell">
                                        <div class="product-name">{{ $product->name }}</div>

                                        <div class="product-meta">
                                            <span class="meta-chip">{{ __('Slug') }}: {{ $product->slug ?: '—' }}</span>
                                            <span class="meta-chip">{{ __('SKU') }}: {{ $product->sku ?: '—' }}</span>
                                            @if ($product->barcode)
                                                <span class="meta-chip">{{ __('Barcode') }}: {{ $product->barcode }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="text-muted fw-medium">
                                        {{ optional($product->category)->name ?: '—' }}
                                    </span>
                                </td>

                                <td>
                                    <span class="text-muted fw-medium">
                                        {{ optional($product->brand)->name ?: '—' }}
                                    </span>
                                </td>

                                {{-- {{ __('Base Price') }} --}}
                                <td>
                                    @if ($editingBasePriceId === $product->id)
                                        <div class="inline-edit-box">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    class="form-control"
                                                    wire:model.defer="inlineBasePrice.{{ $product->id }}"
                                                    wire:keydown.enter="saveInlineBasePrice({{ $product->id }})"
                                                    wire:keydown.escape="cancelEditBasePrice"
                                                >
                                            </div>

                                            @error('inlineBasePrice.' . $product->id)
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror

                                            <div class="d-flex gap-1 mt-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-success btn-xs"
                                                    wire:click="saveInlineBasePrice({{ $product->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="saveInlineBasePrice({{ $product->id }})"
                                                >
                                                    <span wire:loading.remove wire:target="saveInlineBasePrice({{ $product->id }})">{{ __('Save') }}</span>
                                                    <span wire:loading wire:target="saveInlineBasePrice({{ $product->id }})">{{ __('Saving...') }}</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-light btn-xs"
                                                    wire:click="cancelEditBasePrice"
                                                    wire:loading.attr="disabled"
                                                >
                                                    {{ __('Cancel') }}
                                                </button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="inline-display">
                                            <div class="fw-bold price-text">
                                                ${{ number_format((float) ($product->base_price ?? 0), 2) }}
                                            </div>

                                            @if ($product->has_variants)
                                                <div class="small text-muted mt-1">{{ __('Controlled by variants') }}</div>
                                            @else
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-inline-edit"
                                                    wire:click="startEditBasePrice({{ $product->id }}, {{ (float) ($product->base_price ?? 0) }})"
                                                >
                                                    <i class="mdi mdi-pencil-outline"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                {{-- {{ __('Sale Price') }} --}}
                                <td>
                                    @if ($editingSalePriceId === $product->id)
                                        <div class="inline-edit-box">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    class="form-control"
                                                    wire:model.defer="inlineSalePrice.{{ $product->id }}"
                                                    wire:keydown.enter="saveInlineSalePrice({{ $product->id }})"
                                                    wire:keydown.escape="cancelEditSalePrice"
                                                >
                                            </div>

                                            @error('inlineSalePrice.' . $product->id)
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror

                                            <div class="d-flex gap-1 mt-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-success btn-xs"
                                                    wire:click="saveInlineSalePrice({{ $product->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="saveInlineSalePrice({{ $product->id }})"
                                                >
                                                    <span wire:loading.remove wire:target="saveInlineSalePrice({{ $product->id }})">{{ __('Save') }}</span>
                                                    <span wire:loading wire:target="saveInlineSalePrice({{ $product->id }})">{{ __('Saving...') }}</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-light btn-xs"
                                                    wire:click="cancelEditSalePrice"
                                                    wire:loading.attr="disabled"
                                                >
                                                    {{ __('Cancel') }}
                                                </button>
                                            </div>

                                            <div class="small text-muted mt-1">
                                                {{ __('Leave empty to remove sale price.') }}
                                            </div>
                                        </div>
                                    @else
                                        <div class="inline-display">
                                            <div class="fw-bold price-text">
                                                @if (!is_null($product->sale_price))
                                                    ${{ number_format((float) $product->sale_price, 2) }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>

                                            @if ($product->has_variants)
                                                <div class="small text-muted mt-1">{{ __('Controlled by variants') }}</div>
                                            @else
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-inline-edit"
                                                    wire:click="startEditSalePrice({{ $product->id }}, {{ is_null($product->sale_price) ? 'null' : (float) $product->sale_price }})"
                                                >
                                                    <i class="mdi mdi-pencil-outline"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                {{-- {{ __('Quantity') }} --}}
                                <td>
                                    @if ($editingQtyId === $product->id)
                                        <div class="inline-edit-box">
                                            <input
                                                type="number"
                                                min="0"
                                                class="form-control form-control-sm"
                                                wire:model.defer="inlineQty.{{ $product->id }}"
                                                wire:keydown.enter="saveInlineQty({{ $product->id }})"
                                                wire:keydown.escape="cancelEditQty"
                                            >

                                            @error('inlineQty.' . $product->id)
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror

                                            <div class="d-flex gap-1 mt-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-success btn-xs"
                                                    wire:click="saveInlineQty({{ $product->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="saveInlineQty({{ $product->id }})"
                                                >
                                                    <span wire:loading.remove wire:target="saveInlineQty({{ $product->id }})">{{ __('Save') }}</span>
                                                    <span wire:loading wire:target="saveInlineQty({{ $product->id }})">{{ __('Saving...') }}</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-light btn-xs"
                                                    wire:click="cancelEditQty"
                                                    wire:loading.attr="disabled"
                                                >
                                                    {{ __('Cancel') }}
                                                </button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="inline-display">
                                            <div class="fw-bold qty-text">{{ (int) ($product->quantity_value ?? 0) }}</div>

                                            @if ($product->has_variants)
                                                <div class="small text-muted mt-1">{{ __('Controlled by variants') }}</div>
                                            @else
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-inline-edit"
                                                    wire:click="startEditQty({{ $product->id }}, {{ (int) ($product->quantity ?? 0) }})"
                                                >
                                                    <i class="mdi mdi-pencil-outline"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                {{-- {{ __('Type') }} --}}
                                <td>
                                    @if ($product->has_variants)
                                        <span class="badge rounded-pill bg-info-subtle text-info-emphasis border">
                                            {{ __('Variant Product') }}
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-light text-dark border">
                                            {{ __('Simple Product') }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <div class="status-cell">
                                        <button
                                            type="button"
                                            class="status-toggle {{ $product->status ? 'is-active' : 'is-inactive' }}"
                                            wire:click="toggleStatus({{ $product->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleStatus({{ $product->id }})"
                                            title="{{ $product->status ? __('Click to hide product') : __('Click to activate product') }}"
                                        >
                                            <span class="status-toggle-track">
                                                <span class="status-toggle-thumb"></span>
                                            </span>

                                            <span class="status-toggle-text">
                                                {{ $product->status ? __('Active') : __('Hidden') }}
                                            </span>
                                        </button>
                                    </div>
                                </td>

                                <td>
                                    <div class="updated-cell">
                                        <div class="fw-semibold">
                                            {{ optional($product->updated_at)->format('d M Y') }}
                                        </div>
                                        <small class="text-muted">
                                            {{ optional($product->updated_at)->format('h:i A') }}
                                        </small>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <div class="action-group justify-content-end">
                                        <a
                                            href="{{ route('admin.products.edit', $product->id) }}"
                                            class="btn btn-light btn-action"
                                            title="{{ __('Edit Product') }}"
                                        >
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </a>

                                        <button
                                            type="button"
                                            class="btn btn-light btn-action text-primary"
                                            wire:click="duplicate({{ $product->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="duplicate({{ $product->id }})"
                                            title="{{ __('Duplicate product') }}"
                                        >
                                            <i class="mdi mdi-content-copy"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-light btn-action text-danger"
                                            wire:click="deleteSingle({{ $product->id }})"
                                            wire:confirm="{{ __('Are you sure you want to delete this product?') }}"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteSingle({{ $product->id }})"
                                            title="{{ __('Delete') }}"
                                        >
                                            <i class="mdi mdi-trash-can-outline"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="mdi mdi-package-variant-closed empty-state-icon"></i>
                                        <h5 class="mb-2">{{ __('No products found') }}</h5>
                                        <p class="text-muted mb-3">
                                            {{ __('Try changing your search or filters.') }}
                                        </p>
                                        <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i>
                                            {{ __('Add product') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($products->hasPages())
            <div class="card-footer bg-white border-0 pt-3 pb-4 px-3 px-lg-4">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <style>
        .product-admin-page {
            --primary-color: var(--admin-primary);
            --primary-soft: var(--admin-primary-soft);
            --border-soft: var(--admin-border);
            --text-main: var(--admin-text);
            --text-muted: var(--admin-muted);
            --success-soft: var(--admin-success-bg);
            --success-text: var(--admin-success-text);
            --danger-soft: var(--admin-danger-bg);
            --danger-text: var(--admin-danger-text);
        }

        .page-header-card,
        .admin-card {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        }

        .page-header-card {
            padding: 1.25rem 1.25rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .product-toolbar-card h5,
        .product-selection-heading .fw-semibold {
            color: var(--text-main);
            font-weight: 800;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .btn-modern {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.65rem 1rem;
        }

        .alert-modern {
            border: 0;
            border-radius: 14px;
            padding: 0.95rem 1rem;
        }

        .filter-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--admin-muted);
            margin-bottom: 0.45rem;
        }

        .modern-input-group .input-group-text,
        .modern-input-group .form-control,
        .form-select,
        .form-control {
            border-radius: 12px;
        }

        .modern-input-group .input-group-text {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            background: color-mix(in srgb, var(--admin-surface-alt) 45%, white);
        }

        .modern-input-group .form-control {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .selection-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .stat-card .card-body {
            padding: 1rem 1.1rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.45rem;
        }

        .stat-value {
            font-size: 1.6rem;
            line-height: 1;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 0.35rem;
        }

        .stat-note {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .admin-table thead th {
            background: color-mix(in srgb, var(--admin-surface-alt) 72%, white);
            border-bottom: 1px solid var(--border-soft);
            color: var(--admin-muted);
            font-size: 0.82rem;
            font-weight: 800;
            padding: 1rem 0.9rem;
            white-space: nowrap;
        }

        .admin-table tbody td {
            padding: 1rem 0.9rem;
            border-color: color-mix(in srgb, var(--admin-border) 70%, white);
            vertical-align: middle;
        }

        .admin-table tbody tr:hover {
            background: color-mix(in srgb, var(--admin-bg) 55%, white);
        }

        .sort-btn {
            background: transparent;
            border: 0;
            padding: 0;
            font: inherit;
            color: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 800;
        }

        .product-thumb-wrap {
            width: 52px;
            height: 52px;
        }

        .product-thumb {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid color-mix(in srgb, var(--admin-border) 72%, white);
            background: var(--admin-surface);
        }

        .product-thumb-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: color-mix(in srgb, var(--admin-muted) 55%, white);
            background: color-mix(in srgb, var(--admin-surface-alt) 45%, white);
            font-size: 1.25rem;
        }

        .product-main-cell {
            min-width: 220px;
        }

        .product-name {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.45rem;
        }

        .product-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.55rem;
            border-radius: 999px;
            background: color-mix(in srgb, var(--admin-surface-alt) 45%, white);
            color: var(--text-muted);
            font-size: 0.76rem;
            font-weight: 600;
        }

        .inline-display .price-text,
        .inline-display .qty-text {
            color: var(--text-main);
        }

        .btn-inline-edit {
            padding: 0;
            font-size: 0.82rem;
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 700;
        }

        .inline-edit-box {
            max-width: 160px;
        }

        .btn-xs {
            padding: 0.28rem 0.55rem;
            font-size: 0.76rem;
            border-radius: 8px;
        }

        .status-cell {
            min-width: 120px;
        }

        .status-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            border: 0;
            background: transparent;
            padding: 0;
            font-weight: 700;
        }

        .status-toggle-track {
            width: 46px;
            height: 26px;
            border-radius: 999px;
            position: relative;
            display: inline-flex;
            align-items: center;
            padding: 3px;
            transition: all 0.2s ease;
        }

        .status-toggle-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--admin-surface);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
        }

        .status-toggle.is-active .status-toggle-track {
            background: color-mix(in srgb, var(--admin-primary) 78%, white);
        }

        .status-toggle.is-active .status-toggle-thumb {
            transform: translateX(20px);
        }

        .status-toggle.is-active .status-toggle-text {
            color: var(--admin-success-text);
        }

        .status-toggle.is-inactive .status-toggle-track {
            background: color-mix(in srgb, var(--admin-muted) 32%, white);
        }

        .status-toggle.is-inactive .status-toggle-thumb {
            transform: translateX(0);
        }

        .status-toggle.is-inactive .status-toggle-text {
            color: var(--text-muted);
        }

        .updated-cell {
            line-height: 1.2;
        }

        .action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .btn-action {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            border: 1px solid color-mix(in srgb, var(--admin-border) 72%, white);
        }

        .btn-action.text-primary {
            color: var(--admin-primary) !important;
        }

        .btn-action.text-primary:hover {
            background: var(--admin-primary-soft);
            border-color: var(--admin-border);
        }

        .btn-action.text-danger:hover {
            background: var(--admin-danger-bg);
            border-color: color-mix(in srgb, var(--admin-accent) 20%, white);
        }

        .empty-state {
            padding: 1rem;
        }

        .empty-state-icon {
            font-size: 3rem;
            color: color-mix(in srgb, var(--admin-muted) 35%, white);
            margin-bottom: 0.75rem;
            display: block;
        }

        .row-checkbox {
            cursor: pointer;
        }

        .table-loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.65);
            z-index: 20;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .loading-box {
            display: inline-flex;
            align-items: center;
            background: var(--admin-surface);
            border: 1px solid color-mix(in srgb, var(--admin-border) 72%, white);
            border-radius: 999px;
            padding: 0.65rem 1rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            font-weight: 700;
            color: var(--admin-text);
        }
    </style>
</div>