@php
    $data = $section['data'] ?? [];
    $banners = collect($data['banners'] ?? []);
    $title = $data['title'] ?? __('Featured deals');
    $subtitle = $data['subtitle'] ?? __('Editable promo banners');
@endphp

@if($banners->isNotEmpty())
<section id="promo-banners" class="lc-home-section lc-home-section--muted">
    <div class="container">
        <div class="lc-section-shell">
            <div class="lc-section-head">
                <div class="lc-section-head__copy">
                    <span class="lc-section-kicker">{{ $subtitle }}</span>
                    <h2 class="lc-section-title">{{ $title }}</h2>
                    <p class="lc-section-description">{{ __('Use these cards for campaigns, seasonal collections, and quick visual entrances into the catalog.') }}</p>
                </div>
            </div>

            <div class="lc-grid lc-grid-promos">
                @foreach($banners as $banner)
                    <article class="lc-card h-100 overflow-hidden promo-banner-card">
                        <div class="promo-banner-card__media">
                            @if(!empty($banner['image_url']))
                                <img src="{{ $banner['image_url'] }}" alt="{{ $banner['title'] ?? __('Promo banner') }}" class="w-100 promo-banner-card__image">
                            @else
                                <div class="promo-banner-card__placeholder d-flex align-items-center justify-content-center">
                                    <span class="lc-badge"><i class="bi bi-stars"></i> {{ __('Promo banner') }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="p-4 d-flex flex-column h-100">
                            <div class="small text-uppercase fw-bold text-muted mb-2">{{ __('Homepage campaign') }}</div>
                            <h3 class="h4 fw-bold mb-2">{{ $banner['title'] ?? __('Promo banner') }}</h3>
                            <p class="text-muted mb-4">{{ $banner['subtitle'] ?? '' }}</p>
                            @if(!empty($banner['button_text']))
                                <div class="mt-auto">
                                    <a href="{{ $banner['button_link'] ?? '#featured-products' }}" class="btn lc-btn-primary w-100">{{ $banner['button_text'] }}</a>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>

@push('styles')
<style>
.promo-banner-card{position:relative;isolation:isolate}.promo-banner-card__media{padding:1rem 1rem 0}.promo-banner-card__image,.promo-banner-card__placeholder{border-radius:calc(var(--lc-card-radius) - 6px)}.promo-banner-card__image{height:240px;object-fit:cover}.promo-banner-card__placeholder{height:240px;background:linear-gradient(135deg,color-mix(in srgb,var(--lc-soft) 80%, white),color-mix(in srgb,var(--lc-bg) 92%, white));border:1px dashed color-mix(in srgb,var(--lc-border) 78%, white)}
</style>
@endpush
@endif
