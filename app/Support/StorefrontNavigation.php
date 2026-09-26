<?php

namespace App\Support;

use Illuminate\Support\Collection;

class StorefrontNavigation
{
    /** @return array{categories: ?string, offers: string, best_sellers: ?string, new_arrivals: string} */
    public static function links(array $settings, bool $onHome): array
    {
        $show = static fn (string $section): bool => in_array(
            (string) ($settings['show_home_'.$section] ?? '1'),
            ['1', 'true', 'on', 'yes'],
            true
        );
        $homeAnchor = static fn (string $id): string => ($onHome ? '' : route('frontend.home')).'#'.$id;

        return [
            'categories' => $show('categories') ? $homeAnchor('categories') : null,
            'offers' => $onHome && $show('on_sale_products')
                ? '#on-sale-products'
                : route('frontend.search', ['offer' => 'on_sale']),
            'best_sellers' => $show('best_sellers') ? $homeAnchor('best-sellers') : null,
            'new_arrivals' => $onHome && $show('latest_products')
                ? '#latest-products'
                : route('frontend.search', ['sort' => 'latest']),
        ];
    }

    /** Keep known Home anchors usable when a merchant hides their target section. */
    public static function homeDestination(string $link, Collection $sections): string
    {
        $targets = [
            '#hero' => 'hero',
            '#categories' => 'categories',
            '#featured-categories' => 'featured_categories',
            '#featured-products' => 'featured_products',
            '#manual-featured-products' => 'manual_featured_products',
            '#best-sellers' => 'best_sellers',
            '#latest-products' => 'latest_products',
            '#on-sale-products' => 'on_sale_products',
            '#promo-banners' => 'promo_banners',
            '#promo-banner' => 'promo_banner',
            '#trust-blocks' => 'trust_blocks',
        ];

        if (! isset($targets[$link]) || $sections->contains('type', $targets[$link])) {
            return $link;
        }

        return match ($link) {
            '#on-sale-products' => route('frontend.search', ['offer' => 'on_sale']),
            '#latest-products' => route('frontend.search', ['sort' => 'latest']),
            '#hero' => route('frontend.home'),
            default => route('frontend.search'),
        };
    }
}
