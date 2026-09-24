@extends('layouts.app')

@section('title', ($filters['q'] ? __('Search results for :query', ['query' => $filters['q']]) : __('Browse products')) . ' | ' . ($storeSettings['store_name'] ?? 'Storefront'))

@section('content')
<section class="py-5 storefront-search-page">
    <div class="container">
        <x-frontend.page-hero
            :eyebrow="__('Product search')"
            :title="$filters['q'] ? __('Search results for :query', ['query' => $filters['q']]) : __('Browse products')"
            :description="__('Search the full catalog, then narrow results by availability, offers, or price order.')"
            class="p-4 p-lg-5 mb-4"
        >
            <a href="{{ route('frontend.home') }}" class="btn lc-btn-soft">
                <i class="bi bi-arrow-left me-2"></i>{{ __('Back to home') }}
            </a>
        </x-frontend.page-hero>

        <div class="lc-card p-3 p-lg-4 mb-4 storefront-search-filters">
            <form method="GET" action="{{ route('frontend.search') }}" class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label fw-bold" for="catalogSearch">{{ __('Search products') }}</label>
                    <div class="position-relative">
                        <i class="bi bi-search storefront-search-icon"></i>
                        <input
                            id="catalogSearch"
                            type="search"
                            name="q"
                            value="{{ $filters['q'] }}"
                            class="form-control lc-form-control ps-5"
                            placeholder="{{ __('Product name, description, or keyword') }}"
                        >
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label class="form-label fw-bold">{{ __('Availability') }}</label>
                    <select name="availability" class="form-select lc-form-select">
                        <option value="all" @selected($filters['availability'] === 'all')>{{ __('All products') }}</option>
                        <option value="in_stock" @selected($filters['availability'] === 'in_stock')>{{ __('In stock only') }}</option>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label class="form-label fw-bold">{{ __('Offers') }}</label>
                    <select name="offer" class="form-select lc-form-select">
                        <option value="all" @selected($filters['offer'] === 'all')>{{ __('All offers') }}</option>
                        <option value="on_sale" @selected($filters['offer'] === 'on_sale')>{{ __('Discounted only') }}</option>
                    </select>
                </div>

                <div class="col-sm-7 col-lg-2">
                    <label class="form-label fw-bold">{{ __('Sort by') }}</label>
                    <select name="sort" class="form-select lc-form-select">
                        <option value="latest" @selected($filters['sort'] === 'latest')>{{ __('Newest first') }}</option>
                        <option value="price_low_high" @selected($filters['sort'] === 'price_low_high')>{{ __('Price: low to high') }}</option>
                        <option value="price_high_low" @selected($filters['sort'] === 'price_high_low')>{{ __('Price: high to low') }}</option>
                        <option value="name_az" @selected($filters['sort'] === 'name_az')>{{ __('Name: A to Z') }}</option>
                    </select>
                </div>

                <div class="col-sm-5 col-lg-1 d-grid">
                    <button type="submit" class="btn lc-btn-primary px-3" aria-label="{{ __('Apply search filters') }}">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="storefront-search-summary mb-4">
            <div>
                <span class="lc-section-kicker mb-2">{{ __('Catalog results') }}</span>
                <h2 class="h3 fw-bold mb-1">
                    {{ __('Products found: :count', ['count' => number_format($products->total())]) }}
                </h2>
                @if($filters['q'])
                    <div class="text-muted">{{ __('Matching “:query” across the visible catalog.', ['query' => $filters['q']]) }}</div>
                @else
                    <div class="text-muted">{{ __('Showing products currently available in the storefront catalog.') }}</div>
                @endif
            </div>

            @if($filters['q'] || $filters['availability'] !== 'all' || $filters['offer'] !== 'all' || $filters['sort'] !== 'latest')
                <a href="{{ route('frontend.search') }}" class="btn lc-btn-soft">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>{{ __('Clear filters') }}
                </a>
            @endif
        </div>

        <div class="row g-4">
            @forelse($products as $product)
                <div class="col-sm-6 col-xl-4 col-xxl-3 d-flex">
                    @include('frontend.sections.partials.product-card', ['product' => $product])
                </div>
            @empty
                <div class="col-12">
                    <div class="lc-card p-5 text-center storefront-search-empty">
                        <div class="lc-section-empty__icon"><i class="bi bi-search"></i></div>
                        <h3 class="h4 fw-bold mt-3 mb-2">{{ __('No products matched your search.') }}</h3>
                        <p class="text-muted mb-4">{{ __('Try a broader keyword, clear the filters, or browse categories from the home page.') }}</p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <a href="{{ route('frontend.search') }}" class="btn lc-btn-soft">{{ __('Clear filters') }}</a>
                            <a href="{{ route('frontend.home') }}#categories" class="btn lc-btn-primary">{{ __('Browse categories') }}</a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        @if($products->hasPages())
            <div class="mt-5 storefront-search-pagination">
                {{ $products->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</section>
@endsection

@push('styles')
<style>
.storefront-search-page{background:linear-gradient(180deg,color-mix(in srgb,var(--lc-soft) 24%,#fff),#fff 28%)}
.storefront-search-filters{background:linear-gradient(180deg,color-mix(in srgb,var(--lc-surface) 98%,transparent),color-mix(in srgb,var(--lc-soft) 45%,var(--lc-surface)))}
.storefront-search-icon{position:absolute;top:50%;transform:translateY(-50%);left:1rem;color:var(--lc-muted);pointer-events:none}
body[dir="rtl"] .storefront-search-icon{left:auto;right:1rem}
.storefront-search-summary{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.storefront-search-empty{border-style:dashed}
.storefront-search-pagination nav{display:flex;justify-content:center}
.storefront-search-pagination .pagination{gap:.45rem;flex-wrap:wrap}
.storefront-search-pagination .page-link{border:none;border-radius:.9rem;padding:.72rem .95rem;color:var(--lc-primary-dark);background:color-mix(in srgb,var(--lc-soft) 72%,#fff);font-weight:800;box-shadow:none}
.storefront-search-pagination .page-item.active .page-link{background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff}
@media(max-width:767.98px){.storefront-search-summary{align-items:flex-start;flex-direction:column}.storefront-search-summary .btn{width:100%}}
</style>
@endpush
