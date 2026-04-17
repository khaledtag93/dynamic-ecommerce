@php
    $data = $section['data'] ?? [];
    $products = $data['products'] ?? collect();
    $title = $data['title'] ?? __('Featured products');
    $subtitle = $data['subtitle'] ?? __('Best places to start');
    $showCategoriesJump = $data['showCategoriesJump'] ?? false;
@endphp

<section id="featured-products" class="lc-home-section">
    <div class="container">
        <div class="lc-section-shell">
            <div class="lc-section-head">
                <div class="lc-section-head__copy">
                    <span class="lc-section-kicker">{{ $subtitle }}</span>
                    <h2 class="lc-section-title">{{ $title }}</h2>
                    <p class="lc-section-description">{{ __('Lead with products that deserve the first click: stronger pricing visibility, cleaner cards, and faster add-to-cart actions.') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if($showCategoriesJump)
                        <a href="#categories" class="btn lc-btn-soft">{{ __('Jump to categories') }}</a>
                    @endif
                    <a href="#latest-products" class="btn lc-btn-soft">{{ __('See latest arrivals') }}</a>
                </div>
            </div>

            @if($products->isNotEmpty())
                <div class="lc-grid lc-grid-products">
                    @foreach($products as $product)
                        @include('frontend.sections.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            @else
                <div class="lc-section-empty">
                    <div class="lc-section-empty__icon"><i class="bi bi-stars"></i></div>
                    <h3 class="h5 fw-bold mb-2">{{ __('Featured products are not visible yet') }}</h3>
                    <p class="text-muted mb-0">{{ __('Add featured products from the admin panel so this section starts selling immediately.') }}</p>
                </div>
            @endif
        </div>
    </div>
</section>
