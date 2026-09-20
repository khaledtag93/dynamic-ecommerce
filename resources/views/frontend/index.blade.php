@extends('layouts.app')

@section('title', ($storeSettings['store_name'] ?? 'Storefront') . ' | ' . __('Home'))

@section('hero')
    @php($heroSection = collect($homeSections ?? [])->firstWhere('type', 'hero'))

    @if($heroSection)
        @include($heroSection['view'], ['section' => $heroSection])
    @endif
@endsection

@section('content')
    {{-- Retail shortcuts: small, energetic customer paths without crowding the first screen. --}}
    <section class="retail-promo-pods" aria-label="{{ __('Shopping shortcuts') }}">
        <div class="container">
            <div class="retail-promo-pods__grid">
                <a href="#on-sale-products" class="retail-promo-pod retail-promo-pod--deal">
                    <span class="retail-promo-pod__icon"><i class="bi bi-tags"></i></span>
                    <span class="retail-promo-pod__copy">
                        <strong>{{ __('V42 Special Offers') }}</strong>
                        <small>{{ __('Catch electronics deals before they end') }}</small>
                    </span>
                </a>

                <a href="#categories" class="retail-promo-pod retail-promo-pod--category">
                    <span class="retail-promo-pod__icon"><i class="bi bi-grid-3x3-gap"></i></span>
                    <span class="retail-promo-pod__copy">
                        <strong>{{ __('Shop by department') }}</strong>
                        <small>{{ __('Mobiles, screens, laptops, audio') }}</small>
                    </span>
                </a>

                <a href="#best-sellers" class="retail-promo-pod retail-promo-pod--hot">
                    <span class="retail-promo-pod__icon"><i class="bi bi-fire"></i></span>
                    <span class="retail-promo-pod__copy">
                        <strong>{{ __('Popular now') }}</strong>
                        <small>{{ __('Products customers keep choosing') }}</small>
                    </span>
                </a>

                <a href="{{ route('frontend.contact') }}" class="retail-promo-pod retail-promo-pod--support">
                    <span class="retail-promo-pod__icon"><i class="bi bi-headset"></i></span>
                    <span class="retail-promo-pod__copy">
                        <strong>{{ __('Need advice?') }}</strong>
                        <small>{{ __('Ask support before you buy') }}</small>
                    </span>
                </a>
            </div>
        </div>
    </section>

    @php($contentSections = collect($homeSections ?? [])->reject(fn ($section) => ($section['type'] ?? null) === 'hero')->values())

    @forelse($contentSections as $index => $section)
        @include($section['view'], ['section' => $section, 'sectionIndex' => $index])
    @empty
        <section class="lc-home-section">
            <div class="container">
                <div class="lc-card lc-empty-state">
                    <div class="lc-empty-icon"><i class="bi bi-layout-text-window-reverse"></i></div>
                    <h2 class="h4 fw-bold mb-2">{{ __('No homepage sections are visible right now.') }}</h2>
                    <p class="text-muted mb-0">{{ __('Enable homepage sections from White-label Settings to display products, categories, and offers.') }}</p>
                </div>
            </div>
        </section>
    @endforelse
@endsection

@push('styles')
<style>
.retail-promo-pods{position:relative;padding:1.15rem 0;background:linear-gradient(180deg,#fff,#f8fafc);border-block:1px solid rgba(226,232,240,.72)}
.retail-promo-pods__grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.9rem}
.retail-promo-pod{position:relative;overflow:hidden;display:flex;align-items:center;gap:.85rem;min-height:92px;padding:1rem;border-radius:24px;background:#fff;border:1px solid rgba(226,232,240,.9);box-shadow:0 18px 42px rgba(15,23,42,.06);color:#0f172a;isolation:isolate}
.retail-promo-pod::after{content:"";position:absolute;inset:auto -10% -42% -10%;height:72%;background:radial-gradient(circle,color-mix(in srgb,var(--lc-primary) 18%,transparent),transparent 65%);opacity:.55;z-index:-1;transition:.25s ease}
.retail-promo-pod:hover{color:#0f172a;transform:translateY(-3px);box-shadow:0 24px 62px rgba(15,23,42,.12);border-color:color-mix(in srgb,var(--lc-primary) 25%,#e2e8f0)}
.retail-promo-pod:hover::after{opacity:.85;transform:translateY(-6px)}
.retail-promo-pod__icon{flex:0 0 50px;width:50px;height:50px;border-radius:18px;display:inline-flex;align-items:center;justify-content:center;background:#0f172a;color:#fff;font-size:1.22rem;box-shadow:0 14px 26px rgba(15,23,42,.14)}
.retail-promo-pod__copy{display:block;min-width:0}.retail-promo-pod__copy strong{display:block;font-weight:950;font-size:1rem;line-height:1.25}.retail-promo-pod__copy small{display:block;margin-top:.2rem;color:#64748b;font-weight:750;line-height:1.45}
.retail-promo-pod--deal .retail-promo-pod__icon{background:linear-gradient(135deg,#fb923c,#ea580c)}.retail-promo-pod--category .retail-promo-pod__icon{background:linear-gradient(135deg,#38bdf8,#2563eb)}.retail-promo-pod--hot .retail-promo-pod__icon{background:linear-gradient(135deg,#fb7185,#b91c1c)}.retail-promo-pod--support .retail-promo-pod__icon{background:linear-gradient(135deg,#22c55e,#15803d)}
@media(max-width:991.98px){.retail-promo-pods__grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:575.98px){.retail-promo-pods__grid{grid-template-columns:1fr}.retail-promo-pod{min-height:82px}}
</style>
@endpush
