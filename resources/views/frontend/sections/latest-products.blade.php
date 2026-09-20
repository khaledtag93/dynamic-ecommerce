@php
    $data = $section['data'] ?? [];
    $products = collect($data['products'] ?? $section['items'] ?? []);
    $title = $data['title'] ?? __('Latest arrivals');
    $subtitle = $data['subtitle'] ?? __('Recently added devices and accessories');
@endphp
<section id="latest-products" class="lc-home-section lc-home-section--muted">
    <div class="container">
        <div class="lc-section-head lc-section-head--split-lg">
            <div class="lc-section-head__copy"><span class="lc-section-kicker">{{ $subtitle }}</span><h2 class="lc-section-title">{{ $title }}</h2><p class="lc-section-description">{{ __('New products recently added to the catalog.') }}</p></div>
            <a href="#featured-products" class="btn lc-btn-soft">{{ __('Back to featured') }}</a>
        </div>
        @if($products->isNotEmpty())
            <div class="row g-4">@foreach($products as $product)<div class="col-sm-6 col-lg-3">@include('frontend.sections.partials.product-card', ['product' => $product])</div>@endforeach</div>
        @else
            <div class="lc-section-empty"><div class="lc-section-empty__icon"><i class="bi bi-lightning-charge"></i></div><h3 class="h5 fw-bold mb-2">{{ __('No recent arrivals yet') }}</h3><p class="text-muted mb-0">{{ __('Publish new products to keep the homepage fresh.') }}</p></div>
        @endif
    </div>
</section>
