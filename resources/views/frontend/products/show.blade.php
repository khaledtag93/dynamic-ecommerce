@extends('layouts.app')

@section('title', $product->name . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
@php
    $basePrice = (float) ($product->base_price ?? 0);
    $currentPrice = (float) ($product->current_price ?? 0);
    $salePrice = (float) ($product->sale_price ?? 0);
    $hasDiscount = $salePrice > 0 && $basePrice > 0 && $salePrice < $basePrice;
    $discountPercent = $hasDiscount ? max(1, (int) round((($basePrice - $salePrice) / $basePrice) * 100)) : 0;
    $gallery = ($product->productImages ?? collect())->values();
    $galleryImages = $gallery->isNotEmpty()
        ? $gallery
        : collect([(object) ['image_url' => $product->main_image_url ?: asset('images/storefront-placeholder.svg')]]);
    $stockQty = (int) ($product->quantity_value ?? 0);
    $defaultImage = optional($galleryImages->first())->image_url ?: asset('images/storefront-placeholder.svg');
    $activeVariants = ($product->activeVariants ?? collect())->values();
    $defaultVariant = $activeVariants->firstWhere('is_default', true) ?: $activeVariants->first();
    $selectedVariantStock = (int) ($defaultVariant->stock ?? $stockQty);
    $displayStock = $activeVariants->isNotEmpty() ? $selectedVariantStock : $stockQty;
    $selectedAvailable = $product->in_stock && $displayStock > 0;
    $isLowStock = $product->in_stock && $displayStock > 0 && $displayStock <= max(5, (int) ($product->low_stock_threshold ?? 3));
    $bundleProducts = ($bundleProducts ?? collect())->values();
    $addonProducts = ($addonProducts ?? collect())->values();
    $bundleSeedTotal = $currentPrice + $bundleProducts->sum(fn ($item) => (float) ($item->current_price ?? 0));
    $addonSeedTotal = $addonProducts->sum(fn ($item) => (float) ($item->current_price ?? 0));
@endphp

<section class="py-5 product-page-shell">
    <div class="container">
        <nav class="lc-breadcrumb mb-4" aria-label="breadcrumb">
            <a href="{{ route('frontend.home') }}">{{ __('Home') }}</a>
            <span>/</span>
            <a href="{{ route('category.products', $product->category_id) }}">{{ $product->category->name ?? __('Category') }}</a>
            <span>/</span>
            <span>{{ \Illuminate\Support\Str::limit($product->name, 40) }}</span>
        </nav>

        @include('frontend.partials.behavioral-offers', ['cards' => $behavioralOffers['cards'] ?? collect()])

        <div class="row g-4 g-xl-5 align-items-start">
            <div class="col-lg-7">
                <div class="lc-card p-3 p-lg-4 product-gallery-card">
                    <div class="product-gallery-main position-relative mb-3">
                        @if($hasDiscount)
                            <span class="lc-product-badge lc-product-badge--large"><i class="bi bi-stars"></i> {{ __('Save') }} {{ $discountPercent }}%</span>
                        @endif

                        @if($isLowStock)
                            <span class="product-floating-note"><i class="bi bi-lightning-charge-fill"></i> {{ __('Only :count left in stock', ['count' => $displayStock]) }}</span>
                        @endif

                        <img
                            id="mainProductImage"
                            src="{{ $defaultImage }}"
                            class="img-fluid rounded-4 product-gallery-main__image"
                            alt="{{ $product->name }}"
                            data-default-src="{{ $defaultImage }}"
                        >
                    </div>

                    <div class="row g-2">
                        @foreach($galleryImages as $index => $image)
                            <div class="col-3 col-md-2">
                                <button
                                    type="button"
                                    class="btn p-0 border-0 w-100 product-thumb-button {{ $index === 0 ? 'is-active' : '' }}"
                                    data-gallery-thumb
                                    data-image="{{ $image->image_url }}"
                                    aria-label="{{ __('Show image :number', ['number' => $index + 1]) }}"
                                >
                                    <img src="{{ $image->image_url }}" class="img-fluid rounded-3 product-thumb-button__image" alt="{{ $product->name }}">
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="lc-card p-4 product-buy-card sticky-lg-top" style="top: 100px;" id="productPurchaseCard">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                        <span class="lc-badge"><i class="bi bi-grid"></i> {{ $product->category->name ?? __('General') }}</span>
                        <span class="lc-badge {{ $product->in_stock ? '' : 'opacity-75' }}"><i class="bi {{ $product->in_stock ? 'bi-check-circle' : 'bi-x-circle' }}"></i> {{ $product->in_stock ? __('Available') : __('Unavailable now') }}</span>
                    </div>

                    <h1 class="fw-bold mb-3 product-page-title">{{ $product->name }}</h1>

                    <div class="d-flex align-items-end gap-3 mb-3 flex-wrap">
                        <div class="fs-2 fw-bold" id="productMainPrice">EGP {{ number_format((float) ($defaultVariant?->current_price ?? $currentPrice), 2) }}</div>
                        @if($hasDiscount)
                            <div>
                                <div class="lc-price-original fs-6">EGP {{ number_format($basePrice, 2) }}</div>
                                <div class="small fw-semibold text-success">{{ __('You are saving') }} {{ $discountPercent }}%</div>
                            </div>
                        @endif
                    </div>

                    <div class="product-urgency-banner mb-3 {{ $selectedAvailable ? ($isLowStock ? 'is-warning' : 'is-success') : 'is-muted' }}" id="productStockBanner">
                        <i class="bi {{ $selectedAvailable ? ($isLowStock ? 'bi-alarm' : 'bi-check2-circle') : 'bi-exclamation-octagon' }}"></i>
                        <div>
                            <strong id="productStockHeadline">
                                @if(!$product->in_stock)
                                    {{ __('Currently unavailable') }}
                                @elseif(!$selectedAvailable)
                                    {{ __('Selected option is out of stock') }}
                                @elseif($isLowStock)
                                    {{ __('Only :count pieces left', ['count' => $displayStock]) }}
                                @else
                                    {{ __('In stock and ready for checkout') }}
                                @endif
                            </strong>
                            <div class="small opacity-75" id="productStockMeta">{{ __('Availability is checked again before checkout.') }}</div>
                        </div>
                    </div>

                    @if($product->description)
                        <p class="text-muted mb-4">{{ \Illuminate\Support\Str::limit($product->description, 180) }}</p>
                    @endif

                    <form method="POST" action="{{ route('cart.store', $product) }}" class="d-grid gap-3 mb-4" id="productPurchaseForm" data-submit-loading data-live-cart-add>
                        @csrf

                        @if($activeVariants->isNotEmpty())
                            <div>
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
                                    <label class="form-label fw-bold mb-0">{{ __('Choose variant') }}</label>
                                    <span class="small text-muted" id="selectedVariantLabel">{{ $defaultVariant?->variant_name ?: __('Standard option') }}</span>
                                </div>

                                <select name="variant_id" class="form-select lc-form-select d-none" id="productVariantSelect" required>
                                    @foreach($activeVariants as $variant)
                                        <option
                                            value="{{ $variant->id }}"
                                            data-price="{{ number_format((float) $variant->current_price, 2, '.', '') }}"
                                            data-stock="{{ (int) ($variant->stock ?? 0) }}"
                                            data-label="{{ e(trim((string) $variant->variant_name) ?: __('Standard option')) }}"
                                            {{ $defaultVariant && $defaultVariant->id === $variant->id ? 'selected' : '' }}
                                            @disabled((int) $variant->stock < 1)
                                        >
                                            {{ trim((string) $variant->variant_name) ?: __('Standard option') }}
                                        </option>
                                    @endforeach
                                </select>

                                <div class="variant-pills-grid" id="variantPillsGrid">
                                    @foreach($activeVariants as $variant)
                                        @php($variantLabel = trim((string) $variant->variant_name) ?: __('Standard option'))
                                        <button
                                            type="button"
                                            class="variant-pill {{ $defaultVariant && $defaultVariant->id === $variant->id ? 'is-active' : '' }} {{ (int) ($variant->stock ?? 0) < 1 ? 'is-disabled' : '' }}"
                                            data-variant-button
                                            data-variant-id="{{ $variant->id }}"
                                            data-price="{{ number_format((float) $variant->current_price, 2, '.', '') }}"
                                            data-stock="{{ (int) ($variant->stock ?? 0) }}"
                                            data-label="{{ e($variantLabel) }}"
                                            @disabled((int) ($variant->stock ?? 0) < 1)
                                        >
                                            <span class="variant-pill__title">{{ $variantLabel }}</span>
                                            <span class="variant-pill__meta">
                                                EGP {{ number_format((float) $variant->current_price, 2) }}
                                                @if((int) ($variant->stock ?? 0) > 0)
                                                    · {{ __(':count left', ['count' => (int) ($variant->stock ?? 0)]) }}
                                                @else
                                                    · {{ __('Out of stock') }}
                                                @endif
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="form-label fw-bold">{{ __('Quantity') }}</label>
                            <div class="product-qty-control">
                                <button type="button" class="btn product-qty-control__btn" data-qty-step="down" aria-label="{{ __('Decrease quantity') }}">−</button>
                                <input type="number" class="form-control lc-form-control text-center" name="quantity" id="productQtyInput" value="1" min="1" max="{{ max(1, $selectedVariantStock ?: $stockQty ?: 1) }}">
                                <button type="button" class="btn product-qty-control__btn" data-qty-step="up" aria-label="{{ __('Increase quantity') }}">+</button>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn lc-btn-primary btn-lg" type="submit" id="addToCartButton" data-loading-text="{{ __('Adding...') }}" {{ $selectedAvailable ? '' : 'disabled' }}>
                                <i class="bi bi-bag-plus me-2"></i>{{ $selectedAvailable ? __('Add to cart') : __('Out of stock') }}
                            </button>

                            <button class="btn lc-btn-soft btn-lg" type="submit" name="redirect_to" value="checkout" id="buyNowButton" data-loading-text="{{ __('Preparing checkout...') }}" {{ $selectedAvailable ? '' : 'disabled' }}>
                                <i class="bi bi-lightning-charge-fill me-2"></i>{{ __('Buy now') }}
                            </button>
                        </div>

                        <div class="product-mini-checkout-note">
                            <i class="bi bi-lock"></i>
                            <span>{{ __('Buy now takes you directly to checkout.') }}</span>
                        </div>

                        <a href="{{ route('category.products', $product->category_id) }}" class="btn lc-btn-soft">
                            <i class="bi bi-arrow-left-right me-2"></i>{{ __('Continue browsing this category') }}
                        </a>
                    </form>

                </div>
            </div>
        </div>

        <div class="row g-4 mt-1 mt-lg-4">
            @if($product->description)
                <div class="col-lg-8">
                    <div class="lc-card p-4 p-lg-5 h-100">
                        <h2 class="h3 fw-bold mb-3">{{ __('Product details') }}</h2>
                        <p class="text-muted mb-0">{{ $product->description }}</p>
                    </div>
                </div>
            @endif
            <div class="{{ $product->description ? 'col-lg-4' : 'col-12' }}">
                <div class="lc-card p-4 h-100">
                    <span class="lc-section-kicker mb-3">{{ __('Quick facts') }}</span>
                    <div class="product-quick-facts">
                        <div><span>{{ __('Category') }}</span><strong>{{ $product->category->name ?? __('General') }}</strong></div>
                        <div><span>{{ __('Availability') }}</span><strong id="quickFactAvailability">{{ $product->in_stock ? __('Available') : __('Unavailable') }}</strong></div>
                        @if($displayStock > 0)
                            <div><span>{{ __('Quantity available') }}</span><strong id="quickFactQty">{{ $displayStock }}</strong></div>
                        @endif
                        <div><span>{{ __('Purchase option') }}</span><strong>{{ $activeVariants->isNotEmpty() ? number_format($activeVariants->count()) . ' ' . __('variants') : __('Standard') }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        @include('frontend.products._reviews')

        @if($bundleProducts->isNotEmpty() || $addonProducts->isNotEmpty())
            <section class="mt-5">
                <div class="lc-card p-4 p-lg-5 aov-bundle-shell">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <span class="lc-section-kicker">{{ __('Complete your setup') }}</span>
                            <h2 class="h3 fw-bold mb-2">{{ __('Add matching accessories') }}</h2>
                            <p class="text-muted mb-0">{{ __('Choose useful accessories or related products that fit this item.') }}</p>
                        </div>
                        <span class="lc-badge"><i class="bi bi-diagram-3"></i>{{ __('Related products') }}</span>
                    </div>

                    <form method="POST" action="{{ route('cart.bundle.store', $product) }}" class="d-grid gap-4" data-submit-loading id="bundleAovForm">
                        @csrf
                        <input type="hidden" name="variant_id" id="bundleVariantId" value="{{ $defaultVariant?->id }}">
                        <input type="hidden" name="quantity" id="bundleQuantityInput" value="1">

                        <div class="row g-3 align-items-stretch">
                            <div class="col-lg-7">
                                <div class="aov-bundle-grid">
                                    <label class="aov-bundle-card is-main">
                                        <span class="aov-bundle-check">
                                            <input type="checkbox" checked disabled>
                                        </span>
                                        <img src="{{ $product->main_image_url ?: asset('images/storefront-placeholder.svg') }}" alt="{{ $product->name }}">
                                        <div>
                                            <div class="fw-bold">{{ $product->name }}</div>
                                            <div class="text-muted small" id="bundleMainVariantLabel">{{ $defaultVariant?->variant_name ?: __('Selected main option') }}</div>
                                            <div class="aov-bundle-price" id="bundleMainPrice">EGP {{ number_format((float) ($defaultVariant?->current_price ?? $currentPrice), 2) }}</div>
                                        </div>
                                    </label>

                                    @foreach($bundleProducts as $bundleItem)
                                        <label class="aov-bundle-card">
                                            <span class="aov-bundle-check">
                                                <input type="checkbox" name="bundle_product_ids[]" value="{{ $bundleItem->id }}" data-bundle-checkbox data-price="{{ number_format((float) ($bundleItem->current_price ?? 0), 2, '.', '') }}" checked>
                                            </span>
                                            <img src="{{ $bundleItem->main_image_url ?: asset('images/storefront-placeholder.svg') }}" alt="{{ $bundleItem->name }}">
                                            <div>
                                                <div class="fw-bold">{{ $bundleItem->name }}</div>
                                                <div class="text-muted small">{{ $bundleItem->category->name ?? __('Recommended add-on') }}</div>
                                                <div class="aov-bundle-price">EGP {{ number_format((float) ($bundleItem->current_price ?? 0), 2) }}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="aov-summary-card h-100">
                                    <div class="small text-uppercase fw-bold text-muted mb-2">{{ __('Bundle summary') }}</div>
                                    <h3 class="h5 fw-bold mb-2">{{ __('Add the full setup to cart') }}</h3>
                                    <p class="text-muted mb-3">{{ __('The main product stays selected. Extra items can be toggled on or off without leaving the page.') }}</p>
                                    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted">{{ __('Selected items') }}</span><strong id="bundleSelectedCount">{{ 1 + $bundleProducts->count() }}</strong></div>
                                    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted">{{ __('Bundle total') }}</span><strong class="fs-5" id="bundleTotalPrice">EGP {{ number_format($bundleSeedTotal, 2) }}</strong></div>
                                    <div class="d-flex justify-content-between align-items-center mb-4"><span class="text-muted">{{ __('Action') }}</span><span class="lc-badge"><i class="bi bi-bag-plus"></i>{{ __('One-click cart build') }}</span></div>
                                    <div class="d-grid gap-2">
                                        <button class="btn lc-btn-primary" type="submit" data-loading-text="{{ __('Adding bundle...') }}">{{ __('Add selected items to cart') }}</button>
                                        <button class="btn lc-btn-soft" type="submit" name="redirect_to" value="checkout" data-loading-text="{{ __('Preparing checkout...') }}">{{ __('Buy selected items now') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($addonProducts->isNotEmpty())
                            <div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <div>
                                        <h3 class="h5 fw-bold mb-1">{{ __('Add with this product') }}</h3>
                                        <div class="text-muted small">{{ __('Add compatible accessories or useful extras before checkout.') }}</div>
                                    </div>
                                    <span class="lc-badge"><i class="bi bi-plus-circle"></i>{{ __('Optional extras') }}</span>
                                </div>
                                <div class="row g-3">
                                    @foreach($addonProducts as $addonItem)
                                        <div class="col-md-6 col-xl-3">
                                            <label class="aov-addon-card h-100">
                                                <input type="checkbox" name="bundle_product_ids[]" value="{{ $addonItem->id }}" data-bundle-checkbox data-price="{{ number_format((float) ($addonItem->current_price ?? 0), 2, '.', '') }}">
                                                <div class="aov-addon-card__media mb-3">
                                                    <img src="{{ $addonItem->main_image_url ?: asset('images/storefront-placeholder.svg') }}" alt="{{ $addonItem->name }}">
                                                </div>
                                                <div class="fw-bold mb-1">{{ $addonItem->name }}</div>
                                                <div class="text-muted small mb-2">{{ $addonItem->category->name ?? __('Accessory') }}</div>
                                                <div class="aov-bundle-price">+ EGP {{ number_format((float) ($addonItem->current_price ?? 0), 2) }}</div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </form>
                </div>
            </section>
        @endif

        @if(($offerSignals ?? collect())->isNotEmpty())
            <section class="mt-5">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <span class="lc-section-kicker">{{ __('Current offers') }}</span>
                        <h2 class="lc-section-title mb-0">{{ __('Reasons to buy now') }}</h2>
                    </div>
                    <span class="lc-badge"><i class="bi bi-lightning-charge"></i>{{ __('Available offers') }}</span>
                </div>
                <div class="row g-3">
                    @foreach($offerSignals as $signal)
                        <div class="col-md-6 col-xl-3">
                            <article class="product-offer-signal-card h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                    <span class="product-offer-signal-card__chip">{{ $signal['chip'] }}</span>
                                    <strong class="product-offer-signal-card__emphasis">{{ $signal['emphasis'] }}</strong>
                                </div>
                                <h3 class="h6 fw-bold mb-2">{{ $signal['headline'] }}</h3>
                                <p class="text-muted mb-0">{{ $signal['copy'] }}</p>
                            </article>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <span class="lc-section-kicker">{{ __('Keep shopping') }}</span>
                    <h2 class="lc-section-title mb-0">{{ __('Related products') }}</h2>
                </div>
                <a href="{{ route('category.products', $product->category_id) }}" class="btn lc-btn-soft">{{ __('More in this category') }}</a>
            </div>
            <div class="row g-4">
                @forelse($relatedProducts as $related)
                    <div class="col-md-6 col-lg-3">
                        @include('frontend.sections.partials.product-card', ['product' => $related])
                    </div>
                @empty
                    <div class="col-12"><div class="lc-card p-4 text-center text-muted">{{ __('No related products found.') }}</div></div>
                @endforelse
            </div>
        </section>


        @include('frontend.sections.ai-recommendation-strip', [
            'products' => $aiRecommendedProducts ?? collect(),
            'subtitle' => __('Recommended products'),
            'title' => __('Related products'),
            'description' => __('Similar products and useful alternatives from the same department.'),
            'insight' => $aiRecommendationInsight ?? null,
            'badge' => __('Recommended'),
        ])

        @include('frontend.sections.personalized-product-strip', [
            'products' => $personalizedProducts ?? collect(),
            'subtitle' => __('Recommended for you'),
            'title' => __('More products to compare'),
            'description' => __('Similar products and useful alternatives from the same department.'),
            'badge' => __('Suggested'),
            'actionText' => __('See category'),
            'actionLink' => route('category.products', $product->category_id),
        ])

        @include('frontend.sections.personalized-product-strip', [
            'products' => $recentlyViewedProducts ?? collect(),
            'subtitle' => __('Recently viewed'),
            'title' => __('Pick up where you left off'),
            'description' => __('Products you opened recently are saved here for quick access.'),
            'badge' => __('Recently viewed'),
        ])
    </div>
</section>

@if($product->in_stock)
    <div class="product-mobile-sticky d-lg-none">
        <div>
            <div class="small text-muted fw-semibold">{{ __('Today’s price') }}</div>
            <strong id="mobileStickyPrice">EGP {{ number_format((float) ($defaultVariant?->current_price ?? $currentPrice), 2) }}</strong>
        </div>
        <a href="#productPurchaseForm" class="btn lc-btn-primary">{{ __('Add to cart') }}</a>
    </div>

    <div class="product-desktop-sticky d-none d-lg-flex" id="desktopStickyBar">
        <div>
            <div class="product-desktop-sticky__title">{{ \Illuminate\Support\Str::limit($product->name, 46) }}</div>
            <div class="product-desktop-sticky__meta">
                <strong id="desktopStickyPrice">EGP {{ number_format((float) ($defaultVariant?->current_price ?? $currentPrice), 2) }}</strong>
                <span id="desktopStickyVariant">{{ $defaultVariant?->variant_name ?: __('Ready to order') }}</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="#productPurchaseForm" class="btn lc-btn-soft">{{ __('View options') }}</a>
            <button type="button" class="btn lc-btn-primary" id="desktopStickyAddButton">{{ __('Add to cart') }}</button>
        </div>
    </div>
@endif
@endsection

@push('styles')
<style>
.lc-breadcrumb{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap;font-weight:700;color:var(--lc-muted)}
.lc-breadcrumb a{text-decoration:none;color:var(--lc-primary-dark)}
.product-gallery-card,.product-buy-card{overflow:hidden}
.product-gallery-main{border-radius:1.5rem;background:linear-gradient(180deg,color-mix(in srgb,var(--lc-surface) 98%, transparent),color-mix(in srgb,var(--lc-soft) 88%, white));padding:.65rem;border:1px solid color-mix(in srgb,var(--lc-border) 80%, white)}
.product-gallery-main__image{width:100%;aspect-ratio:1/1;object-fit:contain}
.product-thumb-button{transition:transform .2s ease,opacity .2s ease,box-shadow .2s ease}.product-thumb-button:hover{transform:translateY(-2px);opacity:.96}.product-thumb-button.is-active .product-thumb-button__image{box-shadow:0 0 0 2px color-mix(in srgb,var(--lc-primary) 48%, white)}.product-thumb-button__image{aspect-ratio:1/1;object-fit:contain;border:1px solid color-mix(in srgb,var(--lc-border) 75%, white)}
.lc-product-badge--large{position:absolute;top:1rem;left:1rem;z-index:2}
body[dir="rtl"] .lc-product-badge--large{left:auto;right:1rem}
.product-floating-note{position:absolute;right:1rem;bottom:1rem;display:inline-flex;align-items:center;gap:.5rem;padding:.65rem .9rem;border-radius:999px;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);font-weight:800;color:#9a3412;box-shadow:0 16px 34px rgba(15,23,42,.12)}
body[dir="rtl"] .product-floating-note{right:auto;left:1rem}
.product-page-title{line-height:1.12}
.product-offer-signal-card{height:100%;padding:1rem 1rem 1.05rem;border-radius:1.1rem;background:linear-gradient(180deg,rgba(255,255,255,.96),color-mix(in srgb,var(--lc-soft) 72%, white));border:1px solid color-mix(in srgb,var(--lc-border) 78%, white);box-shadow:0 14px 32px color-mix(in srgb,var(--lc-primary) 8%, transparent)}.product-offer-signal-card__chip{display:inline-flex;align-items:center;padding:.4rem .65rem;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.75rem;font-weight:800}.product-offer-signal-card__emphasis{font-size:.78rem;color:var(--lc-primary-dark)}
.product-quick-facts{display:grid;gap:1rem}.product-quick-facts div{display:flex;justify-content:space-between;gap:1rem;padding-bottom:.9rem;border-bottom:1px dashed color-mix(in srgb,var(--lc-border) 80%, white)}.product-quick-facts div:last-child{padding-bottom:0;border-bottom:0}.product-quick-facts span{color:var(--lc-muted)}
.product-urgency-banner{display:flex;gap:.9rem;align-items:flex-start;padding:1rem 1.1rem;border-radius:1.1rem;border:1px solid transparent}.product-urgency-banner i{font-size:1.15rem;margin-top:.05rem}.product-urgency-banner.is-success{background:#ecfdf3;border-color:#bbf7d0;color:#166534}.product-urgency-banner.is-warning{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.product-urgency-banner.is-muted{background:#f8fafc;border-color:#e2e8f0;color:#475569}
.product-qty-control{display:grid;grid-template-columns:52px 1fr 52px;gap:.6rem;align-items:center}.product-qty-control__btn{border-radius:1rem;border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);background:var(--lc-surface);font-weight:800;font-size:1.25rem;padding:.65rem .5rem}
.variant-pills-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.variant-pill{display:flex;flex-direction:column;align-items:flex-start;gap:.3rem;text-align:start;width:100%;padding:1rem;border-radius:1rem;border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);background:linear-gradient(180deg,color-mix(in srgb,var(--lc-surface) 98%, transparent),color-mix(in srgb,var(--lc-soft) 76%, white));transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}.variant-pill:hover{transform:translateY(-2px);box-shadow:0 16px 30px color-mix(in srgb,var(--lc-primary) 10%, transparent)}.variant-pill.is-active{border-color:color-mix(in srgb,var(--lc-primary) 45%, white);box-shadow:0 16px 36px color-mix(in srgb,var(--lc-primary) 16%, transparent);background:#fff}.variant-pill.is-disabled{opacity:.5}.variant-pill__title{font-weight:800;color:var(--lc-text)}.variant-pill__meta{font-size:.88rem;color:var(--lc-muted)}
.product-mini-checkout-note{display:flex;align-items:flex-start;gap:.7rem;padding:.95rem 1rem;border-radius:1rem;background:color-mix(in srgb,var(--lc-soft) 70%, white);border:1px solid color-mix(in srgb,var(--lc-border) 78%, white);color:var(--lc-muted);font-size:.94rem}.product-mini-checkout-note i{color:var(--lc-primary-dark);margin-top:.1rem}
.aov-bundle-shell{background:linear-gradient(180deg,#fff 0%,color-mix(in srgb,var(--lc-soft) 74%, white) 100%)}.aov-bundle-grid{display:grid;gap:.9rem}.aov-bundle-card,.aov-addon-card{position:relative;display:grid;grid-template-columns:auto 88px 1fr;gap:1rem;align-items:center;padding:1rem;border-radius:1.15rem;border:1px solid color-mix(in srgb,var(--lc-border) 78%, white);background:rgba(255,255,255,.9);box-shadow:0 14px 32px color-mix(in srgb,var(--lc-primary) 6%, transparent)}.aov-bundle-card.is-main{background:linear-gradient(180deg,color-mix(in srgb,var(--lc-soft) 60%, white),#fff)}.aov-bundle-card img{width:88px;height:88px;object-fit:cover;border-radius:1rem}.aov-bundle-check input,.aov-addon-card input{width:1.1rem;height:1.1rem}.aov-bundle-price{font-weight:800;color:var(--lc-primary-dark)}.aov-summary-card{padding:1.25rem;border-radius:1.2rem;background:#fff;border:1px solid color-mix(in srgb,var(--lc-border) 78%, white);box-shadow:0 16px 34px color-mix(in srgb,var(--lc-primary) 8%, transparent)}.aov-addon-card{grid-template-columns:1fr;align-items:start;padding:1rem 1rem 1.2rem}.aov-addon-card__media img{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:1rem}.aov-addon-card input{position:absolute;top:1rem;inset-inline-end:1rem}.aov-addon-card:hover,.aov-bundle-card:hover{transform:translateY(-2px);transition:transform .2s ease,box-shadow .2s ease}.aov-addon-card:has(input:checked),.aov-bundle-card:has(input:checked){border-color:color-mix(in srgb,var(--lc-primary) 42%, white);box-shadow:0 18px 40px color-mix(in srgb,var(--lc-primary) 14%, transparent)}
.product-mobile-sticky{position:fixed;left:1rem;right:1rem;bottom:1rem;z-index:1030;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;border-radius:1.2rem;background:rgba(255,255,255,.96);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);box-shadow:0 18px 46px rgba(15,23,42,.16);backdrop-filter:blur(12px)}
.product-desktop-sticky{position:fixed;left:1.5rem;right:1.5rem;bottom:1.25rem;z-index:1030;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.2rem;border-radius:1.2rem;background:rgba(255,255,255,.96);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);box-shadow:0 24px 54px rgba(15,23,42,.16);backdrop-filter:blur(14px);transform:translateY(140%);opacity:0;pointer-events:none;transition:all .25s ease}.product-desktop-sticky.is-visible{transform:translateY(0);opacity:1;pointer-events:auto}.product-desktop-sticky__title{font-weight:800}.product-desktop-sticky__meta{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;color:var(--lc-muted)}.product-desktop-sticky__meta strong{color:var(--lc-text)}
body[dir="rtl"] .product-mobile-sticky,body[dir="rtl"] .product-desktop-sticky{direction:rtl}
@media (max-width: 991.98px){.product-buy-card{position:static!important;top:auto!important}.product-page-shell{padding-bottom:6rem}.variant-pills-grid{grid-template-columns:1fr}.aov-bundle-card{grid-template-columns:auto 72px 1fr}.aov-bundle-card img{width:72px;height:72px}}
@media (max-width: 767.98px){.aov-bundle-card{grid-template-columns:1fr;text-align:start}.aov-bundle-card img{width:100%;max-width:120px;height:auto;aspect-ratio:1/1}.aov-bundle-check{position:absolute;top:1rem;inset-inline-end:1rem}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mainImage = document.getElementById('mainProductImage');
    document.querySelectorAll('[data-gallery-thumb]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!mainImage) return;
            mainImage.src = button.getAttribute('data-image');
            document.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
                thumb.classList.remove('is-active');
            });
            button.classList.add('is-active');
        });
    });

    const qtyInput = document.getElementById('productQtyInput');
    const bundleQtyInput = document.getElementById('bundleQuantityInput');
    const bundleVariantInput = document.getElementById('bundleVariantId');
    const bundleMainPrice = document.getElementById('bundleMainPrice');
    const bundleMainVariantLabel = document.getElementById('bundleMainVariantLabel');
    const bundleTotalPrice = document.getElementById('bundleTotalPrice');
    const bundleSelectedCount = document.getElementById('bundleSelectedCount');
    const bundleCheckboxes = document.querySelectorAll('[data-bundle-checkbox]');

    function updateBundleSummary(currentUnitPrice, optionLabel) {
        if (!bundleTotalPrice || !bundleSelectedCount) return;
        let total = Number(currentUnitPrice || 0);
        let count = 1;

        bundleCheckboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                total += Number(checkbox.getAttribute('data-price') || 0);
                count += 1;
            }
        });

        bundleSelectedCount.textContent = count;
        bundleTotalPrice.textContent = 'EGP ' + total.toFixed(2);
        if (bundleMainPrice) bundleMainPrice.textContent = 'EGP ' + Number(currentUnitPrice || 0).toFixed(2);
        if (bundleMainVariantLabel && optionLabel) bundleMainVariantLabel.textContent = optionLabel;
    }

    if (qtyInput && bundleQtyInput) {
        bundleQtyInput.value = qtyInput.value || 1;
    }

    bundleCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const selectedOption = document.querySelector('#productVariantSelect option:checked');
            updateBundleSummary(Number(selectedOption?.getAttribute('data-price') || {{ number_format((float) ($defaultVariant?->current_price ?? $currentPrice), 2, '.', '') }}), selectedOption?.getAttribute('data-label') || '{{ addslashes($defaultVariant?->variant_name ?: __('Selected main option')) }}');
        });
    });

    document.querySelectorAll('[data-qty-step]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!qtyInput) return;
            const max = parseInt(qtyInput.getAttribute('max') || '999', 10);
            const current = parseInt(qtyInput.value || '1', 10);
            const next = button.getAttribute('data-qty-step') === 'down' ? Math.max(1, current - 1) : Math.min(max, current + 1);
            qtyInput.value = next;
            if (bundleQtyInput) bundleQtyInput.value = next;
        });
    });

    const variantSelect = document.getElementById('productVariantSelect');
    const variantButtons = document.querySelectorAll('[data-variant-button]');
    const mainPrice = document.getElementById('productMainPrice');
    const stockBanner = document.getElementById('productStockBanner');
    const stockHeadline = document.getElementById('productStockHeadline');
    const stockMeta = document.getElementById('productStockMeta');
    const selectedVariantLabel = document.getElementById('selectedVariantLabel');
    const quickFactQty = document.getElementById('quickFactQty');
    const quickFactAvailability = document.getElementById('quickFactAvailability');
    const addToCartButton = document.getElementById('addToCartButton');
    const buyNowButton = document.getElementById('buyNowButton');
    const mobileStickyPrice = document.getElementById('mobileStickyPrice');
    const desktopStickyPrice = document.getElementById('desktopStickyPrice');
    const desktopStickyVariant = document.getElementById('desktopStickyVariant');

    const formatPrice = function (value) {
        return 'EGP ' + Number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };

    const syncVariantMeta = function () {
        if (!variantSelect) return;
        const option = variantSelect.options[variantSelect.selectedIndex];
        if (!option) return;

        const optionPrice = option.getAttribute('data-price') || '0';
        const optionStock = parseInt(option.getAttribute('data-stock') || '0', 10);
        const optionLabel = option.getAttribute('data-label') || option.textContent.trim();

        if (mainPrice) mainPrice.textContent = formatPrice(optionPrice);
        if (mobileStickyPrice) mobileStickyPrice.textContent = formatPrice(optionPrice);
        if (desktopStickyPrice) desktopStickyPrice.textContent = formatPrice(optionPrice);
        if (desktopStickyVariant) desktopStickyVariant.textContent = optionLabel;
        if (selectedVariantLabel) selectedVariantLabel.textContent = optionLabel;
        if (bundleVariantInput) bundleVariantInput.value = option.value;
        updateBundleSummary(optionPrice, optionLabel);
        if (quickFactQty) quickFactQty.textContent = optionStock > 0 ? optionStock : '{{ __('N/A') }}';
        if (quickFactAvailability) quickFactAvailability.textContent = optionStock > 0 ? '{{ __('Available') }}' : '{{ __('Unavailable') }}';

        if (qtyInput) {
            const safeMax = Math.max(1, optionStock || 1);
            qtyInput.setAttribute('max', String(safeMax));
            if (parseInt(qtyInput.value || '1', 10) > safeMax) {
                qtyInput.value = safeMax;
            }
        }

        if (stockBanner && stockHeadline && stockMeta) {
            stockBanner.classList.remove('is-success', 'is-warning', 'is-muted');
            if (optionStock < 1) {
                stockBanner.classList.add('is-muted');
                stockHeadline.textContent = '{{ __('Selected option is out of stock') }}';
                stockMeta.textContent = '{{ __('Pick another option to continue with add to cart or buy now.') }}';
            } else if (optionStock <= 5) {
                stockBanner.classList.add('is-warning');
                stockHeadline.textContent = '{{ __('Only :count pieces left', ['count' => '__count__']) }}'.replace('__count__', optionStock);
                stockMeta.textContent = '{{ __('Stock shown for the selected option.') }}';
            } else {
                stockBanner.classList.add('is-success');
                stockHeadline.textContent = '{{ __('In stock and ready for checkout') }}';
                stockMeta.textContent = '{{ __('Selected option is available right now and ready for a fast checkout path.') }}';
            }
        }

        [addToCartButton, buyNowButton].forEach(function (button) {
            if (!button) return;
            button.disabled = optionStock < 1;
        });

        variantButtons.forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-variant-id') === option.value);
        });
    };

    variantButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!variantSelect || button.disabled) return;
            variantSelect.value = button.getAttribute('data-variant-id');
            syncVariantMeta();
        });
    });

    if (variantSelect) {
        variantSelect.addEventListener('change', syncVariantMeta);
        syncVariantMeta();
    }

    const desktopStickyBar = document.getElementById('desktopStickyBar');
    const purchaseCard = document.getElementById('productPurchaseCard');
    const desktopStickyAddButton = document.getElementById('desktopStickyAddButton');
    const purchaseForm = document.getElementById('productPurchaseForm');

    if (desktopStickyBar && purchaseCard) {
        const toggleStickyBar = function () {
            const rect = purchaseCard.getBoundingClientRect();
            const shouldShow = rect.bottom < 120 || rect.top > window.innerHeight * 0.4;
            desktopStickyBar.classList.toggle('is-visible', shouldShow);
        };

        window.addEventListener('scroll', toggleStickyBar, {passive: true});
        window.addEventListener('resize', toggleStickyBar);
        toggleStickyBar();
    }

    const initialSelectedOption = document.querySelector('#productVariantSelect option:checked');
    updateBundleSummary(Number(initialSelectedOption?.getAttribute('data-price') || {{ number_format((float) ($defaultVariant?->current_price ?? $currentPrice), 2, '.', '') }}), initialSelectedOption?.getAttribute('data-label') || '{{ addslashes($defaultVariant?->variant_name ?: __('Selected main option')) }}');

    if (desktopStickyAddButton && purchaseForm) {
        desktopStickyAddButton.addEventListener('click', function () {
            purchaseForm.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    }
});
</script>
@endpush
