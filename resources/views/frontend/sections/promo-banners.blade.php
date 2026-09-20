@php
    $data = $section['data'] ?? [];
    $banners = collect($data['banners'] ?? []);
    $title = $data['title'] ?? __('Featured offers');
    $subtitle = $data['subtitle'] ?? __('Deals and departments worth checking today');
@endphp

@if($banners->isNotEmpty())
<section id="promo-banners" class="retail-campaigns-v2">
    <div class="container">
        <div class="retail-campaigns-v2__head">
            <span>{{ $subtitle }}</span>
            <h2>{{ $title }}</h2>
            <p>{{ __('Campaigns, offers, and highlighted departments arranged like a real electronics storefront.') }}</p>
        </div>

        <div class="retail-campaigns-v2__grid">
            @foreach($banners as $banner)
                <article class="retail-campaigns-v2__card {{ $loop->first ? 'retail-campaigns-v2__card--wide' : '' }}">
                    <a href="{{ $banner['button_link'] ?? '#featured-products' }}" class="retail-campaigns-v2__link" aria-label="{{ $banner['title'] ?? __('Offer') }}">
                        @if(!empty($banner['image_url']))
                            <img src="{{ $banner['image_url'] }}" alt="{{ $banner['title'] ?? __('Offer') }}">
                        @else
                            <div class="retail-campaigns-v2__visual">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                        @endif

                        <span class="retail-campaigns-v2__shade"></span>

                        <span class="retail-campaigns-v2__content">
                            <small>{{ __('Limited offer') }}</small>
                            <strong>{{ $banner['title'] ?? __('Featured offer') }}</strong>
                            @if(!empty($banner['subtitle']))
                                <em>{{ $banner['subtitle'] }}</em>
                            @endif
                            @if(!empty($banner['button_text']))
                                <span class="retail-campaigns-v2__button">
                                    {{ $banner['button_text'] }}
                                    <i class="bi bi-arrow-up-right"></i>
                                </span>
                            @endif
                        </span>
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>

@once
    @push('styles')
    <style>
    .retail-campaigns-v2{padding:clamp(3rem,5vw,5.5rem) 0;background:linear-gradient(180deg,#fff,#f8fafc,#fff)}.retail-campaigns-v2__head{max-width:720px;margin-bottom:1.3rem}.retail-campaigns-v2__head span{display:inline-flex;margin-bottom:.45rem;color:var(--lc-primary-dark);font-weight:950}.retail-campaigns-v2__head h2{margin:0;color:#0f172a;font-size:clamp(1.7rem,2.8vw,2.9rem);font-weight:950;letter-spacing:-.045em;line-height:1.05}.retail-campaigns-v2__head p{margin:.55rem 0 0;color:#64748b;font-weight:650;line-height:1.75}.retail-campaigns-v2__grid{display:grid;grid-template-columns:1.35fr .85fr;grid-auto-rows:250px;gap:1rem}.retail-campaigns-v2__card{position:relative;border-radius:30px;overflow:hidden;background:#0f172a;box-shadow:0 24px 70px rgba(15,23,42,.13)}.retail-campaigns-v2__card--wide{grid-row:span 2}.retail-campaigns-v2__link{position:absolute;inset:0;display:block;color:#fff;text-decoration:none;isolation:isolate}.retail-campaigns-v2__link>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .48s ease}.retail-campaigns-v2__card:hover img{transform:scale(1.055)}.retail-campaigns-v2__visual{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:radial-gradient(circle at 22% 16%,color-mix(in srgb,var(--lc-primary) 55%,transparent),transparent 34%),linear-gradient(135deg,#111827,#020617);font-size:3rem}.retail-campaigns-v2__shade{position:absolute;inset:0;background:linear-gradient(135deg,rgba(2,6,23,.78),rgba(2,6,23,.12) 58%,rgba(2,6,23,.72));z-index:1}.retail-campaigns-v2__content{position:absolute;z-index:2;inset:auto 0 0 0;display:flex;flex-direction:column;align-items:flex-start;padding:clamp(1.15rem,2.5vw,2rem)}.retail-campaigns-v2__content small{display:inline-flex;margin-bottom:.65rem;padding:.42rem .68rem;border-radius:999px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);font-weight:900;font-style:normal}.retail-campaigns-v2__content strong{font-size:clamp(1.35rem,2.5vw,2.55rem);line-height:1.08;font-weight:950;letter-spacing:-.04em;max-width:560px}.retail-campaigns-v2__content em{font-style:normal;max-width:540px;margin-top:.55rem;color:rgba(255,255,255,.78);line-height:1.65}.retail-campaigns-v2__button{display:inline-flex;align-items:center;gap:.45rem;margin-top:1rem;padding:.7rem .95rem;border-radius:999px;background:#fff;color:#0f172a;font-weight:950}.retail-campaigns-v2__button i{font-size:.9rem}[dir="rtl"] .retail-campaigns-v2__button i{transform:scaleX(-1)}@media(max-width:991.98px){.retail-campaigns-v2__grid{grid-template-columns:1fr;grid-auto-rows:300px}.retail-campaigns-v2__card--wide{grid-row:span 1}}@media(max-width:575.98px){.retail-campaigns-v2__grid{grid-auto-rows:280px}.retail-campaigns-v2__card{border-radius:24px}}
    </style>
    @endpush
@endonce
@endif
