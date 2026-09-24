<div data-live-results aria-busy="false">
<div class="storefront-search-summary mb-4">
    <div>
        <span class="lc-section-kicker mb-2">{{ __('Catalog results') }}</span>
        <h2 class="h3 fw-bold mb-1">{{ __('Products found: :count', ['count' => number_format($products->total())]) }}</h2>
        @if($filters['q'])
            <div class="text-muted">{{ __('Matching “:query” across the visible catalog.', ['query' => $filters['q']]) }}</div>
        @else
            <div class="text-muted">{{ __('Showing products currently available in the storefront catalog.') }}</div>
        @endif
    </div>

    @if($filters['q'] || $filters['availability'] !== 'all' || $filters['offer'] !== 'all' || $filters['sort'] !== 'latest')
        <a href="{{ route('frontend.search') }}" class="btn lc-btn-soft" data-live-reset>
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
                    <a href="{{ route('frontend.search') }}" class="btn lc-btn-soft" data-live-reset>{{ __('Clear filters') }}</a>
                    <a href="{{ route('frontend.home') }}#categories" class="btn lc-btn-primary">{{ __('Browse categories') }}</a>
                </div>
            </div>
        </div>
    @endforelse
</div>

@if($products->hasPages())
    <div class="mt-5 storefront-search-pagination">{{ $products->onEachSide(1)->links() }}</div>
@endif
</div>
