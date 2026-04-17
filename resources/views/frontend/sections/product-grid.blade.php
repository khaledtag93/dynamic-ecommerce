@php
    $data = $section['data'] ?? [];
    $products = $data['products'] ?? collect();
    $title = $data['title'] ?? __('Products');
    $subtitle = $data['subtitle'] ?? __('Collection');
    $anchor = $data['key'] ?? 'products';
    $empty = $data['empty'] ?? __('No products available yet.');
    $actionText = $data['action_text'] ?? null;
    $actionLink = $data['action_link'] ?? null;
    $sectionType = $section['type'] ?? null;
    $sectionTone = in_array($sectionType, ['best_sellers', 'on_sale_products'], true) ? ' lc-home-section--muted' : '';
    $sectionNote = match ($sectionType) {
        'featured_products' => __('Strong first picks that help customers start shopping with confidence.'),
        'latest_products' => __('Fresh additions that give returning shoppers a reason to keep checking the store.'),
        'best_sellers' => __('Social proof matters. These products already have stronger demand signals.'),
        'on_sale_products' => __('Make the offer obvious and reduce hesitation with clear price positioning.'),
        default => __('Clean product presentation helps shoppers compare faster and move toward checkout.'),
    };
@endphp

<section id="{{ $anchor }}" class="lc-home-section{{ $sectionTone }}">
    <div class="container">
        <div class="lc-section-shell lc-section-shell--spacious">
            <div class="lc-section-head lc-section-head--split-lg align-items-end">
                <div class="lc-section-head__copy">
                    <span class="lc-section-kicker">{{ $subtitle }}</span>
                    <h2 class="lc-section-title">{{ $title }}</h2>
                    <p class="lc-section-description mb-0">{{ $sectionNote }}</p>
                </div>

                <div class="lc-section-head__aside">
                    <div class="lc-inline-stat-card">
                        <div class="lc-inline-stat-card__value">{{ $products->count() }}</div>
                        <div class="lc-inline-stat-card__label">{{ __('visible picks') }}</div>
                    </div>

                    @if($actionText && $actionLink)
                        <a href="{{ $actionLink }}" class="btn lc-btn-soft">
                            <i class="bi bi-arrow-up-right me-2"></i>{{ $actionText }}
                        </a>
                    @endif
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
                    <div class="lc-section-empty__icon"><i class="bi bi-grid"></i></div>
                    <h3 class="h5 fw-bold mb-2">{{ $title }}</h3>
                    <p class="text-muted mb-0">{{ $empty }}</p>
                </div>
            @endif
        </div>
    </div>
</section>

@push('styles')
<style>
.lc-section-shell--spacious{padding:clamp(1.25rem,2vw,1.6rem)}
.lc-section-head--split-lg{display:flex;gap:1rem;justify-content:space-between}
.lc-section-head__aside{display:flex;align-items:center;gap:.85rem;flex-wrap:wrap}
.lc-inline-stat-card{min-width:110px;padding:.9rem 1rem;border-radius:1rem;background:linear-gradient(180deg,color-mix(in srgb,var(--lc-surface) 98%, transparent),color-mix(in srgb,var(--lc-soft) 88%, white));border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);box-shadow:0 14px 30px color-mix(in srgb,var(--lc-primary) 8%, transparent)}
.lc-inline-stat-card__value{font-size:1.35rem;font-weight:800;line-height:1}
.lc-inline-stat-card__label{margin-top:.3rem;font-size:.78rem;font-weight:700;color:var(--lc-muted);text-transform:uppercase;letter-spacing:.06em}
@media (max-width: 767.98px){.lc-section-head--split-lg,.lc-section-head__aside{align-items:stretch}.lc-section-head__aside{width:100%}.lc-inline-stat-card{width:100%}}
</style>
@endpush
