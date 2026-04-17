@extends('layouts.app')

@section('title', ($storeSettings['store_name'] ?? 'Storefront') . ' | ' . __('Home'))

@section('hero')
    @php($heroSection = collect($homeSections ?? [])->firstWhere('type', 'hero'))

    @if($heroSection)
        @include($heroSection['view'], ['section' => $heroSection])
    @endif
@endsection

@section('content')
    @if(($continueShoppingProducts ?? collect())->isNotEmpty())
        @include('frontend.sections.personalized-product-strip', [
            'products' => $continueShoppingProducts,
            'subtitle' => __('Continue shopping'),
            'title' => __('Your recently viewed products'),
            'description' => __('Bring shoppers back to the exact products they already explored so the path back to checkout feels effortless.'),
            'badge' => __('Session-based merchandising'),
        ])
    @endif

    @if(($recommendedForYouProducts ?? collect())->isNotEmpty())
        @include('frontend.sections.personalized-product-strip', [
            'products' => $recommendedForYouProducts,
            'subtitle' => __('Recommended for you'),
            'title' => __('Smart picks based on what you viewed'),
            'description' => __('Use browsing intent to push the next best collections, offers, and high-conversion product choices.'),
            'badge' => __('Personalized merchandising'),
        ])
    @endif



    @if(($aiRecommendedProducts ?? collect())->isNotEmpty())
        @include('frontend.sections.ai-recommendation-strip', [
            'products' => $aiRecommendedProducts,
            'subtitle' => __('AI recommendations'),
            'title' => __('Recommended products for you'),
            'description' => __('Blend current-session intent, category affinity, and popularity signals to rank the strongest next clicks.'),
            'insight' => $aiRecommendationInsight ?? null,
            'badge' => __('Behavior + basket + popularity'),
        ])
    @endif

    @php($contentSections = collect($homeSections ?? [])->reject(fn ($section) => ($section['type'] ?? null) === 'hero')->values())

    @forelse($contentSections as $index => $section)
        @include($section['view'], ['section' => $section, 'sectionIndex' => $index])
    @empty
        <section class="lc-home-section">
            <div class="container">
                <div class="lc-card lc-empty-state">
                    <div class="lc-empty-icon"><i class="bi bi-layout-text-window-reverse"></i></div>
                    <h2 class="h4 fw-bold mb-2">{{ __('Homepage sections are currently hidden.') }}</h2>
                    <p class="text-muted mb-0">{{ __('Enable at least one homepage block from branding settings to display storefront content here.') }}</p>
                </div>
            </div>
        </section>
    @endforelse
@endsection
