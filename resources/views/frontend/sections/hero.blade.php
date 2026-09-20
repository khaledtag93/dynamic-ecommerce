@php
    $data = $section['data'] ?? [];
    $heroTitle = $data['heroTitle'] ?? __('Latest electronics and smart devices in one trusted store.');
    $heroSubtitle = $data['heroSubtitle'] ?? __('Shop phones, laptops, gaming gear, TVs, audio, and accessories with clear offers, secure checkout, and fast delivery.');
    $heroBadge = $data['heroBadge'] ?? __('Electronics deals');
    $primaryButtonText = $data['primaryButtonText'] ?? __('Shop now');
    $primaryButtonLink = $data['primaryButtonLink'] ?? '#featured-products';
    $secondaryButtonText = $data['secondaryButtonText'] ?? __('Browse categories');
    $secondaryButtonLink = $data['secondaryButtonLink'] ?? '#categories';
    $heroImage = $data['heroBannerUrl'] ?? $data['heroImage'] ?? ($storeSettings['customer_hero_image_url'] ?? null);
    $slides = collect($data['heroSlides'] ?? [])->values();

    if ($slides->isEmpty()) {
        $slides = collect([[
            'eyebrow' => $heroBadge,
            'title' => $heroTitle,
            'subtitle' => $heroSubtitle,
            'button_text' => $primaryButtonText,
            'button_link' => $primaryButtonLink,
            'image_url' => $heroImage,
            'theme' => 'primary',
        ]]);
    }

    $quickCampaigns = [
        ['icon' => 'bi bi-percent', 'title' => __('Today deals'), 'text' => __('Offers on electronics'), 'link' => '#on-sale-products'],
        ['icon' => 'bi bi-grid-3x3-gap', 'title' => __('Shop by category'), 'text' => __('Phones, laptops, screens'), 'link' => '#categories'],
        ['icon' => 'bi bi-stars', 'title' => __('Top picks'), 'text' => __('Selected devices'), 'link' => '#featured-products'],
    ];
@endphp

<section id="hero" class="retail-hero-slider" data-retail-hero-slider>
    <div class="container">
        <div class="retail-hero-slider__viewport">
            <div class="retail-hero-slider__track" data-hero-track>
                @foreach($slides as $index => $slide)
                    @php
                        $slideTitle = trim((string) ($slide['title'] ?? $heroTitle));
                        $slideSubtitle = trim((string) ($slide['subtitle'] ?? $heroSubtitle));
                        $slideEyebrow = trim((string) ($slide['eyebrow'] ?? $heroBadge));
                        $slideButtonText = trim((string) ($slide['button_text'] ?? $primaryButtonText));
                        $slideButtonLink = trim((string) ($slide['button_link'] ?? $primaryButtonLink));
                        $slideImage = $slide['image_url'] ?? null;
                        $slideTheme = trim((string) ($slide['theme'] ?? 'primary'));
                    @endphp
                    <article class="retail-hero-slide retail-hero-slide--{{ $slideTheme }}" data-hero-slide aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                        <div class="retail-hero-slide__content">
                            <div class="retail-hero-slide__eyebrow">
                                <i class="bi bi-lightning-charge-fill"></i>
                                <span>{{ $slideEyebrow }}</span>
                            </div>
                            <h1>{{ $slideTitle }}</h1>
                            @if($slideSubtitle !== '')
                                <p>{{ $slideSubtitle }}</p>
                            @endif
                            <div class="retail-hero-slide__actions">
                                <a href="{{ $slideButtonLink }}" class="retail-hero-slide__primary">
                                    <span>{{ $slideButtonText }}</span>
                                    <i class="bi bi-arrow-up-left"></i>
                                </a>
                                <a href="{{ $secondaryButtonLink }}" class="retail-hero-slide__secondary">
                                    <span>{{ $secondaryButtonText }}</span>
                                    <i class="bi bi-grid-3x3-gap"></i>
                                </a>
                            </div>
                        </div>

                        <div class="retail-hero-slide__visual">
                            @if($slideImage)
                                <img src="{{ $slideImage }}" alt="{{ $slideTitle }}">
                                <span class="retail-hero-slide__shine"></span>
                            @else
                                <div class="retail-hero-device-art" aria-hidden="true">
                                    <span class="retail-hero-device-art__orb"></span>
                                    <span class="retail-hero-device-art__screen"></span>
                                    <span class="retail-hero-device-art__phone"></span>
                                    <span class="retail-hero-device-art__watch"></span>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if($slides->count() > 1)
                <button type="button" class="retail-hero-slider__nav retail-hero-slider__nav--prev" data-hero-prev aria-label="{{ __('Previous slide') }}">
                    <i class="bi bi-chevron-right"></i>
                </button>
                <button type="button" class="retail-hero-slider__nav retail-hero-slider__nav--next" data-hero-next aria-label="{{ __('Next slide') }}">
                    <i class="bi bi-chevron-left"></i>
                </button>

                <div class="retail-hero-slider__dots" data-hero-dots aria-label="{{ __('Hero slide navigation') }}">
                    @foreach($slides as $index => $slide)
                        <button type="button" class="{{ $index === 0 ? 'is-active' : '' }}" data-hero-dot="{{ $index }}" aria-label="{{ __('Go to slide') }} {{ $index + 1 }}"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="retail-hero-shortcuts" aria-label="{{ __('Shopping shortcuts') }}">
            @foreach($quickCampaigns as $campaign)
                <a href="{{ $campaign['link'] }}" class="retail-hero-shortcut">
                    <span class="retail-hero-shortcut__icon"><i class="{{ $campaign['icon'] }}"></i></span>
                    <span class="retail-hero-shortcut__copy">
                        <strong>{{ $campaign['title'] }}</strong>
                        <small>{{ $campaign['text'] }}</small>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

@once
    @push('styles')
    <style>
    .retail-hero-slider{position:relative;overflow:hidden;padding:clamp(1.8rem,3vw,3.4rem) 0 1.5rem;background:radial-gradient(circle at 18% 8%,color-mix(in srgb,var(--lc-primary) 14%,transparent),transparent 32%),linear-gradient(180deg,#f8fafc 0%,#fff 76%);border-bottom:1px solid color-mix(in srgb,var(--lc-border) 58%,white)}
    .retail-hero-slider__viewport{position:relative;overflow:hidden;border-radius:34px;background:#0f172a;box-shadow:0 30px 90px rgba(15,23,42,.15);min-height:500px}
    .retail-hero-slider__track{display:flex;width:100%;transition:transform .72s cubic-bezier(.22,1,.36,1);will-change:transform}
    .retail-hero-slide{position:relative;display:grid;grid-template-columns:minmax(0,1.02fr) minmax(360px,.88fr);align-items:center;gap:clamp(1.5rem,4vw,4rem);min-width:100%;padding:clamp(2rem,4.5vw,4.6rem);overflow:hidden;color:#fff;isolation:isolate;background:radial-gradient(circle at 78% 22%,rgba(249,115,22,.25),transparent 32%),linear-gradient(135deg,#111827,#020617 72%)}
    .retail-hero-slide:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,rgba(255,255,255,.08),transparent 28%,rgba(255,255,255,.05));z-index:-1}.retail-hero-slide:after{content:"";position:absolute;inset:auto -10% -42% -10%;height:62%;background:linear-gradient(180deg,transparent,rgba(255,255,255,.11));transform:skewY(-5deg);z-index:-1}.retail-hero-slide--campaign-1{background:radial-gradient(circle at 80% 18%,rgba(251,146,60,.28),transparent 31%),linear-gradient(135deg,#111827,#172554 76%)}.retail-hero-slide--campaign-2{background:radial-gradient(circle at 84% 14%,rgba(59,130,246,.28),transparent 32%),linear-gradient(135deg,#020617,#312e81 72%)}.retail-hero-slide--campaign-3{background:radial-gradient(circle at 80% 20%,rgba(16,185,129,.24),transparent 31%),linear-gradient(135deg,#111827,#064e3b 78%)}
    .retail-hero-slide__content{position:relative;z-index:2;max-width:670px}.retail-hero-slide__eyebrow{width:max-content;max-width:100%;display:inline-flex;align-items:center;gap:.52rem;margin-bottom:1rem;padding:.55rem .85rem;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);backdrop-filter:blur(14px);font-weight:950;color:#fff}.retail-hero-slide__eyebrow i{color:#fbbf24}.retail-hero-slide h1{margin:0;font-size:clamp(2.45rem,5.2vw,5.25rem);font-weight:950;line-height:.98;letter-spacing:-.055em;color:#fff}.retail-hero-slide p{max-width:630px;margin:1rem 0 0;color:rgba(255,255,255,.78);font-size:clamp(1rem,1.25vw,1.2rem);line-height:1.9;font-weight:650}.retail-hero-slide__actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.55rem}.retail-hero-slide__primary,.retail-hero-slide__secondary{display:inline-flex;align-items:center;justify-content:center;gap:.55rem;text-decoration:none;border-radius:18px;padding:.92rem 1.18rem;font-weight:950;transition:.22s ease}.retail-hero-slide__primary{background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:#fff!important;box-shadow:0 18px 42px rgba(249,115,22,.27)}.retail-hero-slide__secondary{background:rgba(255,255,255,.96);color:#0f172a!important;border:1px solid rgba(255,255,255,.45)}.retail-hero-slide__primary:hover,.retail-hero-slide__secondary:hover{transform:translateY(-2px)}
    .retail-hero-slide__visual{position:relative;z-index:2;min-height:340px;border-radius:30px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}.retail-hero-slide__visual img{width:100%;height:100%;min-height:340px;object-fit:cover;display:block;transform:scale(1.01)}.retail-hero-slide__shine{position:absolute;inset:0;background:linear-gradient(110deg,rgba(255,255,255,.08),transparent 35%,rgba(255,255,255,.14) 54%,transparent 70%);transform:translateX(-120%);animation:retailHeroShine 6s ease-in-out infinite}.retail-hero-device-art{position:absolute;inset:0}.retail-hero-device-art__orb{position:absolute;width:260px;height:260px;border-radius:50%;inset-inline-end:12%;top:15%;background:color-mix(in srgb,var(--lc-primary) 28%,transparent);filter:blur(18px)}.retail-hero-device-art__screen,.retail-hero-device-art__phone,.retail-hero-device-art__watch{position:absolute;background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(255,255,255,.62));box-shadow:0 30px 70px rgba(0,0,0,.32)}.retail-hero-device-art__screen{width:310px;height:188px;border-radius:28px;inset-inline-start:13%;bottom:14%;transform:rotate(5deg)}.retail-hero-device-art__phone{width:112px;height:216px;border-radius:32px;inset-inline-end:17%;top:12%;transform:rotate(-11deg)}.retail-hero-device-art__watch{width:86px;height:86px;border-radius:26px;inset-inline-end:10%;bottom:15%;transform:rotate(12deg)}
    .retail-hero-slider__nav{position:absolute;top:50%;z-index:5;width:48px;height:48px;border-radius:50%;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.9);color:#0f172a;display:flex;align-items:center;justify-content:center;box-shadow:0 18px 40px rgba(15,23,42,.18);transform:translateY(-50%);transition:.2s ease}.retail-hero-slider__nav:hover{background:var(--lc-primary);color:#fff}.retail-hero-slider__nav--prev{inset-inline-start:1rem}.retail-hero-slider__nav--next{inset-inline-end:1rem}.retail-hero-slider__dots{position:absolute;z-index:5;left:50%;bottom:1.05rem;transform:translateX(-50%);display:flex;gap:.45rem;align-items:center;padding:.38rem .5rem;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.16);backdrop-filter:blur(12px)}.retail-hero-slider__dots button{width:.55rem;height:.55rem;border:0;border-radius:999px;background:rgba(255,255,255,.46);padding:0;transition:.22s ease}.retail-hero-slider__dots button.is-active{width:1.55rem;background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary))}
    .retail-hero-shortcuts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem;margin-top:1rem}.retail-hero-shortcut{display:flex;align-items:center;gap:.9rem;padding:1rem;border-radius:22px;background:#fff;border:1px solid color-mix(in srgb,var(--lc-border) 68%,white);box-shadow:0 18px 42px rgba(15,23,42,.06);text-decoration:none;color:#0f172a;transition:.22s ease}.retail-hero-shortcut:hover{transform:translateY(-2px);border-color:color-mix(in srgb,var(--lc-primary) 30%,#e2e8f0);color:#0f172a}.retail-hero-shortcut__icon{width:46px;height:46px;display:flex;align-items:center;justify-content:center;border-radius:16px;background:#0f172a;color:#fff;flex:0 0 auto;box-shadow:0 14px 28px rgba(15,23,42,.16)}.retail-hero-shortcut__copy{min-width:0;display:flex;flex-direction:column;gap:.12rem}.retail-hero-shortcut__copy strong{font-weight:950;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.retail-hero-shortcut__copy small{color:#64748b;font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    @keyframes retailHeroShine{0%,45%{transform:translateX(-130%)}70%,100%{transform:translateX(130%)}}[dir="rtl"] .retail-hero-slide__primary i{transform:scaleX(-1)}@media(max-width:1199.98px){.retail-hero-slide{grid-template-columns:1fr}.retail-hero-slide__visual{min-height:300px}.retail-hero-slide__visual img{min-height:300px}}@media(max-width:767.98px){.retail-hero-slider__viewport{border-radius:26px}.retail-hero-slide{padding:1.6rem;min-height:620px}.retail-hero-slide h1{font-size:clamp(2.15rem,11vw,3.8rem)}.retail-hero-slide__actions a{width:100%}.retail-hero-shortcuts{grid-template-columns:1fr}.retail-hero-slider__nav{display:none}.retail-hero-slide__visual{min-height:260px}.retail-hero-device-art__screen{width:230px;height:142px}.retail-hero-device-art__phone{width:82px;height:154px}}@media(max-width:575.98px){.retail-hero-slider{padding-top:1rem}.retail-hero-slide{min-height:610px}.retail-hero-slide__eyebrow{font-size:.85rem}.retail-hero-slide p{font-size:.96rem}.retail-hero-shortcut{padding:.85rem}}
    </style>
    @endpush

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-retail-hero-slider]').forEach(function (slider) {
            const track = slider.querySelector('[data-hero-track]');
            const slides = Array.from(slider.querySelectorAll('[data-hero-slide]'));
            const dots = Array.from(slider.querySelectorAll('[data-hero-dot]'));
            const prev = slider.querySelector('[data-hero-prev]');
            const next = slider.querySelector('[data-hero-next]');
            if (!track || slides.length <= 1) return;

            let active = 0;
            let timer = null;
            const interval = 5200;

            function goTo(index) {
                active = (index + slides.length) % slides.length;
                track.style.transform = 'translateX(' + (-active * 100) + '%)';
                slides.forEach(function (slide, i) { slide.setAttribute('aria-hidden', i === active ? 'false' : 'true'); });
                dots.forEach(function (dot, i) { dot.classList.toggle('is-active', i === active); });
            }

            function start() {
                stop();
                timer = window.setInterval(function () { goTo(active + 1); }, interval);
            }

            function stop() {
                if (timer) window.clearInterval(timer);
                timer = null;
            }

            dots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    goTo(Number(dot.dataset.heroDot || 0));
                    start();
                });
            });
            if (prev) prev.addEventListener('click', function () { goTo(active - 1); start(); });
            if (next) next.addEventListener('click', function () { goTo(active + 1); start(); });
            slider.addEventListener('mouseenter', stop);
            slider.addEventListener('mouseleave', start);
            slider.addEventListener('focusin', stop);
            slider.addEventListener('focusout', start);
            goTo(0);
            start();
        });
    });
    </script>
    @endpush
@endonce
