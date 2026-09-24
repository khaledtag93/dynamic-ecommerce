<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
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

    public function test_product_page_uses_local_placeholder_without_counting_it_as_gallery_media(): void
    {
        $category = Category::query()->create([
            'name' => 'Product Detail Category',
            'slug' => 'product-detail-category-' . Str::lower(Str::random(6)),
            'description' => 'Product detail category',
            'status' => 0,
        ]);

        $product = Product::query()->create([
            'name' => 'Product Without Image',
            'slug' => 'product-without-image-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Product without gallery media',
            'base_price' => 250,
            'quantity' => 3,
            'status' => true,
            'has_variants' => false,
        ]);

        $response = $this->get(route('frontend.products.show', $product->slug));

        $response
            ->assertOk()
            ->assertSee(asset('images/storefront-placeholder.svg'))
            ->assertDontSee('via.placeholder.com', false)
            ->assertSee('<div><span>Gallery images</span><strong>0</strong></div>', false);
    }

    public function test_checkout_uses_aligned_billing_toggle_and_real_payment_options(): void
    {
        $user = User::factory()->create();

        $category = Category::query()->create([
            'name' => 'Checkout Category',
            'slug' => 'checkout-category-' . Str::lower(Str::random(6)),
            'description' => 'Checkout test category',
            'status' => 0,
        ]);

        $product = Product::query()->create([
            'name' => 'Checkout Product',
            'slug' => 'checkout-product-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Checkout test product',
            'base_price' => 500,
            'quantity' => 5,
            'status' => true,
            'has_variants' => false,
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 500,
            'quantity' => 1,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $response = $this->actingAs($user)->get(route('checkout.index'));

        $response
            ->assertOk()
            ->assertSee('<div class="checkout-toggle-card mb-3">', false)
            ->assertSee('Cash on Delivery')
            ->assertSee('Bank Transfer')
            ->assertSee('Online Payment')
            ->assertDontSee('>Visa<', false)
            ->assertDontSee('>Mastercard<', false);
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
