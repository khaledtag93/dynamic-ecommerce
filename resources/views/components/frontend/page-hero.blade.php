@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->class(['lc-page-hero', 'lc-card']) }}>
    @if($eyebrow)
        <div class="lc-section-kicker mb-3">{{ $eyebrow }}</div>
    @endif
    <h1 class="lc-section-title mb-2">{{ $title }}</h1>
    @if($description)
        <p class="lc-section-description mt-0">{{ $description }}</p>
    @endif

    @if(trim($slot) !== '')
        <div class="lc-page-hero__actions">{{ $slot }}</div>
    @endif
</div>
