<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Channels\WhatsApp\WhatsAppManager;
use App\Services\Commerce\CouponService;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\PaymentService;
use App\Services\Commerce\ProfitService;
use App\Services\Commerce\ShippingService;
use App\Services\Commerce\StockReservationService;
use App\Services\Commerce\PromotionEngine;
use App\Services\Frontend\CartService;
use App\Services\Frontend\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_checkout_from_same_cart_creates_only_one_order(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5);

        $this->actingAs($user);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 50,
            'quantity' => 2,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $service = app(CheckoutService::class);
        $data = [
            'customer_name' => 'Checkout Test',
            'customer_email' => 'checkout@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ];

        $order = $service->place($data, $user);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(3, (int) $product->fresh()->quantity);

        try {
            $service->place($data, $user);
            $this->fail('A second checkout from the cleared cart should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(3, (int) $product->fresh()->quantity);
    }

    public function test_checkout_rolls_back_everything_when_a_late_business_step_fails(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5);

        $this->actingAs($user);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 50,
            'quantity' => 2,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $couponService = Mockery::mock(CouponService::class)->makePartial();
        $couponService->shouldReceive('resolveDiscountSummary')
            ->once()
            ->andReturn([
                'coupon' => null,
                'discount' => 0.0,
                'label' => null,
                'code' => null,
            ]);
        $couponService->shouldReceive('markCouponAsUsed')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'coupon' => 'Simulated late checkout failure.',
            ]));

        $cartService = new CartService($couponService, app(PromotionEngine::class));
        $service = new CheckoutService(
            $cartService,
            $couponService,
            app(InventoryService::class),
            app(PaymentService::class),
            app(ProfitService::class),
            app(WhatsAppManager::class),
            app(ShippingService::class),
            app(StockReservationService::class),
        );

        $data = [
            'customer_name' => 'Rollback Test',
            'customer_email' => 'rollback@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ];

        try {
            $service->place($data, $user);
            $this->fail('The simulated late checkout failure should abort the transaction.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coupon', $exception->errors());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(5, (int) $product->fresh()->quantity);
    }

    protected function makeProduct(int $quantity): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Checkout Category '.Str::random(6),
            'slug' => 'checkout-category-'.Str::lower(Str::random(8)),
            'description' => 'Checkout test category',
            'meta_title' => 'Checkout',
            'meta_keyword' => 'checkout',
            'meta_description' => 'Checkout test category',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Checkout Product '.Str::random(6),
            'slug' => 'checkout-product-'.Str::lower(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 50,
            'cost_price' => 20,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
