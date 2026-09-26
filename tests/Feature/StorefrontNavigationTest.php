<?php

namespace Tests\Feature;

use App\Support\StorefrontNavigation;
use Tests\TestCase;

class StorefrontNavigationTest extends TestCase
{
    public function test_enabled_home_sections_keep_in_page_navigation(): void
    {
        $links = StorefrontNavigation::links([], true);

        $this->assertSame('#categories', $links['categories']);
        $this->assertSame('#on-sale-products', $links['offers']);
        $this->assertSame('#best-sellers', $links['best_sellers']);
        $this->assertSame('#latest-products', $links['new_arrivals']);
    }

    public function test_other_pages_use_real_destinations_instead_of_local_fragments(): void
    {
        $links = StorefrontNavigation::links([], false);

        $this->assertSame(route('frontend.home').'#categories', $links['categories']);
        $this->assertSame(route('frontend.search', ['offer' => 'on_sale']), $links['offers']);
        $this->assertSame(route('frontend.home').'#best-sellers', $links['best_sellers']);
        $this->assertSame(route('frontend.search', ['sort' => 'latest']), $links['new_arrivals']);
    }

    public function test_disabled_home_sections_do_not_leave_dead_navigation_targets(): void
    {
        $settings = [
            'show_home_categories' => '0',
            'show_home_best_sellers' => '0',
            'show_home_on_sale_products' => '0',
            'show_home_latest_products' => '0',
        ];
        $links = StorefrontNavigation::links($settings, true);

        $this->assertNull($links['categories']);
        $this->assertNull($links['best_sellers']);
        $this->assertSame(route('frontend.search', ['offer' => 'on_sale']), $links['offers']);
        $this->assertSame(route('frontend.search', ['sort' => 'latest']), $links['new_arrivals']);
    }

    public function test_home_actions_only_jump_to_sections_present_on_the_rendered_page(): void
    {
        $sections = collect([
            ['type' => 'hero'],
            ['type' => 'latest_products'],
        ]);

        $this->assertSame('#hero', StorefrontNavigation::homeDestination('#hero', $sections));
        $this->assertSame('#latest-products', StorefrontNavigation::homeDestination('#latest-products', $sections));
        $this->assertSame(route('frontend.search'), StorefrontNavigation::homeDestination('#categories', $sections));
        $this->assertSame(route('frontend.search'), StorefrontNavigation::homeDestination('#featured-products', $sections));
        $this->assertSame(route('frontend.search'), StorefrontNavigation::homeDestination('#best-sellers', $sections));
        $this->assertSame(route('frontend.search', ['offer' => 'on_sale']), StorefrontNavigation::homeDestination('#on-sale-products', $sections));
        $this->assertSame('https://example.com/collection', StorefrontNavigation::homeDestination('https://example.com/collection', $sections));
        $this->assertSame('#merchant-custom-section', StorefrontNavigation::homeDestination('#merchant-custom-section', $sections));
    }
}
