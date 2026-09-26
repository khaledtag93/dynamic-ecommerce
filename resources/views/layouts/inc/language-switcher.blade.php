@php
    $currentLocale = app()->getLocale();
    $currentRedirect = request()->fullUrl();
    $variant = $variant ?? 'default';
@endphp

<div class="language-switcher {{ $class ?? '' }} {{ $variant === 'admin-compact' ? 'language-switcher--admin-compact' : '' }}">
    @if($variant !== 'admin-compact')
        <span class="language-switcher__label">{{ __('Language') }}</span>
    @endif

    <div class="language-switcher__group" role="group" aria-label="{{ __('Language') }}">
        <a class="language-switcher__link {{ $currentLocale === 'en' ? 'is-active' : '' }}" href="{{ route('locale.switch', ['locale' => 'en', 'redirect' => $currentRedirect]) }}" lang="en" hreflang="en" aria-label="{{ __('Switch language to English') }}" @if($currentLocale === 'en') aria-current="true" @endif>
            <span class="language-switcher__code">EN</span>
            @if($variant !== 'admin-compact')
                <span class="language-switcher__name">{{ __('English') }}</span>
            @endif
        </a>
        <a class="language-switcher__link {{ $currentLocale === 'ar' ? 'is-active' : '' }}" href="{{ route('locale.switch', ['locale' => 'ar', 'redirect' => $currentRedirect]) }}" lang="ar" hreflang="ar" aria-label="{{ __('Switch language to Arabic') }}" @if($currentLocale === 'ar') aria-current="true" @endif>
            <span class="language-switcher__code">AR</span>
            @if($variant !== 'admin-compact')
                <span class="language-switcher__name">{{ __('Arabic') }}</span>
            @endif
        </a>
    </div>
</div>
