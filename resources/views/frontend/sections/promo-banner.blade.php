@php
    $data = $section['data'] ?? [];
@endphp

<section id="promo-banner" class="py-5 lc-home-section">
    <div class="container">
        <div class="lc-card p-4 p-lg-5" style="background:linear-gradient(135deg, color-mix(in srgb, var(--lc-soft) 82%, white), color-mix(in srgb, var(--lc-muted-bg, #fff1f2) 80%, white));">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="lc-badge mb-3"><i class="bi bi-lightning-charge"></i> {{ __('Special offer') }}</span>
                    <h2 class="lc-section-title mb-3">{{ $data['title'] ?? __('Upgrade your tech setup today.') }}</h2>
                    <p class="lead text-muted mb-0">{{ $data['subtitle'] ?? __('Discover practical deals on phones, laptops, gaming, audio, TVs, and accessories.') }}</p>
                </div>
                <div class="col-lg-4">
                    <div class="d-grid gap-2">
                        <a href="{{ $data['button_link'] ?? '#featured-products' }}" class="btn lc-btn-primary">{{ $data['button_text'] ?? __('Start shopping') }}</a>
                        <a href="{{ $data['secondary_button_link'] ?? '#categories' }}" class="btn lc-btn-soft">{{ $data['secondary_button_text'] ?? __('Browse categories') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
