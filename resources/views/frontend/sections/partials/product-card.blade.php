@php
    $productUrl = route('frontend.products.show', $product->slug);
    $imageUrl = $product->main_image_url ?: 'https://via.placeholder.com/900x900?text=Product';
    $basePrice = (float) ($product->base_price ?? 0);
    $currentPrice = (float) ($product->current_price ?? 0);
    $salePrice = (float) ($product->sale_price ?? 0);
    $hasDiscount = $salePrice > 0 && $basePrice > 0 && $salePrice < $basePrice;
    $discountPercent = $hasDiscount ? max(1, (int) round((($basePrice - $salePrice) / $basePrice) * 100)) : 0;
    $categoryName = $product->category->name ?? __('General');
    $stockQty = (int) ($product->quantity_value ?? 0);
    $stockText = $product->in_stock ? __('In stock') : __('Out of stock');
    $isLowStock = $product->in_stock && $stockQty > 0 && $stockQty <= max(5, (int) ($product->low_stock_threshold ?? 3));
    $showQuickView = $showQuickView ?? false;
    $productDescription = \Illuminate\Support\Str::limit($product->description ?: __('Clear title, strong price, simple actions, and less friction before checkout.'), 95);
@endphp

<article class="lc-card lc-product-card p-3 p-lg-4 h-100 d-flex flex-column">
    <div class="lc-product-card__media mb-3">
        <a href="{{ $productUrl }}" class="lc-product-thumb-wrap">
            @if($hasDiscount)
                <span class="lc-product-badge"><i class="bi bi-stars"></i> {{ __('Save') }} {{ $discountPercent }}%</span>
            @endif

            @if($isLowStock)
                <span class="lc-product-badge lc-product-badge--secondary"><i class="bi bi-lightning-charge"></i> {{ __('Only :count left', ['count' => $stockQty]) }}</span>
            @endif

            <img src="{{ $imageUrl }}" class="w-100 lc-product-thumb" alt="{{ $product->name }}">
        </a>

        <div class="lc-product-card__quick-meta">
            <span class="lc-product-meta__pill"><i class="bi bi-grid-1x2"></i> {{ $categoryName }}</span>
            <span class="lc-product-meta__pill {{ $product->in_stock ? 'is-success' : 'is-muted' }}">
                <i class="bi {{ $product->in_stock ? 'bi-check-circle' : 'bi-dash-circle' }}"></i> {{ $stockText }}
            </span>
        </div>
    </div>

    <div class="d-flex flex-column flex-grow-1">
        <div class="small text-muted fw-semibold mb-2">{{ __('Ready to order') }}</div>

        <h3 class="h5 fw-bold mb-2 lc-product-card__title">
            <a class="text-dark text-decoration-none" href="{{ $productUrl }}">{{ $product->name }}</a>
        </h3>

        <p class="text-muted small mb-3 lc-product-card__summary">{{ $productDescription }}</p>

        <div class="lc-price-stack mb-2">
            <span class="fw-bold fs-4">EGP {{ number_format($currentPrice, 2) }}</span>
            @if($hasDiscount)
                <span class="lc-price-original">EGP {{ number_format($basePrice, 2) }}</span>
            @endif
        </div>

        <div class="lc-product-selling-points mb-3">
            @if($hasDiscount)
                <span class="lc-product-discount-note">{{ __('Limited-time offer') }}</span>
            @endif
            @if($isLowStock)
                <span class="lc-product-urgency-note">{{ __('Selling fast') }}</span>
            @else
                <span class="lc-product-neutral-note">{{ __('Fast delivery available') }}</span>
            @endif
        </div>

        <div class="lc-product-actions mt-auto">
            <div class="d-grid gap-2">
                <form action="{{ route('cart.store', $product) }}" method="POST">
                    @csrf
                    <button class="btn lc-btn-primary w-100" type="submit" {{ $product->in_stock ? '' : 'disabled' }}>
                        <i class="bi bi-bag-plus me-2"></i>{{ $product->in_stock ? __('Add to cart') : __('Currently unavailable') }}
                    </button>
                </form>

                <div class="d-grid lc-product-secondary-actions">
                    @if($showQuickView)
                        <button
                            type="button"
                            class="btn lc-btn-soft lc-quick-view-trigger"
                            data-bs-toggle="modal"
                            data-bs-target="#quickViewModal"
                            data-product-name="{{ e($product->name) }}"
                            data-product-url="{{ $productUrl }}"
                            data-image-url="{{ $imageUrl }}"
                            data-description="{{ e($product->description ?: __('This product page is ready for stronger sales copy, benefits, and proof.')) }}"
                            data-category="{{ e($categoryName) }}"
                            data-price="EGP {{ number_format($currentPrice, 2) }}"
                            data-base-price="{{ $hasDiscount ? 'EGP ' . number_format($basePrice, 2) : '' }}"
                            data-discount="{{ $hasDiscount ? __('Save :percent%', ['percent' => $discountPercent]) : '' }}"
                            data-stock="{{ $product->in_stock ? ($isLowStock ? __('Only :count left', ['count' => $stockQty]) : __('Ready to ship')) : __('Currently unavailable') }}"
                            data-add-to-cart="{{ route('cart.store', $product) }}"
                        >
                            <i class="bi bi-search-heart me-2"></i>{{ __('Quick view') }}
                        </button>
                    @else
                        <a href="{{ $productUrl }}" class="btn lc-btn-soft w-100">
                            <i class="bi bi-eye me-2"></i>{{ __('View details') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</article>

@once
    @push('styles')
    <style>
    .lc-product-mini-benefits,.lc-product-selling-points{display:flex;flex-wrap:wrap;gap:.55rem}
    .lc-product-mini-benefits span,.lc-product-urgency-note,.lc-product-neutral-note{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .7rem;border-radius:999px;background:color-mix(in srgb,var(--lc-soft) 78%, white);font-size:.78rem;font-weight:700;color:var(--lc-primary-dark);border:1px solid color-mix(in srgb,var(--lc-border) 78%, white)}
    .lc-product-badge--secondary{top:auto;bottom:1rem;background:rgba(15,23,42,.86)}
    .lc-product-discount-note{display:inline-flex;align-items:center;padding:.35rem .6rem;border-radius:999px;font-size:.75rem;font-weight:800;color:#166534;background:#ecfdf3;border:1px solid #bbf7d0}
    .lc-product-urgency-note{color:#9a3412;background:#fff7ed;border-color:#fed7aa}
    .lc-product-neutral-note{color:#334155;background:#f8fafc;border-color:#e2e8f0}
    .lc-product-secondary-actions{grid-template-columns:1fr}
    </style>
    @endpush
@endonce
