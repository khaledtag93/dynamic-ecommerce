<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_home_uses_real_search_route_without_duplicate_shortcut_strip(): void
    {
        $response = $this->get(route('frontend.home'));

        $response
            ->assertOk()
            ->assertSee(route('frontend.search'))
            ->assertSee('Everything you need from one trusted store.')
            ->assertDontSee('retail-promo-pods', false)
            ->assertDontSee('Exclusive electronics deals', false);
    }

    public function test_storefront_search_returns_matching_visible_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Search Category',
            'slug' => 'search-category-' . Str::lower(Str::random(6)),
            'description' => 'Search test category',
            'status' => 0,
        ]);

        $matching = Product::query()->create([
            'name' => 'Ocean Blue Backpack',
            'slug' => 'ocean-blue-backpack-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Durable travel backpack',
            'base_price' => 850,
            'quantity' => 5,
            'status' => true,
            'has_variants' => false,
        ]);

        Product::query()->create([
            'name' => 'Classic Desk Lamp',
            'slug' => 'classic-desk-lamp-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Warm desk light',
            'base_price' => 400,
            'quantity' => 4,
            'status' => true,
            'has_variants' => false,
        ]);

        $response = $this->get(route('frontend.search', ['q' => 'Backpack']));

        $response
            ->assertOk()
            ->assertSee('Ocean Blue Backpack')
            ->assertDontSee('Classic Desk Lamp')
            ->assertSee(route('frontend.products.show', $matching->slug))
            ->assertSee(asset('images/storefront-placeholder.svg'))
            ->assertDontSee('via.placeholder.com', false);
    }

    public function test_storefront_defaults_do_not_expose_demo_contact_details(): void
    {
        $settings = app(StoreSettingsService::class)->all();

        $this->assertSame('', $settings['store_support_email']);
        $this->assertSame('', $settings['store_support_phone']);
        $this->assertSame('', $settings['store_support_whatsapp']);
        $this->assertSame('', $settings['store_contact_address']);
        $this->assertSame('', $settings['store_business_website']);
        $this->assertSame('', $settings['store_contact_hours']);
    }
}
