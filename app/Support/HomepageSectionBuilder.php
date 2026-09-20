<?php

namespace App\Support;

use Illuminate\Support\Collection;

class HomepageSectionBuilder
{
    public static function build(
        array $settings,
        Collection $categories,
        Collection $featuredProducts,
        Collection $latestProducts,
        ?Collection $bestSellers = null,
        ?Collection $onSaleProducts = null,
        ?Collection $featuredCategories = null,
        ?Collection $manualFeaturedProducts = null
    ): Collection {
        $bestSellers ??= collect();
        $onSaleProducts ??= collect();
        $featuredCategories ??= collect();
        $manualFeaturedProducts ??= collect();
        $promoBanners = self::promoBanners($settings);
        $trustBlocks = self::trustBlocks($settings);

        $available = collect([
            'hero' => [
                'type' => 'hero',
                'view' => 'frontend.sections.hero',
                'enabled' => self::toBool($settings['show_home_hero'] ?? true),
                'data' => [
                    'heroTitle' => self::heroTitle($settings),
                    'heroSubtitle' => self::heroSubtitle($settings),
                    'heroBadge' => self::localizedText($settings, 'hero_badge_text', __('Electronics deals')),
                    'primaryButtonText' => self::localizedText($settings, 'hero_primary_button_text', __('Shop now')),
                    'primaryButtonLink' => trim((string) ($settings['hero_primary_button_link'] ?? '#featured-products')),
                    'secondaryButtonText' => self::localizedText($settings, 'hero_secondary_button_text', __('Browse categories')),
                    'secondaryButtonLink' => trim((string) ($settings['hero_secondary_button_link'] ?? '#categories')),
                    'heroBannerUrl' => self::heroBannerUrl($settings),
                    'heroStats' => [
                        'featured_products_count' => $featuredProducts->count(),
                        'latest_products_count' => $latestProducts->count(),
                        'categories_count' => $categories->count(),
                    ],
                    'heroSlides' => self::heroSlides($settings, $promoBanners),
                ],
            ],
            'promo_banners' => [
                'type' => 'promo_banners',
                'view' => 'frontend.sections.promo-banners',
                'enabled' => self::toBool($settings['show_home_promo_banners'] ?? $settings['show_home_promo_banner'] ?? true) && $promoBanners->isNotEmpty(),
                'data' => [
                    'title' => self::localizedText($settings, 'home_promo_banners_title', __('Featured offers')),
                    'subtitle' => self::localizedText($settings, 'home_promo_banners_subtitle', __('Explore highlighted deals and departments.')),
                    'banners' => $promoBanners,
                ],
            ],
            'featured_categories' => [
                'type' => 'featured_categories',
                'view' => 'frontend.sections.categories',
                'enabled' => self::toBool($settings['show_home_featured_categories'] ?? true) && $featuredCategories->isNotEmpty(),
                'data' => [
                    'key' => 'featured-categories',
                    'title' => self::localizedText($settings, 'home_featured_categories_title', __('Featured categories')),
                    'subtitle' => self::localizedText($settings, 'home_featured_categories_subtitle', __('Quick access to your main departments')),
                    'categories' => $featuredCategories,
                    'empty' => __('No featured categories are configured yet.'),
                    'action_text' => __('Browse all categories'),
                    'action_link' => '#categories',
                ],
            ],
            'manual_featured_products' => [
                'type' => 'manual_featured_products',
                'view' => 'frontend.sections.product-grid',
                'enabled' => self::toBool($settings['show_home_manual_featured_products'] ?? false),
                'data' => [
                    'key' => 'manual-featured-products',
                    'title' => self::localizedText($settings, 'home_manual_featured_products_title', __('Picked for you')),
                    'subtitle' => self::localizedText($settings, 'home_manual_featured_products_subtitle', __('Hand-picked products worth checking today')),
                    'products' => $manualFeaturedProducts,
                    'empty' => __('Selected products will appear here when configured.'),
                    'action_text' => self::localizedText($settings, 'home_manual_featured_products_action_text', __('Browse products')),
                    'action_link' => trim((string) ($settings['home_manual_featured_products_action_link'] ?? '#latest-products')),
                ],
            ],
            'featured_products' => [
                'type' => 'featured_products',
                'view' => 'frontend.sections.product-grid',
                'enabled' => self::toBool($settings['show_home_featured_products'] ?? true),
                'data' => [
                    'key' => 'featured-products',
                    'title' => self::localizedText($settings, 'home_featured_products_title', __('Featured products')),
                    'subtitle' => self::localizedText($settings, 'home_featured_products_subtitle', __('Popular electronics')),
                    'products' => $featuredProducts,
                    'empty' => __('No products are visible yet.'),
                    'action_text' => self::toBool($settings['show_home_categories'] ?? true) ? __('Jump to categories') : null,
                    'action_link' => self::toBool($settings['show_home_categories'] ?? true) ? '#categories' : null,
                ],
            ],
            'best_sellers' => [
                'type' => 'best_sellers',
                'view' => 'frontend.sections.product-grid',
                'enabled' => self::toBool($settings['show_home_best_sellers'] ?? true),
                'data' => [
                    'key' => 'best-sellers',
                    'title' => self::localizedText($settings, 'home_best_sellers_title', __('Best sellers')),
                    'subtitle' => self::localizedText($settings, 'home_best_sellers_subtitle', __('Customer favorites')),
                    'products' => $bestSellers,
                    'empty' => __('Best sellers will appear after orders start.'),
                    'action_text' => __('See offers'),
                    'action_link' => '#on-sale-products',
                ],
            ],
            'categories' => [
                'type' => 'categories',
                'view' => 'frontend.sections.categories',
                'enabled' => self::toBool($settings['show_home_categories'] ?? true),
                'data' => [
                    'key' => 'categories',
                    'title' => self::localizedText($settings, 'home_categories_title', __('Browse categories')),
                    'subtitle' => self::localizedText($settings, 'home_categories_subtitle', __('Find the right department faster')),
                    'categories' => $categories,
                    'empty' => __('No categories are visible yet.'),
                ],
            ],
            'latest_products' => [
                'type' => 'latest_products',
                'view' => 'frontend.sections.product-grid',
                'enabled' => self::toBool($settings['show_home_latest_products'] ?? true),
                'data' => [
                    'key' => 'latest-products',
                    'title' => self::localizedText($settings, 'home_latest_products_title', __('Latest arrivals')),
                    'subtitle' => self::localizedText($settings, 'home_latest_products_subtitle', __('Recently added devices and accessories')),
                    'products' => $latestProducts,
                    'empty' => __('New products will appear here soon.'),
                    'action_text' => __('Back to top'),
                    'action_link' => '#hero',
                ],
            ],
            'on_sale_products' => [
                'type' => 'on_sale_products',
                'view' => 'frontend.sections.product-grid',
                'enabled' => self::toBool($settings['show_home_on_sale_products'] ?? true),
                'data' => [
                    'key' => 'on-sale-products',
                    'title' => self::localizedText($settings, 'home_on_sale_products_title', __('On sale')),
                    'subtitle' => self::localizedText($settings, 'home_on_sale_products_subtitle', __('Selected electronics deals')),
                    'products' => $onSaleProducts,
                    'empty' => __('Offers will appear here soon.'),
                    'action_text' => __('Shop now'),
                    'action_link' => '#featured-products',
                ],
            ],
            'trust_blocks' => [
                'type' => 'trust_blocks',
                'view' => 'frontend.sections.trust-blocks',
                'enabled' => self::toBool($settings['show_home_trust_blocks'] ?? true) && $trustBlocks->isNotEmpty(),
                'data' => [
                    'title' => self::localizedText($settings, 'home_trust_blocks_title', __('Why shop with us?')),
                    'subtitle' => self::localizedText($settings, 'home_trust_blocks_subtitle', __('Clear prices, trusted products, secure checkout, and support after purchase.')),
                    'blocks' => $trustBlocks,
                ],
            ],
            'promo_banner' => [
                'type' => 'promo_banner',
                'view' => 'frontend.sections.promo-banner',
                'enabled' => self::toBool($settings['show_home_promo_banner'] ?? false),
                'data' => [
                    'title' => self::localizedText($settings, 'home_promo_title', __('Upgrade your tech setup today.')),
                    'subtitle' => self::localizedText($settings, 'home_promo_subtitle', __('Discover practical deals on phones, laptops, gaming, audio, TVs, and accessories.')),
                    'button_text' => self::localizedText($settings, 'home_promo_button_text', __('Start shopping')),
                    'button_link' => trim((string) ($settings['home_promo_button_link'] ?? '#featured-products')),
                    'secondary_button_text' => self::localizedText($settings, 'home_promo_secondary_button_text', __('Browse categories')),
                    'secondary_button_link' => trim((string) ($settings['home_promo_secondary_button_link'] ?? '#categories')),
                ],
            ],
        ]);

        $requestedOrder = self::parseOrder($settings['homepage_sections_order'] ?? null)
            ->map(fn (string $item) => $item === 'promo_banner' ? 'promo_banners' : $item)
            ->all();

        $defaultOrder = ['hero', 'promo_banners', 'featured_categories', 'manual_featured_products', 'featured_products', 'best_sellers', 'latest_products', 'on_sale_products', 'trust_blocks', 'categories'];
        $order = collect(array_merge($requestedOrder, $defaultOrder, ['promo_banner']))->unique()->values();

        return $order
            ->map(fn (string $key) => $available->get($key))
            ->filter(fn (?array $section) => is_array($section) && ($section['enabled'] ?? false))
            ->values();
    }


    protected static function heroSlides(array $settings, Collection $promoBanners): Collection
    {
        $slides = collect([[ 
            'eyebrow' => self::localizedText($settings, 'hero_badge_text', __('Electronics deals')),
            'title' => self::heroTitle($settings),
            'subtitle' => self::heroSubtitle($settings),
            'button_text' => self::localizedText($settings, 'hero_primary_button_text', __('Shop now')),
            'button_link' => trim((string) ($settings['hero_primary_button_link'] ?? '#featured-products')),
            'image_url' => self::heroBannerUrl($settings),
            'theme' => 'primary',
        ]]);

        $promoBanners->take(3)->each(function (array $banner, int $index) use ($slides) {
            $slides->push([
                'eyebrow' => __('Featured offer'),
                'title' => $banner['title'] ?: __('Upgrade your tech setup today.'),
                'subtitle' => $banner['subtitle'] ?: __('Browse selected electronics deals and departments.'),
                'button_text' => $banner['button_text'] ?: __('Explore now'),
                'button_link' => $banner['button_link'] ?: '#featured-products',
                'image_url' => $banner['image_url'] ?? null,
                'theme' => 'campaign-' . (($index % 3) + 1),
            ]);
        });

        return $slides
            ->filter(fn (array $slide) => trim((string) ($slide['title'] ?? '')) !== '')
            ->values();
    }

    protected static function promoBanners(array $settings): Collection
    {
        $banners = collect(range(1, 3))->map(function (int $index) use ($settings) {
            $imagePath = $settings["promo_banner_{$index}_image_path"] ?? null;

            return [
                'title' => self::localizedText($settings, "promo_banner_{$index}_title", ''),
                'subtitle' => self::localizedText($settings, "promo_banner_{$index}_subtitle", ''),
                'button_text' => self::localizedText($settings, "promo_banner_{$index}_button_text", ''),
                'button_link' => trim((string) ($settings["promo_banner_{$index}_button_link"] ?? '#featured-products')),
                'image_path' => $imagePath,
                'image_url' => AdminBranding::mediaUrl($imagePath, 'promo_banner'),
                'active' => self::toBool($settings["promo_banner_{$index}_active"] ?? false),
                'sort_order' => self::toInt($settings["promo_banner_{$index}_sort_order"] ?? $index, $index),
            ];
        })->filter(function (array $banner) {
            return $banner['active'] && ($banner['title'] !== '' || $banner['subtitle'] !== '' || $banner['image_url']);
        })->sortBy('sort_order')->values();

        if ($banners->isNotEmpty()) {
            return $banners;
        }

        if (! self::toBool($settings['show_home_promo_banner'] ?? false)) {
            return collect();
        }

        return collect([[
            'title' => self::localizedText($settings, 'home_promo_title', __('Upgrade your tech setup today.')),
            'subtitle' => self::localizedText($settings, 'home_promo_subtitle', __('Discover practical deals on phones, laptops, gaming, audio, TVs, and accessories.')),
            'button_text' => self::localizedText($settings, 'home_promo_button_text', __('Start shopping')),
            'button_link' => trim((string) ($settings['home_promo_button_link'] ?? '#featured-products')),
            'image_path' => null,
            'image_url' => null,
            'active' => true,
            'sort_order' => 1,
        ]]);
    }

    protected static function trustBlocks(array $settings): Collection
    {
        return collect(range(1, 4))
            ->map(function (int $index) use ($settings) {
                return [
                    'icon' => trim((string) ($settings["trust_block_{$index}_icon"] ?? 'bi bi-stars')),
                    'title' => self::localizedText($settings, "trust_block_{$index}_title", ''),
                    'subtitle' => self::localizedText($settings, "trust_block_{$index}_subtitle", ''),
                    'active' => self::toBool($settings["trust_block_{$index}_active"] ?? false),
                    'sort_order' => self::toInt($settings["trust_block_{$index}_sort_order"] ?? $index, $index),
                ];
            })
            ->filter(fn (array $block) => $block['active'] && ($block['title'] !== '' || $block['subtitle'] !== ''))
            ->sortBy('sort_order')
            ->values();
    }

    protected static function heroTitle(array $settings): string
    {
        $title = self::localizedText($settings, 'hero_title', '');

        return $title !== ''
            ? $title
            : __('Latest electronics and smart devices in one trusted store.');
    }

    protected static function heroSubtitle(array $settings): string
    {
        $subtitle = self::localizedText($settings, 'hero_subtitle', '');

        return $subtitle !== ''
            ? $subtitle
            : __('Shop phones, laptops, gaming gear, TVs, audio, and accessories with clear offers, secure checkout, and fast delivery.');
    }

    protected static function heroBannerUrl(array $settings): ?string
    {
        return AdminBranding::mediaUrl(
            $settings['hero_banner_path'] ?? $settings['hero_banner'] ?? null,
            'hero_banner'
        );
    }

    /**
     * Read customer-facing white-label text using the active locale first.
     * This keeps Arabic pages Arabic even when the base setting was saved in English.
     */
    protected static function localizedText(array $settings, string $key, string $fallback = ''): string
    {
        $locale = app()->getLocale();

        if ($locale === 'ar') {
            $arabicKeys = array_values(array_unique([$key . '_ar', $key . '_ar_AR', $key . '_' . $locale]));

            foreach ($arabicKeys as $candidate) {
                $value = trim((string) ($settings[$candidate] ?? ''));

                if ($value !== '') {
                    return $value;
                }
            }

            return trim((string) $fallback);
        }

        $candidates = array_values(array_unique([$key . '_' . $locale, $key . '_en', $key]));

        foreach ($candidates as $candidate) {
            $value = trim((string) ($settings[$candidate] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return trim((string) $fallback);
    }

    protected static function parseOrder(null|string|array $value): Collection
    {
        if (is_array($value)) {
            return collect(array_values(array_filter(array_map('strval', $value))));
        }

        $value = trim((string) $value);

        if ($value === '') {
            return collect();
        }

        return collect(array_values(array_filter(array_map(
            static fn (string $item) => trim($item),
            preg_split('/[\s,

	]+/', $value) ?: []
        ))));
    }

    protected static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    protected static function toInt(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
