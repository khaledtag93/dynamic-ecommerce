<?php

namespace Tests\Feature;

use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsProductDailyStat;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAnalyticsExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_overview_uses_compact_decision_first_hierarchy(): void
    {
        $owner = $this->createSuperAdmin();

        AnalyticsDailyStat::create([
            'stat_date' => now()->toDateString(),
            'product_views' => 40,
            'cart_views' => 12,
            'add_to_cart_count' => 10,
            'remove_from_cart_count' => 1,
            'checkout_starts' => 6,
            'purchases' => 3,
            'orders_count' => 3,
            'sessions_count' => 20,
            'users_count' => 15,
            'revenue_gross' => 450,
            'discount_total' => 25,
            'shipping_total' => 0,
            'average_order_value' => 150,
            'cart_abandonment_rate' => 0.25,
            'checkout_completion_rate' => 0.5,
            'view_to_cart_rate' => 0.25,
            'view_to_purchase_rate' => 0.075,
            'aggregated_at' => now(),
        ]);
        Cache::flush();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.index', ['range' => '7d']));

        $response
            ->assertOk()
            ->assertSee('Decision read')
            ->assertSee('Realized revenue')
            ->assertSee('Average realized order value')
            ->assertDontSee('Gross revenue')
            ->assertSee('Performance trends')
            ->assertSee('Funnel health')
            ->assertSee('Commercial drilldowns')
            ->assertSee('More diagnostics')
            ->assertDontSee('Comparison storytelling')
            ->assertDontSee('Operator reading mode');

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'id="analytics-panel-performance"'));
        $this->assertSame(1, substr_count($html, 'id="analytics-panel-decision"'));
        $this->assertSame(1, substr_count($html, 'id="analytics-panel-trends"'));
        $this->assertSame(1, substr_count($html, 'id="analytics-panel-funnel"'));
        $this->assertSame(1, substr_count($html, 'id="analytics-panel-drilldowns"'));
    }

    public function test_growth_analytics_does_not_repeat_the_same_signal_layer(): void
    {
        $owner = $this->createSuperAdmin();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.growth', ['range' => '7d']));

        $response
            ->assertOk()
            ->assertSee('Growth focus')
            ->assertDontSee('Growth storytelling')
            ->assertDontSee('Executive focus');
    }

    public function test_growth_insights_reuses_shared_admin_metric_cards(): void
    {
        $owner = $this->createSuperAdmin();

        $response = $this->actingAs($owner)
            ->get(route('admin.growth.insights'));

        $response
            ->assertOk()
            ->assertSee('Attributed revenue')
            ->assertSee('Average churn risk');

        $source = file_get_contents(resource_path('views/admin/growth/insights.blade.php'));
        $this->assertSame(4, substr_count($source, '<x-admin.stat-card'));
        $this->assertStringContainsString("__('Gross margin')", $source);
        $this->assertStringContainsString("\$row['profit']", $source);
        $this->assertStringContainsString("\$row['gross_margin_percent']", $source);
        $this->assertStringNotContainsString("\$row['attributed_revenue']", $source);
    }

    public function test_offers_analytics_uses_realized_net_order_value(): void
    {
        $owner = $this->createSuperAdmin();

        $product = $this->createAnalyticsProduct();

        $realizedOrder = Order::query()->create([
            'order_number' => 'OFFERS-REALIZED-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'grand_total' => 100,
            'refund_total' => 25,
            'discount_total' => 10,
            'coupon_code' => 'NET25',
            'customer_name' => 'Realized Customer',
            'customer_email' => 'offers-realized@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'placed_at' => now(),
        ]);
        $realizedOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 35,
        ]);

        Order::query()->create([
            'order_number' => 'OFFERS-CANCELLED-001',
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'grand_total' => 500,
            'discount_total' => 50,
            'coupon_code' => 'NET25',
            'customer_name' => 'Cancelled Customer',
            'customer_email' => 'offers-cancelled@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'placed_at' => now(),
        ]);

        Cache::flush();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.offers', ['range' => 'today']));

        $response
            ->assertOk()
            ->assertSee('NET25')
            ->assertSee('EGP 75.00')
            ->assertDontSee('EGP 100.00')
            ->assertDontSee('EGP 500.00');
    }

    public function test_product_analytics_variant_revenue_uses_realized_net_order_value(): void
    {
        $owner = $this->createSuperAdmin();
        $product = $this->createAnalyticsProduct();

        AnalyticsProductDailyStat::query()->create([
            'stat_date' => now()->toDateString(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'category_id' => $product->category_id,
            'views' => 5,
            'add_to_cart_count' => 2,
            'purchases' => 1,
            'purchased_quantity' => 1,
            'revenue_gross' => 75,
            'conversion_rate' => 0.2,
            'aggregated_at' => now(),
        ]);

        $order = Order::query()->create([
            'order_number' => 'PRODUCT-REALIZED-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'grand_total' => 100,
            'refund_total' => 25,
            'discount_total' => 0,
            'customer_name' => 'Product Customer',
            'customer_email' => 'product-realized@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 35,
        ]);

        Cache::flush();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.products.show', [
                'product' => $product,
                'range' => 'today',
            ]));

        $response
            ->assertOk()
            ->assertSee('Default / simple product')
            ->assertSee('EGP 75.00')
            ->assertDontSee('EGP 100.00');
    }

    public function test_offers_analytics_uses_one_summary_layer_before_kpis(): void
    {
        $owner = $this->createSuperAdmin();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.offers', ['range' => '7d']));

        $response
            ->assertOk()
            ->assertSee('Offer focus')
            ->assertDontSee('Offer storytelling')
            ->assertDontSee('Offer performance summary');

        $this->assertStringNotContainsString('id="offers-operator-summary"', $response->getContent());
    }

    private function createAnalyticsProduct(): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Analytics Category '.Str::random(6),
            'slug' => 'analytics-category-'.Str::lower(Str::random(8)),
            'description' => 'Analytics test category',
            'meta_title' => 'Analytics',
            'meta_keyword' => 'analytics',
            'meta_description' => 'Analytics test category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Analytics Product '.Str::random(6),
            'slug' => 'analytics-product-'.Str::lower(Str::random(8)),
            'sku' => 'AN-'.Str::upper(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 100,
            'quantity' => 1,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
