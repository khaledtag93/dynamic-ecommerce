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
        <a class="language-switcher__link {{ $currentLocale === 'en' ? 'is-active' : '' }}" href="{{ route('locale.switch', ['locale' => 'en', 'redirect' => $currentRedirect]) }}" aria-label="{{ __('Switch language to English') }}">
            <span class="language-switcher__code">EN</span>
            @if($variant !== 'admin-compact')
                <span class="language-switcher__name">{{ __('English') }}</span>
            @endif
        </a>
        <a class="language-switcher__link {{ $currentLocale === 'ar' ? 'is-active' : '' }}" href="{{ route('locale.switch', ['locale' => 'ar', 'redirect' => $currentRedirect]) }}" aria-label="{{ __('Switch language to Arabic') }}">
            <span class="language-switcher__code">AR</span>
            @if($variant !== 'admin-compact')
                <span class="language-switcher__name">{{ __('Arabic') }}</span>
            @endif
        </a>
    </div>
</div>
