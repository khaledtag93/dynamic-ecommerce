@extends('layouts.app')

@section('title', ($category->name ?? __('Category')) . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
@php
    $categoryImage = $category->image_url ?: 'https://via.placeholder.com/1200x700?text=Category';
    $filters = $filters ?? ['q' => '', 'availability' => 'all', 'offer' => 'all', 'sort' => 'latest'];
    $categoryStats = $categoryStats ?? [
        'total' => $products->total(),
        'in_stock' => $products->count(),
        'on_sale' => 0,
    ];
@endphp

<section class="py-5">
    <div class="container">
        <div class="lc-card category-hero-card overflow-hidden mb-4">
            <div class="row g-0 align-items-center">
                <div class="col-lg-7">
                    <div class="p-4 p-lg-5">
                        <span class="lc-section-kicker mb-3">{{ __('Category collection') }}</span>
                        <h1 class="lc-section-title mb-3">{{ $category->name ?? __('Category Products') }}</h1>
                        <p class="text-muted mb-4">{{ $category->description ?: __('This category page is now focused on conversion: clearer filters, stronger product cards, and faster paths into product details or cart.') }}</p>

                        <div class="category-hero-stats mb-4">
                            <div class="category-hero-stat">
                                <strong>{{ $categoryStats['total'] }}</strong>
                                <span>{{ __('products') }}</span>
                            </div>
                            <div class="category-hero-stat">
                                <strong>{{ $categoryStats['in_stock'] }}</strong>
                                <span>{{ __('available now') }}</span>
                            </div>
                            <div class="category-hero-stat">
                                <strong>{{ $categoryStats['on_sale'] }}</strong>
                                <span>{{ __('on offer') }}</span>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-3">
                            <a href="{{ route('frontend.home') }}#categories" class="btn lc-btn-soft">
                                <i class="bi bi-arrow-left me-2"></i>{{ __('Back to categories') }}
                            </a>
                            <a href="#category-grid" class="btn lc-btn-primary">
                                <i class="bi bi-grid me-2"></i>{{ __('Browse products') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="category-hero-card__media h-100">
                        <img src="{{ $categoryImage }}" alt="{{ $category->name }}" class="img-fluid w-100 h-100 category-hero-card__image">
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 align-items-start" id="category-grid">
            <div class="col-xl-3">
                <div class="lc-card p-4 category-filter-card sticky-xl-top" style="top: 100px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="lc-section-kicker mb-2">{{ __('Filter & sort') }}</span>
                            <h2 class="h4 fw-bold mb-0">{{ __('Help shoppers find the right product fast') }}</h2>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('category.products', $category->id) }}" class="d-grid gap-3">
                        <div>
                            <label class="form-label fw-bold">{{ __('Search inside this category') }}</label>
                            <div class="position-relative">
                                <i class="bi bi-search category-filter-search-icon"></i>
                                <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control lc-form-control ps-5" placeholder="{{ __('Product name or description') }}">
                            </div>
                        </div>

                        <div>
                            <label class="form-label fw-bold">{{ __('Availability') }}</label>
                            <select name="availability" class="form-select lc-form-select">
                                <option value="all" @selected($filters['availability'] === 'all')>{{ __('All products') }}</option>
                                <option value="in_stock" @selected($filters['availability'] === 'in_stock')>{{ __('In stock only') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-bold">{{ __('Offers') }}</label>
                            <select name="offer" class="form-select lc-form-select">
                                <option value="all" @selected($filters['offer'] === 'all')>{{ __('All offers') }}</option>
                                <option value="on_sale" @selected($filters['offer'] === 'on_sale')>{{ __('Discounted only') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-bold">{{ __('Sort by') }}</label>
                            <select name="sort" class="form-select lc-form-select">
                                <option value="latest" @selected($filters['sort'] === 'latest')>{{ __('Newest first') }}</option>
                                <option value="price_low_high" @selected($filters['sort'] === 'price_low_high')>{{ __('Price: low to high') }}</option>
                                <option value="price_high_low" @selected($filters['sort'] === 'price_high_low')>{{ __('Price: high to low') }}</option>
                                <option value="name_az" @selected($filters['sort'] === 'name_az')>{{ __('Name: A to Z') }}</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2 pt-2">
                            <button class="btn lc-btn-primary" type="submit">
                                <i class="bi bi-funnel me-2"></i>{{ __('Apply filters') }}
                            </button>
                            <a href="{{ route('category.products', $category->id) }}" class="btn lc-btn-soft">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>{{ __('Reset') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-xl-9">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <span class="lc-section-kicker">{{ __('Shopping layout') }}</span>
                        <h2 class="lc-section-title mb-0">{{ __('Products in this category') }}</h2>
                    </div>
                    <div class="category-grid-meta">
                        <span><i class="bi bi-stars"></i> {{ __('Clear pricing') }}</span>
                        <span><i class="bi bi-lightning-charge"></i> {{ __('Fast CTA') }}</span>
                        <span><i class="bi bi-search-heart"></i> {{ __('Quick view') }}</span>
                    </div>
                </div>

                <div class="category-results-bar mb-4">
                    <div>
                        <strong>{{ $products->total() }}</strong>
                        <span>{{ __(':count products found', ['count' => $products->total()]) }}</span>
                    </div>
                    @if($filters['q'] || $filters['availability'] !== 'all' || $filters['offer'] !== 'all')
                        <div class="category-results-active-filters">
                            @if($filters['q'])<span>{{ __('Search') }}: {{ $filters['q'] }}</span>@endif
                            @if($filters['availability'] === 'in_stock')<span>{{ __('In stock only') }}</span>@endif
                            @if($filters['offer'] === 'on_sale')<span>{{ __('Discounted only') }}</span>@endif
                        </div>
                    @endif
                </div>

                <div class="row g-4">
                    @forelse($products as $product)
                        <div class="col-md-6 col-xxl-4 d-flex">
                            @include('frontend.sections.partials.product-card', ['product' => $product, 'showQuickView' => true])
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="lc-card p-5 text-center text-muted category-empty-state">
                                <div class="lc-empty-icon mx-auto"><i class="bi bi-search"></i></div>
                                <h3 class="h4 fw-bold mb-2">{{ __('No products matched these filters.') }}</h3>
                                <p class="mb-3">{{ __('Try changing the search, removing some filters, or browsing the full category again.') }}</p>
                                <a href="{{ route('category.products', $category->id) }}" class="btn lc-btn-soft">{{ __('Show all products') }}</a>
                            </div>
                        </div>
                    @endforelse
                </div>

                @if($products->hasPages())
                    <div class="mt-4 category-pagination-wrap">
                        {{ $products->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content quick-view-modal-content border-0">
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-5">
                        <div class="quick-view-media-wrap h-100">
                            <img src="https://via.placeholder.com/900x900?text=Product" alt="" id="quickViewImage" class="quick-view-image w-100 h-100">
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="p-4 p-lg-5">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <span class="lc-badge mb-2" id="quickViewCategory">{{ __('Category') }}</span>
                                    <h3 class="h3 fw-bold mb-2" id="quickViewName">{{ __('Product') }}</h3>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                                <strong class="fs-3" id="quickViewPrice">EGP 0.00</strong>
                                <span class="lc-price-original" id="quickViewBasePrice"></span>
                                <span class="lc-product-discount-note" id="quickViewDiscount" style="display:none;"></span>
                            </div>

                            <div class="quick-view-stock mb-3" id="quickViewStock">{{ __('Ready to ship') }}</div>
                            <p class="text-muted mb-4" id="quickViewDescription">{{ __('Short overview') }}</p>

                            <form method="POST" action="#" id="quickViewCartForm" class="d-grid gap-2">
                                @csrf
                                <button type="submit" class="btn lc-btn-primary">
                                    <i class="bi bi-bag-plus me-2"></i>{{ __('Add to cart') }}
                                </button>
                                <button type="submit" name="redirect_to" value="checkout" class="btn lc-btn-soft">
                                    <i class="bi bi-lightning-charge-fill me-2"></i>{{ __('Buy now') }}
                                </button>
                                <a href="#" class="btn lc-btn-soft" id="quickViewOpenProduct">
                                    <i class="bi bi-eye me-2"></i>{{ __('Open full product page') }}
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.category-hero-card{background:linear-gradient(135deg,color-mix(in srgb,var(--lc-surface) 98%, transparent),color-mix(in srgb,var(--lc-soft) 86%, white))}
.category-hero-card__media{min-height:100%}.category-hero-card__image{object-fit:cover;min-height:340px}
.category-hero-stats{display:flex;flex-wrap:wrap;gap:.9rem}.category-hero-stat{min-width:120px;padding:1rem;border-radius:1rem;background:#fff;border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);box-shadow:0 12px 26px color-mix(in srgb,var(--lc-primary) 8%, transparent)}.category-hero-stat strong{display:block;font-size:1.45rem;line-height:1}.category-hero-stat span{display:block;margin-top:.35rem;color:var(--lc-muted);font-weight:700;font-size:.82rem;text-transform:uppercase;letter-spacing:.05em}
.category-grid-meta,.category-results-active-filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.category-grid-meta span,.category-results-active-filters span,.category-results-bar{display:inline-flex;align-items:center;gap:.45rem;padding:.6rem .9rem;border-radius:999px;background:color-mix(in srgb,var(--lc-soft) 72%, white);border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);font-weight:700;color:var(--lc-primary-dark)}
.category-results-bar{justify-content:space-between;display:flex;flex-wrap:wrap}
.category-filter-card{background:linear-gradient(180deg,color-mix(in srgb,var(--lc-surface) 98%, transparent),color-mix(in srgb,var(--lc-soft) 82%, white))}
.category-filter-search-icon{position:absolute;top:50%;transform:translateY(-50%);left:1rem;color:var(--lc-muted)}
body[dir="rtl"] .category-filter-search-icon{left:auto;right:1rem}
.category-empty-state .lc-empty-icon{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--lc-soft) 82%, white);font-size:2rem;color:var(--lc-primary-dark);margin-bottom:1rem}
.category-pagination-wrap nav{display:flex;justify-content:center}.category-pagination-wrap .pagination{gap:.45rem;flex-wrap:wrap}.category-pagination-wrap .page-link{border:none;border-radius:.9rem;padding:.72rem .95rem;color:var(--lc-primary-dark);background:color-mix(in srgb,var(--lc-soft) 72%, white);font-weight:800;box-shadow:none}.category-pagination-wrap .page-item.active .page-link{background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff}
.quick-view-modal-content{border-radius:1.5rem;overflow:hidden}.quick-view-media-wrap{background:linear-gradient(180deg,color-mix(in srgb,var(--lc-soft) 78%, white),color-mix(in srgb,var(--lc-surface) 96%, transparent));min-height:100%}.quick-view-image{object-fit:cover;min-height:360px}.quick-view-stock{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem .85rem;border-radius:999px;background:#ecfdf3;border:1px solid #bbf7d0;font-weight:800;color:#166534}
@media (max-width: 1199.98px){.sticky-xl-top{position:static!important;top:auto!important}}
@media (max-width: 991.98px){.category-hero-card__image{min-height:240px}.category-results-bar{border-radius:1.2rem}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const quickViewModal = document.getElementById('quickViewModal');
    if (!quickViewModal) return;

    const elements = {
        image: document.getElementById('quickViewImage'),
        category: document.getElementById('quickViewCategory'),
        name: document.getElementById('quickViewName'),
        price: document.getElementById('quickViewPrice'),
        basePrice: document.getElementById('quickViewBasePrice'),
        discount: document.getElementById('quickViewDiscount'),
        stock: document.getElementById('quickViewStock'),
        description: document.getElementById('quickViewDescription'),
        cartForm: document.getElementById('quickViewCartForm'),
        openProduct: document.getElementById('quickViewOpenProduct')
    };

    document.querySelectorAll('.lc-quick-view-trigger').forEach(function (button) {
        button.addEventListener('click', function () {
            elements.image.src = button.getAttribute('data-image-url') || elements.image.src;
            elements.category.textContent = button.getAttribute('data-category') || '';
            elements.name.textContent = button.getAttribute('data-product-name') || '';
            elements.price.textContent = button.getAttribute('data-price') || '';
            elements.basePrice.textContent = button.getAttribute('data-base-price') || '';
            elements.description.textContent = button.getAttribute('data-description') || '';
            elements.stock.textContent = button.getAttribute('data-stock') || '';
            elements.cartForm.action = button.getAttribute('data-add-to-cart') || '#';
            elements.openProduct.href = button.getAttribute('data-product-url') || '#';

            const discount = button.getAttribute('data-discount') || '';
            elements.discount.textContent = discount;
            elements.discount.style.display = discount ? 'inline-flex' : 'none';
        });
    });
});
</script>
@endpush
