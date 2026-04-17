@php
    $products = collect($products ?? []);
    $title = $title ?? __('AI recommendations');
    $subtitle = $subtitle ?? __('Prediction layer');
    $description = $description ?? __('This layer scores products using current-session behavior, basket compatibility, and commercial strength.');
    $insight = $insight ?? null;
    $badge = $badge ?? __('AI ranked');
@endphp

@if($products->isNotEmpty())
<section class="mt-5 ai-recommendation-strip">
    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-4">
        <div>
            <span class="lc-section-kicker">{{ $subtitle }}</span>
            <h2 class="lc-section-title mb-1">{{ $title }}</h2>
            <p class="text-muted mb-0 ai-recommendation-strip__description">{{ $description }}</p>
        </div>
        <span class="lc-badge"><i class="bi bi-cpu"></i>{{ $badge }}</span>
    </div>

    @if($insight)
        <div class="ai-recommendation-strip__insight mb-4">
            <i class="bi bi-stars"></i>
            <span>{{ $insight }}</span>
        </div>
    @endif

    <div class="row g-4">
        @foreach($products as $product)
            <div class="col-md-6 col-xl-3">
                <div class="ai-recommendation-shell h-100">
                    <div class="ai-recommendation-shell__meta">
                        <span class="ai-recommendation-shell__chip">{{ $product->ai_reason_chip ?? __('Smart pick') }}</span>
                        <span class="ai-recommendation-shell__confidence">{{ $product->ai_confidence ?? 80 }}% {{ __('fit') }}</span>
                    </div>
                    <div class="ai-recommendation-shell__headline">{{ $product->ai_reason ?? __('AI ranked this highly for the current session.') }}</div>
                    <div class="ai-recommendation-shell__detail">{{ $product->ai_reason_detail ?? __('Strong overlap between visitor intent and product signals.') }}</div>
                    @include('frontend.sections.partials.product-card', ['product' => $product])
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

@push('styles')
<style>
.ai-recommendation-strip__description{max-width:66ch}.ai-recommendation-strip__insight{display:flex;align-items:flex-start;gap:.7rem;padding:1rem 1.1rem;border-radius:1rem;background:linear-gradient(180deg,rgba(255,255,255,.96),color-mix(in srgb,var(--lc-soft) 78%, white));border:1px solid color-mix(in srgb,var(--lc-border) 80%, white);color:var(--lc-muted)}.ai-recommendation-strip__insight i{color:var(--lc-primary-dark);margin-top:.1rem}.ai-recommendation-shell{display:grid;gap:.9rem}.ai-recommendation-shell__meta{display:flex;align-items:center;justify-content:space-between;gap:.65rem;flex-wrap:wrap}.ai-recommendation-shell__chip{display:inline-flex;align-items:center;padding:.4rem .7rem;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.76rem;font-weight:800}.ai-recommendation-shell__confidence{font-size:.8rem;font-weight:800;color:var(--lc-primary-dark)}.ai-recommendation-shell__headline{font-weight:800;color:var(--lc-text)}.ai-recommendation-shell__detail{font-size:.92rem;color:var(--lc-muted)}
</style>
@endpush
