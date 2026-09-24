<div data-live-results aria-busy="false">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <span class="lc-section-kicker">{{ __('Products') }}</span>
        <h2 class="lc-section-title mb-0">{{ __('Products in this category') }}</h2>
    </div>
    <div class="category-grid-meta">
        <span><i class="bi bi-stars"></i> {{ __('Clear prices') }}</span>
        <span><i class="bi bi-lightning-charge"></i> {{ __('Easy ordering') }}</span>
        <span><i class="bi bi-search-heart"></i> {{ __('Quick view') }}</span>
    </div>
</div>

<div class="category-results-bar mb-4">
    <div>
        <strong>{{ $products->total() }}</strong>
        <span>{{ __('Products found') }}</span>
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
                <a href="{{ route('category.products', $category->id) }}" class="btn lc-btn-soft" data-live-reset>{{ __('Show all products') }}</a>
            </div>
        </div>
    @endforelse
</div>

@if($products->hasPages())
    <div class="mt-4 category-pagination-wrap">{{ $products->onEachSide(1)->links() }}</div>
@endif
</div>
