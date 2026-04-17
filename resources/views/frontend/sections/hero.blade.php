@php
    $data = $section['data'] ?? [];
    $heroTitle = $data['heroTitle'] ?? __('Find the right products faster with a storefront designed to convert browsing into orders.');
    $heroSubtitle = $data['heroSubtitle'] ?? __('Clear sections, stronger product focus, and cleaner calls to action make the homepage feel like a real production store instead of a demo.');
    $heroBadge = $data['heroBadge'] ?? __('Built for selling, not just showing');
    $primaryButtonText = $data['primaryButtonText'] ?? __('Start shopping');
    $primaryButtonLink = $data['primaryButtonLink'] ?? '#featured-products';
    $secondaryButtonText = $data['secondaryButtonText'] ?? __('Browse categories');
    $secondaryButtonLink = $data['secondaryButtonLink'] ?? '#categories';
    $heroBannerUrl = $data['heroBannerUrl'] ?? null;
    $heroStats = $data['heroStats'] ?? [];
    $featuredCount = (int) ($heroStats['featured_products_count'] ?? 0);
    $categoriesCount = (int) ($heroStats['categories_count'] ?? 0);
    $latestCount = (int) ($heroStats['latest_products_count'] ?? 0);
@endphp

<section class="lc-hero lc-home-section" id="hero">
    <div class="container py-lg-4">
        <div class="row align-items-center g-4 g-xl-5">
            <div class="col-lg-6 order-2 order-lg-1">
                <span class="lc-badge mb-3"><i class="bi bi-stars"></i> {{ $heroBadge }}</span>
                <h1 class="display-4 fw-bold mb-3 lc-hero-title">{{ $heroTitle }}</h1>
                <p class="lead text-muted mb-4 lc-hero-subtitle">{{ $heroSubtitle }}</p>

                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="{{ $primaryButtonLink }}" class="btn lc-btn-primary lc-btn-lg">
                        <i class="bi bi-bag-check me-2"></i>{{ $primaryButtonText }}
                    </a>
                    <a href="{{ $secondaryButtonLink }}" class="btn lc-btn-soft lc-btn-lg">
                        <i class="bi bi-grid me-2"></i>{{ $secondaryButtonText }}
                    </a>
                </div>

                <div class="lc-hero-social-proof mb-4">
                    <div class="lc-hero-social-proof__avatars" aria-hidden="true">
                        <span><i class="bi bi-person-fill"></i></span>
                        <span><i class="bi bi-person-fill"></i></span>
                        <span><i class="bi bi-person-fill"></i></span>
                    </div>
                    <div>
                        <div class="fw-bold">{{ __('Clear catalog, faster decisions') }}</div>
                        <div class="text-muted small">{{ __('A cleaner homepage helps shoppers reach the right product without wasting clicks.') }}</div>
                    </div>
                </div>

                <div class="lc-feature-strip">
                    <div class="lc-feature-item">
                        <i class="bi bi-box-seam"></i>
                        <div class="fw-bold mt-2">{{ __('Featured') }}</div>
                        <div class="text-muted small">{{ $featuredCount }} {{ __('products ready') }}</div>
                    </div>
                    <div class="lc-feature-item">
                        <i class="bi bi-grid"></i>
                        <div class="fw-bold mt-2">{{ __('Categories') }}</div>
                        <div class="text-muted small">{{ $categoriesCount }} {{ __('live collections') }}</div>
                    </div>
                    <div class="lc-feature-item">
                        <i class="bi bi-lightning-charge"></i>
                        <div class="fw-bold mt-2">{{ __('New arrivals') }}</div>
                        <div class="text-muted small">{{ $latestCount }} {{ __('fresh picks') }}</div>
                    </div>
                    <div class="lc-feature-item">
                        <i class="bi bi-shield-check"></i>
                        <div class="fw-bold mt-2">{{ __('Store-ready') }}</div>
                        <div class="text-muted small">{{ __('Clean branding, stronger trust, easier discovery.') }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 order-1 order-lg-2">
                <div class="lc-hero-card p-3 p-lg-4">
                    <div class="lc-hero-card__top mb-3">
                        <div>
                            <div class="small text-uppercase fw-bold text-muted mb-2">{{ __('Store snapshot') }}</div>
                            <h2 class="h4 fw-bold mb-0">{{ __('Home page built to move shoppers forward') }}</h2>
                        </div>
                        <span class="lc-badge"><i class="bi bi-patch-check"></i> {{ __('Clean selling UI') }}</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="lc-hero-stat-card">
                                <div class="text-muted small">{{ __('Featured products') }}</div>
                                <div class="fs-3 fw-bold">{{ $featuredCount }}</div>
                                <div class="small text-muted">{{ __('priority offers and top picks') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="lc-hero-stat-card">
                                <div class="text-muted small">{{ __('Visible categories') }}</div>
                                <div class="fs-3 fw-bold">{{ $categoriesCount }}</div>
                                <div class="small text-muted">{{ __('clear paths into the catalog') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="lc-hero-visual position-relative overflow-hidden rounded-4">
                        @if($heroBannerUrl)
                            <img
                                src="{{ $heroBannerUrl }}"
                                class="img-fluid lc-hero-visual__image"
                                alt="{{ ($storeSettings['store_name'] ?? 'Store') . ' ' . __('hero banner') }}"
                            >
                        @else
                            <div class="lc-hero-visual__placeholder">
                                <div class="lc-hero-visual__placeholder-badge lc-badge mb-3"><i class="bi bi-image"></i> {{ __('Brand banner area') }}</div>
                                <h3 class="h4 fw-bold mb-2">{{ __('Add a banner that sells the store in one glance') }}</h3>
                                <p class="text-muted mb-0">{{ __('Upload a hero banner from White-label Settings to complete the first impression.') }}</p>
                            </div>
                        @endif

                        <div class="lc-hero-floating-card lc-hero-floating-card--offer">
                            <div class="small text-muted">{{ __('Conversion focus') }}</div>
                            <div class="fw-bold">{{ __('Stronger CTA + cleaner cards') }}</div>
                        </div>

                        <div class="lc-hero-floating-card lc-hero-floating-card--trust">
                            <div class="small text-muted">{{ __('Customer feel') }}</div>
                            <div class="fw-bold">{{ __('Production-ready storefront') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('styles')
<style>
.lc-btn-lg{padding:.95rem 1.3rem}.lc-hero-title{max-width:12ch}.lc-hero-subtitle{max-width:58ch}.lc-hero-social-proof{display:flex;align-items:center;gap:1rem;padding:1rem 1.1rem;border-radius:1.1rem;background:color-mix(in srgb,var(--lc-surface) 96%, transparent);border:1px solid var(--lc-border);box-shadow:0 14px 30px color-mix(in srgb,var(--lc-primary) 8%, transparent);max-width:640px}.lc-hero-social-proof__avatars{display:flex;align-items:center}.lc-hero-social-proof__avatars span{width:2.4rem;height:2.4rem;margin-inline-end:-.45rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;color:#fff;background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));border:3px solid #fff;box-shadow:0 8px 20px color-mix(in srgb,var(--lc-primary) 15%, transparent)}.lc-hero-card__top{display:flex;align-items:start;justify-content:space-between;gap:1rem}.lc-hero-stat-card{padding:1rem;border-radius:1rem;background:color-mix(in srgb,var(--lc-soft) 78%, white);border:1px solid color-mix(in srgb,var(--lc-border) 75%, white)}.lc-hero-visual{min-height:360px;background:linear-gradient(135deg,color-mix(in srgb,var(--lc-soft) 84%, white),color-mix(in srgb,var(--lc-bg) 92%, white));border:1px solid color-mix(in srgb,var(--lc-border) 72%, white)}.lc-hero-visual__image{width:100%;min-height:360px;object-fit:cover;display:block}.lc-hero-visual__placeholder{min-height:360px;padding:2rem;display:flex;flex-direction:column;justify-content:center;align-items:flex-start}.lc-hero-floating-card{position:absolute;z-index:2;padding:.85rem 1rem;border-radius:1rem;background:rgba(255,255,255,.92);border:1px solid rgba(255,255,255,.65);box-shadow:0 18px 40px rgba(15,23,42,.10);backdrop-filter:blur(8px);max-width:230px}.lc-hero-floating-card--offer{left:1rem;bottom:1rem}.lc-hero-floating-card--trust{right:1rem;top:1rem}.lc-feature-item{box-shadow:0 14px 32px color-mix(in srgb,var(--lc-primary) 8%, transparent)}.lc-product-card__media{position:relative}.lc-product-card__quick-meta{position:absolute;left:1rem;right:1rem;bottom:1rem;display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap}.lc-product-meta__pill.is-success{background:#ecfdf3;color:#166534;border-color:#bbf7d0}.lc-product-meta__pill.is-muted{background:#f8fafc;color:#475569;border-color:#e2e8f0}.lc-product-card__summary{min-height:3.2rem}.lc-product-discount-note{display:inline-flex;align-items:center;padding:.35rem .6rem;border-radius:999px;font-size:.75rem;font-weight:800;color:#166534;background:#ecfdf3;border:1px solid #bbf7d0}.lc-category-card{transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}.lc-category-card:hover{transform:translateY(-4px);box-shadow:0 22px 52px color-mix(in srgb,var(--lc-dark) 12%, transparent);border-color:color-mix(in srgb,var(--lc-primary) 24%, white)}.lc-category-card__media{position:relative}.lc-category-card__image{width:100%;height:230px;object-fit:cover;border-radius:1rem}.lc-category-card__badge{position:absolute;top:1rem;inset-inline-start:1rem;display:inline-flex;align-items:center;justify-content:center;padding:.42rem .72rem;border-radius:999px;font-size:.75rem;font-weight:800;color:#fff;background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));box-shadow:0 12px 24px color-mix(in srgb,var(--lc-primary) 18%, transparent)}@media (max-width:991.98px){.lc-hero-card__top{flex-direction:column}.lc-hero-title{max-width:none}}@media (max-width:767.98px){.lc-hero-social-proof{align-items:flex-start;flex-direction:column}.lc-hero-visual,.lc-hero-visual__image,.lc-hero-visual__placeholder{min-height:280px}.lc-hero-floating-card{position:static;max-width:none;margin:1rem}.lc-product-card__quick-meta{left:.75rem;right:.75rem;bottom:.75rem}}
</style>
@endpush
