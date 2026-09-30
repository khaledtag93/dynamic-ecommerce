<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\GrowthAttributionTouch;
use App\Models\GrowthAudienceSegment;
use App\Models\GrowthAutomationRule;
use App\Models\GrowthCampaign;
use App\Models\GrowthCohortSnapshot;
use App\Models\GrowthExperiment;
use App\Models\GrowthMessageTemplate;
use App\Models\GrowthDelivery;
use App\Models\GrowthMessageLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Analytics\GrowthAutomationService;
use App\Services\Commerce\OrderActionService;
use App\Services\Growth\GrowthAttributionService;
use App\Services\Growth\GrowthCampaignService;
use App\Services\Growth\GrowthCohortRetentionService;
use App\Services\Growth\GrowthDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthControlIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_growth_settings_update_preserves_unsubmitted_advanced_controls(): void
    {
        WebsiteSetting::setValue('growth_ai_selection_enabled', '1', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_smart_timing_enabled', '1', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_predictive_enabled', '1', 'growth', 'boolean');

        app(GrowthCampaignService::class)->updateSettings([
            'growth_engine_enabled' => 0,
            'growth_messaging_enabled' => 1,
        ]);

        $this->assertSame('0', (string) WebsiteSetting::getValue('growth_engine_enabled'));
        $this->assertSame('1', (string) WebsiteSetting::getValue('growth_messaging_enabled'));
        $this->assertSame('1', (string) WebsiteSetting::getValue('growth_ai_selection_enabled'));
        $this->assertSame('1', (string) WebsiteSetting::getValue('growth_smart_timing_enabled'));
        $this->assertSame('1', (string) WebsiteSetting::getValue('growth_predictive_enabled'));
    }

    public function test_ensure_defaults_does_not_overwrite_admin_growth_customizations(): void
    {
        $service = app(GrowthCampaignService::class);
        $service->ensureDefaults();

        $campaign = GrowthCampaign::query()->where('campaign_key', 'cart_recovery')->firstOrFail();
        $rule = GrowthAutomationRule::query()->where('rule_key', 'cart_recovery')->firstOrFail();
        $segment = GrowthAudienceSegment::query()->where('segment_key', 'cart_abandoners')->firstOrFail();
        $template = GrowthMessageTemplate::query()->where('template_key', 'cart_recovery')->where('locale', 'en')->firstOrFail();
        $experiment = GrowthExperiment::query()->where('experiment_key', 'cart_recovery_offer_test')->firstOrFail();

        $campaign->update(['name' => 'Custom campaign name', 'is_active' => false]);
        $rule->update(['name' => 'Custom rule name', 'is_active' => false]);
        $segment->update(['description' => 'Custom segment description', 'is_active' => false]);
        $template->update(['body' => 'Custom template body', 'is_active' => false]);
        $experiment->update(['name' => 'Custom experiment name', 'is_active' => false]);

        $service->ensureDefaults();

        $this->assertSame('Custom campaign name', $campaign->fresh()->name);
        $this->assertFalse((bool) $campaign->fresh()->is_active);
        $this->assertSame('Custom rule name', $rule->fresh()->name);
        $this->assertFalse((bool) $rule->fresh()->is_active);
        $this->assertSame('Custom segment description', $segment->fresh()->description);
        $this->assertFalse((bool) $segment->fresh()->is_active);
        $this->assertSame('Custom template body', $template->fresh()->body);
        $this->assertFalse((bool) $template->fresh()->is_active);
        $this->assertSame('Custom experiment name', $experiment->fresh()->name);
        $this->assertFalse((bool) $experiment->fresh()->is_active);
    }

    public function test_growth_dashboard_snapshot_does_not_recompute_heavy_analytics_on_get(): void
    {
        $serviceSource = file_get_contents(app_path('Services/Growth/GrowthCampaignService.php'));
        $commandSource = file_get_contents(app_path('Console/Commands/RunGrowthAutomationCommand.php'));

        $snapshotStart = strpos($serviceSource, 'public function dashboardSnapshot(');
        $snapshotEnd = strpos($serviceSource, 'public function engineEnabled(): bool', $snapshotStart);
        $snapshotSource = substr($serviceSource, $snapshotStart, $snapshotEnd - $snapshotStart);

        $this->assertStringNotContainsString('syncRecentAttribution()', $snapshotSource);
        $this->assertStringNotContainsString('refreshSnapshots((int) config(\'growth.cohort_months\'', $snapshotSource);
        $this->assertStringNotContainsString('refreshScores()', $snapshotSource);
        $this->assertStringNotContainsString('GrowthAdaptiveLearningService::class)->refreshSnapshots()', $snapshotSource);

        $controllerSource = file_get_contents(app_path('Http/Controllers/Admin/GrowthController.php'));

        $this->assertStringContainsString('dashboardSnapshot($page)', $controllerSource);
        $this->assertStringContainsString('public function dashboardSnapshot(string $page = \'full\'): array', $serviceSource);
        $this->assertStringContainsString('growth:run', file_get_contents(app_path('Console/Kernel.php')));
        $this->assertStringContainsString('syncRecentAttribution()', $commandSource);
        $this->assertStringContainsString('GrowthCohortRetentionService', $commandSource);
        $this->assertStringNotContainsString('GrowthPredictiveIntelligenceService', $commandSource);
        $this->assertStringNotContainsString('GrowthAdaptiveLearningService', $commandSource);
    }

    public function test_growth_workspace_snapshots_only_load_the_data_needed_by_that_page(): void
    {
        $service = app(GrowthCampaignService::class);

        $content = $service->dashboardSnapshot('content');
        $this->assertTrue($content['campaigns']->isNotEmpty());
        $this->assertTrue($content['rules']->isNotEmpty());
        $this->assertTrue($content['templates']->isNotEmpty());
        $this->assertTrue($content['deliveries']->isEmpty());
        $this->assertTrue($content['trigger_logs']->isEmpty());
        $this->assertSame([], $content['attribution_summary']);
        $this->assertSame([], $content['predictive_summary']);
        $this->assertSame([], $content['performance']);

        $operations = $service->dashboardSnapshot('operations');
        $this->assertTrue($operations['campaigns']->isEmpty());
        $this->assertTrue($operations['rules']->isEmpty());
        $this->assertTrue($operations['templates']->isEmpty());
        $this->assertTrue($operations['segments']->isEmpty());
        $this->assertSame([], $operations['attribution_summary']);
        $this->assertSame([], $operations['cohort_summary']);
        $this->assertSame([], $operations['predictive_summary']);
        $this->assertSame([], $operations['performance']);

        $insights = $service->dashboardSnapshot('insights');
        $this->assertTrue($insights['campaigns']->isEmpty());
        $this->assertTrue($insights['rules']->isEmpty());
        $this->assertTrue($insights['templates']->isEmpty());
        $this->assertTrue($insights['deliveries']->isEmpty());
        $this->assertSame([], $insights['performance']);
    }

    public function test_growth_experiment_performance_batches_order_windows_without_changing_results(): void
    {
        $user = User::factory()->create();

        $experiment = GrowthExperiment::query()->create([
            'name' => 'Performance test',
            'experiment_key' => 'performance_test',
            'variants' => [
                ['key' => 'a', 'name' => 'A', 'weight' => 1],
                ['key' => 'b', 'name' => 'B', 'weight' => 1],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $reference = now()->startOfMinute();

        foreach ([
            ['variant' => 'a', 'sent_at' => $reference->copy()->subMinutes(120)],
            ['variant' => 'a', 'sent_at' => $reference->copy()->subMinutes(60)],
            ['variant' => 'b', 'sent_at' => $reference->copy()->subMinutes(180)],
        ] as $delivery) {
            GrowthDelivery::query()->create([
                'experiment_id' => $experiment->id,
                'user_id' => $user->id,
                'channel' => 'in_app',
                'status' => 'sent',
                'experiment_variant' => $delivery['variant'],
                'sent_at' => $delivery['sent_at'],
            ]);
        }

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'GROWTH-PERF-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'grand_total' => 120,
            'refund_total' => 20,
            'customer_name' => 'Growth Test',
            'customer_email' => 'growth-performance@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
        ]);
        $order->forceFill([
            'placed_at' => $reference->copy()->subMinutes(90),
            'created_at' => $reference->copy()->subDays(30),
            'updated_at' => $reference->copy()->subDays(30),
        ])->save();

        $result = collect(app(GrowthCampaignService::class)->variantPerformance($experiment))->keyBy('key');

        $this->assertSame(2, $result['a']['deliveries']);
        $this->assertSame(1, $result['a']['converted']);
        $this->assertSame(50.0, $result['a']['conversion_rate']);
        $this->assertSame(100.0, $result['a']['revenue']);

        $this->assertSame(1, $result['b']['deliveries']);
        $this->assertSame(1, $result['b']['converted']);
        $this->assertSame(100.0, $result['b']['conversion_rate']);
        $this->assertSame(100.0, $result['b']['revenue']);

        $serviceSource = file_get_contents(app_path('Services/Growth/GrowthCampaignService.php'));
        $methodStart = strpos($serviceSource, 'public function variantPerformance(GrowthExperiment $experiment): array');
        $methodEnd = strpos($serviceSource, 'protected function defaultCampaigns(): array', $methodStart);
        $methodSource = substr($serviceSource, $methodStart, $methodEnd - $methodStart);

        $this->assertSame(1, substr_count($methodSource, 'GrowthDelivery::query()'));
        $this->assertSame(1, substr_count($methodSource, 'Order::query()'));
        $this->assertStringNotContainsString('->exists()', $methodSource);
    }

    public function test_predictive_commerce_metrics_ignore_unrealized_orders_and_use_net_revenue(): void
    {
        $user = User::factory()->create();

        foreach ([
            ['number' => 'REALIZED-001', 'status' => Order::STATUS_COMPLETED, 'payment' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, 'total' => 200, 'refund' => 50],
            ['number' => 'CANCELLED-001', 'status' => Order::STATUS_CANCELLED, 'payment' => Order::PAYMENT_STATUS_PAID, 'total' => 300, 'refund' => 0],
            ['number' => 'UNPAID-001', 'status' => Order::STATUS_COMPLETED, 'payment' => Order::PAYMENT_STATUS_UNPAID, 'total' => 400, 'refund' => 0],
        ] as $row) {
            Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $row['number'],
                'status' => $row['status'],
                'payment_status' => $row['payment'],
                'grand_total' => $row['total'],
                'refund_total' => $row['refund'],
                'customer_name' => 'Commercial Integrity',
                'customer_email' => 'commercial-integrity@example.test',
                'customer_phone' => '01000000000',
                'shipping_address_line_1' => 'Test address',
                'shipping_city' => 'Cairo',
                'placed_at' => now()->subDay(),
            ]);
        }

        AnalyticsEvent::query()->create([
            'user_id' => $user->id,
            'session_id' => 'predictive-realized',
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'meta' => ['counts_as_purchase' => true],
            'occurred_at' => now()->subDay(),
        ]);
        AnalyticsEvent::query()->create([
            'user_id' => $user->id,
            'session_id' => 'predictive-refunded',
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'meta' => ['counts_as_purchase' => false],
            'occurred_at' => now()->subDay(),
        ]);

        $score = app(\App\Services\Growth\GrowthPredictiveIntelligenceService::class)->refreshUserScore($user);

        $this->assertSame(1, $score->orders_count);
        $this->assertSame(1, $score->completed_orders_count);
        $this->assertSame('150.00', $score->total_revenue);
        $this->assertSame('150.00', $score->average_order_value);
        $this->assertSame(1, $score->purchase_count_90d);
    }

    public function test_cohort_revenue_accumulates_all_realized_repeat_orders_within_each_window(): void
    {
        $user = User::factory()->create();
        $firstAt = now()->startOfDay()->subDays(80);

        $createOrder = function (
            string $number,
            int $daysAfterFirst,
            float $grandTotal,
            string $status = Order::STATUS_COMPLETED,
            string $paymentStatus = Order::PAYMENT_STATUS_PAID,
            float $refundTotal = 0
        ) use ($user, $firstAt): Order {
            return Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $number,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_method' => Order::PAYMENT_METHOD_COD,
                'delivery_status' => $status === Order::STATUS_COMPLETED
                    ? Order::DELIVERY_STATUS_DELIVERED
                    : Order::DELIVERY_STATUS_CANCELLED,
                'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
                'currency' => 'EGP',
                'subtotal' => $grandTotal,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'grand_total' => $grandTotal,
                'refund_total' => $refundTotal,
                'customer_name' => 'Cohort Customer',
                'customer_email' => 'cohort-customer@example.test',
                'customer_phone' => '01000000000',
                'shipping_address_line_1' => 'Test address',
                'shipping_city' => 'Cairo',
                'shipping_country' => 'Egypt',
                'billing_same_as_shipping' => true,
                'placed_at' => $firstAt->copy()->addDays($daysAfterFirst),
            ]);
        };

        $createOrder('COHORT-FIRST', 0, 50);
        $createOrder('COHORT-10D', 10, 100);
        $createOrder(
            'COHORT-25D-PARTIAL',
            25,
            150,
            Order::STATUS_COMPLETED,
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            20
        );
        $createOrder('COHORT-CANCELLED', 15, 999, Order::STATUS_CANCELLED, Order::PAYMENT_STATUS_FAILED);
        $createOrder('COHORT-50D', 50, 200);
        $createOrder('COHORT-75D', 75, 300);

        app(GrowthCohortRetentionService::class)->refreshSnapshots(6);

        $snapshot = GrowthCohortSnapshot::query()
            ->where('cohort_key', $firstAt->format('Y-m'))
            ->firstOrFail();

        $this->assertSame(1, $snapshot->cohort_size);
        $this->assertSame(1, $snapshot->retained_30d);
        $this->assertSame(1, $snapshot->retained_60d);
        $this->assertSame(1, $snapshot->retained_90d);
        $this->assertSame(230.0, (float) $snapshot->revenue_30d);
        $this->assertSame(430.0, (float) $snapshot->revenue_60d);
        $this->assertSame(730.0, (float) $snapshot->revenue_90d);
    }

    public function test_attribution_weights_and_revenue_are_normalized_per_realized_order(): void
    {
        $user = User::factory()->create();
        $olderDelivery = GrowthDelivery::query()->create([
            'user_id' => $user->id,
            'channel' => 'in_app',
            'provider' => 'database',
            'status' => 'sent',
            'sent_at' => now()->subHours(2),
        ]);
        $newerDelivery = GrowthDelivery::query()->create([
            'user_id' => $user->id,
            'channel' => 'in_app',
            'provider' => 'database',
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ATTR-NORMALIZED-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 10,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'refund_total' => 20,
            'profit_total' => 50,
            'customer_name' => 'Attribution Customer',
            'customer_email' => 'attribution@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now()->subMinutes(30),
            'delivered_at' => now()->subMinutes(20),
        ]);

        $service = app(GrowthAttributionService::class);
        $service->syncForDelivery($olderDelivery);
        $service->syncForDelivery($newerDelivery);

        $touches = GrowthAttributionTouch::query()
            ->where('order_id', $order->id)
            ->orderBy('delivery_id')
            ->get();

        $this->assertCount(2, $touches);
        $this->assertSame(1.0, round((float) $touches->sum('attribution_weight'), 4));
        $this->assertSame(80.0, round((float) $touches->sum('revenue'), 2));
        $this->assertSame(10.0, round((float) $touches->sum('discount_total'), 2));
        $this->assertSame(50.0, round((float) $touches->sum('profit_total'), 2));
        $this->assertSame('assist', $touches->firstWhere('delivery_id', $olderDelivery->id)->touch_type);
        $this->assertSame('last_touch', $touches->firstWhere('delivery_id', $newerDelivery->id)->touch_type);

        $summary = $service->summary();
        $this->assertSame(1, $summary['attributed_orders']);
        $this->assertSame(80.0, $summary['attributed_revenue']);
        $this->assertSame(50.0, $summary['attributed_profit']);
        $this->assertSame(62.5, $summary['attributed_gross_margin_percent']);

        $olderDelivery->update(['sent_at' => now()->subDays(30)]);
        $newerDelivery->update(['sent_at' => now()->subDays(29)]);
        GrowthAttributionTouch::query()
            ->where('order_id', $order->id)
            ->update(['attributed_at' => now()->subMinute()]);

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            'refund_total' => 100,
            'profit_total' => -20,
        ]);

        $service->syncRecentAttribution();

        $touches = GrowthAttributionTouch::query()->where('order_id', $order->id)->get();
        $this->assertCount(2, $touches);
        $this->assertSame(0.0, round((float) $touches->sum('revenue'), 2));
        $this->assertSame(-20.0, round((float) $touches->sum('profit_total'), 2));

        $summary = $service->summary();
        $this->assertSame(0, $summary['attributed_orders']);
        $this->assertSame(0.0, $summary['attributed_revenue']);
        $this->assertSame(-20.0, $summary['attributed_profit']);
        $this->assertNull($summary['attributed_gross_margin_percent']);
    }

    public function test_aggregated_growth_margin_is_weighted_by_revenue_not_averaged_by_order(): void
    {
        $campaign = GrowthCampaign::query()->create([
            'name' => 'Weighted Margin Campaign',
            'campaign_key' => 'weighted-margin-campaign',
            'campaign_type' => 'retention',
            'channel' => 'in_app',
        ]);

        $orders = collect([
            ['number' => 'MARGIN-WEIGHT-001', 'revenue' => 10.0, 'profit' => 9.0],
            ['number' => 'MARGIN-WEIGHT-002', 'revenue' => 90.0, 'profit' => 9.0],
        ])->map(function (array $row) {
            return Order::query()->create([
                'order_number' => $row['number'],
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'payment_method' => Order::PAYMENT_METHOD_COD,
                'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
                'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
                'currency' => 'EGP',
                'subtotal' => $row['revenue'],
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'grand_total' => $row['revenue'],
                'refund_total' => 0,
                'profit_total' => $row['profit'],
                'customer_name' => 'Weighted Margin Customer',
                'customer_email' => strtolower($row['number']).'@example.test',
                'customer_phone' => '01000000000',
                'shipping_address_line_1' => 'Test address',
                'shipping_city' => 'Cairo',
                'shipping_country' => 'Egypt',
                'billing_same_as_shipping' => true,
                'placed_at' => now()->subHour(),
                'delivered_at' => now()->subMinutes(30),
            ]);
        });

        foreach ($orders as $index => $order) {
            $row = $index === 0
                ? ['revenue' => 10.0, 'profit' => 9.0]
                : ['revenue' => 90.0, 'profit' => 9.0];

            GrowthAttributionTouch::query()->create([
                'campaign_id' => $campaign->id,
                'order_id' => $order->id,
                'touch_type' => 'last_touch',
                'status' => 'attributed',
                'attribution_weight' => 1,
                'revenue' => $row['revenue'],
                'discount_total' => 0,
                'profit_total' => $row['profit'],
                'occurred_at' => now()->subMinutes(20),
                'attributed_at' => now()->subMinutes(10),
            ]);
        }

        $service = app(GrowthAttributionService::class);
        $summary = $service->summary();
        $breakdown = $service->campaignBreakdown()->first();

        $this->assertSame(100.0, $summary['attributed_revenue']);
        $this->assertSame(18.0, $summary['attributed_profit']);
        $this->assertSame(18.0, $summary['attributed_gross_margin_percent']);
        $this->assertSame('Weighted Margin Campaign', $breakdown['campaign_name']);
        $this->assertSame(100.0, $breakdown['revenue']);
        $this->assertSame(18.0, $breakdown['profit']);
        $this->assertSame(18.0, $breakdown['gross_margin_percent']);
        $this->assertSame(2, $breakdown['orders']);
        $this->assertSame(2, $breakdown['touches']);
        $this->assertNotSame(50.0, $breakdown['gross_margin_percent']);
    }

    public function test_refund_restates_growth_attribution_immediately_without_waiting_for_growth_run(): void
    {
        $user = User::factory()->create();
        $delivery = GrowthDelivery::query()->create([
            'user_id' => $user->id,
            'channel' => 'in_app',
            'provider' => 'database',
            'status' => 'sent',
            'sent_at' => now()->subHours(2),
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ATTR-REFUND-IMMEDIATE-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'refund_total' => 0,
            'cost_total' => 40,
            'profit_total' => 60,
            'customer_name' => 'Immediate Attribution Customer',
            'customer_email' => 'attribution-refund@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now()->subHour(),
            'delivered_at' => now()->subMinutes(50),
        ]);
        $order->items()->create([
            'product_name' => 'Attributed product',
            'sku' => 'ATTR-REFUND-001',
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'ATTR-REFUND-PAYMENT-001',
            'amount' => 100,
            'currency' => 'EGP',
            'paid_at' => now()->subMinutes(55),
        ]);

        $attribution = app(GrowthAttributionService::class);
        $attribution->syncForDelivery($delivery);

        $touch = GrowthAttributionTouch::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(100.0, (float) $touch->revenue);
        $this->assertSame(60.0, (float) $touch->profit_total);

        app(OrderActionService::class)->refund($order, 25, 'Immediate attribution refund');

        $touch = $touch->fresh();
        $freshOrder = $order->fresh();

        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $freshOrder->payment_status);
        $this->assertSame(75.0, (float) $freshOrder->realized_revenue);
        $this->assertSame(35.0, (float) $freshOrder->profit_total);
        $this->assertSame(75.0, (float) $touch->revenue);
        $this->assertSame(35.0, (float) $touch->profit_total);
        $this->assertSame(75.0, $attribution->summary()['attributed_revenue']);
        $this->assertSame(35.0, $attribution->summary()['attributed_profit']);

        app(OrderActionService::class)->refund($freshOrder, 75, 'Full attribution refund');

        $touch = $touch->fresh();
        $freshOrder = $order->fresh();
        $summary = $attribution->summary();

        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $freshOrder->payment_status);
        $this->assertSame(0.0, (float) $freshOrder->realized_revenue);
        $this->assertSame(-40.0, (float) $freshOrder->profit_total);
        $this->assertSame(0.0, (float) $touch->revenue);
        $this->assertSame(-40.0, (float) $touch->profit_total);
        $this->assertSame(0, $summary['attributed_orders']);
        $this->assertSame(0.0, $summary['attributed_revenue']);
        $this->assertSame(-40.0, $summary['attributed_profit']);
    }

    public function test_growth_opportunities_stop_targeting_placed_orders_and_ignore_unrealized_sales(): void
    {
        $user = User::factory()->create();
        $sessionId = 'growth-order-placed-session';
        $occurredAt = now()->subHour();

        foreach ([
            AnalyticsEvent::EVENT_ADD_TO_CART,
            AnalyticsEvent::EVENT_CHECKOUT_START,
            AnalyticsEvent::EVENT_ORDER_PLACED,
        ] as $eventType) {
            AnalyticsEvent::query()->create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'event_type' => $eventType,
                'occurred_at' => $occurredAt,
            ]);
        }

        $createOrder = function (
            string $number,
            string $status,
            string $paymentStatus,
            float $discount
        ) use ($user): void {
            Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $number,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_method' => Order::PAYMENT_METHOD_COD,
                'delivery_status' => $status === Order::STATUS_COMPLETED
                    ? Order::DELIVERY_STATUS_DELIVERED
                    : Order::DELIVERY_STATUS_PENDING,
                'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
                'currency' => 'EGP',
                'subtotal' => 100,
                'discount_total' => $discount,
                'shipping_total' => 0,
                'tax_total' => 0,
                'grand_total' => 100 - $discount,
                'customer_name' => 'Growth Summary Customer',
                'customer_email' => 'growth-summary@example.test',
                'customer_phone' => '01000000000',
                'shipping_address_line_1' => 'Test address',
                'shipping_city' => 'Cairo',
                'shipping_country' => 'Egypt',
                'billing_same_as_shipping' => true,
                'placed_at' => now()->subMinutes(30),
            ]);
        };

        $createOrder(
            'GROWTH-REALIZED-001',
            Order::STATUS_COMPLETED,
            Order::PAYMENT_STATUS_PAID,
            10
        );
        $createOrder(
            'GROWTH-REALIZED-002',
            Order::STATUS_COMPLETED,
            Order::PAYMENT_STATUS_PAID,
            0
        );
        $createOrder(
            'GROWTH-UNREALIZED-001',
            Order::STATUS_PENDING,
            Order::PAYMENT_STATUS_UNPAID,
            25
        );

        $snapshot = app(GrowthAutomationService::class)->buildSnapshot(
            now()->subDay()->startOfDay(),
            now()->endOfDay()
        );

        $this->assertSame(0, $snapshot['summary']['warm_cart_sessions']);
        $this->assertSame(0, $snapshot['summary']['checkout_drop_sessions']);
        $this->assertSame(1, $snapshot['summary']['repeat_customer_candidates']);
        $this->assertSame(1, $snapshot['summary']['discounted_orders']);
    }

    public function test_skipped_growth_delivery_does_not_record_a_sent_timestamp(): void
    {
        $delivery = GrowthDelivery::query()->create([
            'channel' => 'in_app',
            'provider' => 'database',
            'status' => 'pending',
            'message' => 'Growth message without a recipient.',
            'payload' => ['message' => 'Growth message without a recipient.'],
            'scheduled_for' => now(),
        ]);

        $result = app(GrowthDeliveryService::class)->send($delivery->id);
        $messageLog = GrowthMessageLog::query()->where('delivery_id', $delivery->id)->firstOrFail();

        $this->assertSame('skipped', $result->status);
        $this->assertNull($result->sent_at);
        $this->assertSame('skipped', $messageLog->status);
        $this->assertNull($messageLog->sent_at);
    }

    public function test_disabled_growth_run_returns_a_complete_result_shape(): void
    {
        WebsiteSetting::setValue('growth_engine_enabled', '0', 'growth', 'boolean');

        $result = app(GrowthCampaignService::class)->runNow();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['triggered']);
        $this->assertSame(0, $result['messages']);
        $this->assertSame(0, $result['scheduled']);
        $this->assertSame(0, $result['due']);
        $this->assertSame(0, $result['skipped']);
        $this->assertNotEmpty($result['note']);
    }
}
