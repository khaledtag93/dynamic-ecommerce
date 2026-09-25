<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontAddToCartLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_to_cart_can_return_server_confirmed_live_count_and_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5);

        $first = $this->actingAs($user)
            ->postJson(route('cart.store', $product), ['quantity' => 2]);

        $first
            ->assertOk()
            ->assertJsonPath('item.product_id', $product->id)
            ->assertJsonPath('item.quantity', 2)
            ->assertJsonPath('cart.items_count', 2)
            ->assertJsonPath('message', __('Product added to cart successfully.'));

        $second = $this->postJson(route('cart.store', $product), ['quantity' => 10]);

        $second
            ->assertOk()
            ->assertJsonPath('item.quantity', 5)
            ->assertJsonPath('cart.items_count', 5);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_normal_add_to_cart_form_keeps_redirect_fallback(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(4);
        $productUrl = route('frontend.products.show', $product->slug);

        $this->actingAs($user)
            ->from($productUrl)
            ->post(route('cart.store', $product), ['quantity' => 1])
            ->assertRedirect($productUrl);

        $this->assertSame(1, (int) CartItem::query()->where('user_id', $user->id)->value('quantity'));
    }

    public function test_storefront_sources_use_delegated_live_add_but_keep_buy_now_navigation(): void
    {
        $card = file_get_contents(resource_path('views/frontend/sections/partials/product-card.blade.php'));
        $show = file_get_contents(resource_path('views/frontend/products/show.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $script = file_get_contents(public_path('js/storefront-cart-actions.js'));

        $this->assertStringContainsString('data-live-cart-add', $card);
        $this->assertStringContainsString('data-live-cart-add', $show);
        $this->assertStringContainsString('data-layout-cart-count', $layout);
        $this->assertStringContainsString('js/storefront-cart-actions.js', $layout);
        $this->assertStringContainsString("document.addEventListener('submit'", $script);
        $this->assertStringContainsString("'X-Cart-Add-Live': '1'", $script);
        $this->assertStringContainsString("submitter?.name === 'redirect_to'", $script);
        $this->assertStringContainsString("submitter.value === 'checkout'", $script);
    }

    private function makeProduct(int $quantity): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Live Add Category '.Str::random(6),
            'slug' => 'live-add-category-'.Str::lower(Str::random(8)),
            'description' => 'Live add category',
            'meta_title' => 'Live add',
            'meta_keyword' => 'live add',
            'meta_description' => 'Live add category',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Live Add Product '.Str::random(6),
            'slug' => 'live-add-product-'.Str::lower(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 50,
            'cost_price' => 20,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
