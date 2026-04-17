@php
    $data = $section['data'] ?? [];
    $blocks = collect($data['blocks'] ?? []);
    $title = $data['title'] ?? __('Why customers keep coming back');
    $subtitle = $data['subtitle'] ?? __('Trust signals that reduce hesitation');
@endphp

@if($blocks->isNotEmpty())
<section id="trust-blocks" class="lc-home-section">
    <div class="container">
        <div class="lc-section-shell lc-section-shell--spacious">
            <div class="lc-section-head lc-section-head--center">
                <div class="lc-section-head__copy">
                    <span class="lc-section-kicker justify-content-center">{{ $subtitle }}</span>
                    <h2 class="lc-section-title">{{ $title }}</h2>
                    <p class="lc-section-description mx-auto">{{ __('Trust matters in electronics shopping. Clear delivery, warranty, payment, and support details help customers complete checkout with confidence.') }}</p>
                </div>
            </div>

            <div class="row g-4 align-items-stretch">
                <div class="col-lg-4">
                    <div class="lc-card trust-block-feature h-100 p-4 p-lg-5">
                        <span class="lc-badge mb-3"><i class="bi bi-patch-check"></i> {{ __('Why shoppers trust us') }}</span>
                        <h3 class="h3 fw-bold mb-3">{{ __('A strong electronics store answers the final questions before checkout.') }}</h3>
                        <p class="text-muted mb-4">{{ __('When delivery, support, warranty, and payment are clear, shoppers can focus on choosing the right device instead of doubting the store.') }}</p>
                        <div class="trust-block-feature__stack">
                            <div><i class="bi bi-check2-circle"></i>{{ __('Warranty and delivery details stay visible') }}</div>
                            <div><i class="bi bi-check2-circle"></i>{{ __('Clearer wording reduces hesitation') }}</div>
                            <div><i class="bi bi-check2-circle"></i>{{ __('Professional presentation increases confidence') }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="lc-grid lc-grid-trust">
                        @foreach($blocks as $block)
                            <article class="lc-card trust-block-card h-100 p-4 p-lg-4">
                                <div class="trust-block-card__icon mb-3">
                                    <i class="{{ $block['icon'] ?? 'bi bi-stars' }}"></i>
                                </div>
                                <div class="small text-uppercase fw-bold text-muted mb-2">{{ __('Customer promise') }}</div>
                                <h3 class="h5 fw-bold mb-2">{{ $block['title'] ?? __('Trust block') }}</h3>
                                <p class="text-muted mb-0">{{ $block['subtitle'] ?? '' }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('styles')
<style>
.trust-block-card,.trust-block-feature{transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
.trust-block-card:hover,.trust-block-feature:hover{transform:translateY(-4px);border-color:color-mix(in srgb,var(--lc-primary) 24%, white);box-shadow:0 18px 40px color-mix(in srgb,var(--lc-dark) 10%, transparent)}
.trust-block-card__icon{width:76px;height:76px;border-radius:1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.7rem;background:linear-gradient(135deg,color-mix(in srgb,var(--lc-soft) 80%, white),color-mix(in srgb,var(--lc-surface) 96%, transparent));color:var(--lc-primary);border:1px solid color-mix(in srgb,var(--lc-border) 74%, white);box-shadow:0 16px 32px color-mix(in srgb,var(--lc-primary) 10%, transparent)}
.trust-block-feature{background:linear-gradient(180deg,color-mix(in srgb,var(--lc-surface) 98%, transparent),color-mix(in srgb,var(--lc-soft) 88%, white))}
.trust-block-feature__stack{display:grid;gap:.9rem}
.trust-block-feature__stack div{display:flex;gap:.7rem;align-items:flex-start;font-weight:700}
.trust-block-feature__stack i{color:var(--lc-primary-dark);margin-top:.18rem}
</style>
@endpush
@endif
