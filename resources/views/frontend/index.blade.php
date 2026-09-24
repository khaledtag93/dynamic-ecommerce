@extends('layouts.app')

@section('title', ($storeSettings['store_name'] ?? 'Storefront') . ' | ' . __('Home'))

@section('hero')
    @php($heroSection = collect($homeSections ?? [])->firstWhere('type', 'hero'))

    @if($heroSection)
        @include($heroSection['view'], ['section' => $heroSection])
    @endif
@endsection

@section('content')
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
