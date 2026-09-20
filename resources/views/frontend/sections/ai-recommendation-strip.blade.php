@php
    $products = $products ?? collect();
    $title = $title ?? __('Recommended for you');
    $subtitle = $subtitle ?? __('Picked from the catalog');
    $description = $description ?? __('Products that match popular choices, current offers, and items customers often compare together.');
@endphp

@if($products->isNotEmpty())
<section class="lc-home-section lc-home-section--muted recommendation-section">
    <div class="container">
        <div class="lc-section-head lc-section-head--split-lg">
            <div class="lc-section-head__copy">
                <span class="lc-section-kicker">{{ $subtitle }}</span>
                <h2 class="lc-section-title mb-1">{{ $title }}</h2>
                <p class="lc-section-description mb-0">{{ $description }}</p>
            </div>
            <a href="#featured-products" class="btn lc-btn-soft">{{ __('View more products') }}</a>
        </div>
        <div class="row g-4">
            @foreach($products as $product)
                <div class="col-sm-6 col-lg-3">
                    @include('frontend.sections.partials.product-card', ['product' => $product])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
