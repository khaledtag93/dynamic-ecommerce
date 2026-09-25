<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontCartLiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_cart_quantity_can_return_live_json_summary_without_redirect(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(8);

        $item = CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 50,
            'quantity' => 1,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('cart.update', $item), ['quantity' => 3]);

        $response
            ->assertOk()
            ->assertJsonPath('item.id', $item->id)
            ->assertJsonPath('item.quantity', 3)
            ->assertJsonPath('item.line_total', 150)
            ->assertJsonPath('cart.items_count', 3)
            ->assertJsonPath('cart.subtotal', 150)
            ->assertJsonPath('cart.coupon_discount', 0)
            ->assertJsonPath('cart.promotion_discount', 0)
            ->assertJsonPath('cart.total', 150);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'user_id' => $user->id,
            'quantity' => 3,
        ]);
    }

    public function test_live_cart_quantity_update_keeps_existing_ownership_boundary(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = $this->makeProduct(8);

        $item = CartItem::query()->create([
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 50,
            'quantity' => 1,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $this->actingAs($otherUser)
            ->patchJson(route('cart.update', $item), ['quantity' => 4])
            ->assertForbidden();

        $this->assertSame(1, (int) $item->fresh()->quantity);
    }

    public function test_cart_view_keeps_no_javascript_fallback_and_separates_discount_buckets(): void
    {
        $source = file_get_contents(resource_path('views/frontend/cart/index.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('data-cart-live-root', $source);
        $this->assertStringContainsString("'X-Cart-Live': '1'", $source);
        $this->assertStringContainsString('cart-live-enabled .cart-qty-update-fallback', $source);
        $this->assertStringContainsString("number_format(\$cart['coupon_discount'] ?? 0, 2)", $source);
        $this->assertStringContainsString("number_format(\$cart['promotion_discount'] ?? 0, 2)", $source);
        $this->assertStringNotContainsString("number_format(\$cart['discount'], 2)", $source);

        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(
            'تعذر تحديث سلة التسوق الآن. حاول مرة أخرى.',
            $arabic['Unable to update the cart right now.'] ?? null
        );
    }

    protected function makeProduct(int $quantity): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Cart Category '.Str::random(6),
            'slug' => 'cart-category-'.Str::lower(Str::random(8)),
            'description' => 'Cart test category',
            'meta_title' => 'Cart',
            'meta_keyword' => 'cart',
            'meta_description' => 'Cart test category',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Cart Product '.Str::random(6),
            'slug' => 'cart-product-'.Str::lower(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 50,
            'cost_price' => 20,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
