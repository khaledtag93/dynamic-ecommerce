<div
    class="container-fluid position-relative product-form-root"
    wire:key="product-form-root-{{ $productId ?: 'new' }}"
>
    <style>
        .product-form-top-progress {
            position: fixed;
            inset-inline: 0;
            top: 0;
            height: 3px;
            z-index: 2005;
            background: linear-gradient(90deg, var(--admin-primary) 0%, color-mix(in srgb, var(--admin-primary) 55%, white) 50%, var(--admin-accent) 100%);
            transform-origin: left center;
            transform: scaleX(0);
            opacity: 0;
            transition: transform .18s ease, opacity .18s ease;
        }

        .product-form-root.is-optimistic-saving .product-form-top-progress {
            transform: scaleX(1);
            opacity: 1;
        }

        .product-status-pill {
            min-height: 38px;
            border-radius: 999px;
            padding: .45rem .9rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .875rem;
            font-weight: 600;
            border: 1px solid rgba(0, 0, 0, .08);
            background: var(--admin-surface);
            color: var(--admin-muted);
            box-shadow: 0 8px 20px color-mix(in srgb, var(--admin-text) 6%, transparent);
            transition: all .18s ease;
        }

        .product-status-pill[data-state="dirty"] {
            color: var(--admin-primary-dark);
            background: color-mix(in srgb, var(--admin-primary-soft) 72%, var(--admin-surface));
            border-color: var(--admin-border);
        }

        .product-status-pill[data-state="saving"] {
            color: color-mix(in srgb, var(--admin-accent) 78%, var(--admin-sidebar));
            background: color-mix(in srgb, var(--admin-accent-soft) 65%, var(--admin-surface));
            border-color: color-mix(in srgb, var(--admin-accent) 25%, white);
        }

        .product-status-pill[data-state="saved"] {
            color: var(--admin-success-text);
            background: color-mix(in srgb, var(--admin-primary-soft) 60%, var(--admin-surface));
            border-color: color-mix(in srgb, var(--admin-primary) 22%, white);
        }

        .product-status-pill[data-state="error"] {
            color: var(--admin-danger-text);
            background: var(--admin-danger-bg);
            border-color: color-mix(in srgb, var(--admin-accent) 20%, white);
        }

        .product-save-btn {
            min-width: 180px;
            justify-content: center;
        }

        .product-form-root.is-optimistic-saving .product-save-btn {
            pointer-events: none;
            opacity: .92;
        }

        .product-dropzone.dragging {
            border-color: var(--admin-primary) !important;
            background: color-mix(in srgb, var(--admin-accent-soft) 55%, var(--admin-surface)) !important;
        }

        .aov-manager-card {
            border: 1px solid color-mix(in srgb, var(--admin-primary) 10%, rgba(0,0,0,.04));
            border-radius: 1rem;
            background: linear-gradient(180deg, color-mix(in srgb, var(--admin-primary-soft) 22%, #fff), #fff);
            overflow: hidden;
        }

        .aov-manager-search-results {
            max-height: 280px;
            overflow: auto;
        }

        .aov-manager-item {
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        }

        .aov-manager-item.is-inactive {
            opacity: .72;
            background: #f8fafc;
        }

        .aov-relation-badge {
            border-radius: 999px;
            padding: .3rem .7rem;
            font-size: .75rem;
            font-weight: 700;
        }

        .product-readiness-card {
            margin-bottom: 1rem;
            padding: 1rem 1.1rem;
            border: 1px solid var(--admin-border);
            border-radius: 1.15rem;
            background: linear-gradient(135deg, color-mix(in srgb, var(--admin-primary-soft) 34%, var(--admin-surface)), var(--admin-surface));
            box-shadow: 0 12px 28px color-mix(in srgb, var(--admin-text) 5%, transparent);
        }

        .product-readiness-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .product-readiness-title-wrap {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            min-width: min(100%, 26rem);
        }

        .product-readiness-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: .85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            background: var(--admin-primary-soft);
            color: var(--admin-primary-dark);
            font-size: 1.25rem;
        }

        .product-readiness-meta {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .product-readiness-count,
        .product-visibility-badge {
            display: inline-flex;
            align-items: center;
            min-height: 2rem;
            padding: .35rem .65rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 800;
            border: 1px solid var(--admin-border);
            background: var(--admin-surface);
        }

        .product-visibility-badge[data-state="active"] {
            color: var(--admin-success-text);
            background: var(--admin-success-bg);
        }

        .product-visibility-badge[data-state="inactive"] {
            color: var(--admin-muted);
            background: color-mix(in srgb, var(--admin-surface) 82%, var(--admin-bg));
        }

        .product-readiness-progress {
            height: .4rem;
            margin: .9rem 0;
            overflow: hidden;
            border-radius: 999px;
            background: color-mix(in srgb, var(--admin-border) 65%, white);
        }

        .product-readiness-progress > span {
            display: block;
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--admin-primary), var(--admin-accent));
            transition: width .2s ease;
        }

        .product-readiness-checks {
            display: flex;
            gap: .45rem;
            flex-wrap: wrap;
        }

        .product-readiness-check {
            border: 1px solid var(--admin-border);
            background: var(--admin-surface);
            color: var(--admin-muted);
            border-radius: 999px;
            padding: .42rem .68rem;
            display: inline-flex;
            align-items: center;
            gap: .38rem;
            font-size: .74rem;
            font-weight: 750;
            cursor: pointer;
        }

        .product-readiness-check:hover {
            color: var(--admin-primary-dark);
            border-color: color-mix(in srgb, var(--admin-primary) 30%, var(--admin-border));
        }

        .product-readiness-check.is-ready {
            color: var(--admin-success-text);
            background: var(--admin-success-bg);
            border-color: color-mix(in srgb, #16a34a 22%, white);
        }

        .product-readiness-check.is-missing[data-required="1"] {
            color: var(--admin-danger-text);
            background: var(--admin-danger-bg);
        }

        @media (max-width: 575.98px) {
            .product-readiness-meta { width: 100%; }
            .product-readiness-checks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .product-readiness-check { justify-content: flex-start; width: 100%; }
        }
    </style>

    <div class="product-form-top-progress"></div>

    <div
        id="productFormToastContainer"
        class="position-fixed bottom-0 start-50 translate-middle-x p-3"
        style="z-index: 2000; width: 520px; max-width: calc(100% - 24px);"
        wire:ignore
    ></div>

    @if(!empty($newImages))
    <div
        wire:loading.flex
        wire:target="save"
        class="position-fixed top-0 start-0 w-100 h-100 align-items-center justify-content-center"
        style="background: color-mix(in srgb, var(--admin-surface) 42%, transparent); z-index: 1055; backdrop-filter: blur(2px);"
    >
        <div class="card border-0 shadow-lg rounded-4">
            <div class="card-body px-4 py-4 text-center">
                <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                <div class="fw-semibold">{{ __('Saving product...') }}</div>
                <div class="text-muted small">
                    {{ __('Please wait while your changes are being processed.') }}
                </div>
            </div>
        </div>
    </div>
    @endif

    <form id="productFormMain" wire:submit.prevent="save" data-admin-section-tabs="product-editor">
        <div class="product-form-header-card d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="product-form-header-copy">
                <h2 class="mb-1">{{ $productId ? __('Edit Product') : __('Create Product') }}</h2>
                <p class="text-muted mb-0">
                    {{ __('Manage product details, pricing, stock, images, SEO information, and variants.') }}
                </p>
            </div>

            <div class="d-flex gap-2 align-items-center flex-wrap">
                <div id="productFormStatusPill" class="product-status-pill" data-state="idle">
                    <i class="mdi mdi-content-save-outline"></i>
                    <span id="productFormStatusText">
                        @if(!empty($this->lastSavedProductId))
                            {{ __('Saved #') }}{{ $this->lastSavedProductId }}
                        @else
                            {{ __('Ready to save') }}
                        @endif
                    </span>
                </div>

                @if(!empty($this->lastSavedProductId))
                    <span class="badge bg-success-subtle text-success border" id="serverLastSavedBadge">
                        {{ __('Last saved:') }} #{{ $this->lastSavedProductId }}
                    </span>
                @endif

                <a
                    href="{{ route('admin.products.index') }}"
                    class="btn btn-outline-secondary"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    @disabled($isSaving)
                >
                    <i class="mdi mdi-arrow-left"></i> {{ __('Back') }}
                </a>

                <button
                    id="productSaveButton"
                    type="submit"
                    class="btn btn-primary d-inline-flex align-items-center gap-2 product-save-btn"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">
                        <i class="mdi mdi-content-save-outline"></i>
                        {{ __('Save Product') }}
                    </span>

                    <span wire:loading.inline-flex wire:target="save" class="align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        {{ __('Saving...') }}
                    </span>
                </button>
            </div>
        </div>

        @if ($saveErrorMessage)
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 product-error-field">
                <div class="fw-semibold mb-1">{{ __('Save failed') }}</div>
                <div class="small mb-0">{{ $saveErrorMessage }}</div>
            </div>
        @endif

        <div class="product-readiness-card" id="productPublishReadiness">
            <div class="product-readiness-head">
                <div class="product-readiness-title-wrap">
                    <span class="product-readiness-icon"><i class="mdi mdi-clipboard-check-outline"></i></span>
                    <div>
                        <div class="fw-bold">{{ __('Publish readiness') }}</div>
                        <div class="text-muted small" id="productReadinessSummary">
                            {{ __('Complete the core product information, then review the recommended storefront details.') }}
                        </div>
                    </div>
                </div>
                <div class="product-readiness-meta">
                    <span class="product-readiness-count" id="productReadinessCount">0/7</span>
                    <span class="product-visibility-badge" id="productVisibilityBadge" data-state="{{ (int) $status === 1 ? 'active' : 'inactive' }}">
                        {{ (int) $status === 1 ? __('Visible on storefront') : __('Hidden from storefront') }}
                    </span>
                </div>
            </div>

            <div class="product-readiness-progress" aria-hidden="true">
                <span id="productReadinessProgress"></span>
            </div>

            <div class="product-readiness-checks" id="productReadinessChecks">
                <button type="button" class="product-readiness-check" data-readiness-key="name" data-required="1" data-readiness-section="details">
                    <i class="mdi mdi-circle-outline"></i><span>{{ __('Name') }}</span>
                </button>
                <button type="button" class="product-readiness-check" data-readiness-key="category" data-required="1" data-readiness-section="details">
                    <i class="mdi mdi-circle-outline"></i><span>{{ __('Category') }}</span>
                </button>
                <button type="button" class="product-readiness-check" data-readiness-key="pricing" data-required="1" data-readiness-section="pricing">
                    <i class="mdi mdi-circle-outline"></i><span>{{ __('Pricing') }}</span>
                </button>
                <button type="button" class="product-readiness-check" data-readiness-key="description" data-required="0" data-readiness-section="details">
                    <i class="mdi mdi-circle-outline"></i><span>{{ __('Description') }}</span>
                </button>
                <button type="button" class="product-readiness-check" data-readiness-key="image" data-required="0" data-readiness-section="images">
                    <i class="mdi mdi-circle-outline"></i><span>{{ __('Product image') }}</span>
                </button>
                <button type="button" class="product-readiness-check" data-readiness-key="identifier" data-required="0" data-readiness-section="details">
                    <i class="mdi mdi-circle-outline"></i><span>{{ __('SKU / Barcode') }}</span>
                </button>
                <button type="button" class="product-readiness-check" data-readiness-key="seo" data-required="0" data-readiness-section="seo">
                    <i class="mdi mdi-circle-outline"></i><span>{{ __('SEO') }}</span>
                </button>
            </div>
        </div>

        <x-admin.section-tabs id="product" :sections="[
            'details' => __('Basic Information'),
            'pricing' => __('Pricing & Inventory'),
            'variants' => __('Variants'),
            'related' => __('Upsells & Bundles'),
            'seo' => __('SEO / Meta'),
            'images' => __('Images Manager'),
        ]" />

        <div class="row g-4 admin-section-columns">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4" id="product-panel-details" role="tabpanel" aria-labelledby="product-tab-details" data-admin-section-panel="details">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="mb-1">{{ __('Basic Information') }}</h5>
                        <small class="text-muted">{{ __('Main product data and description.') }}</small>
                    </div>

                    <div class="card-body pt-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Product Name') }}</label>
                                <input type="text" class="form-control @error('name') is-invalid product-error-field @enderror" wire:model.defer="name">
                                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ __('Slug') }}</label>
                                <input type="text" class="form-control @error('slug') is-invalid product-error-field @enderror" wire:model.defer="slug">
                                @error('slug') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ __('SKU') }}</label>
                                <input type="text" class="form-control @error('sku') is-invalid product-error-field @enderror" wire:model.defer="sku" autocomplete="off">
                                <div class="form-text">{{ __('Internal stock identifier used by inventory and future POS workflows.') }}</div>
                                @error('sku') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ __('Barcode') }}</label>
                                <input type="text" class="form-control @error('barcode') is-invalid product-error-field @enderror" wire:model.defer="barcode" inputmode="numeric" autocomplete="off">
                                <div class="form-text">{{ __('Optional scannable identifier for retail and stock operations.') }}</div>
                                @error('barcode') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ __('Video URL') }}</label>
                                <input type="url" class="form-control @error('video_url') is-invalid product-error-field @enderror" wire:model.defer="video_url" placeholder="https://">
                                @error('video_url') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ __('Category') }}</label>
                                <select class="form-select @error('category_id') is-invalid product-error-field @enderror" wire:model.defer="category_id">
                                    <option value="">Select {{ __('Category') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('category_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ __('Brand') }}</label>
                                <select class="form-select @error('brand_id') is-invalid product-error-field @enderror" wire:model.defer="brand_id">
                                    <option value="">{{ __('Select Brand') }}</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand['id'] }}">{{ $brand['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('brand_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">{{ __('Description') }}</label>
                                <textarea class="form-control @error('description') is-invalid product-error-field @enderror" rows="5" wire:model.defer="description"></textarea>
                                @error('description') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4" id="product-panel-pricing" role="tabpanel" aria-labelledby="product-tab-pricing" data-admin-section-panel="pricing">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="mb-1">{{ __('Pricing & Inventory') }}</h5>
                        <small class="text-muted">
                            @if($hasVariants)
                                Base product pricing is optional when variants are enabled. Variant rows will control price and stock.
                            @else
                                {{ __('Price, quantity, and stock settings.') }}
                            @endif
                        </small>
                    </div>

                    <div class="card-body pt-3">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Base Price') }}</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    class="form-control @error('base_price') is-invalid product-error-field @enderror"
                                    wire:model.defer="base_price"
                                    @if($hasVariants) disabled @endif
                                >
                                @error('base_price') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">{{ __('Sale Price') }}</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    class="form-control @error('sale_price') is-invalid product-error-field @enderror"
                                    wire:model.defer="sale_price"
                                    @if($hasVariants) disabled @endif
                                >
                                @error('sale_price') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">{{ __('Quantity') }}</label>
                                <input
                                    type="number"
                                    class="form-control @error('quantity') is-invalid product-error-field @enderror"
                                    wire:model.defer="quantity"
                                    @if($hasVariants) disabled @endif
                                >
                                @error('quantity') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">{{ __('Low Stock Threshold') }}</label>
                                <input
                                    type="number"
                                    class="form-control @error('low_stock_threshold') is-invalid product-error-field @enderror"
                                    wire:model.defer="low_stock_threshold"
                                >
                                @error('low_stock_threshold') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ __('Stock Status') }}</label>
                                <select class="form-select @error('stock_status') is-invalid product-error-field @enderror" wire:model.defer="stock_status" @if($hasVariants) disabled @endif>
                                    <option value="in_stock" >{{ __('In Stock') }}</option>
                                    <option value="out_of_stock" >{{ __('Out of Stock') }}</option>
                                    <option value="preorder" >{{ __('Preorder') }}</option>
                                    <option value="backorder" >{{ __('Backorder') }}</option>
                                </select>
                                @error('stock_status') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">{{ __('Storefront status') }}</label>
                                <select class="form-select @error('status') is-invalid product-error-field @enderror" wire:model.defer="status">
                                    <option value="1">{{ __('Active — visible on storefront') }}</option>
                                    <option value="0">{{ __('Inactive — hidden from storefront') }}</option>
                                </select>
                                <div class="form-text">{{ __('Saving and storefront visibility are separate: keep the product inactive while its content is still being prepared.') }}</div>
                                @error('status') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">{{ __('Featured') }}</label>
                                <select class="form-select @error('is_featured') is-invalid product-error-field @enderror" wire:model.defer="is_featured">
                                    <option value="0">{{ __('No') }}</option>
                                    <option value="1">{{ __('Yes') }}</option>
                                </select>
                                @error('is_featured') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4" id="product-panel-variants" role="tabpanel" aria-labelledby="product-tab-variants" data-admin-section-panel="variants">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="mb-1">{{ __('Variants') }}</h5>
                        <small class="text-muted">
                            {{ __('Enable this if the product has multiple options like color, size, weight, or flavor.') }}
                        </small>
                    </div>

                    <div class="card-body pt-3">
                        <div class="form-check form-switch mb-3">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="hasVariantsSwitch"
                                wire:model.live="hasVariants"
                            >
                            <label class="form-check-label" for="hasVariantsSwitch">
                                {{ __('This product has variants') }}
                            </label>
                        </div>

                        @if($hasVariants)
                            <div class="alert alert-info border-0 mb-4">
                                <div class="fw-semibold mb-1">{{ __('Variants mode is enabled') }}</div>
                                <div class="small mb-0">
                                    {{ __('Each variant can have its own SKU, price, sale price, stock, and attribute values.') }}
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-light border-0">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <strong>{{ __('Auto Generate Variants') }}</strong>
                                            <div class="small text-muted">
                                                {{ __('Example: Color = Red, Blue and Size = S, M, L') }}
                                            </div>
                                        </div>

                                        <span class="badge bg-primary-subtle text-primary border">
                                            {{ __('Smart Generator') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="card-body">
                                    @error('variantGenerator')
                                        <div class="small text-danger mb-3 product-error-field">{{ $message }}</div>
                                    @enderror

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-5">
                                            <div class="small text-muted fw-semibold">{{ __('Attribute') }}</div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="small text-muted fw-semibold">{{ __('Values (comma or new line separated)') }}</div>
                                        </div>
                                        <div class="col-md-2"></div>
                                    </div>

                                    @foreach($variantGenerator as $gIndex => $group)
                                        <div class="row g-2 align-items-start mb-2" wire:key="generator-row-{{ $gIndex }}">
                                            <div class="col-md-5">
                                                <select class="form-select @error('variantGenerator') is-invalid product-error-field @enderror" wire:model.defer="variantGenerator.{{ $gIndex }}.attribute_id">
                                                    <option value="">{{ __('Select Attribute') }}</option>
                                                    @foreach($attributes as $attribute)
                                                        <option value="{{ $attribute['id'] }}">{{ $attribute['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-5">
                                                <textarea
                                                    rows="2"
                                                    class="form-control @error('variantGenerator') is-invalid product-error-field @enderror"
                                                    placeholder="{{ __('Red, Blue, Green') }}"
                                                    wire:model.defer="variantGenerator.{{ $gIndex }}.values"
                                                ></textarea>
                                            </div>

                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-outline-danger w-100" wire:click="removeGeneratorAttribute({{ $gIndex }})">
                                                    {{ __('Remove') }}
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addGeneratorAttribute">
                                            <i class="mdi mdi-plus"></i> {{ __('Add Attribute') }}
                                        </button>

                                        <button type="button" class="btn btn-success btn-sm" wire:click="generateVariants" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="generateVariants">
                                                <i class="mdi mdi-auto-fix"></i> {{ __('Generate Variants') }}
                                            </span>
                                            <span wire:loading wire:target="generateVariants">
                                                {{ __('Generating...') }}
                                            </span>
                                        </button>
                                    </div>

                                    <div class="small text-muted mt-3 mb-0">
                                        {{ __('Existing combinations will stay as they are. Only new unique combinations will be added.') }}
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div>
                                    <h6 class="mb-0">{{ __('Variant Rows') }}</h6>
                                    <small class="text-muted">{{ __('Add one row for each sellable combination.') }}</small>
                                </div>

                                <button type="button" class="btn btn-sm btn-primary" wire:click="addVariant">
                                    <i class="mdi mdi-plus"></i> {{ __('Add Variant') }}
                                </button>
                            </div>

                            @error('variants')
                                <div class="small text-danger mb-3 product-error-field">{{ $message }}</div>
                            @enderror

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body py-3">
                                    @php $stats = $this->getVariantStats(); @endphp

                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                        <div class="d-flex flex-wrap gap-3 small text-muted">
                                            <div><strong>{{ $stats['count'] }}</strong> {{ __('variants') }}</div>
                                            <div><strong>{{ $stats['active'] }}</strong> {{ __('active') }}</div>
                                            <div><strong>{{ $stats['inactive'] }}</strong> {{ __('inactive') }}</div>
                                            <div><strong>{{ $stats['total_stock'] }}</strong> {{ __('total stock') }}</div>
                                            <div>{{ __('Default') }}: <strong>{{ $stats['default_label'] }}</strong></div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="expandAllVariants">{{ __('Expand All') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="collapseAllVariants">{{ __('Collapse All') }}</button>
                                        </div>
                                    </div>

                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <input type="number" step="0.01" class="form-control" placeholder="{{ __('Price') }}" wire:model.defer="variantBulk.price">
                                        </div>

                                        <div class="col-md-2">
                                            <input type="number" step="0.01" class="form-control" placeholder="{{ __('Sale') }}" wire:model.defer="variantBulk.sale_price">
                                        </div>

                                        <div class="col-md-2">
                                            <input type="number" class="form-control" placeholder="{{ __('Stock') }}" wire:model.defer="variantBulk.stock">
                                        </div>

                                        <div class="col-md-2">
                                            <select class="form-select" wire:model.defer="variantBulk.status">
                                                <option value="1">{{ __('Active') }}</option>
                                                <option value="0">{{ __('Inactive') }}</option>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-primary w-100" wire:click="applyBulkToVariants">
                                                {{ __('Apply All') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @forelse($variants as $i => $variant)
                                @php
                                    $expanded = $this->isVariantExpanded($i);
                                @endphp

                                <div class="card border-0 shadow-sm mb-3" wire:key="variant-card-{{ $i }}-{{ $variant['id'] ?? 'new' }}">
                                    <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <strong>{{ $this->getVariantLabel($i) }}</strong>
                                            <div class="small text-muted">
                                                {{ __('Configure pricing, stock, and attributes for this variant.') }}
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleVariant({{ $i }})">
                                                {{ $expanded ? __('Collapse') : __('Expand') }}
                                            </button>

                                            <button type="button" class="btn btn-sm btn-outline-info" wire:click="duplicateVariant({{ $i }})">
                                                {{ __('Duplicate') }}
                                            </button>

                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="moveVariantUp({{ $i }})" @if($i === 0) disabled @endif>
                                                ↑
                                            </button>

                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="moveVariantDown({{ $i }})" @if($i === count($variants) - 1) disabled @endif>
                                                ↓
                                            </button>

                                            @if(!empty($variant['is_default']))
                                                <span class="badge bg-success">{{ __('Default') }}</span>
                                            @endif

                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeVariant({{ $i }})">
                                                {{ __('Remove') }}
                                            </button>
                                        </div>
                                    </div>

                                    @if($expanded)
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-2">
                                                    <label class="form-label">{{ __('SKU') }}</label>
                                                    <input type="text" class="form-control @error('variants.' . $i . '.sku') is-invalid product-error-field @enderror" placeholder="{{ __('Variant SKU') }}" wire:model.defer="variants.{{ $i }}.sku">
                                                    @error('variants.' . $i . '.sku') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="form-label">{{ __('Barcode') }}</label>
                                                    <input type="text" class="form-control @error('variants.' . $i . '.barcode') is-invalid product-error-field @enderror" placeholder="{{ __('Variant barcode') }}" wire:model.defer="variants.{{ $i }}.barcode" autocomplete="off">
                                                    @error('variants.' . $i . '.barcode') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="form-label">{{ __('Price') }}</label>
                                                    <input type="number" step="0.01" class="form-control @error('variants.' . $i . '.price') is-invalid product-error-field @enderror" placeholder="0.00" wire:model.defer="variants.{{ $i }}.price">
                                                    @error('variants.' . $i . '.price') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="form-label">{{ __('Sale Price') }}</label>
                                                    <input type="number" step="0.01" class="form-control @error('variants.' . $i . '.sale_price') is-invalid product-error-field @enderror" placeholder="0.00" wire:model.defer="variants.{{ $i }}.sale_price">
                                                    @error('variants.' . $i . '.sale_price') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="form-label">{{ __('Stock') }}</label>
                                                    <input type="number" class="form-control @error('variants.' . $i . '.stock') is-invalid product-error-field @enderror" placeholder="0" wire:model.defer="variants.{{ $i }}.stock">
                                                    @error('variants.' . $i . '.stock') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="form-label">{{ __('Status') }}</label>
                                                    <select class="form-select" wire:model.defer="variants.{{ $i }}.status">
                                                        <option value="1">{{ __('Active') }}</option>
                                                        <option value="0">{{ __('Inactive') }}</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label d-block mb-2">{{ __('Default Variant') }}</label>

                                                    <div class="border rounded-3 px-3 py-2 bg-light-subtle">
                                                        <div class="form-check d-flex align-items-center gap-2 mb-0">
                                                            <input
                                                                type="radio"
                                                                class="form-check-input mt-0"
                                                                name="default_variant_choice"
                                                                id="variant-default-{{ $i }}"
                                                                @checked(!empty($variant['is_default']))
                                                                wire:click="setDefaultVariant({{ $i }})"
                                                            >

                                                            <label class="form-check-label w-100 d-flex justify-content-between align-items-center" for="variant-default-{{ $i }}">
                                                                <span>{{ __('Set as default variant') }}</span>

                                                                @if(!empty($variant['is_default']))
                                                                    <span class="badge bg-success-subtle text-success border">{{ __('Selected') }}</span>
                                                                @endif
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <small class="text-muted d-block mt-1">{{ __('Only one variant can be the default.') }}</small>
                                                </div>
                                            </div>

                                            <hr class="my-4">

                                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                                <div>
                                                    <h6 class="mb-0">{{ __('Attributes') }}</h6>
                                                    <small class="text-muted">{{ __('Example: Color = Red, Size = Large') }}</small>
                                                </div>

                                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addVariantAttribute({{ $i }})">
                                                    <i class="mdi mdi-plus"></i> {{ __('Add Attribute') }}
                                                </button>
                                            </div>

                                            @forelse(($variant['attributes'] ?? []) as $j => $attr)
                                                <div class="row g-2 align-items-end mb-2" wire:key="variant-attr-{{ $i }}-{{ $j }}">
                                                    <div class="col-md-5">
                                                        <label class="form-label">{{ __('Attribute') }}</label>
                                                        <select class="form-select @error('variants.' . $i . '.attributes.' . $j . '.attribute_id') is-invalid product-error-field @enderror" wire:model.defer="variants.{{ $i }}.attributes.{{ $j }}.attribute_id">
                                                            <option value="">{{ __('Select Attribute') }}</option>
                                                            @foreach($attributes as $attribute)
                                                                <option value="{{ $attribute['id'] }}">{{ $attribute['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('variants.' . $i . '.attributes.' . $j . '.attribute_id') <small class="text-danger">{{ $message }}</small> @enderror
                                                    </div>

                                                    <div class="col-md-5">
                                                        <label class="form-label">{{ __('Value') }}</label>
                                                        <input type="text" class="form-control @error('variants.' . $i . '.attributes.' . $j . '.value') is-invalid product-error-field @enderror" placeholder="{{ __('e.g. Red / Large / 500g') }}" wire:model.defer="variants.{{ $i }}.attributes.{{ $j }}.value">
                                                        @error('variants.' . $i . '.attributes.' . $j . '.value') <small class="text-danger">{{ $message }}</small> @enderror
                                                    </div>

                                                    <div class="col-md-2">
                                                        <button type="button" class="btn btn-outline-danger w-100" wire:click="removeVariantAttribute({{ $i }}, {{ $j }})">
                                                            {{ __('Remove') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="text-muted small">{{ __('No attributes added yet.') }}</div>
                                            @endforelse
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="border rounded-4 p-4 bg-light text-center">
                                    <div class="mb-2">
                                        <i class="mdi mdi-shape-outline" style="font-size: 2rem;"></i>
                                    </div>
                                    <div class="fw-semibold mb-1">{{ __('No variants yet') }}</div>
                                    <div class="text-muted small mb-3">
                                        {{ __('Start by adding your first variant.') }}
                                    </div>

                                    <button type="button" class="btn btn-primary btn-sm" wire:click="addVariant">
                                        <i class="mdi mdi-plus"></i> {{ __('Add First Variant') }}
                                    </button>
                                </div>
                            @endforelse
                        @else
                            <div class="border rounded-4 p-4 bg-light">
                                <div class="fw-semibold mb-1">{{ __('Simple product mode') }}</div>
                                <div class="text-muted small mb-0">
                                    {{ __('This product currently uses the main product price and quantity fields above.') }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>


                <div class="card shadow-sm border-0 mb-4" id="product-panel-related" role="tabpanel" aria-labelledby="product-tab-related" data-admin-section-panel="related">
                    <div class="card-header bg-white border-0 pb-0">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="mb-1">{{ __('Upsells & Bundles') }}</h5>
                                <small class="text-muted">{{ __('Control related products, bundle offers, and add-ons from one place.') }}</small>
                            </div>

                            <span class="badge bg-primary-subtle text-primary border">
                                {{ __('AOV Manager') }}
                            </span>
                        </div>
                    </div>

                    <div class="card-body pt-3">
                        <div class="row g-3">
                            @php
                                $relationSections = [
                                    [
                                        'type' => 'related',
                                        'title' => __('Related Products'),
                                        'subtitle' => __('Shown as recommendations across PDP, cart, and checkout.'),
                                        'searchModel' => 'relatedSearch',
                                        'searchResults' => $relatedSearchResults,
                                        'items' => $this->relatedProductsManager,
                                        'empty' => __('No related products selected yet.'),
                                    ],
                                    [
                                        'type' => 'bundle',
                                        'title' => __('Bundle Products'),
                                        'subtitle' => __('Products that can be sold together in a single offer block.'),
                                        'searchModel' => 'bundleSearch',
                                        'searchResults' => $bundleSearchResults,
                                        'items' => $this->bundleProductsManager,
                                        'empty' => __('No bundle products selected yet.'),
                                    ],
                                    [
                                        'type' => 'addon',
                                        'title' => __('Add-ons'),
                                        'subtitle' => __('Small extras the customer can add before checkout.'),
                                        'searchModel' => 'addonSearch',
                                        'searchResults' => $addonSearchResults,
                                        'items' => $this->addonProductsManager,
                                        'empty' => __('No add-ons selected yet.'),
                                    ],
                                ];
                            @endphp

                            @foreach($relationSections as $section)
                                <div class="col-12">
                                    <div class="aov-manager-card p-3 p-lg-4">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                    <h6 class="mb-0">{{ $section['title'] }}</h6>
                                                    <span class="aov-relation-badge bg-light text-dark border">
                                                        {{ count($section['items']) }} {{ __('selected') }}
                                                    </span>
                                                </div>
                                                <small class="text-muted">{{ $section['subtitle'] }}</small>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-lg-5">
                                                <label class="form-label">{{ __('Search product') }}</label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    wire:model.live.debounce.300ms="{{ $section['searchModel'] }}"
                                                    placeholder="{{ __('Type at least 2 characters') }}"
                                                >

                                                <div class="small text-muted mt-2">
                                                    {{ __('Search by product name or slug, then add the result below.') }}
                                                </div>

                                                <div class="aov-manager-search-results mt-3">
                                                    @if(strlen(trim((string) data_get($this, $section['searchModel']))) >= 2)
                                                        @forelse($section['searchResults'] as $searchProduct)
                                                            <div class="d-flex align-items-center justify-content-between gap-3 border rounded-4 px-3 py-2 bg-white mb-2">
                                                                <div class="min-w-0">
                                                                    <div class="fw-semibold text-truncate">{{ $searchProduct['name'] }}</div>
                                                                    <div class="small text-muted text-truncate">/{{ $searchProduct['slug'] }}</div>
                                                                </div>

                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-primary"
                                                                    wire:click="addAovRelation('{{ $section['type'] }}', {{ $searchProduct['id'] }})"
                                                                >
                                                                    <i class="mdi mdi-plus"></i>
                                                                </button>
                                                            </div>
                                                        @empty
                                                            <div class="border rounded-4 px-3 py-3 bg-white text-muted small">
                                                                {{ __('No matching products found for this search.') }}
                                                            </div>
                                                        @endforelse
                                                    @else
                                                        <div class="border rounded-4 px-3 py-3 bg-white text-muted small">
                                                            {{ __('Start typing to search for products to attach to this product.') }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-lg-7">
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                    <label class="form-label mb-0">{{ __('Selected products') }}</label>
                                                    <small class="text-muted">{{ __('Move, disable, or remove items. Active items appear on the storefront.') }}</small>
                                                </div>

                                                <div class="d-grid gap-2">
                                                    @forelse($section['items'] as $index => $item)
                                                        <div class="aov-manager-item p-3 @if(!($item['is_active'] ?? true)) is-inactive @endif" wire:key="aov-{{ $section['type'] }}-{{ $item['product_id'] }}-{{ $index }}">
                                                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                                                <div>
                                                                    <div class="fw-semibold mb-1">{{ $item['name'] }}</div>
                                                                    <div class="small text-muted">{{ __('Sort order') }}: {{ $index + 1 }}</div>
                                                                </div>

                                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                    @if($item['is_active'] ?? true)
                                                                        <span class="badge bg-success-subtle text-success border">{{ __('Active') }}</span>
                                                                    @else
                                                                        <span class="badge bg-secondary-subtle text-secondary border">{{ __('Hidden') }}</span>
                                                                    @endif

                                                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="moveAovRelationUp('{{ $section['type'] }}', {{ $index }})" @disabled($index === 0)>
                                                                        <i class="mdi mdi-arrow-up"></i>
                                                                    </button>

                                                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="moveAovRelationDown('{{ $section['type'] }}', {{ $index }})" @disabled($index === count($section['items']) - 1)>
                                                                        <i class="mdi mdi-arrow-down"></i>
                                                                    </button>

                                                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="toggleAovRelationActive('{{ $section['type'] }}', {{ $index }})">
                                                                        @if($item['is_active'] ?? true)
                                                                            <i class="mdi mdi-eye-off-outline"></i>
                                                                        @else
                                                                            <i class="mdi mdi-eye-outline"></i>
                                                                        @endif
                                                                    </button>

                                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeAovRelation('{{ $section['type'] }}', {{ $index }})">
                                                                        <i class="mdi mdi-delete-outline"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="border rounded-4 px-3 py-4 bg-white text-center text-muted small">
                                                            {{ $section['empty'] }}
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm border-0" id="product-panel-seo" role="tabpanel" aria-labelledby="product-tab-seo" data-admin-section-panel="seo">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="mb-1">{{ __('SEO / Meta') }}</h5>
                        <small class="text-muted">{{ __('Optional SEO information.') }}</small>
                    </div>

                    <div class="card-body pt-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Meta Title') }}</label>
                                <input type="text" class="form-control @error('meta_title') is-invalid product-error-field @enderror" wire:model.defer="meta_title">
                                @error('meta_title') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Meta {{ __('Description') }}</label>
                                <textarea class="form-control @error('meta_description') is-invalid product-error-field @enderror" rows="3" wire:model.defer="meta_description"></textarea>
                                @error('meta_description') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4" id="product-panel-images" role="tabpanel" aria-labelledby="product-tab-images" data-admin-section-panel="images">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="mb-1">{{ __('Images Manager') }}</h5>
                        <small class="text-muted">{{ __('Upload, preview, reorder, set main, and delete.') }}</small>
                    </div>

                    <div class="card-body pt-3">
                        <label class="form-label">{{ __('Product Images') }}</label>

                        <input
                            type="file"
                            class="d-none"
                            id="productImagesInput"
                            wire:model="newImages"
                            multiple
                            accept="image/*"
                        >

                        <div class="border rounded-4 p-4 text-center bg-light product-dropzone mb-3" id="productDropzone" style="cursor:pointer;">
                            <div class="mb-2">
                                <i class="mdi mdi-cloud-upload-outline" style="font-size: 2rem;"></i>
                            </div>

                            <div class="fw-semibold mb-1">{{ __('Drag & drop images here') }}</div>
                            <div class="text-muted small mb-3">{{ __('or click to browse multiple files') }}</div>

                            <button type="button" class="btn btn-outline-primary btn-sm" id="browseProductImages">
                                {{ __('Choose Images') }}
                            </button>
                        </div>

                        @error('newImages.*')
                            <small class="text-danger d-block mt-2 product-error-field">{{ $message }}</small>
                        @enderror

                        <div wire:loading wire:target="newImages" class="text-muted mt-2">
                            {{ __('Uploading images...') }}
                        </div>

                        @if (!empty($newImages))
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <label class="form-label mb-0">{{ __('New Images') }}</label>
                                <small class="text-muted">{{ __('Drag cards to reorder') }} before save.</small>
                            </div>

                            <div class="row g-3 mb-3" id="newImagesGrid">
                                @foreach ($newImages as $index => $image)
                                    <div
                                        class="col-md-6 col-sm-6 col-12 new-image-item"
                                        draggable="true"
                                        data-new-index="{{ $index }}"
                                        wire:key="new-image-preview-{{ $index }}"
                                    >
                                        <div class="border rounded p-2 text-center h-100 bg-white">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small">
                                                    <i class="mdi mdi-drag"></i> {{ __('Move') }}
                                                </span>

                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeNewImage({{ $index }})">
                                                    <i class="mdi mdi-close"></i>
                                                </button>
                                            </div>

                                            <img
                                                src="{{ $image->temporaryUrl() }}"
                                                class="img-fluid rounded mb-2"
                                                style="height: 130px; object-fit: cover; width: 100%;"
                                                alt="New image"
                                            >

                                            <div class="small text-muted">{{ __('New upload') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (!empty($existingImages))
                            <hr>

                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <label class="form-label mb-0">{{ __('Existing Images') }}</label>
                                <small class="text-muted">{{ __('Drag cards to reorder') }}.</small>
                            </div>

                            <div class="row g-3" id="existingImagesGrid">
                                @foreach ($existingImages as $image)
                                    <div
                                        class="col-md-6 col-sm-6 col-12 existing-image-item"
                                        draggable="true"
                                        data-existing-id="{{ $image['id'] }}"
                                        wire:key="existing-image-card-{{ $image['id'] }}-{{ $image['is_main'] }}-{{ $image['sort_order'] }}"
                                    >
                                        <div class="border rounded p-2 text-center h-100 bg-white">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small">
                                                    <i class="mdi mdi-drag"></i> {{ __('Move') }}
                                                </span>

                                                @if(!empty($image['is_main']))
                                                    <span class="badge bg-success">{{ __('Main') }}</span>
                                                @endif
                                            </div>

                                            <img
                                                src="{{ $image['image_url'] }}"
                                                class="img-fluid rounded mb-2"
                                                style="height: 130px; object-fit: cover; width: 100%;"
                                                alt="Product image"
                                            >

                                            <div class="d-grid gap-2">
                                                @if(empty($image['is_main']))
                                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="setMainExistingImage({{ $image['id'] }})">
                                                        {{ __('Set Main') }}
                                                    </button>
                                                @endif

                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteExistingImage({{ $image['id'] }})">
                                                    {{ __('Delete') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-form-actions mt-4">
            <div class="admin-form-actions-copy">
                <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                <div class="admin-form-actions-subtitle">{{ __('Review product data, pricing, stock, SEO fields, and image ordering, then save this product when you are ready.') }}</div>
            </div>
            <div class="admin-form-actions-buttons">
                <a
                    href="{{ route('admin.products.index') }}"
                    class="btn btn-outline-secondary"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    @disabled($isSaving)
                >
                    <i class="mdi mdi-arrow-left"></i> {{ __('Back') }}
                </a>

                <button
                    type="submit"
                    class="btn btn-primary d-inline-flex align-items-center gap-2 product-save-btn"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">
                        <i class="mdi mdi-content-save-outline"></i>
                        {{ __('Save Product') }}
                    </span>

                    <span wire:loading.inline-flex wire:target="save" class="align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        {{ __('Saving...') }}
                    </span>
                </button>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            function getProductFormRoot() {
                return document.querySelector('.product-form-root');
            }

            function getProductFormComponentId() {
                const root = getProductFormRoot();
                return root ? root.closest('[wire\\:id]')?.getAttribute('wire:id') : null;
            }

            function getProductStatusPill() {
                return document.getElementById('productFormStatusPill');
            }

            function getProductStatusText() {
                return document.getElementById('productFormStatusText');
            }

            function setProductStatus(state, text, iconClass = null) {
                const pill = getProductStatusPill();
                const textEl = getProductStatusText();

                if (!pill || !textEl) return;

                pill.setAttribute('data-state', state);
                textEl.textContent = text || '';

                const icon = pill.querySelector('i');
                if (icon && iconClass) {
                    icon.className = 'mdi ' + iconClass;
                }
            }

            function markProductFormDirty() {
                const root = getProductFormRoot();
                if (!root) return;

                if (root.classList.contains('is-optimistic-saving')) {
                    return;
                }

                root.dataset.dirty = '1';
                setProductStatus('dirty', 'Unsaved changes', 'mdi-circle-edit-outline');
            }

            function markProductFormSaving() {
                const root = getProductFormRoot();
                if (!root) return;

                root.classList.add('is-optimistic-saving');
                root.dataset.dirty = '0';
                setProductStatus('saving', 'Saving changes...', 'mdi-loading mdi-spin');
            }

            function markProductFormSaved(detail = {}) {
                const root = getProductFormRoot();
                if (!root) return;

                root.classList.remove('is-optimistic-saving');
                root.dataset.dirty = '0';

                const productId = detail.product_id ? '#' + detail.product_id : '';
                const action = detail.action ? detail.action.charAt(0).toUpperCase() + detail.action.slice(1) : 'Saved';
                const savedAt = detail.saved_at ? ' at ' + detail.saved_at : '';

                setProductStatus('saved', productId ? `${action} ${productId}${savedAt}` : `Saved${savedAt}`, 'mdi-check-circle-outline');
            }

            function markProductFormError(message = '{{ __('Save failed') }}') {
                const root = getProductFormRoot();
                if (!root) return;

                root.classList.remove('is-optimistic-saving');
                setProductStatus('error', message, 'mdi-alert-circle-outline');
            }

            function getProductField(model) {
                const form = document.getElementById('productFormMain');
                if (!form) return null;

                return Array.from(form.querySelectorAll('input, textarea, select')).find((field) => {
                    return ['wire:model.defer', 'wire:model', 'wire:model.live', 'wire:model.lazy']
                        .some((attribute) => field.getAttribute(attribute) === model);
                }) || null;
            }

            function productFieldValue(model) {
                const field = getProductField(model);
                return field ? String(field.value ?? '').trim() : '';
            }

            function hasValidNumericValue(value) {
                if (value === '' || value === null || value === undefined) return false;
                const number = Number(value);
                return Number.isFinite(number) && number >= 0;
            }

            function updateProductReadiness() {
                const form = document.getElementById('productFormMain');
                const card = document.getElementById('productPublishReadiness');
                if (!form || !card) return;

                const hasVariants = document.getElementById('hasVariantsSwitch')?.checked === true;
                const variantPriceFields = Array.from(form.querySelectorAll('input')).filter((field) => {
                    const model = field.getAttribute('wire:model.defer') || field.getAttribute('wire:model') || '';
                    return /^variants\.\d+\.price$/.test(model);
                });

                const checks = {
                    name: productFieldValue('name') !== '',
                    category: productFieldValue('category_id') !== '',
                    pricing: hasVariants
                        ? variantPriceFields.some((field) => hasValidNumericValue(field.value))
                        : hasValidNumericValue(productFieldValue('base_price')),
                    description: productFieldValue('description') !== '',
                    image: Boolean(
                        form.querySelector('.existing-image-item') ||
                        form.querySelector('.new-image-item') ||
                        (document.getElementById('productImagesInput')?.files?.length > 0)
                    ),
                    identifier: productFieldValue('sku') !== '' || productFieldValue('barcode') !== '',
                    seo: productFieldValue('meta_title') !== '' || productFieldValue('meta_description') !== ''
                };

                const checkButtons = Array.from(card.querySelectorAll('[data-readiness-key]'));
                let readyCount = 0;
                let missingRequired = 0;

                checkButtons.forEach((button) => {
                    const ready = Boolean(checks[button.dataset.readinessKey]);
                    if (ready) readyCount += 1;
                    if (!ready && button.dataset.required === '1') missingRequired += 1;

                    button.classList.toggle('is-ready', ready);
                    button.classList.toggle('is-missing', !ready);

                    const icon = button.querySelector('i');
                    if (icon) icon.className = ready ? 'mdi mdi-check-circle-outline' : 'mdi mdi-circle-outline';
                });

                const total = checkButtons.length;
                const count = document.getElementById('productReadinessCount');
                const progress = document.getElementById('productReadinessProgress');
                const summary = document.getElementById('productReadinessSummary');
                const status = productFieldValue('status');
                const visibility = document.getElementById('productVisibilityBadge');

                if (count) count.textContent = readyCount + '/' + total;
                if (progress) progress.style.width = (total ? (readyCount / total) * 100 : 0) + '%';

                if (summary) {
                    if (missingRequired > 0) {
                        summary.textContent = @json(__('Complete the required product information before saving.'));
                    } else if (readyCount < total) {
                        summary.textContent = @json(__('Core information is ready. Complete the recommended details before publishing.'));
                    } else {
                        summary.textContent = @json(__('Product content is ready for storefront review.'));
                    }
                }

                if (visibility) {
                    const active = status === '1';
                    visibility.dataset.state = active ? 'active' : 'inactive';
                    visibility.textContent = active
                        ? @json(__('Visible on storefront'))
                        : @json(__('Hidden from storefront'));
                }
            }

            function initProductReadiness() {
                const form = document.getElementById('productFormMain');
                if (!form) return;

                if (form.dataset.readinessInitialized !== '1') {
                    form.dataset.readinessInitialized = '1';

                    form.addEventListener('input', updateProductReadiness);
                    form.addEventListener('change', updateProductReadiness);

                    form.querySelectorAll('[data-readiness-section]').forEach((button) => {
                        button.addEventListener('click', function () {
                            const section = this.dataset.readinessSection;
                            if (!section) return;
                            window.AdminSectionTabs?.activate(form, section, true);
                            document.getElementById('product-tab-' + section)?.scrollIntoView({
                                behavior: 'smooth',
                                block: 'nearest'
                            });
                        });
                    });
                }

                updateProductReadiness();
            }

            function initDirtyTracking() {
                const form = document.getElementById('productFormMain');
                if (!form || form.dataset.dirtyTrackingInitialized === '1') return;

                form.dataset.dirtyTrackingInitialized = '1';

                form.addEventListener('input', function (e) {
                    const target = e.target;
                    if (!target) return;

                    if (target.matches('input, textarea, select')) {
                        markProductFormDirty();
                    }
                });

                form.addEventListener('change', function (e) {
                    const target = e.target;
                    if (!target) return;

                    if (target.matches('input, textarea, select')) {
                        markProductFormDirty();
                    }
                });

                form.addEventListener('submit', function () {
                    markProductFormSaving();
                }, true);
            }

            function initProductDropzone() {
                const input = document.getElementById('productImagesInput');
                const dropzone = document.getElementById('productDropzone');
                const browseButton = document.getElementById('browseProductImages');

                if (!input || !dropzone) return;
                if (dropzone.dataset.initialized === '1') return;

                dropzone.dataset.initialized = '1';

                dropzone.addEventListener('click', () => input.click());

                if (browseButton) {
                    browseButton.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        input.click();
                    });
                }

                ['dragenter', 'dragover'].forEach((eventName) => {
                    dropzone.addEventListener(eventName, function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.add('dragging');
                    });
                });

                ['dragleave', 'drop'].forEach((eventName) => {
                    dropzone.addEventListener(eventName, function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.remove('dragging');
                    });
                });

                dropzone.addEventListener('drop', function (e) {
                    const files = e.dataTransfer.files;
                    if (!files || !files.length) return;

                    input.files = files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    markProductFormDirty();
                });
            }

            function initSortableGrid(gridSelector, itemSelector, livewireMethod) {
                const grid = document.querySelector(gridSelector);
                if (!grid) return;
                if (grid.dataset.sortableInitialized === '1') return;

                grid.dataset.sortableInitialized = '1';

                let draggedItem = null;

                grid.addEventListener('dragstart', function (e) {
                    const item = e.target.closest(itemSelector);
                    if (!item) return;
                    draggedItem = item;
                    item.classList.add('opacity-50');
                });

                grid.addEventListener('dragend', function (e) {
                    const item = e.target.closest(itemSelector);
                    if (!item) return;
                    item.classList.remove('opacity-50');
                    draggedItem = null;
                });

                grid.addEventListener('dragover', function (e) {
                    e.preventDefault();
                });

                grid.addEventListener('drop', function (e) {
                    e.preventDefault();

                    const targetItem = e.target.closest(itemSelector);
                    if (!draggedItem || !targetItem || draggedItem === targetItem) return;

                    const parent = targetItem.parentNode;
                    const children = Array.from(parent.querySelectorAll(itemSelector));
                    const draggedIndex = children.indexOf(draggedItem);
                    const targetIndex = children.indexOf(targetItem);

                    if (draggedIndex < targetIndex) {
                        parent.insertBefore(draggedItem, targetItem.nextSibling);
                    } else {
                        parent.insertBefore(draggedItem, targetItem);
                    }

                    const ordered = Array.from(parent.querySelectorAll(itemSelector)).map((node) =>
                        node.dataset.existingId || node.dataset.newIndex
                    );

                    const componentId = getProductFormComponentId();
                    if (componentId && window.Livewire?.find(componentId)) {
                        window.Livewire.find(componentId).call(livewireMethod, ordered);
                        markProductFormDirty();
                    }
                });
            }

            function showProductFormToast(type, message, duration = 4500) {
                const container = document.getElementById('productFormToastContainer');
                if (!container || !message) return;

                let bgClass = 'text-bg-primary';
                let icon = 'mdi-information-outline';

                if (type === 'success') {
                    bgClass = 'text-bg-success';
                    icon = 'mdi-check-circle-outline';
                } else if (type === 'error') {
                    bgClass = 'text-bg-danger';
                    icon = 'mdi-alert-circle-outline';
                } else if (type === 'warning') {
                    bgClass = 'text-bg-warning';
                    icon = 'mdi-alert-outline';
                }

                const toastId = 'toast-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);

                const toastHtml = `
                    <div id="${toastId}" data-toast-message="${String(message).replace(/"/g, "&quot;")}" class="toast align-items-center ${bgClass} border-0 shadow mb-2 mx-auto" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 320px;">
                        <div class="d-flex">
                            <div class="toast-body d-flex align-items-start gap-2 text-center w-100 justify-content-center">
                                <i class="mdi ${icon}" style="font-size: 1.1rem;"></i>
                                <div>${message}</div>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `;

                const duplicates = Array.from(container.querySelectorAll('[data-toast-message]'));
                duplicates.forEach((item) => {
                    if (item.getAttribute('data-toast-message') === message) {
                        item.remove();
                    }
                });

                container.insertAdjacentHTML('beforeend', toastHtml);

                const toastEl = document.getElementById(toastId);
                const toast = new bootstrap.Toast(toastEl, {
                    delay: duration
                });

                toastEl.addEventListener('hidden.bs.toast', function () {
                    toastEl.remove();
                });

                const allToasts = Array.from(container.querySelectorAll('.toast'));
                if (allToasts.length > 3) {
                    allToasts.slice(0, allToasts.length - 3).forEach((item) => item.remove());
                }

                toast.show();
            }

            function scrollToFirstProductError() {
                setTimeout(() => {
                    const firstError = document.querySelector(
                        '.product-form-root .is-invalid, .product-form-root .product-error-field'
                    );

                    const collapsedVariant = firstError?.closest('.card')?.querySelector('[wire\\:click^="toggleVariant"]');
                    const collapsedBody = firstError?.closest('.card')?.querySelector('.card-body');
                    if (collapsedVariant && !collapsedBody) {
                        collapsedVariant.click();
                    }

                    if (!firstError) return;

                    const panel = firstError.closest('[data-admin-section-panel]');
                    if (panel) window.AdminSectionTabs?.activate(document.getElementById('productFormMain'), panel.dataset.adminSectionPanel);

                    const card = firstError.closest('.card');
                    const target = card || firstError;

                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    setTimeout(() => {
                        if (typeof firstError.focus === 'function') {
                            firstError.focus({
                                preventScroll: true
                            });
                        }
                    }, 450);
                }, 120);
            }

            function initProductFormHelpers() {
                if (!getProductFormRoot()) return;

                window.AdminSectionTabs?.init(document.getElementById('productFormMain'));
                initDirtyTracking();
                initProductReadiness();
                initProductDropzone();
                initSortableGrid('#existingImagesGrid', '.existing-image-item', 'reorderExistingImages');
                initSortableGrid('#newImagesGrid', '.new-image-item', 'reorderNewImages');
            }

            document.addEventListener('livewire:load', () => {
                initProductFormHelpers();

                window.addEventListener('product-form-toast', (event) => {
                    const detail = event.detail || {};
                    showProductFormToast(detail.type || 'success', detail.message || '', detail.duration || 4500);
                });

                window.addEventListener('product-form-scroll-to-first-error', () => {
                    scrollToFirstProductError();
                });

                window.addEventListener('product-form-saving-state', (event) => {
                    const detail = event.detail || {};

                    if (detail.saving) {
                        markProductFormSaving();
                    } else {
                        const root = getProductFormRoot();
                        if (root && root.dataset.dirty !== '1' && getProductStatusPill()?.getAttribute('data-state') === 'saving') {
                            setProductStatus('idle', '{{ __('Ready to save') }}', 'mdi-content-save-outline');
                            root.classList.remove('is-optimistic-saving');
                        }
                    }
                });

                window.addEventListener('product-form-save-succeeded', (event) => {
                    markProductFormSaved(event.detail || {});
                });

                window.addEventListener('product-form-save-failed', (event) => {
                    const detail = event.detail || {};
                    markProductFormError(detail.message || '{{ __('Save failed') }}');
                });

                if (window.Livewire?.hook) {
                    Livewire.hook('message.processed', () => {
                        window.requestAnimationFrame(() => {
                            initProductFormHelpers();
                        });
                    });
                }
            });
        </script>
    @endpush
</div>
