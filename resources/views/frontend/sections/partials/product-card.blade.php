@php
    use Illuminate\Support\Str;

    $productUrl = route('frontend.products.show', $product->slug);
    $imageUrl = $product->main_image_url ?: asset('images/storefront-placeholder.svg');
    $basePrice = (float) ($product->base_price ?? 0);
    $currentPrice = (float) ($product->current_price ?? 0);
    $salePrice = (float) ($product->sale_price ?? 0);
    $hasDiscount = $salePrice > 0 && $basePrice > 0 && $salePrice < $basePrice;
    $discountPercent = $hasDiscount ? max(1, (int) round((($basePrice - $salePrice) / $basePrice) * 100)) : 0;
    $categoryName = $product->category->name ?? '';
    $categoryNameText = trim((string) $categoryName);
    $isDemoCategory = $categoryNameText === '' || Str::contains(Str::lower($categoryNameText), ['demo', 'growth', 'validation']);
    $displayCategoryName = $isDemoCategory ? null : $categoryNameText;
    $stockQty = (int) ($product->quantity_value ?? 0);
    $isLowStock = $product->in_stock && $stockQty > 0 && $stockQty <= max(5, (int) ($product->low_stock_threshold ?? 3));
    $showQuickView = $showQuickView ?? false;
@endphp

<article class="commerce-product-card-v4 h-100">
    <a href="{{ $productUrl }}" class="commerce-product-card-v4__media" aria-label="{{ $product->name }}">
        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" loading="lazy">

        @if($displayCategoryName)
            <span class="commerce-product-card-v4__chip commerce-product-card-v4__chip--category">{{ $displayCategoryName }}</span>
        @endif

        @if($hasDiscount)
            <span class="commerce-product-card-v4__chip commerce-product-card-v4__chip--sale">{{ __('Save :percent%', ['percent' => $discountPercent]) }}</span>
        @elseif($product->in_stock)
            <span class="commerce-product-card-v4__chip commerce-product-card-v4__chip--ready">{{ __('In stock') }}</span>
        @endif
    </a>

    <div class="commerce-product-card-v4__body">
        <div class="commerce-product-card-v4__meta">
            <span>{{ $product->in_stock ? __('Ready to ship') : __('Unavailable') }}</span>
            @if($isLowStock)
                <em>{{ __('Only :count left', ['count' => $stockQty]) }}</em>
            @endif
        </div>

        <h3 class="commerce-product-card-v4__title">
            <a href="{{ $productUrl }}">{{ $product->name }}</a>
        </h3>

        <div class="commerce-product-card-v4__price">
            <strong>EGP {{ number_format($currentPrice, 2) }}</strong>
            @if($hasDiscount)
                <span>EGP {{ number_format($basePrice, 2) }}</span>
            @endif
        </div>

        <div class="commerce-product-card-v4__actions">
            <form action="{{ route('cart.store', $product) }}" method="POST" data-submit-loading data-live-cart-add>
                @csrf
                <button class="commerce-product-card-v4__cart" type="submit" data-loading-text="{{ __('Adding...') }}" {{ $product->in_stock ? '' : 'disabled' }}>
                    <span>{{ $product->in_stock ? __('Add to cart') : __('Unavailable') }}</span>
                    <i class="bi bi-bag-plus"></i>
                </button>
            </form>

            @if($showQuickView)
                <button type="button" class="commerce-product-card-v4__view lc-quick-view-trigger" data-bs-toggle="modal" data-bs-target="#quickViewModal" data-product-name="{{ e($product->name) }}" data-product-url="{{ $productUrl }}" data-image-url="{{ $imageUrl }}" data-description="{{ e($product->description ?: __('Clear details, price, and availability.')) }}" data-category="{{ e($displayCategoryName) }}" data-price="EGP {{ number_format($currentPrice, 2) }}" data-base-price="{{ $hasDiscount ? 'EGP ' . number_format($basePrice, 2) : '' }}" data-discount="{{ $hasDiscount ? __('Save :percent%', ['percent' => $discountPercent]) : '' }}" data-stock="{{ $product->in_stock ? ($isLowStock ? __('Only :count left', ['count' => $stockQty]) : __('Ready to ship')) : __('Currently unavailable') }}" data-add-to-cart="{{ route('cart.store', $product) }}">
                    <i class="bi bi-search"></i>
                </button>
            @else
                <a href="{{ $productUrl }}" class="commerce-product-card-v4__view" aria-label="{{ __('View details') }}">
                    <i class="bi bi-arrow-up-right"></i>
                </a>
            @endif
        </div>
    </div>
</article>

@once
    @push('styles')
    <style>
    .commerce-product-card-v4{position:relative;display:flex;flex-direction:column;overflow:hidden;border-radius:24px;background:var(--lc-surface);border:1px solid color-mix(in srgb,var(--lc-border) 94%,transparent);box-shadow:0 18px 44px rgba(15,23,42,.07);transition:transform .24s ease,box-shadow .24s ease,border-color .24s ease}.commerce-product-card-v4:hover{transform:translateY(-5px);border-color:color-mix(in srgb,var(--lc-primary) 28%,var(--lc-border));box-shadow:0 28px 70px rgba(15,23,42,.13)}
    .commerce-product-card-v4__media{position:relative;display:block;margin:.65rem;border-radius:20px;overflow:hidden;background:linear-gradient(180deg,color-mix(in srgb,var(--lc-soft) 72%,var(--lc-surface)),var(--lc-surface));isolation:isolate}.commerce-product-card-v4__media::after{content:"";position:absolute;inset:auto -18% -45% -18%;height:66%;background:radial-gradient(circle,color-mix(in srgb,var(--lc-primary) 18%,transparent),transparent 64%);z-index:-1}.commerce-product-card-v4__media img{width:100%;aspect-ratio:1.18/1;object-fit:contain;padding:1.1rem;display:block;transition:transform .28s ease}.commerce-product-card-v4:hover .commerce-product-card-v4__media img{transform:scale(1.04)}
    .commerce-product-card-v4__chip{position:absolute;z-index:2;display:inline-flex;align-items:center;max-width:68%;border-radius:999px;padding:.4rem .65rem;font-size:.72rem;font-weight:950;line-height:1;background:color-mix(in srgb,var(--lc-surface) 94%,transparent);border:1px solid color-mix(in srgb,var(--lc-border) 90%,transparent);box-shadow:0 12px 24px rgba(15,23,42,.1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.commerce-product-card-v4__chip--category{inset-block-start:.72rem;inset-inline-start:.72rem;color:var(--lc-primary-dark)}.commerce-product-card-v4__chip--sale{inset-block-start:.72rem;inset-inline-end:.72rem;background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:var(--lc-btn-text);border-color:transparent}.commerce-product-card-v4__chip--ready{inset-block-start:.72rem;inset-inline-end:.72rem;background:color-mix(in srgb,var(--lc-primary) 10%,var(--lc-surface));color:var(--lc-primary-dark);border-color:color-mix(in srgb,var(--lc-primary) 18%,var(--lc-border))}
    .commerce-product-card-v4__body{display:flex;flex-direction:column;gap:.62rem;padding:.35rem 1rem 1rem;flex:1}.commerce-product-card-v4__meta{display:flex;align-items:center;justify-content:space-between;gap:.5rem;color:var(--lc-muted);font-size:.75rem;font-weight:850}.commerce-product-card-v4__meta span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.commerce-product-card-v4__meta em{font-style:normal;flex:0 0 auto;color:var(--lc-primary-dark)}.commerce-product-card-v4__title{margin:0;min-height:2.55rem;font-size:.98rem;font-weight:950;line-height:1.42}.commerce-product-card-v4__title a{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;color:var(--lc-primary-dark)}.commerce-product-card-v4__title a:hover{color:var(--lc-primary-dark)}.commerce-product-card-v4__price{display:flex;align-items:baseline;gap:.42rem;flex-wrap:wrap}.commerce-product-card-v4__price strong{color:var(--lc-primary-dark);font-size:1.12rem;font-weight:950;letter-spacing:-.02em}.commerce-product-card-v4__price span{color:var(--lc-muted);text-decoration:line-through;font-size:.82rem;font-weight:850}
    .commerce-product-card-v4__actions{display:grid;grid-template-columns:minmax(0,1fr) 44px;gap:.55rem;margin-top:auto}.commerce-product-card-v4__actions form{min-width:0}.commerce-product-card-v4__cart,.commerce-product-card-v4__view{height:44px;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-weight:950;transition:.2s ease}.commerce-product-card-v4__cart{width:100%;gap:.45rem;border:0;background:var(--lc-primary-dark);color:var(--lc-btn-text);font-size:.84rem;line-height:1;padding:0 .8rem;white-space:nowrap}.commerce-product-card-v4__cart:hover{background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff}.commerce-product-card-v4__cart:disabled{opacity:.55;cursor:not-allowed}.commerce-product-card-v4__cart span{overflow:hidden;text-overflow:ellipsis}.commerce-product-card-v4__cart i{font-size:.95rem;flex:0 0 auto}.commerce-product-card-v4__view{border:1px solid rgba(148,163,184,.34);background:color-mix(in srgb,var(--lc-soft) 72%,var(--lc-surface));color:var(--lc-primary-dark)}.commerce-product-card-v4__view:hover{background:color-mix(in srgb,var(--lc-soft) 74%,var(--lc-surface));border-color:color-mix(in srgb,var(--lc-primary) 34%,var(--lc-border));color:var(--lc-primary-dark)}[dir="rtl"] .commerce-product-card-v4__view i{transform:scaleX(-1)}@media(max-width:575.98px){.commerce-product-card-v4__media img{aspect-ratio:1.35/1}.commerce-product-card-v4__title{min-height:auto}}
    </style>
    @endpush
@endonce
