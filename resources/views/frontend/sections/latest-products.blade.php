@php
    $data = $section['data'] ?? [];
    $products = $data['products'] ?? collect();
    $title = $data['title'] ?? __('Latest arrivals');
    $subtitle = $data['subtitle'] ?? __('Fresh in store');
@endphp

<section id="latest-products" class="lc-home-section lc-home-section--muted">
    <div class="container">
        <div class="lc-section-shell">
            <div class="lc-section-head">
                <div class="lc-section-head__copy">
                    <span class="lc-section-kicker">{{ $subtitle }}</span>
                    <h2 class="lc-section-title">{{ $title }}</h2>
                    <p class="lc-section-description">{{ __('Keep the storefront active with new arrivals that encourage repeat visits and make the catalog feel alive.') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#featured-products" class="btn lc-btn-soft">{{ __('Back to featured') }}</a>
                    <a href="#hero" class="btn lc-btn-soft">{{ __('Back to top') }}</a>
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
                    <div class="lc-section-empty__icon"><i class="bi bi-lightning-charge"></i></div>
                    <h3 class="h5 fw-bold mb-2">{{ __('No recent arrivals yet') }}</h3>
                    <p class="text-muted mb-0">{{ __('Publish new products to keep the homepage fresh and worth revisiting.') }}</p>
                </div>
            @endif
        </div>
    </div>
</section>
