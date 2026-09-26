@php
    $products = $products ?? collect();
    $title = $title ?? __('Recommended for you');
    $subtitle = $subtitle ?? __('Selected products');
    $description = $description ?? __('A focused selection to help you continue shopping without searching from the beginning.');
    $categoryLink = \App\Support\StorefrontNavigation::links($storeSettings ?? [], false)['categories'];
    $actionLink = $actionLink ?? $categoryLink ?? route('frontend.search');
    $actionText = $actionText ?? ($categoryLink ? __('Browse categories') : __('Browse products'));
@endphp

@if($products->isNotEmpty())
<section class="lc-home-section personalized-strip-section">
    <div class="container">
        <div class="lc-section-head lc-section-head--split-lg">
            <div class="lc-section-head__copy">
                <span class="lc-section-kicker">{{ $subtitle }}</span>
                <h2 class="lc-section-title mb-1">{{ $title }}</h2>
                <p class="lc-section-description mb-0">{{ $description }}</p>
            </div>
            <a href="{{ $actionLink }}" class="btn lc-btn-soft">{{ $actionText }}</a>
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
