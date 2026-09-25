<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCity;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Commerce\ShippingService;
use App\Services\Frontend\CheckoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShippingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_zone_rate_quote_and_free_shipping_threshold_before_discounts(): void
    {
        [$zone, $method, $rate] = $this->configureRate(
            city: 'Cairo',
            amount: 75,
            threshold: 600,
            basis: ShippingRate::BASIS_BEFORE_DISCOUNTS,
        );

        $service = app(ShippingService::class);

        $quote = $service->quote($method->code, '  cairo ', 'Egypt', 500, 50);

        $this->assertSame($zone->id, $quote['zone_id']);
        $this->assertSame($rate->id, $quote['rate_id']);
        $this->assertSame(75.0, $quote['amount']);
        $this->assertSame(100.0, $quote['free_shipping_remaining']);
        $this->assertFalse($quote['free_shipping_qualified']);

        $qualified = $service->quote($method->code, 'Cairo', 'EG', 650, 100);

        $this->assertSame(0.0, $qualified['amount']);
        $this->assertTrue($qualified['free_shipping_qualified']);
        $this->assertSame(0.0, $qualified['free_shipping_remaining']);
    }

    public function test_after_discount_threshold_uses_discounted_subtotal(): void
    {
        [, $method] = $this->configureRate(
            city: 'Giza',
            amount: 60,
            threshold: 600,
            basis: ShippingRate::BASIS_AFTER_DISCOUNTS,
        );

        $quote = app(ShippingService::class)->quote($method->code, 'Giza', 'Egypt', 650, 100);

        $this->assertSame(60.0, $quote['amount']);
        $this->assertSame(550.0, $quote['threshold_basis_amount']);
        $this->assertSame(50.0, $quote['free_shipping_remaining']);
    }

    public function test_pickup_is_zero_and_does_not_require_zone_or_rate(): void
    {
        $pickup = ShippingMethod::query()
            ->where('code', Order::DELIVERY_METHOD_PICKUP)
            ->firstOrFail();

        $quote = app(ShippingService::class)->quote($pickup->code, '', '', 250, 0);

        $this->assertTrue($quote['pickup']);
        $this->assertSame(0.0, $quote['amount']);
        $this->assertNull($quote['zone_id']);
        $this->assertNull($quote['rate_id']);
    }

    public function test_shipping_quote_rejects_unsupported_city_and_inactive_method(): void
    {
        [, $method] = $this->configureRate(city: 'Alexandria', amount: 80);

        try {
            app(ShippingService::class)->quote($method->code, 'Mansoura', 'Egypt', 300, 0);
            $this->fail('Unsupported city should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shipping_city', $exception->errors());
        }

        $method->update(['is_active' => false]);

        try {
            app(ShippingService::class)->quote($method->code, 'Alexandria', 'Egypt', 300, 0);
            $this->fail('Inactive shipping method should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delivery_method', $exception->errors());
        }
    }

    public function test_checkout_quote_endpoint_returns_server_authoritative_shipping_and_total(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $user = User::factory()->create();
        $product = $this->makeProduct(10, 200);
        [, $method] = $this->configureRate(city: 'Cairo', amount: 50);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 200,
            'quantity' => 2,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $this->actingAs($user)
            ->postJson(route('checkout.shipping-quote'), [
                'delivery_method' => $method->code,
                'shipping_city' => 'Cairo',
                'shipping_country' => 'Egypt',
            ])
            ->assertOk()
            ->assertJson([
                'shipping' => 50,
                'total' => 450,
                'currency' => 'EGP',
                'pickup' => false,
            ]);
    }

    public function test_checkout_persists_shipping_snapshot_and_eta_without_repricing_history(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');

        $user = User::factory()->create();
        $product = $this->makeProduct(10, 100);
        [$zone, $method, $rate] = $this->configureRate(
            city: 'Cairo',
            amount: 40,
            threshold: 500,
            basis: ShippingRate::BASIS_BEFORE_DISCOUNTS,
            etaMin: 2,
            etaMax: 4,
        );

        $this->actingAs($user);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 2,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $order = app(CheckoutService::class)->place([
            'customer_name' => 'Shipping Customer',
            'customer_email' => 'shipping@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_method' => $method->code,
        ], $user);

        $this->assertSame('40.00', $order->shipping_total);
        $this->assertSame('240.00', $order->grand_total);
        $this->assertSame($method->id, $order->shipping_method_id);
        $this->assertSame($zone->id, $order->shipping_zone_id);
        $this->assertSame($rate->id, $order->shipping_rate_id);
        $this->assertSame('2026-09-29', $order->estimated_delivery_date->format('Y-m-d'));
        $this->assertSame('Cairo Zone', $order->shipping_snapshot['zone_name_snapshot']);
        $this->assertSame('calendar_days', $order->shipping_snapshot['eta_basis']);

        $rate->update(['amount' => 99]);
        $method->update(['name' => 'Renamed shipping']);

        $order->refresh();

        $this->assertSame('40.00', $order->shipping_total);
        $this->assertSame('Standard shipping', $order->shipping_snapshot['method_name_snapshot']);
        $this->assertSame('Standard shipping', $order->delivery_method_label);
    }

    public function test_shipping_setup_requires_settings_permission(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $operations = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');

        $this->actingAs($operations)
            ->get(route('admin.settings.shipping.methods'))
            ->assertOk();

        $this->actingAs($cashier)
            ->get(route('admin.settings.shipping.methods'))
            ->assertForbidden();
    }

    public function test_city_cannot_be_assigned_to_two_zones_in_same_country(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $operations = $this->staffWithRole('operations_manager');

        $zoneA = ShippingZone::query()->create([
            'code' => 'CAIRO-A',
            'name' => 'Cairo A',
            'country_code' => 'EG',
            'country_name' => 'Egypt',
            'priority' => 10,
            'is_active' => true,
        ]);

        $zoneB = ShippingZone::query()->create([
            'code' => 'CAIRO-B',
            'name' => 'Cairo B',
            'country_code' => 'EG',
            'country_name' => 'Egypt',
            'priority' => 20,
            'is_active' => true,
        ]);

        $this->actingAs($operations)
            ->post(route('admin.settings.shipping.cities.store', $zoneA), ['name' => 'New Cairo'])
            ->assertRedirect();

        $this->post(route('admin.settings.shipping.cities.store', $zoneB), ['name' => ' new   cairo '])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('shipping_zone_cities', 1);
    }

    private function configureRate(
        string $city,
        float $amount,
        ?float $threshold = null,
        ?string $basis = null,
        int $etaMin = 1,
        int $etaMax = 3,
    ): array {
        $method = ShippingMethod::query()
            ->where('code', Order::DELIVERY_METHOD_STANDARD)
            ->firstOrFail();

        $method->update([
            'is_active' => true,
            'eta_min_days' => $etaMin,
            'eta_max_days' => $etaMax,
        ]);

        $zone = ShippingZone::query()->create([
            'code' => 'CAIRO-' . Str::upper(Str::random(5)),
            'name' => 'Cairo Zone',
            'name_ar' => 'منطقة القاهرة',
            'country_code' => 'EG',
            'country_name' => 'Egypt',
            'priority' => 10,
            'is_active' => true,
        ]);

        ShippingZoneCity::query()->create([
            'shipping_zone_id' => $zone->id,
            'country_code' => 'EG',
            'name' => $city,
            'normalized_name' => app(ShippingService::class)->normalizeCity($city),
        ]);

        $rate = ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'amount' => $amount,
            'free_shipping_threshold' => $threshold,
            'threshold_basis' => $threshold !== null ? ($basis ?? ShippingRate::BASIS_BEFORE_DISCOUNTS) : null,
            'is_active' => true,
        ]);

        return [$zone, $method, $rate];
    }

    private function staffWithRole(string $slug): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function makeProduct(int $quantity, float $price): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Shipping Category ' . Str::random(6),
            'slug' => 'shipping-category-' . Str::lower(Str::random(8)),
            'description' => 'Shipping test category',
            'meta_title' => 'Shipping',
            'meta_keyword' => 'shipping',
            'meta_description' => 'Shipping test category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Shipping Product ' . Str::random(6),
            'slug' => 'shipping-product-' . Str::lower(Str::random(8)),
            'sku' => 'SHIP-' . Str::upper(Str::random(6)),
            'category_id' => $categoryId,
            'base_price' => $price,
            'cost_price' => 20,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
