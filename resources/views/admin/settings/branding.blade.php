@extends('layouts.admin')

@section('title', __('White-label Settings') . ' | Admin')

@php
    use App\Support\AdminBranding;
    use Illuminate\Support\Str;

    $storeLogoStoredPath = $settings['logo_path'] ?? $settings['logo'] ?? '';
    $adminLogoStoredPath = $settings['admin_logo_path'] ?? $settings['admin_logo'] ?? '';
    $heroBannerStoredPath = $settings['hero_banner_path'] ?? $settings['hero_banner'] ?? '';
    $faviconStoredPath = $settings['favicon_path'] ?? '';

    $storeLogoPath = old('logo_path', $storeLogoStoredPath);
    $adminLogoPath = old('admin_logo_path', $adminLogoStoredPath);
    $heroBannerPath = old('hero_banner_path', $heroBannerStoredPath);
    $faviconPath = old('favicon_path', $faviconStoredPath);

    $resolvedStoreLogoPath = AdminBranding::resolveMediaPath($storeLogoPath, 'logo');
    $resolvedAdminLogoPath = AdminBranding::resolveMediaPath($adminLogoPath, 'admin_logo');
    $resolvedHeroBannerPath = AdminBranding::resolveMediaPath($heroBannerPath, 'hero_banner');
    $resolvedFaviconPath = AdminBranding::resolveMediaPath($faviconPath, 'favicon');

    $logoPreviewUrl = AdminBranding::mediaUrl($storeLogoPath, 'logo');
    $adminLogoPreviewUrl = AdminBranding::mediaUrl($adminLogoPath, 'admin_logo');
    $bannerPreviewUrl = AdminBranding::mediaUrl($heroBannerPath, 'hero_banner');
    $faviconPreviewUrl = AdminBranding::mediaUrl($faviconPath, 'favicon');
    $selectedPreset = old('theme_preset', $settings['theme_preset'] ?? 'professional_commerce');
    $selectedPresetLabel = __($presets[$selectedPreset]['theme_label'] ?? Str::headline(str_replace('_', ' ', $selectedPreset)));
@endphp

@section('content')
<x-admin.page-header :kicker="__('Settings')" :title="__('Branding & Appearance')" :description="__('Control brand identity, colors, and storefront visuals with a cleaner bilingual settings experience.')" />

<div class="admin-page-shell settings-page">
<form class="admin-form-shell" method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" data-submit-loading data-admin-section-tabs="branding" data-admin-error-fields="{{ json_encode($errors->keys()) }}">
    @csrf
    @method('PUT')

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <x-admin.stat-card
                :label="__('Default theme')"
                :value="$selectedPresetLabel"
                icon="mdi-palette-outline"
                :help="__('This is the active theme that both admin and storefront pages use after saving.')"
                class="h-100"
            />
        </div>
        <div class="col-md-4">
            <x-admin.stat-card
                :label="__('Promo banners')"
                value="3"
                icon="mdi-image-multiple-outline"
                :help="__('Manage three editable promo banners with images, text, links, and sort order.')"
                class="h-100"
            />
        </div>
        <div class="col-md-4">
            <x-admin.stat-card
                :label="__('Saved themes')"
                :value="count($customThemes ?? [])"
                icon="mdi-content-save-cog-outline"
                :help="__('Save your own theme after adjusting the colors manually.')"
                class="h-100"
            />
        </div>
    </div>

    <x-admin.section-tabs id="branding" :sections="[
        'theme' => __('Theme presets'),
        'identity' => __('Global identity'),
        'colors' => __('Customer branding'),
        'homepage' => __('Homepage CMS'),
        'promos' => __('Promo banners'),
        'trust' => __('Trust blocks'),
        'admin' => __('Admin branding'),
        'media' => __('Images'),
        'preview' => __('Live preview'),
    ]" />

    <div class="row g-4 admin-section-columns">
        <div class="col-xl-8">
            <div class="admin-card mb-4" id="branding-panel-theme" role="tabpanel" aria-labelledby="branding-tab-theme" data-admin-section-panel="theme">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
                        <div>
                            <h4 class="mb-1">{{ __('Theme presets') }}</h4>
                            <div class="text-muted small">{{ __('Choose a polished starting point. You can fine-tune the palette later without changing the rest of your store settings.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Admin + Customer') }}</span>
                    </div>

                    @php
                        $themeMarkets = [
                            'professional_commerce' => __('General commerce'),
                            'sunset_bakery' => __('Food & bakery'),
                            'midnight_luxury' => __('Luxury'),
                            'fresh_market' => __('Grocery & organic'),
                            'ocean_breeze' => __('Travel & lifestyle'),
                            'rose_boutique' => __('Fashion & beauty'),
                            'desert_gold' => __('Regional retail'),
                            'royal_navy' => __('Business & premium'),
                            'emerald_studio' => __('Natural & artisan'),
                            'plum_editorial' => __('Editorial & fashion'),
                            'graphite_modern' => __('Modern retail'),
                            'luxury_noir' => __('Luxury & jewelry'),
                            'beauty_blush' => __('Beauty & cosmetics'),
                            'tech_neon' => __('Electronics & tech'),
                            'nordic_home' => __('Home & furniture'),
                            'kids_pop' => __('Kids & family'),
                            'coffee_craft' => __('Coffee & artisan'),
                            'healthcare_calm' => __('Health & wellness'),
                            'streetwear_volt' => __('Streetwear & sports'),
                        ];
                    @endphp
                    <div class="theme-gallery-tools mb-3">
                        <div class="theme-gallery-search">
                            <i class="mdi mdi-magnify" aria-hidden="true"></i>
                            <input type="search" class="form-control" id="themeGallerySearch" placeholder="{{ __('Search themes or markets') }}" aria-label="{{ __('Search themes or markets') }}">
                        </div>
                        <div class="theme-gallery-filters" role="group" aria-label="{{ __('Filter themes') }}">
                            <button type="button" class="theme-filter-chip is-active" data-theme-filter="all">{{ __('All themes') }}</button>
                            <button type="button" class="theme-filter-chip" data-theme-filter="commerce">{{ __('Commerce') }}</button>
                            <button type="button" class="theme-filter-chip" data-theme-filter="fashion">{{ __('Fashion & beauty') }}</button>
                            <button type="button" class="theme-filter-chip" data-theme-filter="food">{{ __('Food & grocery') }}</button>
                            <button type="button" class="theme-filter-chip" data-theme-filter="premium">{{ __('Premium') }}</button>
                            <button type="button" class="theme-filter-chip" data-theme-filter="lifestyle">{{ __('Lifestyle') }}</button>
                        </div>
                        <div class="theme-gallery-meta text-muted small"><span id="themeGalleryCount">{{ count($presets) }}</span> {{ __('themes available') }}</div>
                    </div>
                    <div class="theme-preset-grid mb-3" role="list" aria-label="{{ __('Theme presets') }}">
                        @foreach($presets as $presetKey => $preset)
                            @php
                                $presetLabel = __($preset['theme_label'] ?? Str::headline(str_replace('_', ' ', $presetKey)));
                                $presetMarket = $themeMarkets[$presetKey] ?? __('Custom theme');
                                $presetCategory = match ($presetKey) {
                                    'professional_commerce', 'royal_navy', 'graphite_modern' => 'commerce',
                                    'rose_boutique', 'plum_editorial', 'beauty_blush', 'streetwear_volt' => 'fashion',
                                    'sunset_bakery', 'fresh_market', 'coffee_craft' => 'food',
                                    'midnight_luxury', 'desert_gold', 'luxury_noir' => 'premium',
                                    default => 'lifestyle',
                                };
                            @endphp
                            <button
                                type="button"
                                class="theme-preset-card {{ $selectedPreset === $presetKey ? 'is-active' : '' }}"
                                data-theme-preset-choice="{{ $presetKey }}"
                                data-theme-category="{{ Str::startsWith($presetKey, 'custom_') ? 'commerce' : $presetCategory }}"
                                data-theme-profile="{{ in_array($presetKey, ['midnight_luxury','luxury_noir','desert_gold'], true) ? 'refined' : (in_array($presetKey, ['tech_neon','graphite_modern','streetwear_volt'], true) ? 'sharp' : (in_array($presetKey, ['beauty_blush','rose_boutique','kids_pop'], true) ? 'playful' : (in_array($presetKey, ['nordic_home','emerald_studio','coffee_craft'], true) ? 'organic' : 'balanced'))) }}"
                                data-theme-search="{{ Str::lower($presetLabel . ' ' . $presetMarket) }}"
                                aria-pressed="{{ $selectedPreset === $presetKey ? 'true' : 'false' }}"
                                role="listitem"
                            >
                                <span class="theme-preset-card__visual" style="--preset-primary:{{ $preset['brand_primary_color'] ?? '#2563eb' }};--preset-secondary:{{ $preset['brand_secondary_color'] ?? '#0f172a' }};--preset-bg:{{ $preset['brand_background_color'] ?? '#f8fafc' }};--preset-surface:{{ $preset['brand_surface_color'] ?? '#ffffff' }};">
                                    <span class="theme-preset-card__sidebar"></span>
                                    <span class="theme-preset-card__surface">
                                        <span class="theme-preset-card__bar"></span>
                                        <span class="theme-preset-card__tile"></span>
                                    </span>
                                </span>
                                <span class="theme-preset-card__body">
                                    <span class="theme-preset-card__name">{{ $presetLabel }}<small>{{ $presetMarket }}</small></span>
                                    <span class="theme-preset-card__swatches" aria-hidden="true">
                                        <i style="background:{{ $preset['brand_primary_color'] ?? '#2563eb' }}"></i>
                                        <i style="background:{{ $preset['brand_secondary_color'] ?? '#0f172a' }}"></i>
                                        <i style="background:{{ $preset['brand_accent_color'] ?? '#0891b2' }}"></i>
                                    </span>
                                    @if($presetKey === 'professional_commerce')
                                        <span class="theme-preset-card__recommended">{{ __('Recommended') }}</span>
                                    @elseif(Str::startsWith($presetKey, 'custom_'))
                                        <span class="theme-preset-card__custom">{{ __('Custom theme') }}</span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <div class="theme-gallery-empty d-none mb-4" id="themeGalleryEmpty">
                        <i class="mdi mdi-palette-swatch-outline"></i>
                        <strong>{{ __('No themes match your search') }}</strong>
                        <span>{{ __('Try another name or market filter.') }}</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label fw-semibold">{{ __('Selected theme') }}</label>
                            <div class="input-group">
                                <select class="form-select" name="theme_preset" id="theme_preset">
                                    @foreach($presets as $presetKey => $preset)
                                        <option value="{{ $presetKey }}" @selected($selectedPreset === $presetKey)>
                                            {{ __($preset['theme_label'] ?? Str::headline(str_replace('_', ' ', $presetKey))) }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-primary" type="button" id="reapplyThemePreset" title="{{ __('Reapply selected preset') }}">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                            <div class="form-text">{{ __('Reapply restores the selected preset colors if you changed individual fields.') }}</div>
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label fw-semibold">{{ __('Default language') }}</label>
                            <select class="form-select" name="default_locale">
                                <option value="ar" @selected(old('default_locale', $settings['default_locale'] ?? 'ar') === 'ar')>{{ __('Arabic') }}</option>
                                <option value="en" @selected(old('default_locale', $settings['default_locale'] ?? 'ar') === 'en')>{{ __('English') }}</option>
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-semibold">{{ __('Save current colors as a new theme') }}</label>
                            <input type="text" name="custom_theme_name" class="form-control" value="{{ old('custom_theme_name') }}" placeholder="{{ __('Example: Green Fashion') }}">
                            <label class="branding-inline-switch mt-2" for="save_as_custom_theme">
                                <span><strong>{{ __('Save as custom theme') }}</strong><small>{{ __('Keep this visual setup as a reusable theme preset.') }}</small></span>
                                <span class="form-check form-switch branding-toggle-control"><input class="form-check-input" type="checkbox" role="switch" id="save_as_custom_theme" name="save_as_custom_theme" value="1" @checked(old('save_as_custom_theme'))></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card mb-4" id="branding-panel-identity" role="tabpanel" aria-labelledby="branding-tab-identity" data-admin-section-panel="identity">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Global identity') }}</h4>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Project name') }}</label><input type="text" name="project_name" value="{{ old('project_name', $settings['project_name'] ?? 'Tag Marketplace') }}" class="form-control"><div class="form-text">{{ __('Used across browser title, admin identity, and customer-facing branding. Keep it fixed, not translated.') }}</div></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Store name') }}</label><input type="text" name="store_name" value="{{ old('store_name', $settings['store_name'] ?? 'Storefront') }}" class="form-control"><div class="form-text">{{ __('Customer-facing store label shown beside the logo.') }}</div></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Store tagline') }}</label><input type="text" name="store_tagline" value="{{ old('store_tagline', $settings['store_tagline'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Footer copyright') }}</label><input type="text" name="footer_copyright" value="{{ old('footer_copyright', $settings['footer_copyright'] ?? '') }}" class="form-control" placeholder="{{ __('All rights reserved.') }}"></div>
                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Footer about text') }}</label><textarea name="footer_about" class="form-control" rows="3">{{ old('footer_about', $settings['footer_about'] ?? '') }}</textarea></div>
                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Hero title') }}</label><input type="text" name="hero_title" value="{{ old('hero_title', $settings['hero_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Hero subtitle') }}</label><textarea name="hero_subtitle" class="form-control" rows="4">{{ old('hero_subtitle', $settings['hero_subtitle'] ?? '') }}</textarea></div>
                    </div>
                </div>
            </div>

            <div class="admin-card mb-4" id="branding-panel-colors" role="tabpanel" aria-labelledby="branding-tab-colors" data-admin-section-panel="colors">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Customer branding') }}</h4>
                            <div class="text-muted small">{{ __('Start with the core brand colors. Open the advanced palette only when you need detailed control.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Storefront') }}</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Primary color'),'name'=>'brand_primary_color','value'=>old('brand_primary_color',$settings['brand_primary_color'] ?? '#2563eb')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Secondary color'),'name'=>'brand_secondary_color','value'=>old('brand_secondary_color',$settings['brand_secondary_color'] ?? '#0f172a')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Accent color'),'name'=>'brand_accent_color','value'=>old('brand_accent_color',$settings['brand_accent_color'] ?? '#0891b2')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Background color'),'name'=>'brand_background_color','value'=>old('brand_background_color',$settings['brand_background_color'] ?? '#f8fafc')])</div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">{{ __('Card radius') }}</label>
                            <input type="number" min="8" max="40" name="customer_card_radius" value="{{ old('customer_card_radius', $settings['customer_card_radius'] ?? 18) }}" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">{{ __('Badge style') }}</label>
                            <select name="customer_badge_style" class="form-select">
                                <option value="soft" @selected(old('customer_badge_style', $settings['customer_badge_style'] ?? 'soft') === 'soft')>{{ __('Soft') }}</option>
                                <option value="pill" @selected(old('customer_badge_style', $settings['customer_badge_style'] ?? 'soft') === 'pill')>{{ __('Pill') }}</option>
                                <option value="outline" @selected(old('customer_badge_style', $settings['customer_badge_style'] ?? 'soft') === 'outline')>{{ __('Outline') }}</option>
                            </select>
                        </div>
                    </div>

                    <details class="branding-advanced-palette mt-4">
                        <summary>
                            <span>
                                <strong>{{ __('Advanced palette') }}</strong>
                                <small>{{ __('Fine-tune surfaces, borders, tables, hover states, and button text.') }}</small>
                            </span>
                            <i class="mdi mdi-chevron-down"></i>
                        </summary>
                        <div class="row g-3 pt-3">
                            <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Surface color'),'name'=>'brand_surface_color','value'=>old('brand_surface_color',$settings['brand_surface_color'] ?? '#ffffff')])</div>
                            <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Soft background color'),'name'=>'brand_soft_color','value'=>old('brand_soft_color',$settings['brand_soft_color'] ?? '#eff6ff')])</div>
                            <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Border color'),'name'=>'brand_border_color','value'=>old('brand_border_color',$settings['brand_border_color'] ?? '#dbe3ef')])</div>
                            <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Muted background color'),'name'=>'brand_muted_bg_color','value'=>old('brand_muted_bg_color',$settings['brand_muted_bg_color'] ?? '#f1f5f9')])</div>
                            <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Table header color'),'name'=>'brand_table_head_color','value'=>old('brand_table_head_color',$settings['brand_table_head_color'] ?? '#f8fafc')])</div>
                            <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Row hover color'),'name'=>'brand_row_hover_color','value'=>old('brand_row_hover_color',$settings['brand_row_hover_color'] ?? '#f8fafc')])</div>
                            <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Button text color'),'name'=>'brand_button_text_color','value'=>old('brand_button_text_color',$settings['brand_button_text_color'] ?? '#ffffff')])</div>
                        </div>
                    </details>
                </div>
            </div>

            <div class="admin-card mb-4" id="branding-panel-homepage" role="tabpanel" aria-labelledby="branding-tab-homepage" data-admin-section-panel="homepage">
                <div class="admin-card-body">
                    <div class="admin-section-heading mb-4">
                        <div>
                            <h4 class="admin-section-title">{{ __('Homepage CMS') }}</h4>
                            <p class="admin-section-subtitle">{{ __('Organize homepage visibility, hero content, merchandising sections, and legacy blocks without scanning one long form.') }}</p>
                        </div>
                    </div>

                    <details class="branding-home-section border rounded-4 mb-3" open>
                        <summary class="p-3 d-flex justify-content-between align-items-center gap-3">
                            <span>
                                <strong>{{ __('Visibility & order') }}</strong>
                                <small class="d-block text-muted mt-1">{{ __('Choose which homepage sections are visible and control their display order.') }}</small>
                            </span>
                            <i class="mdi mdi-chevron-down"></i>
                        </summary>
                        <div class="p-3 pt-0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_hero">{{ __('Show hero') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_hero" name="show_home_hero" value="1" @checked(old('show_home_hero', $settings['show_home_hero'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_categories">{{ __('Show categories') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_categories" name="show_home_categories" value="1" @checked(old('show_home_categories', $settings['show_home_categories'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_featured_categories">{{ __('Show featured categories') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_featured_categories" name="show_home_featured_categories" value="1" @checked(old('show_home_featured_categories', $settings['show_home_featured_categories'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_featured_products">{{ __('Show featured') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_featured_products" name="show_home_featured_products" value="1" @checked(old('show_home_featured_products', $settings['show_home_featured_products'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_latest_products">{{ __('Show latest') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_latest_products" name="show_home_latest_products" value="1" @checked(old('show_home_latest_products', $settings['show_home_latest_products'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_best_sellers">{{ __('Show best sellers') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_best_sellers" name="show_home_best_sellers" value="1" @checked(old('show_home_best_sellers', $settings['show_home_best_sellers'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_on_sale_products">{{ __('Show on sale') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_on_sale_products" name="show_home_on_sale_products" value="1" @checked(old('show_home_on_sale_products', $settings['show_home_on_sale_products'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_manual_featured_products">{{ __('Show manual featured') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_manual_featured_products" name="show_home_manual_featured_products" value="1" @checked(old('show_home_manual_featured_products', $settings['show_home_manual_featured_products'] ?? false))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_promo_banners">{{ __('Show promo banners') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_promo_banners" name="show_home_promo_banners" value="1" @checked(old('show_home_promo_banners', $settings['show_home_promo_banners'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_trust_blocks">{{ __('Show trust blocks') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_trust_blocks" name="show_home_trust_blocks" value="1" @checked(old('show_home_trust_blocks', $settings['show_home_trust_blocks'] ?? true))>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branding-toggle-row">
                                <label class="branding-toggle-copy" for="show_home_promo_banner">{{ __('Keep legacy promo block') }}</label>
                                <div class="form-check form-switch branding-toggle-control">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_home_promo_banner" name="show_home_promo_banner" value="1" @checked(old('show_home_promo_banner', $settings['show_home_promo_banner'] ?? false))>
                                </div>
                            </div>
                        </div>

                        @php
                            $homeSectionLabels = [
                                'hero' => __('Hero'),
                                'promo_banners' => __('Promo banners'),
                                'featured_categories' => __('Featured categories'),
                                'manual_featured_products' => __('Hand-picked products'),
                                'featured_products' => __('Featured products'),
                                'best_sellers' => __('Best sellers'),
                                'latest_products' => __('Latest arrivals'),
                                'on_sale_products' => __('On sale'),
                                'trust_blocks' => __('Trust blocks'),
                                'categories' => __('Categories'),
                                'promo_banner' => __('Legacy promo banner'),
                            ];
                            $defaultHomeOrder = array_keys($homeSectionLabels);
                            $savedHomeOrder = array_values(array_unique(array_filter(array_map('trim', explode(',', old('homepage_sections_order', $settings['homepage_sections_order'] ?? implode(',', $defaultHomeOrder)))), fn ($key) => isset($homeSectionLabels[$key]))));
                            $savedHomeOrder = array_values(array_unique(array_merge($savedHomeOrder, $defaultHomeOrder)));
                        @endphp
                        <div class="col-12">
                            <label class="form-label fw-semibold">{{ __('Homepage sections order') }}</label>
                            <input type="hidden" name="homepage_sections_order" id="homepage_sections_order" value="{{ implode(',', $savedHomeOrder) }}">
                            <div class="homepage-order-editor" id="homepageOrderEditor">
                                @foreach($savedHomeOrder as $sectionKey)
                                    <div class="homepage-order-item" data-home-section="{{ $sectionKey }}">
                                        <span class="homepage-order-handle"><i class="mdi mdi-drag-vertical"></i></span>
                                        <span class="homepage-order-copy"><strong>{{ $homeSectionLabels[$sectionKey] }}</strong><small>{{ __('Homepage section') }}</small></span>
                                        <span class="homepage-order-actions">
                                            <button type="button" class="btn btn-sm btn-light" data-home-move="up" aria-label="{{ __('Move up') }}"><i class="mdi mdi-chevron-up"></i></button>
                                            <button type="button" class="btn btn-sm btn-light" data-home-move="down" aria-label="{{ __('Move down') }}"><i class="mdi mdi-chevron-down"></i></button>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text">{{ __('Arrange the storefront sections visually. Your order is saved automatically with the branding form.') }}</div>
                        </div>
                    </div>
                        </div>
                    </details>

                    <details class="branding-home-section border rounded-4 mb-3">
                        <summary class="p-3 d-flex justify-content-between align-items-center gap-3">
                            <span>
                                <strong>{{ __('Hero content') }}</strong>
                                <small class="d-block text-muted mt-1">{{ __('Edit the homepage badge and primary/secondary call-to-action links.') }}</small>
                            </span>
                            <i class="mdi mdi-chevron-down"></i>
                        </summary>
                        <div class="p-3 pt-0">
                            <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Hero badge text') }}</label><input type="text" name="hero_badge_text" value="{{ old('hero_badge_text', $settings['hero_badge_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Hero primary button text') }}</label><input type="text" name="hero_primary_button_text" value="{{ old('hero_primary_button_text', $settings['hero_primary_button_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Hero primary button link') }}</label><input type="text" name="hero_primary_button_link" value="{{ old('hero_primary_button_link', $settings['hero_primary_button_link'] ?? '#featured-products') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Hero secondary button text') }}</label><input type="text" name="hero_secondary_button_text" value="{{ old('hero_secondary_button_text', $settings['hero_secondary_button_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Hero secondary button link') }}</label><input type="text" name="hero_secondary_button_link" value="{{ old('hero_secondary_button_link', $settings['hero_secondary_button_link'] ?? '#categories') }}" class="form-control"></div>
                            </div>
                        </div>
                    </details>

                    <details class="branding-home-section border rounded-4 mb-3">
                        <summary class="p-3 d-flex justify-content-between align-items-center gap-3">
                            <span>
                                <strong>{{ __('Merchandising sections') }}</strong>
                                <small class="d-block text-muted mt-1">{{ __('Configure featured, latest, best-seller, sale, and category blocks.') }}</small>
                            </span>
                            <i class="mdi mdi-chevron-down"></i>
                        </summary>
                        <div class="p-3 pt-0">
                            <div class="row g-3">
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Featured title') }}</label><input type="text" name="home_featured_products_title" value="{{ old('home_featured_products_title', $settings['home_featured_products_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Featured subtitle') }}</label><input type="text" name="home_featured_products_subtitle" value="{{ old('home_featured_products_subtitle', $settings['home_featured_products_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Featured limit') }}</label><input type="number" min="1" max="24" name="home_featured_products_limit" value="{{ old('home_featured_products_limit', $settings['home_featured_products_limit'] ?? 8) }}" class="form-control"></div>

                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Featured categories title') }}</label><input type="text" name="home_featured_categories_title" value="{{ old('home_featured_categories_title', $settings['home_featured_categories_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Featured categories subtitle') }}</label><input type="text" name="home_featured_categories_subtitle" value="{{ old('home_featured_categories_subtitle', $settings['home_featured_categories_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Featured categories limit') }}</label><input type="number" min="1" max="24" name="home_featured_categories_limit" value="{{ old('home_featured_categories_limit', $settings['home_featured_categories_limit'] ?? 4) }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Featured categories source') }}</label><select name="home_featured_categories_source" class="form-select"><option value="manual" @selected(old('home_featured_categories_source', $settings['home_featured_categories_source'] ?? 'manual') === 'manual')>{{ __('Manual IDs') }}</option><option value="latest" @selected(old('home_featured_categories_source', $settings['home_featured_categories_source'] ?? 'manual') === 'latest')>{{ __('Latest visible categories') }}</option></select></div>
                        <div class="col-md-8"><label class="form-label fw-semibold">{{ __('Featured category IDs') }}</label><input type="text" name="home_featured_categories_ids" value="{{ old('home_featured_categories_ids', $settings['home_featured_categories_ids'] ?? '') }}" class="form-control" placeholder="1,4,7,12"><div class="form-text">{{ __('Used when source = Manual IDs. Keep order as you want it to appear on the homepage.') }}</div></div>

                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Categories title') }}</label><input type="text" name="home_categories_title" value="{{ old('home_categories_title', $settings['home_categories_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Categories subtitle') }}</label><input type="text" name="home_categories_subtitle" value="{{ old('home_categories_subtitle', $settings['home_categories_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Categories limit') }}</label><input type="number" min="1" max="24" name="home_categories_limit" value="{{ old('home_categories_limit', $settings['home_categories_limit'] ?? 8) }}" class="form-control"></div>

                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Latest title') }}</label><input type="text" name="home_latest_products_title" value="{{ old('home_latest_products_title', $settings['home_latest_products_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Latest subtitle') }}</label><input type="text" name="home_latest_products_subtitle" value="{{ old('home_latest_products_subtitle', $settings['home_latest_products_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Latest limit') }}</label><input type="number" min="1" max="24" name="home_latest_products_limit" value="{{ old('home_latest_products_limit', $settings['home_latest_products_limit'] ?? 8) }}" class="form-control"></div>

                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Best sellers title') }}</label><input type="text" name="home_best_sellers_title" value="{{ old('home_best_sellers_title', $settings['home_best_sellers_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Best sellers subtitle') }}</label><input type="text" name="home_best_sellers_subtitle" value="{{ old('home_best_sellers_subtitle', $settings['home_best_sellers_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Best sellers limit') }}</label><input type="number" min="1" max="24" name="home_best_sellers_limit" value="{{ old('home_best_sellers_limit', $settings['home_best_sellers_limit'] ?? 8) }}" class="form-control"></div>

                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('On sale title') }}</label><input type="text" name="home_on_sale_products_title" value="{{ old('home_on_sale_products_title', $settings['home_on_sale_products_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('On sale subtitle') }}</label><input type="text" name="home_on_sale_products_subtitle" value="{{ old('home_on_sale_products_subtitle', $settings['home_on_sale_products_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('On sale limit') }}</label><input type="number" min="1" max="24" name="home_on_sale_products_limit" value="{{ old('home_on_sale_products_limit', $settings['home_on_sale_products_limit'] ?? 8) }}" class="form-control"></div>

                            </div>
                        </div>
                    </details>

                    <details class="branding-home-section border rounded-4 mb-3">
                        <summary class="p-3 d-flex justify-content-between align-items-center gap-3">
                            <span>
                                <strong>{{ __('Manual featured products') }}</strong>
                                <small class="d-block text-muted mt-1">{{ __('Add product IDs in the exact order you want them to appear.') }}</small>
                            </span>
                            <i class="mdi mdi-chevron-down"></i>
                        </summary>
                        <div class="p-3 pt-0">
                            <div class="row g-3">
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Manual featured title') }}</label><input type="text" name="home_manual_featured_products_title" value="{{ old('home_manual_featured_products_title', $settings['home_manual_featured_products_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Manual featured subtitle') }}</label><input type="text" name="home_manual_featured_products_subtitle" value="{{ old('home_manual_featured_products_subtitle', $settings['home_manual_featured_products_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Manual featured limit') }}</label><input type="number" min="1" max="24" name="home_manual_featured_products_limit" value="{{ old('home_manual_featured_products_limit', $settings['home_manual_featured_products_limit'] ?? 8) }}" class="form-control"></div>
                        <div class="col-md-8"><label class="form-label fw-semibold">{{ __('Manual featured product IDs') }}</label><textarea name="home_manual_featured_products_ids" class="form-control" rows="3" placeholder="12,8,31,5">{{ old('home_manual_featured_products_ids', $settings['home_manual_featured_products_ids'] ?? '') }}</textarea></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Action text') }}</label><input type="text" name="home_manual_featured_products_action_text" value="{{ old('home_manual_featured_products_action_text', $settings['home_manual_featured_products_action_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Action link') }}</label><input type="text" name="home_manual_featured_products_action_link" value="{{ old('home_manual_featured_products_action_link', $settings['home_manual_featured_products_action_link'] ?? '#latest-products') }}" class="form-control"></div>
                            </div>
                        </div>
                    </details>

                    <details class="branding-home-section border rounded-4 mb-0">
                        <summary class="p-3 d-flex justify-content-between align-items-center gap-3">
                            <span>
                                <strong>{{ __('Trust & legacy content') }}</strong>
                                <small class="d-block text-muted mt-1">{{ __('Maintain trust-section headings and legacy promo content only when still required.') }}</small>
                            </span>
                            <i class="mdi mdi-chevron-down"></i>
                        </summary>
                        <div class="p-3 pt-0">
                            <div class="row g-3">
                        <div class="col-12"><div class="fw-bold">{{ __('Trust section heading') }}</div></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Trust section title') }}</label><input type="text" name="home_trust_blocks_title" value="{{ old('home_trust_blocks_title', $settings['home_trust_blocks_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Trust section subtitle') }}</label><input type="text" name="home_trust_blocks_subtitle" value="{{ old('home_trust_blocks_subtitle', $settings['home_trust_blocks_subtitle'] ?? '') }}" class="form-control"></div>

                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Legacy promo title') }}</label><input type="text" name="home_promo_title" value="{{ old('home_promo_title', $settings['home_promo_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Legacy promo subtitle') }}</label><input type="text" name="home_promo_subtitle" value="{{ old('home_promo_subtitle', $settings['home_promo_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Legacy promo button text') }}</label><input type="text" name="home_promo_button_text" value="{{ old('home_promo_button_text', $settings['home_promo_button_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Legacy promo button link') }}</label><input type="text" name="home_promo_button_link" value="{{ old('home_promo_button_link', $settings['home_promo_button_link'] ?? '#featured-products') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Legacy secondary text') }}</label><input type="text" name="home_promo_secondary_button_text" value="{{ old('home_promo_secondary_button_text', $settings['home_promo_secondary_button_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Legacy secondary link') }}</label><input type="text" name="home_promo_secondary_button_link" value="{{ old('home_promo_secondary_button_link', $settings['home_promo_secondary_button_link'] ?? '#categories') }}" class="form-control"></div>
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            <div class="admin-card mb-4" id="branding-panel-promos" role="tabpanel" aria-labelledby="branding-tab-promos" data-admin-section-panel="promos">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Promo banners') }}</h4>
                            <div class="text-muted small">{{ __('Each banner supports image, title, subtitle, button text, button link, active state, and sort order.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Promo banners') }}</span>
                    </div>

                    @for($i = 1; $i <= 3; $i++)
                        @php
                            $promoPath = old("promo_banner_{$i}_image_path", $settings["promo_banner_{$i}_image_path"] ?? '');
                            $resolvedPromoPath = AdminBranding::resolveMediaPath($promoPath, 'promo_banner');
                            $promoPreview = AdminBranding::mediaUrl($promoPath, 'promo_banner');
                        @endphp
                        <details class="admin-promo-card border rounded-4 {{ $i < 3 ? 'mb-3' : '' }}" @if($i === 1) open @endif>
                            <summary class="p-3 d-flex justify-content-between align-items-center gap-3">
                                <span>
                                    <strong>{{ __('Promo banner') }} {{ $i }}</strong>
                                    <small class="d-block text-muted mt-1">
                                        {{ old("promo_banner_{$i}_title", $settings["promo_banner_{$i}_title"] ?? '') ?: __('Untitled banner') }}
                                    </small>
                                </span>
                                <span class="d-flex align-items-center gap-2">
                                    @if(old("promo_banner_{$i}_active", $settings["promo_banner_{$i}_active"] ?? true))
                                        <span class="badge rounded-pill text-bg-success-subtle border">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge rounded-pill text-bg-light border">{{ __('Inactive') }}</span>
                                    @endif
                                    <i class="mdi mdi-chevron-down"></i>
                                </span>
                            </summary>
                            <div class="p-3 pt-0">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-8">
                                    <div class="row g-3">
                                        <div class="col-md-8"><label class="form-label fw-semibold">{{ __('Banner') }} {{ $i }} {{ __('title') }}</label><input type="text" name="promo_banner_{{ $i }}_title" value="{{ old("promo_banner_{$i}_title", $settings["promo_banner_{$i}_title"] ?? '') }}" class="form-control"></div>
                                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Sort') }}</label><input type="number" min="1" max="99" name="promo_banner_{{ $i }}_sort_order" value="{{ old("promo_banner_{$i}_sort_order", $settings["promo_banner_{$i}_sort_order"] ?? $i) }}" class="form-control"></div>
                                        <div class="col-md-2 d-flex align-items-end"><label class="branding-compact-switch" for="promo_banner_{{ $i }}_active"><span>{{ __('Active') }}</span><span class="form-check form-switch branding-toggle-control"><input class="form-check-input" type="checkbox" role="switch" id="promo_banner_{{ $i }}_active" name="promo_banner_{{ $i }}_active" value="1" @checked(old("promo_banner_{$i}_active", $settings["promo_banner_{$i}_active"] ?? true))></span></label></div>
                                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Subtitle') }}</label><textarea name="promo_banner_{{ $i }}_subtitle" class="form-control" rows="3">{{ old("promo_banner_{$i}_subtitle", $settings["promo_banner_{$i}_subtitle"] ?? '') }}</textarea></div>
                                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Button text') }}</label><input type="text" name="promo_banner_{{ $i }}_button_text" value="{{ old("promo_banner_{$i}_button_text", $settings["promo_banner_{$i}_button_text"] ?? '') }}" class="form-control"></div>
                                        <div class="col-md-8"><label class="form-label fw-semibold">{{ __('Button link') }}</label><input type="text" name="promo_banner_{{ $i }}_button_link" value="{{ old("promo_banner_{$i}_button_link", $settings["promo_banner_{$i}_button_link"] ?? '#featured-products') }}" class="form-control"></div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="admin-media-block">
                                        <div class="admin-media-preview mb-3" data-preview-box="promo_banner_{{ $i }}_image_path" data-initial-src="{{ $promoPreview ?? '' }}">
                                            @if($promoPreview)
                                                <img src="{{ $promoPreview }}" alt="{{ __('Promo banner') }} {{ $i }}" class="admin-thumb-lg admin-banner-thumb" data-preview-img="promo_banner_{{ $i }}_image_path">
                                            @else
                                                <div class="admin-empty-state py-4" data-preview-empty="promo_banner_{{ $i }}_image_path">
                                                    <div class="admin-empty-icon"><i class="mdi mdi-image-outline"></i></div>
                                                    <p class="text-muted mb-0">{{ __('No promo image uploaded yet.') }}</p>
                                                </div>
                                                <img src="" alt="{{ __('Promo banner') }} {{ $i }}" class="admin-thumb-lg admin-banner-thumb d-none" data-preview-img="promo_banner_{{ $i }}_image_path">
                                            @endif
                                        </div>
                                        <input type="file" name="promo_banner_{{ $i }}_file" class="form-control mb-2 js-image-file" accept="image/*" data-preview-target="promo_banner_{{ $i }}_image_path">
                                        <div class="admin-current-path small text-muted mb-2">
                                            <span class="fw-semibold">{{ __('Current path') }}:</span>
                                            <span dir="ltr">{{ $resolvedPromoPath ?: __('Not set') }}</span>
                                        </div>
                                        <details class="admin-manual-path">
                                            <summary>{{ __('Use a manual path instead') }}</summary>
                                            <input type="text" id="promo_banner_{{ $i }}_image_path" name="promo_banner_{{ $i }}_image_path" value="{{ $promoPath }}" class="form-control js-image-path mt-2" data-preview-target="promo_banner_{{ $i }}_image_path" placeholder="branding/promo-banner-{{ $i }}.jpg">
                                        </details>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </details>
                    @endfor
                </div>
            </div>

            <div class="admin-card mb-4" id="branding-panel-trust" role="tabpanel" aria-labelledby="branding-tab-trust" data-admin-section-panel="trust">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Trust blocks') }}</h4>
                            <div class="text-muted small">{{ __('Each block supports icon class, title, subtitle, active state, and sort order.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Storefront sections') }}</span>
                    </div>

                    @for($i = 1; $i <= 4; $i++)
                        <details class="admin-promo-card border rounded-4 {{ $i < 4 ? 'mb-3' : '' }}" @if($i === 1) open @endif>
                            <summary class="p-3 d-flex justify-content-between align-items-center gap-3">
                                <span>
                                    <strong>{{ __('Trust block') }} {{ $i }}</strong>
                                    <small class="d-block text-muted mt-1">
                                        {{ old("trust_block_{$i}_title", $settings["trust_block_{$i}_title"] ?? '') ?: __('Untitled trust block') }}
                                    </small>
                                </span>
                                <span class="d-flex align-items-center gap-2">
                                    @if(old("trust_block_{$i}_active", $settings["trust_block_{$i}_active"] ?? true))
                                        <span class="badge rounded-pill text-bg-success-subtle border">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge rounded-pill text-bg-light border">{{ __('Inactive') }}</span>
                                    @endif
                                    <i class="mdi mdi-chevron-down"></i>
                                </span>
                            </summary>
                            <div class="p-3 pt-0">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-9">
                                    <div class="row g-3">
                                        <div class="col-md-5"><label class="form-label fw-semibold">{{ __('Block') }} {{ $i }} {{ __('title') }}</label><input type="text" name="trust_block_{{ $i }}_title" value="{{ old("trust_block_{$i}_title", $settings["trust_block_{$i}_title"] ?? '') }}" class="form-control"></div>
                                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Icon class') }}</label><input type="text" name="trust_block_{{ $i }}_icon" value="{{ old("trust_block_{$i}_icon", $settings["trust_block_{$i}_icon"] ?? 'bi bi-stars') }}" class="form-control" placeholder="bi bi-truck"></div>
                                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Sort') }}</label><input type="number" min="1" max="99" name="trust_block_{{ $i }}_sort_order" value="{{ old("trust_block_{$i}_sort_order", $settings["trust_block_{$i}_sort_order"] ?? $i) }}" class="form-control"></div>
                                        <div class="col-md-2 d-flex align-items-end"><label class="branding-compact-switch" for="trust_block_{{ $i }}_active"><span>{{ __('Active') }}</span><span class="form-check form-switch branding-toggle-control"><input class="form-check-input" type="checkbox" role="switch" id="trust_block_{{ $i }}_active" name="trust_block_{{ $i }}_active" value="1" @checked(old("trust_block_{$i}_active", $settings["trust_block_{$i}_active"] ?? true))></span></label></div>
                                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Subtitle') }}</label><textarea name="trust_block_{{ $i }}_subtitle" class="form-control" rows="3">{{ old("trust_block_{$i}_subtitle", $settings["trust_block_{$i}_subtitle"] ?? '') }}</textarea></div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="admin-promo-meta h-100">
                                        <div class="admin-promo-meta__icon mb-3"><i class="{{ old("trust_block_{$i}_icon", $settings["trust_block_{$i}_icon"] ?? 'bi bi-stars') }}"></i></div>
                                        <div class="fw-bold mb-1">{{ old("trust_block_{$i}_title", $settings["trust_block_{$i}_title"] ?? __('Trust block')) }}</div>
                                        <div class="text-muted small">{{ __('Tip: use Bootstrap Icons classes like bi bi-truck, bi bi-shield-check, bi bi-headset.') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="admin-card" id="branding-panel-admin" role="tabpanel" aria-labelledby="branding-tab-admin" data-admin-section-panel="admin">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Admin branding') }}</h4>
                    <div class="row g-3">
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Sidebar color'),'name'=>'admin_sidebar_color','value'=>old('admin_sidebar_color',$settings['admin_sidebar_color'] ?? '#0b1220')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Header color'),'name'=>'admin_header_color','value'=>old('admin_header_color',$settings['admin_header_color'] ?? '#ffffff')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Admin surface color'),'name'=>'admin_surface_color','value'=>old('admin_surface_color',$settings['admin_surface_color'] ?? '#ffffff')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Admin card border color'),'name'=>'admin_card_border_color','value'=>old('admin_card_border_color',$settings['admin_card_border_color'] ?? '#e2e8f0')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Admin accent soft color'),'name'=>'admin_accent_soft_color','value'=>old('admin_accent_soft_color',$settings['admin_accent_soft_color'] ?? '#ecfeff')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Admin primary soft color'),'name'=>'admin_primary_soft_color','value'=>old('admin_primary_soft_color',$settings['admin_primary_soft_color'] ?? '#dbeafe')])</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 branding-side-column">
            <div class="branding-side-stack">
            <div class="admin-card mb-4" id="branding-panel-media" role="tabpanel" aria-labelledby="branding-tab-media" data-admin-section-panel="media">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Images') }}</h4>
                            <div class="text-muted small">{{ __('Keep media assets focused while the live preview stays available beside your edits.') }}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-media-density-toggle aria-expanded="true">
                            <i class="mdi mdi-unfold-less-horizontal"></i>
                            <span>{{ __('Compact media') }}</span>
                        </button>
                    </div>
                    <div data-media-editor>

                    <div class="admin-media-block mb-4">
                        <div class="admin-media-block__title">{{ __('Store logo') }}</div>
                        <div class="admin-media-preview mb-3" data-preview-box="logo_path" data-initial-src="{{ $logoPreviewUrl ?? '' }}">
                            @if($logoPreviewUrl)
                                <img src="{{ $logoPreviewUrl }}" alt="{{ __('Store logo') }}" class="admin-thumb-lg" data-preview-img="logo_path">
                            @else
                                <div class="admin-empty-state py-4" data-preview-empty="logo_path"><div class="admin-empty-icon"><i class="mdi mdi-image-outline"></i></div><p class="text-muted mb-0">{{ __('No logo uploaded yet.') }}</p></div>
                                <img src="" alt="{{ __('Store logo') }}" class="admin-thumb-lg d-none" data-preview-img="logo_path">
                            @endif
                        </div>
                        <input type="file" name="logo_file" class="form-control mb-2 js-image-file" accept="image/*" data-preview-target="logo_path">
                        <div class="admin-current-path small text-muted mb-2"><span class="fw-semibold">{{ __('Current path') }}:</span> <span dir="ltr">{{ $resolvedStoreLogoPath ?: __('Not set') }}</span></div>
                        <details class="admin-manual-path"><summary>{{ __('Use a manual path instead') }}</summary><input type="text" id="logo_path" name="logo_path" value="{{ $storeLogoPath }}" class="form-control js-image-path mt-2" data-preview-target="logo_path" placeholder="branding/logo.png"></details>
                    </div>

                    <div class="admin-media-block mb-4">
                        <div class="admin-media-block__title">{{ __('Admin logo') }}</div>
                        <div class="admin-media-preview mb-3" data-preview-box="admin_logo_path" data-initial-src="{{ $adminLogoPreviewUrl ?? '' }}">
                            @if($adminLogoPreviewUrl)
                                <img src="{{ $adminLogoPreviewUrl }}" alt="{{ __('Admin logo') }}" class="admin-thumb-lg" data-preview-img="admin_logo_path">
                            @else
                                <div class="admin-empty-state py-4" data-preview-empty="admin_logo_path"><div class="admin-empty-icon"><i class="mdi mdi-monitor-dashboard"></i></div><p class="text-muted mb-0">{{ __('No admin logo uploaded yet.') }}</p></div>
                                <img src="" alt="{{ __('Admin logo') }}" class="admin-thumb-lg d-none" data-preview-img="admin_logo_path">
                            @endif
                        </div>
                        <input type="file" name="admin_logo_file" class="form-control mb-2 js-image-file" accept="image/*" data-preview-target="admin_logo_path">
                        <div class="admin-current-path small text-muted mb-2"><span class="fw-semibold">{{ __('Current path') }}:</span> <span dir="ltr">{{ $resolvedAdminLogoPath ?: __('Not set') }}</span></div>
                        <details class="admin-manual-path"><summary>{{ __('Use a manual path instead') }}</summary><input type="text" id="admin_logo_path" name="admin_logo_path" value="{{ $adminLogoPath }}" class="form-control js-image-path mt-2" data-preview-target="admin_logo_path" placeholder="branding/admin-logo.png"></details>
                    </div>

                    <div class="admin-media-block mb-4">
                        <div class="admin-media-block__title">{{ __('Favicon') }}</div>
                        <div class="admin-media-preview admin-media-preview--favicon mb-3" data-preview-box="favicon_path" data-initial-src="{{ $faviconPreviewUrl ?? '' }}">
                            @if($faviconPreviewUrl)
                                <img src="{{ $faviconPreviewUrl }}" alt="{{ __('Favicon') }}" class="admin-thumb-favicon" data-preview-img="favicon_path">
                            @else
                                <div class="admin-empty-state py-4" data-preview-empty="favicon_path"><div class="admin-empty-icon"><i class="mdi mdi-earth"></i></div><p class="text-muted mb-0">{{ __('No favicon uploaded yet.') }}</p></div>
                                <img src="" alt="{{ __('Favicon') }}" class="admin-thumb-favicon d-none" data-preview-img="favicon_path">
                            @endif
                        </div>
                        <input type="file" name="favicon_file" class="form-control mb-2 js-image-file" accept=".ico,image/png,image/svg+xml,image/webp,image/jpeg,image/jpg" data-preview-target="favicon_path">
                        <div class="admin-current-path small text-muted mb-2"><span class="fw-semibold">{{ __('Current path') }}:</span> <span dir="ltr">{{ $resolvedFaviconPath ?: __('Not set') }}</span></div>
                        <details class="admin-manual-path"><summary>{{ __('Use a manual path instead') }}</summary><input type="text" id="favicon_path" name="favicon_path" value="{{ $faviconPath }}" class="form-control js-image-path mt-2" data-preview-target="favicon_path" placeholder="branding/favicon.ico"></details>
                    </div>

                    <div class="admin-media-block">
                        <div class="admin-media-block__title">{{ __('Hero banner') }}</div>
                        <div class="admin-media-preview mb-3" data-preview-box="hero_banner_path" data-initial-src="{{ $bannerPreviewUrl ?? '' }}">
                            @if($bannerPreviewUrl)
                                <img src="{{ $bannerPreviewUrl }}" alt="{{ __('Hero banner') }}" class="admin-thumb-lg admin-banner-thumb" data-preview-img="hero_banner_path">
                            @else
                                <div class="admin-empty-state py-4" data-preview-empty="hero_banner_path"><div class="admin-empty-icon"><i class="mdi mdi-image-area"></i></div><p class="text-muted mb-0">{{ __('No hero banner uploaded yet.') }}</p></div>
                                <img src="" alt="{{ __('Hero banner') }}" class="admin-thumb-lg admin-banner-thumb d-none" data-preview-img="hero_banner_path">
                            @endif
                        </div>
                        <input type="file" name="hero_banner_file" class="form-control mb-2 js-image-file" accept="image/*" data-preview-target="hero_banner_path">
                        <div class="admin-current-path small text-muted mb-2"><span class="fw-semibold">{{ __('Current path') }}:</span> <span dir="ltr">{{ $resolvedHeroBannerPath ?: __('Not set') }}</span></div>
                        <details class="admin-manual-path"><summary>{{ __('Use a manual path instead') }}</summary><input type="text" id="hero_banner_path" name="hero_banner_path" value="{{ $heroBannerPath }}" class="form-control js-image-path mt-2" data-preview-target="hero_banner_path" placeholder="branding/hero-banner.jpg"></details>
                    </div>
                    </div>
                    </div>
                </div>
            </div>

            <div class="admin-card" id="branding-panel-preview" role="tabpanel" aria-labelledby="branding-tab-preview" data-admin-section-panel="preview">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Live preview') }}</h4>
                            <div class="text-muted small">{{ __('Preview the visual direction before saving. Content is illustrative; only colors and shape settings are represented here.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Preview only') }}</span>
                    </div>
                    <div class="admin-theme-preview" id="adminThemePreview">
                        <div class="admin-theme-preview__sidebar">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="admin-theme-preview__content">
                            <div class="admin-theme-preview__header"></div>
                            <div class="admin-theme-preview__cards">
                                <div class="admin-theme-preview__card"></div>
                                <div class="admin-theme-preview__card"></div>
                            </div>
                        </div>
                    </div>
                    <div class="customer-theme-preview mt-3" id="customerThemePreview">
                        <div class="customer-theme-preview__nav"><span class="customer-theme-preview__logo"></span><strong data-preview-store-name>{{ old('store_name', $settings['store_name'] ?? __('Your store')) }}</strong><span class="customer-theme-preview__search"><i class="mdi mdi-magnify"></i></span></div>
                        <div class="customer-theme-preview__hero"><span class="customer-theme-preview__accent"></span><strong data-preview-hero-title>{{ old('hero_title', $settings['hero_title'] ?? __('Discover something new')) }}</strong><small data-preview-hero-subtitle>{{ old('hero_subtitle', $settings['hero_subtitle'] ?? __('A storefront preview that follows your brand direction.')) }}</small><span class="customer-theme-preview__hero-cta" data-preview-hero-cta>{{ old('hero_primary_button_text', $settings['hero_primary_button_text'] ?? __('Shop now')) }}</span></div>
                        <div class="customer-theme-preview__section-head"><span><strong>{{ __('Featured products') }}</strong><small>{{ __('Curated for your customers') }}</small></span><span class="customer-theme-preview__link">{{ __('View all') }}</span></div>
                        <div class="customer-theme-preview__products">
                            @foreach([__('Signature item'), __('New arrival')] as $previewProduct)
                                <div class="customer-theme-preview__product"><span class="customer-theme-preview__product-image"><i class="mdi mdi-shopping-outline"></i></span><span class="customer-theme-preview__product-copy"><span class="customer-theme-preview__badge" data-preview-badge>{{ $loop->first ? __('Featured') : __('New') }}</span><strong>{{ $previewProduct }}</strong><span class="customer-theme-preview__price">{{ $loop->first ? '$89' : '$64' }}</span></span></div>
                            @endforeach
                        </div>
                        <div class="customer-theme-preview__trust"><span><i class="mdi mdi-truck-fast-outline"></i>{{ __('Fast delivery') }}</span><span><i class="mdi mdi-shield-check-outline"></i>{{ __('Secure checkout') }}</span></div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <div class="admin-form-actions mt-4">
        <div class="admin-form-actions-copy">
            <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
            <div class="admin-form-actions-subtitle">{{ __('Review presets, colors, media assets, and live preview changes, then save your branding workspace when you are ready.') }}</div>
        </div>
        <div class="admin-form-actions-buttons">
            <span class="branding-save-state" id="brandingSaveState" data-clean-text="{{ __('No unsaved changes') }}" data-dirty-text="{{ __('Unsaved changes') }}">
                <i class="mdi mdi-check-circle-outline"></i>
                <span>{{ __('No unsaved changes') }}</span>
            </span>
            <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}">
                <i class="mdi mdi-content-save-outline"></i>
                <span>{{ __('Save branding') }}</span>
            </button>
        </div>
    </div>
</form>
</div>
@endsection

@push('styles')
<style>
.admin-section-columns{align-items:flex-start}
.branding-side-stack{display:grid;gap:1rem}
.branding-side-stack>.admin-card{margin-bottom:0!important}
[data-media-editor].is-compact .admin-media-block{display:none}
[data-media-editor].is-compact .admin-media-block:first-child{display:block;margin-bottom:0!important}
[data-media-editor].is-compact .admin-media-block:first-child .admin-current-path,
[data-media-editor].is-compact .admin-media-block:first-child .admin-manual-path{display:none}
@media(min-width:1200px){.branding-side-column{align-self:stretch}.branding-side-stack{display:block}.branding-side-stack>#branding-panel-media{margin-bottom:1rem!important}.branding-side-stack>#branding-panel-preview{position:sticky;top:1rem}}
.theme-gallery-tools{display:grid;gap:.8rem}
.theme-gallery-search{position:relative;max-width:420px}
.theme-gallery-search>i{position:absolute;inset-inline-start:.9rem;top:50%;transform:translateY(-50%);color:var(--admin-muted);z-index:2}
.theme-gallery-search .form-control{padding-inline-start:2.6rem}
.theme-gallery-filters{display:flex;gap:.5rem;flex-wrap:wrap}
.theme-filter-chip{border:1px solid var(--admin-border);background:var(--admin-surface);color:var(--admin-muted);border-radius:999px;padding:.45rem .78rem;font-size:.76rem;font-weight:800;transition:.18s ease}
.theme-filter-chip:hover,.theme-filter-chip.is-active{border-color:var(--admin-primary);background:var(--admin-primary-soft);color:var(--admin-primary-dark)}
.theme-gallery-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:.25rem;padding:2rem;border:1px dashed var(--admin-border);border-radius:1rem;color:var(--admin-muted)}
.theme-gallery-empty>i{font-size:1.8rem;color:var(--admin-primary)}
.theme-gallery-empty strong{color:var(--admin-text)}
.theme-preset-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
.theme-preset-card{appearance:none;width:100%;padding:0;overflow:hidden;text-align:start;border:1px solid var(--admin-border);border-radius:1.15rem;background:var(--admin-surface);color:var(--admin-text);box-shadow:0 12px 28px color-mix(in srgb,var(--admin-text) 5%,transparent);transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
.theme-preset-card:hover{transform:translateY(-2px);border-color:color-mix(in srgb,var(--admin-primary) 42%,var(--admin-border));box-shadow:0 16px 34px color-mix(in srgb,var(--admin-primary) 11%,transparent)}
.theme-preset-card:focus-visible{outline:0;box-shadow:0 0 0 4px color-mix(in srgb,var(--admin-primary) 18%,transparent),0 16px 34px color-mix(in srgb,var(--admin-primary) 11%,transparent)}
.theme-preset-card.is-active{border-color:var(--admin-primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--admin-primary) 14%,transparent),0 16px 34px color-mix(in srgb,var(--admin-primary) 12%,transparent)}
.theme-preset-card__visual{display:grid;grid-template-columns:28% 1fr;height:86px;background:var(--preset-bg)}
.theme-preset-card__sidebar{background:var(--preset-secondary)}
.theme-preset-card__surface{display:block;position:relative;background:var(--preset-surface);margin:10px;border-radius:.65rem;border:1px solid color-mix(in srgb,var(--preset-primary) 15%,#e2e8f0)}
.theme-preset-card__bar{display:block;height:13px;background:color-mix(in srgb,var(--preset-primary) 16%,var(--preset-surface));border-radius:.55rem .55rem 0 0;border-bottom:1px solid color-mix(in srgb,var(--preset-primary) 12%,#e2e8f0)}
.theme-preset-card__tile{display:block;width:52%;height:25px;margin:10px;border-radius:.5rem;background:linear-gradient(135deg,var(--preset-primary),color-mix(in srgb,var(--preset-primary) 36%,var(--preset-secondary)));opacity:.82}
.theme-preset-card__body{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;padding:.8rem .9rem}
.theme-preset-card__name{display:grid;gap:.12rem;font-weight:850;margin-inline-end:auto}
.theme-preset-card__name small{font-size:.68rem;font-weight:650;color:var(--admin-muted);line-height:1.2}
.theme-preset-card__swatches{display:inline-flex;gap:.25rem}
.theme-preset-card__swatches i{display:block;width:.82rem;height:.82rem;border-radius:999px;border:1px solid rgba(15,23,42,.1)}
.theme-preset-card__recommended,.theme-preset-card__custom{font-size:.68rem;font-weight:850;padding:.24rem .5rem;border-radius:999px}
.theme-preset-card__recommended{background:#ecfdf5;color:#047857}
.theme-preset-card__custom{background:var(--admin-primary-soft);color:var(--admin-primary-dark)}
.branding-advanced-palette{border:1px solid var(--admin-border);border-radius:1rem;background:color-mix(in srgb,var(--admin-surface) 96%,var(--admin-primary-soft));padding:.2rem 1rem 1rem}
.branding-advanced-palette summary{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 0;list-style:none}
.branding-advanced-palette summary::-webkit-details-marker{display:none}
.branding-advanced-palette summary span{display:grid;gap:.16rem}
.branding-advanced-palette summary small{color:var(--admin-muted);font-weight:500}
.branding-advanced-palette summary i{font-size:1.1rem;transition:transform .18s ease}
.branding-advanced-palette[open] summary i{transform:rotate(180deg)}
.homepage-order-editor{display:grid;gap:.55rem}.homepage-order-item{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.75rem;padding:.7rem .8rem;border:1px solid var(--admin-border);border-radius:.9rem;background:var(--admin-surface)}.homepage-order-handle{color:var(--admin-muted);font-size:1.2rem}.homepage-order-copy{display:grid;gap:.08rem}.homepage-order-copy strong{font-size:.86rem}.homepage-order-copy small{font-size:.7rem;color:var(--admin-muted)}.homepage-order-actions{display:flex;gap:.35rem}.homepage-order-actions .btn{width:32px;height:32px;padding:0;display:grid;place-items:center}html[dir="rtl"] .homepage-order-copy{text-align:right}
.branding-inline-switch,.branding-compact-switch{display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid var(--admin-border);border-radius:.9rem;background:var(--admin-surface);cursor:pointer}.branding-inline-switch{padding:.75rem .85rem}.branding-inline-switch>span:first-child{display:grid;gap:.12rem}.branding-inline-switch strong{font-size:.84rem}.branding-inline-switch small{font-size:.72rem;color:var(--admin-muted)}.branding-compact-switch{width:100%;min-height:42px;padding:.55rem .7rem;font-weight:750;font-size:.8rem}html[dir="rtl"] .branding-inline-switch,html[dir="rtl"] .branding-compact-switch{text-align:right}
.branding-toggle-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:1rem;min-height:58px;padding:.8rem .9rem;border:1px solid var(--admin-border);border-radius:1rem;background:color-mix(in srgb,var(--admin-surface) 96%,var(--admin-primary-soft))}
.branding-toggle-copy{margin:0;color:var(--admin-text);font-weight:750;line-height:1.4;cursor:pointer}
.branding-toggle-control{padding:0!important;margin:0!important;min-height:0}
.branding-toggle-control .form-check-input{float:none!important;margin:0!important;cursor:pointer}
html[dir="rtl"] .branding-toggle-row{text-align:right}
.branding-save-state{display:inline-flex;align-items:center;gap:.42rem;padding:.52rem .72rem;border-radius:999px;background:#f0fdf4;color:#166534;font-size:.78rem;font-weight:800;white-space:nowrap}
.branding-save-state.is-dirty{background:#fffbeb;color:#92400e}
.admin-color-field{display:flex;gap:.75rem;align-items:center}
.admin-current-path{word-break:break-word}
.admin-manual-path{margin-top:.25rem}
.admin-manual-path summary{cursor:pointer;color:var(--admin-primary-dark);font-weight:700}
.admin-color-field .form-control-color{width:72px;min-width:72px;height:48px;padding:.35rem;border-radius:.9rem}
.admin-color-code{text-transform:uppercase}
.admin-media-preview{position:relative;min-height:180px;border:1px dashed var(--admin-border);border-radius:1rem;padding:.5rem;background:color-mix(in srgb,var(--admin-surface) 92%,var(--admin-primary-soft))}
.admin-media-preview img{width:100%;height:180px;object-fit:cover;border-radius:1rem}
.admin-media-preview--favicon{min-height:120px;display:flex;align-items:center;justify-content:center}
.admin-media-preview--favicon img{width:72px;height:72px;object-fit:contain;border-radius:1rem}
.admin-thumb-favicon{width:72px;height:72px;object-fit:contain;border-radius:1rem}
.admin-banner-thumb{height:220px}
.admin-media-block__title{font-weight:800;margin-bottom:.65rem}
.admin-promo-card{padding:1rem;border:1px solid var(--admin-border);border-radius:1.1rem;background:color-mix(in srgb,var(--admin-surface) 96%,var(--admin-primary-soft))}
.admin-theme-preview{display:grid;grid-template-columns:90px 1fr;overflow:hidden;border-radius:1.25rem;border:1px solid var(--preview-admin-border,#e2e8f0);min-height:180px;background:var(--preview-admin-surface,#fff)}
.admin-theme-preview__sidebar{background:var(--preview-sidebar,#0b1220);padding:1rem .75rem;display:grid;align-content:start;gap:.65rem}
.admin-theme-preview__sidebar span{display:block;height:9px;border-radius:999px;background:rgba(255,255,255,.2)}
.admin-theme-preview__sidebar span:first-child{width:75%;background:rgba(255,255,255,.72)}
.admin-theme-preview__content{background:var(--preview-admin-surface,#fff)}
.admin-theme-preview__header{height:52px;background:var(--preview-header,#fff);border-bottom:1px solid var(--preview-admin-border,#e2e8f0)}
.admin-theme-preview__cards{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;padding:1rem}
.admin-theme-preview__card{height:72px;border-radius:1rem;background:linear-gradient(135deg,var(--preview-primary,#2563eb),var(--preview-secondary,#0f172a));opacity:.14;border:1px solid var(--preview-admin-border,#e2e8f0)}
.customer-theme-preview{--preview-control-radius:14px;--preview-media-radius:14px;--preview-lift:-4px;padding:1rem;border:1px solid var(--preview-border,#dbe3ef);border-radius:1.25rem;background:var(--preview-bg,#f8fafc);overflow:hidden}
.customer-theme-preview__nav{display:flex;align-items:center;gap:.6rem;padding:.15rem 0 .85rem;color:var(--preview-secondary,#0f172a)}.customer-theme-preview__nav strong{font-size:.78rem;max-width:42%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.customer-theme-preview__logo{width:30px;height:30px;flex:0 0 30px;border-radius:9px;background:linear-gradient(135deg,var(--preview-primary,#2563eb),var(--preview-secondary,#0f172a))}
.customer-theme-preview__search{margin-inline-start:auto;width:34px;height:28px;border-radius:999px;display:grid;place-items:center;background:var(--preview-admin-surface,#fff);border:1px solid var(--preview-border,#dbe3ef);color:var(--preview-primary,#2563eb)}
.customer-theme-preview__hero{min-height:132px;border-radius:var(--preview-control-radius);background:linear-gradient(135deg,color-mix(in srgb,var(--preview-primary,#2563eb) 20%,white),color-mix(in srgb,var(--preview-secondary,#0f172a) 13%,white));margin-bottom:1rem;padding:1rem;display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:.38rem;color:var(--preview-secondary,#0f172a)}
.customer-theme-preview__accent{display:block;width:2.4rem;height:.3rem;border-radius:999px;background:var(--preview-accent,#0891b2)}.customer-theme-preview__hero strong{font-size:1rem;line-height:1.15;max-width:90%}.customer-theme-preview__hero small{font-size:.66rem;line-height:1.35;opacity:.7;max-width:90%}
.customer-theme-preview__hero-cta{display:inline-flex;margin-top:.25rem;padding:.42rem .7rem;border-radius:var(--preview-control-radius);background:var(--preview-primary,#2563eb);color:var(--preview-button-text,#fff);font-size:.65rem;font-weight:850}
.customer-theme-preview__section-head{display:flex;justify-content:space-between;align-items:end;gap:.5rem;margin-bottom:.65rem;color:var(--preview-secondary,#0f172a)}.customer-theme-preview__section-head>span:first-child{display:grid}.customer-theme-preview__section-head strong{font-size:.76rem}.customer-theme-preview__section-head small{font-size:.58rem;opacity:.62}.customer-theme-preview__link{font-size:.6rem;font-weight:800;color:var(--preview-primary,#2563eb)}
.customer-theme-preview__products{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}.customer-theme-preview__product{display:flex;flex-direction:column;gap:.5rem;padding:.55rem;border-radius:var(--preview-control-radius);background:var(--preview-admin-surface,#fff);border:1px solid var(--preview-border,#dbe3ef);min-width:0}.customer-theme-preview__product-image{height:64px;border-radius:var(--preview-media-radius);display:grid;place-items:center;background:linear-gradient(145deg,var(--preview-soft,#eff6ff),color-mix(in srgb,var(--preview-accent,#0891b2) 13%,white));color:var(--preview-primary,#2563eb);font-size:1.25rem}.customer-theme-preview__product-copy{display:grid;gap:.18rem;min-width:0;color:var(--preview-secondary,#0f172a)}.customer-theme-preview__product-copy strong{font-size:.66rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.customer-theme-preview__price{font-size:.72rem;font-weight:900;color:var(--preview-primary,#2563eb)}
.customer-theme-preview__badge{width:max-content;display:inline-flex;padding:.18rem .38rem;border-radius:.45rem;background:var(--preview-soft,#eff6ff);border:1px solid var(--preview-border,#dbe3ef);font-size:.5rem;font-weight:800;color:var(--preview-primary,#2563eb)}.customer-theme-preview__badge[data-style="pill"]{border-radius:999px}.customer-theme-preview__badge[data-style="outline"]{background:transparent;border-color:var(--preview-primary,#2563eb)}.customer-theme-preview__badge[data-style="soft"]{border-color:color-mix(in srgb,var(--preview-primary,#2563eb) 18%,var(--preview-border,#dbe3ef))}
.customer-theme-preview__trust{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.75rem;padding-top:.65rem;border-top:1px solid var(--preview-border,#dbe3ef)}.customer-theme-preview__trust span{display:inline-flex;align-items:center;gap:.25rem;font-size:.54rem;font-weight:750;color:var(--preview-secondary,#0f172a);opacity:.75}.customer-theme-preview__trust i{color:var(--preview-primary,#2563eb)}
.admin-promo-meta{display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;min-height:100%;padding:1.25rem;border:1px dashed var(--admin-border);border-radius:1rem;background:color-mix(in srgb,var(--admin-surface) 92%,var(--admin-primary-soft))}
.admin-promo-meta__icon{width:72px;height:72px;border-radius:1.1rem;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--admin-accent-soft) 80%,white);color:var(--admin-primary-dark);font-size:1.65rem}
@media(max-width:1199.98px){.theme-preset-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.branding-side-stack{display:grid;grid-template-columns:1fr 1fr;align-items:start}.branding-side-stack>#branding-panel-media,.branding-side-stack>#branding-panel-preview{margin-bottom:0!important}}
@media(max-width:767.98px){.branding-side-stack{grid-template-columns:1fr}.branding-side-stack>#branding-panel-media{margin-bottom:1rem!important}}
@media(max-width:575.98px){.theme-preset-grid{grid-template-columns:1fr}.branding-save-state{width:100%;justify-content:center}.admin-form-actions-buttons{width:100%}.admin-form-actions-buttons .btn{width:100%;justify-content:center}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[data-admin-section-tabs="branding"]');
    const saveState = document.getElementById('brandingSaveState');
    const presets = @json($presets);
    const presetSelect = document.getElementById('theme_preset');
    const errorFields = (() => {
        try { return JSON.parse(form?.dataset.adminErrorFields || '[]'); } catch (_) { return []; }
    })();
    const errorSectionPrefixes = {
        theme: ['theme_preset', 'save_as_custom_theme', 'custom_theme_name'],
        identity: ['default_locale', 'project_name', 'store_name', 'store_tagline', 'footer_'],
        colors: ['brand_', 'customer_'],
        homepage: ['hero_', 'show_home_', 'home_featured_', 'home_manual_', 'home_categories_', 'home_latest_', 'home_best_', 'home_on_sale_', 'homepage_sections_order'],
        promos: ['promo_banner_'],
        trust: ['trust_block_', 'home_trust_'],
        admin: ['admin_'],
        media: ['logo_', 'favicon_', 'hero_banner_', 'admin_logo_'],
    };
    let isDirty = false;

    function revealValidationError() {
        if (!form || !errorFields.length) return;
        const firstField = errorFields[0];
        const section = Object.entries(errorSectionPrefixes).find(([, prefixes]) => prefixes.some((prefix) => firstField === prefix || firstField.startsWith(prefix)))?.[0];
        const tab = section ? document.querySelector('[data-admin-section-tab="' + section + '"]') : null;
        tab?.click();

        const escaped = window.CSS?.escape ? CSS.escape(firstField) : firstField.replace(/"/g, '\\"');
        const field = form.querySelector('[name="' + escaped + '"]') || document.getElementById(firstField);
        if (field) {
            field.setAttribute('aria-invalid', 'true');
            window.setTimeout(() => {
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.focus({ preventScroll: true });
            }, 80);
        }
    }

    function setDirtyState(dirty) {
        isDirty = dirty;
        if (!saveState) return;
        const text = saveState.querySelector('span');
        const icon = saveState.querySelector('i');
        saveState.classList.toggle('is-dirty', dirty);
        if (text) text.textContent = dirty ? saveState.dataset.dirtyText : saveState.dataset.cleanText;
        if (icon) icon.className = dirty ? 'mdi mdi-pencil-circle-outline' : 'mdi mdi-check-circle-outline';
    }

    function syncColor(picker) {
        const target = document.getElementById(picker.getAttribute('data-sync-color'));
        if (!target) return;
        picker.addEventListener('input', () => {
            target.value = picker.value;
            updatePreview();
        });
        target.addEventListener('input', () => {
            if (/^#(?:[0-9a-fA-F]{3}){1,2}$/.test(target.value)) {
                picker.value = target.value;
                updatePreview();
            }
        });
    }

    function setActivePresetCard(key) {
        document.querySelectorAll('[data-theme-preset-choice]').forEach((card) => {
            const active = card.dataset.themePresetChoice === key;
            card.classList.toggle('is-active', active);
            card.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function applyPreset(key, markDirty = true) {
        const preset = presets[key];
        if (!preset) return;
        Object.entries(preset).forEach(([field, value]) => {
            const input = document.getElementById(field);
            if (input) input.value = value;
            const picker = document.querySelector(`[data-sync-color="${field}"]`);
            if (picker && /^#/.test(String(value))) picker.value = value;
            const named = form?.querySelector(`[name="${field}"]`);
            if (named && named !== input) named.value = value;
        });
        if (presetSelect) presetSelect.value = key;
        setActivePresetCard(key);
        presetSelect?.setAttribute('data-applied-preset', key);
        const profile = document.querySelector('[data-theme-preset-choice="' + key + '"]')?.dataset.themeProfile || 'balanced';
        const profileTokens = { refined:['8px','6px','-2px'], sharp:['10px','8px','-3px'], playful:['18px','20px','-5px'], organic:['14px','16px','-3px'], balanced:['14px','14px','-4px'] };
        const tokens = profileTokens[profile] || profileTokens.balanced;
        const preview = document.getElementById('customerThemePreview');
        preview?.style.setProperty('--preview-control-radius', tokens[0]);
        preview?.style.setProperty('--preview-media-radius', tokens[1]);
        preview?.style.setProperty('--preview-lift', tokens[2]);
        updatePreview();
        if (markDirty) setDirtyState(true);
    }

    function updatePreview() {
        const root = document.documentElement;
        const set = (key, id, fallback) => root.style.setProperty(key, document.getElementById(id)?.value || fallback);
        set('--preview-primary', 'brand_primary_color', '#2563eb');
        set('--preview-secondary', 'brand_secondary_color', '#0f172a');
        set('--preview-accent', 'brand_accent_color', '#0891b2');
        set('--preview-bg', 'brand_background_color', '#f8fafc');
        set('--preview-soft', 'brand_soft_color', '#eff6ff');
        set('--preview-border', 'brand_border_color', '#dbe3ef');
        set('--preview-button-text', 'brand_button_text_color', '#ffffff');
        set('--preview-sidebar', 'admin_sidebar_color', '#0b1220');
        set('--preview-header', 'admin_header_color', '#ffffff');
        set('--preview-admin-surface', 'admin_surface_color', '#ffffff');
        set('--preview-admin-border', 'admin_card_border_color', '#e2e8f0');
        root.style.setProperty('--preview-radius', form?.querySelector('[name="customer_card_radius"]')?.value || 18);
        document.querySelectorAll('[data-preview-badge]').forEach((badge) => badge.dataset.style = form?.querySelector('[name="customer_badge_style"]')?.value || 'soft');
        const copy = (name, selector, fallback) => { const input = form?.querySelector('[name="' + name + '"]'); const target = document.querySelector(selector); if (target) target.textContent = (input?.value || '').trim() || fallback; };
        copy('store_name', '[data-preview-store-name]', @json(__('Your store')));
        copy('hero_title', '[data-preview-hero-title]', @json(__('Discover something new')));
        copy('hero_subtitle', '[data-preview-hero-subtitle]', @json(__('A storefront preview that follows your brand direction.')));
        copy('hero_primary_button_text', '[data-preview-hero-cta]', @json(__('Shop now')));
    }

    function updateImagePreview(targetKey, src) {
        const img = document.querySelector(`[data-preview-img="${targetKey}"]`);
        const empty = document.querySelector(`[data-preview-empty="${targetKey}"]`);
        if (!img) return;
        if (src) {
            img.src = src;
            img.classList.remove('d-none');
            empty?.classList.add('d-none');
        } else {
            img.src = '';
            img.classList.add('d-none');
            empty?.classList.remove('d-none');
        }
    }

    const mediaEditor = document.querySelector('[data-media-editor]');
    const mediaDensityToggle = document.querySelector('[data-media-density-toggle]');
    mediaDensityToggle?.addEventListener('click', function () {
        const compact = !mediaEditor?.classList.contains('is-compact');
        mediaEditor?.classList.toggle('is-compact', compact);
        this.setAttribute('aria-expanded', compact ? 'false' : 'true');
        const label = this.querySelector('span');
        const icon = this.querySelector('i');
        if (label) label.textContent = compact ? @json(__('Expand media')) : @json(__('Compact media'));
        if (icon) icon.className = compact ? 'mdi mdi-unfold-more-horizontal' : 'mdi mdi-unfold-less-horizontal';
    });

    revealValidationError();
        document.querySelectorAll('[data-sync-color]').forEach(syncColor);

    presetSelect?.addEventListener('change', function () {
        applyPreset(this.value);
    });

    document.querySelectorAll('[data-theme-preset-choice]').forEach((card) => {
        card.addEventListener('click', function () {
            applyPreset(this.dataset.themePresetChoice);
        });
    });

    const homepageOrderEditor = document.getElementById('homepageOrderEditor');
    const homepageOrderInput = document.getElementById('homepage_sections_order');
    function syncHomepageOrder() {
        if (!homepageOrderEditor || !homepageOrderInput) return;
        homepageOrderInput.value = [...homepageOrderEditor.querySelectorAll('[data-home-section]')].map((item) => item.dataset.homeSection).join(',');
        setDirtyState(true);
    }
    homepageOrderEditor?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-home-move]');
        if (!button) return;
        const item = button.closest('[data-home-section]');
        if (!item) return;
        if (button.dataset.homeMove === 'up' && item.previousElementSibling) homepageOrderEditor.insertBefore(item, item.previousElementSibling);
        if (button.dataset.homeMove === 'down' && item.nextElementSibling) homepageOrderEditor.insertBefore(item.nextElementSibling, item);
        syncHomepageOrder();
    });

    const themeSearch = document.getElementById('themeGallerySearch');
    const themeCount = document.getElementById('themeGalleryCount');
    const themeEmpty = document.getElementById('themeGalleryEmpty');
    let activeThemeFilter = 'all';

    function filterThemeGallery() {
        const query = (themeSearch?.value || '').trim().toLocaleLowerCase();
        let visible = 0;
        document.querySelectorAll('[data-theme-preset-choice]').forEach((card) => {
            const matchesCategory = activeThemeFilter === 'all' || card.dataset.themeCategory === activeThemeFilter;
            const matchesQuery = !query || (card.dataset.themeSearch || '').toLocaleLowerCase().includes(query);
            const show = matchesCategory && matchesQuery;
            card.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        if (themeCount) themeCount.textContent = visible;
        themeEmpty?.classList.toggle('d-none', visible !== 0);
    }

    themeSearch?.addEventListener('input', filterThemeGallery);
    document.querySelectorAll('[data-theme-filter]').forEach((chip) => {
        chip.addEventListener('click', function () {
            activeThemeFilter = this.dataset.themeFilter || 'all';
            document.querySelectorAll('[data-theme-filter]').forEach((item) => item.classList.toggle('is-active', item === this));
            filterThemeGallery();
        });
    });

    document.getElementById('reapplyThemePreset')?.addEventListener('click', function () {
        if (presetSelect?.value) applyPreset(presetSelect.value);
    });

    document.querySelectorAll('.js-image-file').forEach(input => {
        input.addEventListener('change', function () {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) return;
            updateImagePreview(this.dataset.previewTarget, URL.createObjectURL(file));
        });
    });

    document.querySelectorAll('.js-image-path').forEach(input => {
        input.addEventListener('input', function () {
            const value = (this.value || '').trim();
            const targetKey = this.dataset.previewTarget;
            const previewBox = targetKey ? document.querySelector(`[data-preview-box="${targetKey}"]`) : null;
            const initialSrc = (previewBox?.dataset.initialSrc || '').trim();

            if (!value) {
                updateImagePreview(targetKey, initialSrc || '');
                return;
            }

            const normalized = value.replace(/^\//, '');
            const clean = normalized.replace(/^public\//, '').replace(/^storage\//, '');
            const filename = normalized.split('/').pop();
            const brandingRoute = `{{ url('/branding-media') }}/${clean}`;
            const tries = [brandingRoute, normalized, `/${normalized}`, `/${clean}`, `/storage/${clean}`, `/uploads/${filename}`, `/assets/img/${filename}`, initialSrc].filter(Boolean);
            updateImagePreview(targetKey, tries[0] || '');
        });
    });

    document.querySelectorAll('.admin-media-preview').forEach((box) => {
        const targetKey = box.dataset.previewBox;
        const initialSrc = (box.dataset.initialSrc || '').trim();
        if (targetKey && initialSrc) updateImagePreview(targetKey, initialSrc);
    });

    document.querySelectorAll('[data-preview-img]').forEach((img) => {
        img.addEventListener('error', function () {
            const targetKey = this.dataset.previewImg;
            const box = targetKey ? document.querySelector(`[data-preview-box="${targetKey}"]`) : null;
            const initialSrc = (box?.dataset.initialSrc || '').trim();
            if (initialSrc && this.src !== initialSrc) {
                updateImagePreview(targetKey, initialSrc);
                return;
            }
            updateImagePreview(targetKey, '');
        });
    });

    form?.querySelectorAll('input, select, textarea').forEach((element) => {
        element.addEventListener('input', updatePreview);
        element.addEventListener('change', updatePreview);
    });

    form?.addEventListener('input', () => setDirtyState(true));
    form?.addEventListener('change', () => setDirtyState(true));
    form?.addEventListener('submit', () => setDirtyState(false));

    window.addEventListener('beforeunload', (event) => {
        if (!isDirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    setActivePresetCard(presetSelect?.value || '');
    presetSelect?.setAttribute('data-applied-preset', presetSelect?.value || '');
    setDirtyState(false);
    updatePreview();
});
</script>
@endpush
