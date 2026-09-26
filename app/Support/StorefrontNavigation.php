<?php

namespace App\Support;

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
}
