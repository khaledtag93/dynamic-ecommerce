@php
    $products = collect($products ?? []);
    $title = $title ?? __('Recommended for you');
    $subtitle = $subtitle ?? __('Personalized picks');
    $description = $description ?? __('Use recent interest and basket intent to keep the shopper moving without making the experience feel random.');
    $badge = $badge ?? null;
    $actionText = $actionText ?? null;
    $actionLink = $actionLink ?? null;
@endphp

@if($products->isNotEmpty())
<section class="mt-5 personalized-strip-section">
    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-4">
        <div>
            <span class="lc-section-kicker">{{ $subtitle }}</span>
            <h2 class="lc-section-title mb-1">{{ $title }}</h2>
            <p class="text-muted mb-0 personalized-strip-section__description">{{ $description }}</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($badge)
                <span class="lc-badge"><i class="bi bi-magic"></i>{{ $badge }}</span>
            @endif
            @if($actionText && $actionLink)
                <a href="{{ $actionLink }}" class="btn lc-btn-soft">{{ $actionText }}</a>
            @endif
        </div>
    </div>

    <div class="row g-4">
        @foreach($products as $product)
            <div class="col-md-6 col-xl-3">
                @include('frontend.sections.partials.product-card', ['product' => $product])
            </div>
        @endforeach
    </div>
</section>
@endif

@push('styles')
<style>
.personalized-strip-section__description{max-width:62ch}
</style>
@endpush
