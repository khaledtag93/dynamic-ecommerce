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
    $selectedPreset = old('theme_preset', $settings['theme_preset'] ?? 'sunset_bakery');
    $selectedPresetLabel = __($presets[$selectedPreset]['theme_label'] ?? Str::headline(str_replace('_', ' ', $selectedPreset)));
@endphp

@section('content')
<x-admin.page-header :kicker="__('Settings')" :title="__('Branding & Appearance')" :description="__('Control brand identity, colors, and storefront visuals with a cleaner bilingual settings experience.')" />

<div class="admin-page-shell settings-page">
<form class="admin-form-shell" method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" data-submit-loading>
    @csrf
    @method('PUT')

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-palette-outline"></i></span>
                <div class="admin-stat-label">{{ __('Default theme') }}</div>
                <div class="admin-stat-value">{{ $selectedPresetLabel }}</div>
                <div class="text-muted small mt-2">{{ __('This is the active theme that both admin and storefront pages use after saving.') }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-image-multiple-outline"></i></span>
                <div class="admin-stat-label">{{ __('Promo banners') }}</div>
                <div class="admin-stat-value">3</div>
                <div class="text-muted small mt-2">{{ __('Phase 2 supports three editable promo banners with images, text, links, and sort order.') }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-content-save-cog-outline"></i></span>
                <div class="admin-stat-label">{{ __('Saved themes') }}</div>
                <div class="admin-stat-value">{{ count($customThemes ?? []) }}</div>
                <div class="text-muted small mt-2">{{ __('Save your own theme after adjusting the colors manually.') }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Theme presets') }}</h4>
                            <div class="text-muted small">{{ __('Start from a ready-made design direction, then customize every important color.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Admin + Customer') }}</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Default theme') }}</label>
                            <select class="form-select" name="theme_preset" id="theme_preset">
                                @foreach($presets as $presetKey => $preset)
                                    <option value="{{ $presetKey }}" @selected($selectedPreset === $presetKey)>
                                        {{ __($preset['theme_label'] ?? Str::headline(str_replace('_', ' ', $presetKey))) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Default language') }}</label>
                            <select class="form-select" name="default_locale">
                                <option value="ar" @selected(old('default_locale', $settings['default_locale'] ?? 'ar') === 'ar')>{{ __('Arabic') }}</option>
                                <option value="en" @selected(old('default_locale', $settings['default_locale'] ?? 'ar') === 'en')>{{ __('English') }}</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">{{ __('Save current colors as a new theme') }}</label>
                            <input type="text" name="custom_theme_name" class="form-control" value="{{ old('custom_theme_name') }}" placeholder="{{ __('Example: Green Fashion') }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch pb-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="save_as_custom_theme" value="1" @checked(old('save_as_custom_theme'))>
                                <label class="form-check-label ms-2">{{ __('Save as custom theme') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card mb-4">
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

            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Customer branding') }}</h4>
                    <div class="row g-3">
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Primary color'),'name'=>'brand_primary_color','value'=>old('brand_primary_color',$settings['brand_primary_color'] ?? '#f97316')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Secondary color'),'name'=>'brand_secondary_color','value'=>old('brand_secondary_color',$settings['brand_secondary_color'] ?? '#ec4899')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Accent color'),'name'=>'brand_accent_color','value'=>old('brand_accent_color',$settings['brand_accent_color'] ?? '#fb923c')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Background color'),'name'=>'brand_background_color','value'=>old('brand_background_color',$settings['brand_background_color'] ?? '#fffaf5')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Surface color'),'name'=>'brand_surface_color','value'=>old('brand_surface_color',$settings['brand_surface_color'] ?? '#ffffff')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Soft background color'),'name'=>'brand_soft_color','value'=>old('brand_soft_color',$settings['brand_soft_color'] ?? '#fff7ed')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Border color'),'name'=>'brand_border_color','value'=>old('brand_border_color',$settings['brand_border_color'] ?? '#fed7aa')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Muted background color'),'name'=>'brand_muted_bg_color','value'=>old('brand_muted_bg_color',$settings['brand_muted_bg_color'] ?? '#fff1f2')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Table header color'),'name'=>'brand_table_head_color','value'=>old('brand_table_head_color',$settings['brand_table_head_color'] ?? '#fff4ec')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Row hover color'),'name'=>'brand_row_hover_color','value'=>old('brand_row_hover_color',$settings['brand_row_hover_color'] ?? '#fffaf6')])</div>
                        <div class="col-md-4">@include('admin.settings.partials.color-field',['label'=>__('Button text color'),'name'=>'brand_button_text_color','value'=>old('brand_button_text_color',$settings['brand_button_text_color'] ?? '#ffffff')])</div>
                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Radius') }}</label><input type="number" min="8" max="40" name="customer_card_radius" value="{{ old('customer_card_radius', $settings['customer_card_radius'] ?? 20) }}" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Badge style') }}</label><input type="text" name="customer_badge_style" value="{{ old('customer_badge_style', $settings['customer_badge_style'] ?? 'pill') }}" class="form-control"></div>
                    </div>
                </div>
            </div>

            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Homepage CMS') }}</h4>
                    <div class="row g-3">
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_hero" value="1" @checked(old('show_home_hero', $settings['show_home_hero'] ?? true))><label class="form-check-label ms-2">{{ __('Show hero') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_categories" value="1" @checked(old('show_home_categories', $settings['show_home_categories'] ?? true))><label class="form-check-label ms-2">{{ __('Show categories') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_featured_categories" value="1" @checked(old('show_home_featured_categories', $settings['show_home_featured_categories'] ?? true))><label class="form-check-label ms-2">{{ __('Show featured categories') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_featured_products" value="1" @checked(old('show_home_featured_products', $settings['show_home_featured_products'] ?? true))><label class="form-check-label ms-2">{{ __('Show featured') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_latest_products" value="1" @checked(old('show_home_latest_products', $settings['show_home_latest_products'] ?? true))><label class="form-check-label ms-2">{{ __('Show latest') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_best_sellers" value="1" @checked(old('show_home_best_sellers', $settings['show_home_best_sellers'] ?? true))><label class="form-check-label ms-2">{{ __('Show best sellers') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_on_sale_products" value="1" @checked(old('show_home_on_sale_products', $settings['show_home_on_sale_products'] ?? true))><label class="form-check-label ms-2">{{ __('Show on sale') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_manual_featured_products" value="1" @checked(old('show_home_manual_featured_products', $settings['show_home_manual_featured_products'] ?? false))><label class="form-check-label ms-2">{{ __('Show manual featured') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_promo_banners" value="1" @checked(old('show_home_promo_banners', $settings['show_home_promo_banners'] ?? true))><label class="form-check-label ms-2">{{ __('Show promo banners') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_trust_blocks" value="1" @checked(old('show_home_trust_blocks', $settings['show_home_trust_blocks'] ?? true))><label class="form-check-label ms-2">{{ __('Show trust blocks') }}</label></div></div>
                        <div class="col-md-3"><div class="form-check form-switch pt-4"><input class="form-check-input" type="checkbox" name="show_home_promo_banner" value="1" @checked(old('show_home_promo_banner', $settings['show_home_promo_banner'] ?? false))><label class="form-check-label ms-2">{{ __('Keep legacy promo block') }}</label></div></div>

                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Homepage sections order') }}</label><input type="text" name="homepage_sections_order" value="{{ old('homepage_sections_order', $settings['homepage_sections_order'] ?? 'hero,promo_banners,featured_categories,manual_featured_products,featured_products,best_sellers,latest_products,on_sale_products,trust_blocks,categories') }}" class="form-control" placeholder="hero,promo_banners,featured_categories,manual_featured_products,featured_products,best_sellers,latest_products,on_sale_products,trust_blocks,categories"><div class="form-text">{{ __('Use comma-separated section keys: hero, promo_banners, featured_categories, manual_featured_products, featured_products, best_sellers, categories, latest_products, on_sale_products, trust_blocks.') }}</div></div>

                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Hero badge text') }}</label><input type="text" name="hero_badge_text" value="{{ old('hero_badge_text', $settings['hero_badge_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Hero primary button text') }}</label><input type="text" name="hero_primary_button_text" value="{{ old('hero_primary_button_text', $settings['hero_primary_button_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Hero primary button link') }}</label><input type="text" name="hero_primary_button_link" value="{{ old('hero_primary_button_link', $settings['hero_primary_button_link'] ?? '#featured-products') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Hero secondary button text') }}</label><input type="text" name="hero_secondary_button_text" value="{{ old('hero_secondary_button_text', $settings['hero_secondary_button_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Hero secondary button link') }}</label><input type="text" name="hero_secondary_button_link" value="{{ old('hero_secondary_button_link', $settings['hero_secondary_button_link'] ?? '#categories') }}" class="form-control"></div>

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

                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-12"><div class="fw-bold">{{ __('Phase 2.3 — Manual featured products') }}</div><div class="form-text mt-0">{{ __('Add product IDs in the exact order you want them to appear.') }}</div></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Manual featured title') }}</label><input type="text" name="home_manual_featured_products_title" value="{{ old('home_manual_featured_products_title', $settings['home_manual_featured_products_title'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Manual featured subtitle') }}</label><input type="text" name="home_manual_featured_products_subtitle" value="{{ old('home_manual_featured_products_subtitle', $settings['home_manual_featured_products_subtitle'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Manual featured limit') }}</label><input type="number" min="1" max="24" name="home_manual_featured_products_limit" value="{{ old('home_manual_featured_products_limit', $settings['home_manual_featured_products_limit'] ?? 8) }}" class="form-control"></div>
                        <div class="col-md-8"><label class="form-label fw-semibold">{{ __('Manual featured product IDs') }}</label><textarea name="home_manual_featured_products_ids" class="form-control" rows="3" placeholder="12,8,31,5">{{ old('home_manual_featured_products_ids', $settings['home_manual_featured_products_ids'] ?? '') }}</textarea></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Action text') }}</label><input type="text" name="home_manual_featured_products_action_text" value="{{ old('home_manual_featured_products_action_text', $settings['home_manual_featured_products_action_text'] ?? '') }}" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Action link') }}</label><input type="text" name="home_manual_featured_products_action_link" value="{{ old('home_manual_featured_products_action_link', $settings['home_manual_featured_products_action_link'] ?? '#latest-products') }}" class="form-control"></div>

                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-12"><div class="fw-bold">{{ __('Phase 2.3 — Trust section heading') }}</div></div>
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
            </div>

            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Promo banners') }}</h4>
                            <div class="text-muted small">{{ __('Each banner supports image, title, subtitle, button text, button link, active state, and sort order.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Phase 2.1') }}</span>
                    </div>

                    @for($i = 1; $i <= 3; $i++)
                        @php
                            $promoPath = old("promo_banner_{$i}_image_path", $settings["promo_banner_{$i}_image_path"] ?? '');
                            $resolvedPromoPath = AdminBranding::resolveMediaPath($promoPath, 'promo_banner');
                            $promoPreview = AdminBranding::mediaUrl($promoPath, 'promo_banner');
                        @endphp
                        <div class="admin-promo-card {{ $i < 3 ? 'mb-4' : '' }}">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-8">
                                    <div class="row g-3">
                                        <div class="col-md-8"><label class="form-label fw-semibold">{{ __('Banner') }} {{ $i }} {{ __('title') }}</label><input type="text" name="promo_banner_{{ $i }}_title" value="{{ old("promo_banner_{$i}_title", $settings["promo_banner_{$i}_title"] ?? '') }}" class="form-control"></div>
                                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Sort') }}</label><input type="number" min="1" max="99" name="promo_banner_{{ $i }}_sort_order" value="{{ old("promo_banner_{$i}_sort_order", $settings["promo_banner_{$i}_sort_order"] ?? $i) }}" class="form-control"></div>
                                        <div class="col-md-2 d-flex align-items-end"><div class="form-check form-switch pb-2"><input class="form-check-input" type="checkbox" name="promo_banner_{{ $i }}_active" value="1" @checked(old("promo_banner_{$i}_active", $settings["promo_banner_{$i}_active"] ?? true))><label class="form-check-label ms-2">{{ __('Active') }}</label></div></div>
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
                    @endfor
                </div>
            </div>

            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Trust blocks') }}</h4>
                            <div class="text-muted small">{{ __('Each block supports icon class, title, subtitle, active state, and sort order.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Phase 2.3') }}</span>
                    </div>

                    @for($i = 1; $i <= 4; $i++)
                        <div class="admin-promo-card {{ $i < 4 ? 'mb-4' : '' }}">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-9">
                                    <div class="row g-3">
                                        <div class="col-md-5"><label class="form-label fw-semibold">{{ __('Block') }} {{ $i }} {{ __('title') }}</label><input type="text" name="trust_block_{{ $i }}_title" value="{{ old("trust_block_{$i}_title", $settings["trust_block_{$i}_title"] ?? '') }}" class="form-control"></div>
                                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Icon class') }}</label><input type="text" name="trust_block_{{ $i }}_icon" value="{{ old("trust_block_{$i}_icon", $settings["trust_block_{$i}_icon"] ?? 'bi bi-stars') }}" class="form-control" placeholder="bi bi-truck"></div>
                                        <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Sort') }}</label><input type="number" min="1" max="99" name="trust_block_{{ $i }}_sort_order" value="{{ old("trust_block_{$i}_sort_order", $settings["trust_block_{$i}_sort_order"] ?? $i) }}" class="form-control"></div>
                                        <div class="col-md-2 d-flex align-items-end"><div class="form-check form-switch pb-2"><input class="form-check-input" type="checkbox" name="trust_block_{{ $i }}_active" value="1" @checked(old("trust_block_{$i}_active", $settings["trust_block_{$i}_active"] ?? true))><label class="form-check-label ms-2">{{ __('Active') }}</label></div></div>
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

            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Admin branding') }}</h4>
                    <div class="row g-3">
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Sidebar color'),'name'=>'admin_sidebar_color','value'=>old('admin_sidebar_color',$settings['admin_sidebar_color'] ?? '#0f172a')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Header color'),'name'=>'admin_header_color','value'=>old('admin_header_color',$settings['admin_header_color'] ?? '#fff2e7')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Admin surface color'),'name'=>'admin_surface_color','value'=>old('admin_surface_color',$settings['admin_surface_color'] ?? '#ffffff')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Admin card border color'),'name'=>'admin_card_border_color','value'=>old('admin_card_border_color',$settings['admin_card_border_color'] ?? '#f2dac8')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Admin accent soft color'),'name'=>'admin_accent_soft_color','value'=>old('admin_accent_soft_color',$settings['admin_accent_soft_color'] ?? '#fff1f2')])</div>
                        <div class="col-md-6">@include('admin.settings.partials.color-field',['label'=>__('Admin primary soft color'),'name'=>'admin_primary_soft_color','value'=>old('admin_primary_soft_color',$settings['admin_primary_soft_color'] ?? '#ffedd5')])</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Images') }}</h4>

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

            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Live preview') }}</h4>
                    <div class="admin-theme-preview" id="adminThemePreview">
                        <div class="admin-theme-preview__sidebar"></div>
                        <div class="admin-theme-preview__content">
                            <div class="admin-theme-preview__header"></div>
                            <div class="admin-theme-preview__cards">
                                <div class="admin-theme-preview__card"></div>
                                <div class="admin-theme-preview__card"></div>
                            </div>
                        </div>
                    </div>
                    <div class="customer-theme-preview mt-3" id="customerThemePreview">
                        <div class="customer-theme-preview__hero"></div>
                        <div class="customer-theme-preview__button">{{ __('Primary action') }}</div>
                        <div class="customer-theme-preview__badge">{{ __('Badge') }}</div>
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
.admin-color-field{display:flex;gap:.75rem;align-items:center}.admin-current-path{word-break:break-word}.admin-manual-path{margin-top:.25rem}.admin-manual-path summary{cursor:pointer;color:var(--admin-primary-dark);font-weight:700}.admin-color-field .form-control-color{width:72px;min-width:72px;height:48px;padding:.35rem;border-radius:.9rem}.admin-color-code{text-transform:uppercase}.admin-media-preview{position:relative;min-height:180px;border:1px dashed var(--admin-border);border-radius:1rem;padding:.5rem;background:color-mix(in srgb, var(--admin-surface) 92%, var(--admin-primary-soft))}.admin-media-preview img{width:100%;height:180px;object-fit:cover;border-radius:1rem}.admin-media-preview--favicon{min-height:120px;display:flex;align-items:center;justify-content:center}.admin-media-preview--favicon img{width:72px;height:72px;object-fit:contain;border-radius:1rem}.admin-thumb-favicon{width:72px;height:72px;object-fit:contain;border-radius:1rem}.admin-banner-thumb{height:220px}.admin-media-block__title{font-weight:800;margin-bottom:.65rem}.admin-promo-card{padding:1rem;border:1px solid var(--admin-border);border-radius:1.1rem;background:color-mix(in srgb, var(--admin-surface) 96%, var(--admin-primary-soft))}.admin-theme-preview{display:grid;grid-template-columns:90px 1fr;overflow:hidden;border-radius:1.25rem;border:1px solid var(--admin-border);min-height:180px}.admin-theme-preview__sidebar{background:var(--preview-sidebar,#0f172a)}.admin-theme-preview__content{background:var(--preview-admin-surface,#fff)}.admin-theme-preview__header{height:52px;background:var(--preview-header,#fff2e7);border-bottom:1px solid rgba(15,23,42,.06)}.admin-theme-preview__cards{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;padding:1rem}.admin-theme-preview__card{height:72px;border-radius:1rem;background:linear-gradient(135deg,var(--preview-primary,#f97316),var(--preview-secondary,#ec4899));opacity:.16;border:1px solid var(--preview-admin-border,#f2dac8)}.customer-theme-preview{padding:1rem;border:1px solid var(--preview-border,#fed7aa);border-radius:1.25rem;background:var(--preview-bg,#fffaf5)}.customer-theme-preview__hero{height:90px;border-radius:1rem;background:linear-gradient(135deg,var(--preview-primary,#f97316),var(--preview-secondary,#ec4899));margin-bottom:.9rem;opacity:.2}.customer-theme-preview__button{display:inline-flex;align-items:center;justify-content:center;padding:.7rem 1rem;border-radius:calc(var(--preview-radius,20) * 1px);background:linear-gradient(135deg,var(--preview-primary,#f97316),var(--preview-secondary,#ec4899));color:var(--preview-button-text,#fff);font-weight:800;margin-bottom:.8rem}.customer-theme-preview__badge{display:inline-flex;align-items:center;padding:.45rem .9rem;border-radius:999px;background:var(--preview-soft,#fff7ed);border:1px solid var(--preview-border,#fed7aa);font-weight:700;color:var(--preview-primary,#f97316)}.admin-promo-meta{display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;min-height:100%;padding:1.25rem;border:1px dashed var(--admin-border);border-radius:1rem;background:color-mix(in srgb,var(--admin-surface) 92%, var(--admin-primary-soft))}.admin-promo-meta__icon{width:72px;height:72px;border-radius:1.1rem;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--admin-accent-soft) 80%, white);color:var(--admin-primary-dark);font-size:1.65rem}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function syncColor(picker){
        const target=document.getElementById(picker.getAttribute('data-sync-color'));
        if(!target) return;
        picker.addEventListener('input',()=>{target.value=picker.value; updatePreview();});
        target.addEventListener('input',()=>{if(/^#(?:[0-9a-fA-F]{3}){1,2}$/.test(target.value)){picker.value=target.value; updatePreview();}});
    }
    document.querySelectorAll('[data-sync-color]').forEach(syncColor);

    const presets = @json($presets);
    const presetSelect = document.getElementById('theme_preset');
    presetSelect?.addEventListener('change', function(){
        const preset=presets[this.value];
        if(!preset) return;
        Object.entries(preset).forEach(([key,value])=>{
            const input=document.getElementById(key);
            if(input){ input.value=value; }
            const picker=document.querySelector(`[data-sync-color="${key}"]`);
            if(picker){ picker.value=value; }
        });
        updatePreview();
    });

    function updatePreview(){
        const root=document.documentElement;
        const set=(key,id,fallback)=> root.style.setProperty(key, document.getElementById(id)?.value || fallback);
        set('--preview-primary','brand_primary_color','#f97316');
        set('--preview-secondary','brand_secondary_color','#ec4899');
        set('--preview-bg','brand_background_color','#fffaf5');
        set('--preview-soft','brand_soft_color','#fff7ed');
        set('--preview-border','brand_border_color','#fed7aa');
        set('--preview-button-text','brand_button_text_color','#ffffff');
        set('--preview-sidebar','admin_sidebar_color','#0f172a');
        set('--preview-header','admin_header_color','#fff2e7');
        set('--preview-admin-surface','admin_surface_color','#ffffff');
        set('--preview-admin-border','admin_card_border_color','#f2dac8');
        root.style.setProperty('--preview-radius', document.querySelector('[name="customer_card_radius"]')?.value || 20);
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
        if (targetKey && initialSrc) {
            updateImagePreview(targetKey, initialSrc);
        }
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

    document.querySelectorAll('input,select,textarea').forEach(el=>el.addEventListener('input', updatePreview));
    updatePreview();
});
</script>
@endpush
