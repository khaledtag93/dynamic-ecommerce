<?php

namespace Tests\Feature;

use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProductDailyStat;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\PosReturnItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Analytics\AnalyticsDashboardService;
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

    public function test_analytics_overview_does_not_hide_loss_only_refunded_activity(): void
    {
        $owner = $this->createSuperAdmin();
        $product = $this->createAnalyticsProduct();

        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'entity_type' => AnalyticsEvent::ENTITY_ORDER,
            'entity_id' => 'loss-only-refund',
            'session_id' => null,
            'meta' => [
                'counts_as_purchase' => false,
                'grand_total' => 0,
                'line_items' => [[
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => 0,
                    'realized_revenue' => 0,
                    'realized_cogs' => 40,
                    'profit_total' => -40,
                ]],
            ],
            'occurred_at' => now(),
        ]);

        Cache::flush();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.index', ['range' => 'today']));

        $response
            ->assertOk()
            ->assertViewHas('snapshot', fn ($snapshot) =>
                (int) data_get($snapshot, 'current.totals.orders_count', -1) === 0
                && (float) data_get($snapshot, 'current.totals.revenue_gross', -1) === 0.0
                && collect(data_get($snapshot, 'current.top_products', []))
                    ->contains(fn ($row) => (float) data_get($row, 'profit_total', 0) === -40.0)
            )
            ->assertViewHas('uiState', fn ($state) =>
                data_get($state, 'empty') === false
                && data_get($state, 'show_drilldowns') === true
            );
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
            'cost_total' => 40,
            'profit_total' => 35,
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
            'order_number' => 'OFFERS-REFUNDED-LOSS-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            'grand_total' => 50,
            'refund_total' => 50,
            'discount_total' => 10,
            'cost_total' => 20,
            'profit_total' => -20,
            'customer_name' => 'Refunded Loss Customer',
            'customer_email' => 'offers-refunded-loss@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'placed_at' => now(),
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
            ->assertViewHas('drilldown', function ($drilldown) {
                $discounted = data_get($drilldown, 'discounted_orders');

                return (int) data_get($discounted, 'orders_count', 0) === 1
                    && (float) data_get($discounted, 'discount_total', 0) === 10.0
                    && (float) data_get($discounted, 'revenue_gross', 0) === 75.0
                    && (float) data_get($discounted, 'profit_total', 0) === 15.0;
            })
            ->assertSee('NET25')
            ->assertSee('EGP 75.00')
            ->assertSee('EGP 35.00')
            ->assertSee('46.7%')
            ->assertDontSee('EGP 100.00')
            ->assertDontSee('EGP 500.00');
    }

    public function test_coupon_profitability_uses_weighted_realized_profit_instead_of_revenue_leader(): void
    {
        $owner = $this->createSuperAdmin();

        foreach ([
            ['number' => 'COUPON-HIGHREV-001', 'coupon' => 'HIGHREV', 'total' => 100, 'refund' => 0, 'discount' => 20, 'profit' => 5],
            ['number' => 'COUPON-HIGHPROFIT-001', 'coupon' => 'HIGHPROFIT', 'total' => 60, 'refund' => 0, 'discount' => 5, 'profit' => 40],
            ['number' => 'COUPON-HIGHPROFIT-002', 'coupon' => 'HIGHPROFIT', 'total' => 40, 'refund' => 20, 'discount' => 5, 'profit' => 10],
        ] as $row) {
            Order::query()->create([
                'order_number' => $row['number'],
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => $row['refund'] > 0 ? Order::PAYMENT_STATUS_PARTIALLY_REFUNDED : Order::PAYMENT_STATUS_PAID,
                'grand_total' => $row['total'],
                'refund_total' => $row['refund'],
                'discount_total' => $row['discount'],
                'cost_total' => max(0, ($row['total'] - $row['refund']) - $row['profit']),
                'profit_total' => $row['profit'],
                'coupon_code' => $row['coupon'],
                'customer_name' => 'Coupon Profit Customer',
                'customer_email' => strtolower($row['number']).'@example.test',
                'customer_phone' => '01000000000',
                'shipping_address_line_1' => 'Test address',
                'shipping_city' => 'Cairo',
                'placed_at' => now(),
            ]);
        }

        Order::query()->create([
            'order_number' => 'COUPON-LOSS-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            'grand_total' => 50,
            'refund_total' => 50,
            'discount_total' => 10,
            'cost_total' => 20,
            'profit_total' => -20,
            'coupon_code' => 'LOSS',
            'customer_name' => 'Refunded Coupon Customer',
            'customer_email' => 'coupon-loss@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'placed_at' => now(),
        ]);

        $rows = app(\App\Services\Analytics\AnalyticsRevenueService::class)
            ->couponPerformance(now()->startOfDay(), now()->endOfDay());

        $highRevenue = $rows->firstWhere('coupon_code', 'HIGHREV');
        $highProfit = $rows->firstWhere('coupon_code', 'HIGHPROFIT');
        $loss = $rows->firstWhere('coupon_code', 'LOSS');

        $this->assertSame(100.0, (float) $highRevenue->realized_revenue);
        $this->assertSame(5.0, (float) $highRevenue->profit_total);
        $this->assertSame(5.0, (float) $highRevenue->gross_margin_percent);
        $this->assertSame(80.0, (float) $highProfit->realized_revenue);
        $this->assertSame(50.0, (float) $highProfit->profit_total);
        $this->assertSame(62.5, (float) $highProfit->gross_margin_percent);
        $this->assertSame(2, (int) $highProfit->orders_count);
        $this->assertSame(0, (int) $loss->orders_count);
        $this->assertSame(0.0, (float) $loss->realized_revenue);
        $this->assertSame(0.0, (float) $loss->discount_total);
        $this->assertSame(-20.0, (float) $loss->profit_total);
        $this->assertNull($loss->gross_margin_percent);
        $this->assertSame(0.0, (float) $loss->average_order_value);

        Cache::flush();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.offers', ['range' => 'today']));

        $response
            ->assertOk()
            ->assertSee('HIGHPROFIT')
            ->assertSee('Profit EGP 50.00')
            ->assertSee('62.5%');
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
            ->assertSee('EGP 35.00')
            ->assertSee('46.7%')
            ->assertDontSee('EGP 100.00');
    }

    public function test_product_profitability_excludes_shipping_and_tax_from_merchandise_revenue(): void
    {
        $product = $this->createAnalyticsProduct();
        $order = Order::query()->create([
            'order_number' => 'PRODUCT-MERCH-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'grand_total' => 115,
            'subtotal' => 100,
            'discount_total' => 10,
            'shipping_total' => 20,
            'tax_total' => 5,
            'refund_total' => 0,
            'customer_name' => 'Merchandise Customer',
            'customer_email' => 'merchandise@example.test',
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
            'profit_amount' => 60,
        ]);

        $row = app(\App\Services\Analytics\AnalyticsRevenueService::class)
            ->topVariantsForProduct($product, now()->startOfDay(), now()->endOfDay(), 8)
            ->sole();

        $this->assertSame(90.0, (float) $row->realized_revenue);
        $this->assertSame(40.0, (float) $row->realized_cogs);
        $this->assertSame(50.0, (float) $row->profit_total);
        $this->assertSame(55.56, (float) $row->gross_margin_percent);
    }

    public function test_variant_profitability_retains_fully_refunded_non_restocked_loss_without_counting_quantity(): void
    {
        $product = $this->createAnalyticsProduct();
        $order = Order::query()->create([
            'order_number' => 'PRODUCT-REFUNDED-LOSS-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            'grand_total' => 100,
            'subtotal' => 100,
            'refund_total' => 100,
            'discount_total' => 0,
            'customer_name' => 'Refunded Variant Customer',
            'customer_email' => 'variant-refunded-loss@example.test',
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
            'profit_amount' => 60,
        ]);

        $row = app(\App\Services\Analytics\AnalyticsRevenueService::class)
            ->topVariantsForProduct($product, now()->startOfDay(), now()->endOfDay(), 8)
            ->sole();

        $this->assertSame(0, (int) $row->quantity);
        $this->assertSame(0.0, (float) $row->realized_revenue);
        $this->assertSame(40.0, (float) $row->realized_cogs);
        $this->assertSame(-40.0, (float) $row->profit_total);
        $this->assertNull($row->gross_margin_percent);

        $owner = $this->createSuperAdmin();
        Cache::flush();

        $this->actingAs($owner)
            ->get(route('admin.analytics.products.show', [
                'product' => $product,
                'range' => 'today',
            ]))
            ->assertOk()
            ->assertViewHas('trust', fn ($trust) => data_get($trust, 'has_data') === true);
    }

    public function test_variant_profitability_uses_net_physical_return_quantity(): void
    {
        $product = $this->createAnalyticsProduct();
        $order = Order::query()->create([
            'order_number' => 'PRODUCT-PARTIAL-RETURN-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'grand_total' => 200,
            'subtotal' => 200,
            'refund_total' => 100,
            'discount_total' => 0,
            'customer_name' => 'Partial Return Customer',
            'customer_email' => 'variant-partial-return@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'placed_at' => now(),
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 2,
            'line_total' => 200,
            'profit_amount' => 120,
        ]);
        $refund = OrderRefund::query()->create([
            'order_id' => $order->id,
            'amount' => 100,
            'reason' => 'Physical partial return',
            'processed_at' => now(),
        ]);
        PosReturnItem::query()->create([
            'order_refund_id' => $refund->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
            'amount' => 100,
            'restocked' => true,
        ]);

        $row = app(\App\Services\Analytics\AnalyticsRevenueService::class)
            ->topVariantsForProduct($product, now()->startOfDay(), now()->endOfDay(), 8)
            ->sole();

        $this->assertSame(1, (int) $row->quantity);
        $this->assertSame(100.0, (float) $row->realized_revenue);
        $this->assertSame(40.0, (float) $row->realized_cogs);
        $this->assertSame(60.0, (float) $row->profit_total);
        $this->assertSame(60.0, (float) $row->gross_margin_percent);
    }

    public function test_variant_profitability_separates_revenue_leader_from_profit_leader_and_weights_margin(): void
    {
        $owner = $this->createSuperAdmin();
        $product = $this->createAnalyticsProduct();

        $variantA = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'VAR-A-'.Str::upper(Str::random(6)),
            'price' => 100,
            'stock' => 10,
            'status' => true,
        ]);
        $variantB = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'VAR-B-'.Str::upper(Str::random(6)),
            'price' => 80,
            'stock' => 10,
            'status' => true,
        ]);

        foreach ([
            ['number' => 'VAR-A-LOW', 'variant' => $variantA, 'name' => 'Variant A', 'revenue' => 10, 'cost' => 1],
            ['number' => 'VAR-A-HIGH', 'variant' => $variantA, 'name' => 'Variant A', 'revenue' => 90, 'cost' => 81],
            ['number' => 'VAR-B-PROFIT', 'variant' => $variantB, 'name' => 'Variant B', 'revenue' => 80, 'cost' => 20],
        ] as $row) {
            $order = Order::query()->create([
                'order_number' => $row['number'],
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'grand_total' => $row['revenue'],
                'refund_total' => 0,
                'discount_total' => 0,
                'customer_name' => 'Variant Profit Customer',
                'customer_email' => strtolower($row['number']).'@example.test',
                'customer_phone' => '01000000000',
                'shipping_address_line_1' => 'Test address',
                'shipping_city' => 'Cairo',
                'placed_at' => now(),
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $row['variant']->id,
                'product_name' => $product->name,
                'variant_name' => $row['name'],
                'sku' => $row['variant']->sku,
                'unit_price' => $row['revenue'],
                'unit_cost' => $row['cost'],
                'quantity' => 1,
                'line_total' => $row['revenue'],
                'profit_amount' => $row['revenue'] - $row['cost'],
            ]);
        }

        $rows = app(\App\Services\Analytics\AnalyticsRevenueService::class)
            ->topVariantsForProduct($product, now()->startOfDay(), now()->endOfDay(), 8);

        $revenueLeader = $rows->firstWhere('product_variant_id', $variantA->id);
        $profitLeader = $rows->firstWhere('product_variant_id', $variantB->id);

        $this->assertSame(100.0, (float) $revenueLeader->realized_revenue);
        $this->assertSame(82.0, (float) $revenueLeader->realized_cogs);
        $this->assertSame(18.0, (float) $revenueLeader->profit_total);
        $this->assertSame(18.0, (float) $revenueLeader->gross_margin_percent);
        $this->assertSame(80.0, (float) $profitLeader->realized_revenue);
        $this->assertSame(20.0, (float) $profitLeader->realized_cogs);
        $this->assertSame(60.0, (float) $profitLeader->profit_total);
        $this->assertSame(75.0, (float) $profitLeader->gross_margin_percent);
        $this->assertSame($variantA->id, $rows->first()->product_variant_id);
        $this->assertSame($variantB->id, $rows->sortByDesc('profit_total')->first()->product_variant_id);
        $this->assertNotSame(50.0, (float) $revenueLeader->gross_margin_percent);

        Cache::flush();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.products.show', [
                'product' => $product,
                'range' => 'today',
            ]));

        $response
            ->assertOk()
            ->assertSee('Top revenue variant')
            ->assertSee('Top profit variant')
            ->assertSee('Variant A')
            ->assertSee('Variant B')
            ->assertSee('EGP 60.00')
            ->assertSee('75.0%');
    }

    public function test_variant_profitability_keeps_refund_and_restock_economics_on_the_correct_variant(): void
    {
        $product = $this->createAnalyticsProduct();

        $variantA = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'VAR-REF-A-'.Str::upper(Str::random(6)),
            'price' => 100,
            'stock' => 10,
            'status' => true,
        ]);
        $variantB = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'VAR-REF-B-'.Str::upper(Str::random(6)),
            'price' => 100,
            'stock' => 10,
            'status' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'VAR-REFUND-RESTOCK-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'grand_total' => 200,
            'refund_total' => 80,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'customer_name' => 'Variant Return Customer',
            'customer_email' => 'variant-return@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'placed_at' => now(),
        ]);

        $itemA = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variantA->id,
            'product_name' => $product->name,
            'variant_name' => 'Variant A',
            'sku' => $variantA->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);
        $itemB = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variantB->id,
            'product_name' => $product->name,
            'variant_name' => 'Variant B',
            'sku' => $variantB->sku,
            'unit_price' => 100,
            'unit_cost' => 60,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 40,
        ]);

        $refundA = OrderRefund::query()->create([
            'order_id' => $order->id,
            'idempotency_key' => 'variant-refund-a',
            'amount' => 30,
            'reason' => 'Variant A partial refund',
            'processed_at' => now(),
        ]);
        PosReturnItem::query()->create([
            'order_refund_id' => $refundA->id,
            'order_id' => $order->id,
            'order_item_id' => $itemA->id,
            'quantity' => 1,
            'amount' => 30,
            'restocked' => false,
        ]);

        $refundB = OrderRefund::query()->create([
            'order_id' => $order->id,
            'idempotency_key' => 'variant-refund-b',
            'amount' => 50,
            'reason' => 'Variant B return',
            'processed_at' => now(),
        ]);
        PosReturnItem::query()->create([
            'order_refund_id' => $refundB->id,
            'order_id' => $order->id,
            'order_item_id' => $itemB->id,
            'quantity' => 1,
            'amount' => 50,
            'restocked' => true,
        ]);

        $rows = app(\App\Services\Analytics\AnalyticsRevenueService::class)
            ->topVariantsForProduct($product, now()->startOfDay(), now()->endOfDay(), 8);

        $rowA = $rows->firstWhere('product_variant_id', $variantA->id);
        $rowB = $rows->firstWhere('product_variant_id', $variantB->id);

        $this->assertSame(70.0, (float) $rowA->realized_revenue);
        $this->assertSame(40.0, (float) $rowA->realized_cogs);
        $this->assertSame(30.0, (float) $rowA->profit_total);
        $this->assertSame(42.86, (float) $rowA->gross_margin_percent);

        $this->assertSame(50.0, (float) $rowB->realized_revenue);
        $this->assertSame(0.0, (float) $rowB->realized_cogs);
        $this->assertSame(50.0, (float) $rowB->profit_total);
        $this->assertSame(100.0, (float) $rowB->gross_margin_percent);

        $this->assertSame(120.0, (float) $rows->sum('realized_revenue'));
        $this->assertSame(40.0, (float) $rows->sum('realized_cogs'));
        $this->assertSame(80.0, (float) $rows->sum('profit_total'));
    }

    public function test_product_drilldown_uses_live_events_when_daily_stats_are_dirty(): void
    {
        $owner = $this->createSuperAdmin();
        $product = $this->createAnalyticsProduct();

        AnalyticsProductDailyStat::query()->create([
            'stat_date' => now()->toDateString(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'category_id' => $product->category_id,
            'views' => 9,
            'add_to_cart_count' => 4,
            'purchases' => 1,
            'purchased_quantity' => 1,
            'revenue_gross' => 999,
            'realized_cogs' => 111,
            'profit_total' => 888,
            'conversion_rate' => 0.11,
            'aggregated_at' => now()->subMinute(),
        ]);

        Cache::flush();

        $staleResponse = $this->actingAs($owner)
            ->get(route('admin.analytics.products.show', [
                'product' => $product,
                'range' => 'today',
            ]));

        $staleResponse
            ->assertOk()
            ->assertSee('EGP 999.00');

        AnalyticsDailyStat::query()->create([
            'stat_date' => now()->toDateString(),
            'meta' => [
                'restatement_requested_at' => now()->toIso8601String(),
                'restatement_reason' => 'realized_purchase_changed',
            ],
        ]);

        foreach ([1, 2, 3] as $suffix) {
            AnalyticsEvent::query()->create([
                'event_type' => AnalyticsEvent::EVENT_VIEW_PRODUCT,
                'entity_type' => AnalyticsEvent::ENTITY_PRODUCT,
                'entity_id' => (string) $product->id,
                'occurred_at' => now(),
                'meta' => ['product_id' => $product->id, 'view_copy' => $suffix],
            ]);
        }

        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::EVENT_ADD_TO_CART,
            'entity_type' => AnalyticsEvent::ENTITY_PRODUCT,
            'entity_id' => (string) $product->id,
            'occurred_at' => now(),
            'meta' => ['product_id' => $product->id, 'quantity' => 1],
        ]);

        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'entity_type' => AnalyticsEvent::ENTITY_ORDER,
            'entity_id' => '999999',
            'occurred_at' => now(),
            'meta' => [
                'grand_total' => 75,
                'line_items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'line_total' => 100,
                    'realized_revenue' => 75,
                    'realized_cogs' => 40,
                    'profit_total' => 35,
                    'gross_margin_percent' => 46.67,
                ]],
            ],
        ]);

        $drilldown = app(AnalyticsDashboardService::class)
            ->buildProductDrilldown($product, now()->startOfDay(), now()->endOfDay());

        $this->assertFalse($drilldown['is_aggregated']);
        $this->assertSame(3, $drilldown['totals']['views']);
        $this->assertSame(1, $drilldown['totals']['add_to_cart_count']);
        $this->assertSame(1, $drilldown['totals']['purchases']);
        $this->assertSame(75.0, (float) $drilldown['totals']['revenue_gross']);
        $this->assertSame(40.0, (float) $drilldown['totals']['realized_cogs']);
        $this->assertSame(35.0, (float) $drilldown['totals']['profit_total']);
        $this->assertSame(46.67, (float) $drilldown['totals']['gross_margin_percent']);
        $this->assertSame(75.0, (float) $drilldown['daily']->sole()->revenue_gross);
        $this->assertNotSame(999.0, (float) $drilldown['totals']['revenue_gross']);

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.products.show', [
                'product' => $product,
                'range' => 'today',
            ]));

        $response
            ->assertOk()
            ->assertSee('EGP 75.00')
            ->assertSee('EGP 35.00')
            ->assertSee('46.7%')
            ->assertDontSee('EGP 999.00');
    }

    public function test_product_and_category_profitability_use_weighted_realized_margin_and_preserve_unknown_history(): void
    {
        $first = $this->createAnalyticsProduct();
        $second = Product::query()->create([
            'name' => 'Analytics Product '.Str::random(6),
            'slug' => 'analytics-product-'.Str::lower(Str::random(8)),
            'sku' => 'AN-'.Str::upper(Str::random(8)),
            'category_id' => $first->category_id,
            'base_price' => 100,
            'quantity' => 1,
            'status' => true,
            'has_variants' => false,
        ]);

        foreach ([
            [$first, 10, 1, 9],
            [$second, 90, 81, 9],
        ] as [$product, $revenue, $cogs, $profit]) {
            AnalyticsProductDailyStat::query()->create([
                'stat_date' => now()->toDateString(),
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'category_id' => $product->category_id,
                'views' => 10,
                'add_to_cart_count' => 2,
                'purchases' => 1,
                'purchased_quantity' => 1,
                'revenue_gross' => $revenue,
                'realized_cogs' => $cogs,
                'profit_total' => $profit,
                'conversion_rate' => 0.1,
                'aggregated_at' => now(),
            ]);
        }

        $snapshot = app(AnalyticsDashboardService::class)
            ->buildSnapshot(now()->startOfDay(), now()->endOfDay());

        $products = collect($snapshot['current']['top_products']);
        $firstRow = $products->firstWhere('product_id', $first->id);
        $secondRow = $products->firstWhere('product_id', $second->id);
        $category = collect($snapshot['current']['top_categories'])->sole();

        $this->assertSame(90.0, (float) $firstRow->gross_margin_percent);
        $this->assertSame(10.0, (float) $secondRow->gross_margin_percent);
        $this->assertSame(100.0, (float) $category->revenue_gross);
        $this->assertSame(18.0, (float) $category->profit_total);
        $this->assertSame(18.0, (float) $category->gross_margin_percent);
        $this->assertNotSame(50.0, (float) $category->gross_margin_percent);

        $historical = $this->createAnalyticsProduct();
        AnalyticsProductDailyStat::query()->create([
            'stat_date' => now()->subDay()->toDateString(),
            'product_id' => $historical->id,
            'product_name' => $historical->name,
            'product_slug' => $historical->slug,
            'category_id' => $historical->category_id,
            'views' => 5,
            'add_to_cart_count' => 1,
            'purchases' => 1,
            'purchased_quantity' => 1,
            'revenue_gross' => 75,
            'conversion_rate' => 0.2,
            'aggregated_at' => now(),
        ]);

        $historicalSnapshot = app(AnalyticsDashboardService::class)
            ->buildSnapshot(now()->subDay()->startOfDay(), now()->subDay()->endOfDay());
        $historicalRow = collect($historicalSnapshot['current']['top_products'])->sole();

        $this->assertSame(75.0, (float) $historicalRow->revenue_gross);
        $this->assertFalse((bool) $historicalRow->profitability_complete);
        $this->assertNull($historicalRow->profit_total);
        $this->assertNull($historicalRow->gross_margin_percent);
    }

    public function test_zero_purchase_legacy_rows_do_not_poison_profitability_completeness(): void
    {
        $product = $this->createAnalyticsProduct();

        AnalyticsProductDailyStat::query()->create([
            'stat_date' => now()->subDay()->toDateString(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'category_id' => $product->category_id,
            'views' => 12,
            'add_to_cart_count' => 2,
            'purchases' => 0,
            'purchased_quantity' => 0,
            'revenue_gross' => 0,
            'realized_cogs' => null,
            'profit_total' => null,
            'conversion_rate' => 0,
            'aggregated_at' => now()->subDay(),
        ]);

        AnalyticsProductDailyStat::query()->create([
            'stat_date' => now()->toDateString(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'category_id' => $product->category_id,
            'views' => 8,
            'add_to_cart_count' => 2,
            'purchases' => 1,
            'purchased_quantity' => 1,
            'revenue_gross' => 100,
            'realized_cogs' => 40,
            'profit_total' => 60,
            'conversion_rate' => 0.125,
            'aggregated_at' => now(),
        ]);

        $snapshot = app(AnalyticsDashboardService::class)
            ->buildSnapshot(now()->subDay()->startOfDay(), now()->endOfDay());

        $productRow = collect($snapshot['current']['top_products'])->sole();
        $categoryRow = collect($snapshot['current']['top_categories'])->sole();

        foreach ([$productRow, $categoryRow] as $row) {
            $this->assertTrue((bool) $row->profitability_complete);
            $this->assertSame(100.0, (float) $row->revenue_gross);
            $this->assertSame(40.0, (float) $row->realized_cogs);
            $this->assertSame(60.0, (float) $row->profit_total);
            $this->assertSame(60.0, (float) $row->gross_margin_percent);
        }
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
