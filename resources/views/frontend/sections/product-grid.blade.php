@php
    $data = $section['data'] ?? [];
    $products = collect($data['products'] ?? $section['items'] ?? []);
    $title = $data['title'] ?? __('Products');
    $subtitle = $data['subtitle'] ?? __('Shop the collection');
    $empty = $data['empty'] ?? __('No products available yet.');
    $sectionKey = $data['key'] ?? ($section['type'] ?? 'products');
    $actionText = $data['action_text'] ?? __('See more');
    $actionLink = array_key_exists('action_link', $data) ? $data['action_link'] : '#categories';
    $actionLink = $actionLink ? \App\Support\StorefrontNavigation::homeDestination($actionLink, collect($homeSections ?? [])) : null;
    $sectionType = $section['type'] ?? '';

    $sectionMeta = match($sectionType) {
        'featured_products' => [__('Featured picks'), __('Selected products worth checking today.'), 'bi-stars', 'retail-products-v5--featured'],
        'manual_featured_products' => [__('Recommended now'), __('Store-team picks for faster shopping.'), 'bi-patch-check', 'retail-products-v5--featured'],
        'latest_products' => [__('New in store'), __('Fresh products just added to the store.'), 'bi-lightning-charge', 'retail-products-v5--fresh'],
        'best_sellers' => [__('Best sellers'), __('Popular choices customers keep ordering.'), 'bi-fire', 'retail-products-v5--hot'],
        'on_sale_products' => [__('Today deals'), __('Clear price drops and current offers.'), 'bi-tags', 'retail-products-v5--deals'],
        default => [__('Shop now'), __('Browse products with clear prices and fast actions.'), 'bi-grid', 'retail-products-v5--default'],
    };

    [$campaignLabel, $sectionNote, $sectionIcon, $sectionTone] = $sectionMeta;
    $previewProducts = $products->take(8);
@endphp

<section id="{{ $sectionKey }}" class="retail-products-v5 {{ $sectionTone }}">
    <div class="container">
        <div class="retail-products-v5__panel">
            <div class="retail-products-v5__head">
                <div class="retail-products-v5__copy">
                    <span class="retail-products-v5__kicker">
                        <i class="bi {{ $sectionIcon }}"></i>
                        <span>{{ $campaignLabel }}</span>
                    </span>
                    <h2>{{ $title }}</h2>
                    <p>{{ $subtitle ?: $sectionNote }}</p>
                </div>

                @if(!empty($actionText) && !empty($actionLink))
                    <a class="retail-products-v5__action" href="{{ $actionLink }}">
                        <span>{{ $actionText }}</span>
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                @endif
            </div>

            @if($products->isNotEmpty())
                <div class="retail-products-v5__campaign">
                    <div>
                        <span>{{ $campaignLabel }}</span>
                        <strong>{{ $sectionNote }}</strong>
                    </div>
                    @if(!empty($actionLink))
                        <a href="{{ $actionLink }}">{{ __('Explore') }} <i class="bi bi-arrow-up-right"></i></a>
                    @endif
                </div>

                <div class="retail-products-v5__grid">
                    @foreach($previewProducts as $product)
                        @include('frontend.sections.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            @else
                <div class="lc-section-empty">
                    <div class="lc-section-empty__icon"><i class="bi {{ $sectionIcon }}"></i></div>
                    <h3 class="h5 fw-bold mb-2">{{ $empty }}</h3>
                    <p class="text-muted mb-0">{{ __('Add products from the admin panel to show them here.') }}</p>
                </div>
            @endif
        </div>
    </div>
</section>

@once
    @push('styles')
    <style>
    .retail-products-v5{position:relative;padding:clamp(3rem,5vw,5.6rem) 0;background:#fff}.retail-products-v5:nth-of-type(even){background:linear-gradient(180deg,#fff,#f8fafc,#fff)}
    .retail-products-v5__panel{position:relative;overflow:hidden;border-radius:34px;background:linear-gradient(180deg,#fff,#f8fafc);border:1px solid rgba(226,232,240,.86);box-shadow:0 24px 80px rgba(15,23,42,.075);padding:clamp(1rem,2vw,1.45rem)}
    .retail-products-v5__head{display:flex;align-items:flex-end;justify-content:space-between;gap:1.2rem;padding:.3rem .35rem 1.1rem}.retail-products-v5__copy{max-width:760px}.retail-products-v5__kicker{display:inline-flex;align-items:center;gap:.55rem;margin-bottom:.55rem;color:var(--lc-primary-dark);font-weight:950}.retail-products-v5__kicker i{width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:13px;background:color-mix(in srgb,var(--lc-primary) 11%,#fff);border:1px solid color-mix(in srgb,var(--lc-primary) 20%,#fff)}.retail-products-v5 h2{margin:0;color:#0f172a;font-size:clamp(1.65rem,2.6vw,2.65rem);font-weight:950;line-height:1.08;letter-spacing:-.04em}.retail-products-v5__copy p{margin:.55rem 0 0;color:#64748b;line-height:1.75;font-weight:650}.retail-products-v5__action,.retail-products-v5__campaign a{display:inline-flex;align-items:center;gap:.45rem;border-radius:999px;font-weight:950;white-space:nowrap}.retail-products-v5__action{padding:.78rem 1rem;background:#0f172a;color:#fff;box-shadow:0 14px 32px rgba(15,23,42,.14)}.retail-products-v5__action:hover{color:#fff;background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));transform:translateY(-1px)}
    .retail-products-v5__campaign{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;padding:1rem 1.15rem;border-radius:26px;color:#fff;background:radial-gradient(circle at 18% 20%,color-mix(in srgb,var(--lc-primary) 45%,transparent),transparent 30%),linear-gradient(135deg,#0f172a,#111827);overflow:hidden;isolation:isolate}.retail-products-v5__campaign span{display:inline-flex;margin-bottom:.22rem;color:rgba(255,255,255,.72);font-weight:850;font-size:.85rem}.retail-products-v5__campaign strong{display:block;font-size:clamp(1rem,1.6vw,1.35rem);font-weight:950;line-height:1.35}.retail-products-v5__campaign a{padding:.65rem .85rem;background:#fff;color:#0f172a}.retail-products-v5__campaign a:hover{color:#0f172a;transform:translateY(-1px)}
    .retail-products-v5__grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.retail-products-v5--deals .retail-products-v5__campaign{background:radial-gradient(circle at 18% 20%,rgba(251,146,60,.55),transparent 30%),linear-gradient(135deg,#7c2d12,#111827)}.retail-products-v5--hot .retail-products-v5__campaign{background:radial-gradient(circle at 18% 20%,rgba(248,113,113,.52),transparent 30%),linear-gradient(135deg,#450a0a,#111827)}.retail-products-v5--fresh .retail-products-v5__campaign{background:radial-gradient(circle at 18% 20%,rgba(96,165,250,.5),transparent 30%),linear-gradient(135deg,#172554,#111827)}
    [dir="rtl"] .retail-products-v5__action i,[dir="rtl"] .retail-products-v5__campaign a i{transform:scaleX(-1)}@media(max-width:1199.98px){.retail-products-v5__grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:991.98px){.retail-products-v5__head{align-items:flex-start;flex-direction:column}.retail-products-v5__grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:575.98px){.retail-products-v5{padding:2.4rem 0}.retail-products-v5__panel{border-radius:24px}.retail-products-v5__campaign{align-items:flex-start;flex-direction:column}.retail-products-v5__grid{grid-template-columns:1fr}}
    </style>
    @endpush
@endonce
