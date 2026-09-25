<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontCartCouponLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_can_be_applied_and_removed_with_server_confirmed_live_summary(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 1,
            'meta' => ['product_slug' => $product->slug],
        ]);

        Coupon::query()->create([
            'name' => 'Ten Off',
            'code' => 'SAVE10',
            'type' => Coupon::TYPE_FIXED,
            'value' => 10,
            'is_active' => true,
        ]);

        $apply = $this->actingAs($user)
            ->postJson(route('cart.coupon.apply'), ['coupon_code' => 'save10']);

        $apply
            ->assertOk()
            ->assertJsonPath('coupon.code', 'SAVE10')
            ->assertJsonPath('coupon.label', 'Ten Off')
            ->assertJsonPath('cart.subtotal', 100)
            ->assertJsonPath('cart.coupon_discount', 10)
            ->assertJsonPath('cart.total', 90)
            ->assertJsonPath('message', __('Coupon :code applied successfully.', ['code' => 'SAVE10']));

        $this->assertSame('SAVE10', session(CouponService::SESSION_KEY));

        $remove = $this->deleteJson(route('cart.coupon.remove'));

        $remove
            ->assertOk()
            ->assertJsonPath('coupon', null)
            ->assertJsonPath('cart.coupon_discount', 0)
            ->assertJsonPath('cart.total', 100)
            ->assertJsonPath('message', __('Coupon removed from cart.'));

        $this->assertNull(session(CouponService::SESSION_KEY));
    }

    public function test_invalid_coupon_error_is_localized_for_arabic_live_request(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(3);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 1,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'ar'])
            ->postJson(route('cart.coupon.apply'), ['coupon_code' => 'missing'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.coupon.0', 'لم يتم العثور على رمز الكوبون.');
    }

    public function test_coupon_live_ui_keeps_normal_form_fallback_and_separate_discount_rows(): void
    {
        $source = file_get_contents(resource_path('views/frontend/cart/index.blade.php'));
        $service = file_get_contents(app_path('Services/Commerce/CouponService.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('data-cart-coupon-box', $source);
        $this->assertStringContainsString('data-cart-coupon-apply', $source);
        $this->assertStringContainsString('data-cart-coupon-remove', $source);
        $this->assertStringContainsString("'X-Cart-Coupon-Live': '1'", $source);
        $this->assertStringContainsString('data-cart-coupon-discount-row', $source);
        $this->assertStringContainsString('data-cart-promotion-discount-row', $source);

        $this->assertStringContainsString("__('Coupon code was not found.')", $service);
        $this->assertStringContainsString("__('This coupon is not active right now.')", $service);
        $this->assertStringContainsString("__('Order subtotal must be at least :amount to use this coupon.'", $service);
    }

    private function makeProduct(int $quantity): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Coupon Cart Category '.Str::random(6),
            'slug' => 'coupon-cart-category-'.Str::lower(Str::random(8)),
            'description' => 'Coupon cart test category',
            'meta_title' => 'Coupon cart',
            'meta_keyword' => 'coupon cart',
            'meta_description' => 'Coupon cart test category',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Coupon Cart Product '.Str::random(6),
            'slug' => 'coupon-cart-product-'.Str::lower(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 100,
            'cost_price' => 50,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
