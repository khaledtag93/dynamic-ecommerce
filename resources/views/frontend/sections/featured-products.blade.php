@php
    $data = $section['data'] ?? [];
    $products = collect($data['products'] ?? $section['items'] ?? []);
    $title = $data['title'] ?? __('Featured products');
    $subtitle = $data['subtitle'] ?? __('Best places to start');
@endphp

<section id="featured-products" class="lc-home-section">
    <div class="container">
        <div class="lc-section-head lc-section-head--split-lg">
            <div class="lc-section-head__copy">
                <span class="lc-section-kicker">{{ $subtitle }}</span>
                <h2 class="lc-section-title">{{ $title }}</h2>
                <p class="lc-section-description">{{ __('Selected products with clear prices, availability, and quick access to the cart.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="#categories" class="btn lc-btn-soft">{{ __('Browse categories') }}</a>
                <a href="#latest-products" class="btn lc-btn-soft">{{ __('New arrivals') }}</a>
            </div>
        </div>
        @if($products->isNotEmpty())
            <div class="row g-4">
                @foreach($products as $product)
                    <div class="col-sm-6 col-lg-3">@include('frontend.sections.partials.product-card', ['product' => $product])</div>
                @endforeach
            </div>
        @else
            <div class="lc-section-empty"><div class="lc-section-empty__icon"><i class="bi bi-stars"></i></div><h3 class="h5 fw-bold mb-2">{{ __('Featured products are not visible yet') }}</h3><p class="text-muted mb-0">{{ __('Choose featured products from the admin panel to fill this section.') }}</p></div>
        @endif
    </div>
</section>
