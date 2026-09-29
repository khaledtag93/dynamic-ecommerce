<?php

namespace Tests\Feature;

use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProductDailyStat;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsRealizedPurchaseReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_dry_run_and_apply_restore_realized_purchase_history(): void
    {
        $product = $this->product();
        $staleDate = now()->subDays(10)->startOfDay()->addHours(12);
        $stale = $this->order(
            'AN-STALE',
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            100,
            25,
            $staleDate
        );

        $this->item($stale, $product, 100);
        $legacyMeta = [
            'order_id' => $stale->id,
            'order_number' => $stale->order_number,
            'grand_total' => 100,
            'line_items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'line_total' => 100,
            ]],
        ];

        foreach ([1, 2] as $suffix) {
            AnalyticsEvent::query()->create([
                'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
                'entity_type' => AnalyticsEvent::ENTITY_ORDER,
                'entity_id' => (string) $stale->id,
                'occurred_at' => $staleDate,
                'meta' => $legacyMeta + ['legacy_copy' => $suffix],
            ]);
        }

        $refundedDate = now()->subDays(8)->startOfDay()->addHours(12);
        $refunded = $this->order(
            'AN-REFUNDED',
            Order::PAYMENT_STATUS_REFUNDED,
            50,
            50,
            $refundedDate
        );
        $this->item($refunded, $product, 50);
        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'entity_type' => AnalyticsEvent::ENTITY_ORDER,
            'entity_id' => (string) $refunded->id,
            'occurred_at' => $refundedDate,
            'meta' => [
                'order_id' => $refunded->id,
                'grand_total' => 50,
            ],
        ]);

        $missingDate = now()->subDays(6)->startOfDay()->addHours(9);
        $missing = $this->order(
            'AN-MISSING',
            Order::PAYMENT_STATUS_PAID,
            40,
            0,
            null,
            $missingDate
        );
        $this->item($missing, $product, 40);

        $this->assertSame(0, Artisan::call(
            'analytics:reconcile-realized-purchases',
            ['--limit' => 20]
        ));

        $dryRun = Artisan::output();
        $this->assertStringContainsString('DRY-RUN complete', $dryRun);
        $this->assertStringContainsString('changed=3', $dryRun);
        $this->assertStringContainsString('creates=1', $dryRun);
        $this->assertStringContainsString('updates=1', $dryRun);
        $this->assertStringContainsString('deletes=1', $dryRun);
        $this->assertStringContainsString('duplicate-events=1', $dryRun);
        $this->assertStringContainsString('applied=0', $dryRun);

        $this->assertSame(2, $this->purchaseEvents($stale)->count());
        $this->assertSame(1, $this->purchaseEvents($refunded)->count());
        $this->assertSame(0, $this->purchaseEvents($missing)->count());

        $this->assertSame(0, Artisan::call(
            'analytics:reconcile-realized-purchases',
            ['--limit' => 20, '--apply' => true]
        ));

        $apply = Artisan::output();
        $this->assertStringContainsString('APPLY complete', $apply);
        $this->assertStringContainsString('applied=3', $apply);

        $staleEvent = $this->purchaseEvents($stale)->sole();
        $this->assertSame(75.0, (float) data_get($staleEvent->meta, 'grand_total'));
        $this->assertSame(
            75.0,
            (float) data_get($staleEvent->meta, 'line_items.0.realized_revenue')
        );
        $this->assertSame(10.0, (float) data_get($staleEvent->meta, 'line_items.0.realized_cogs'));
        $this->assertSame(65.0, (float) data_get($staleEvent->meta, 'line_items.0.profit_total'));
        $this->assertSame(86.67, (float) data_get($staleEvent->meta, 'line_items.0.gross_margin_percent'));
        $this->assertSame(10.0, (float) data_get($staleEvent->meta, 'realized_cogs'));
        $this->assertSame(65.0, (float) data_get($staleEvent->meta, 'profit_total'));

        $this->assertTrue($staleEvent->occurred_at->equalTo($staleDate));

        $this->assertSame(0, $this->purchaseEvents($refunded)->count());

        $missingEvent = $this->purchaseEvents($missing)->sole();
        $this->assertSame(40.0, (float) data_get($missingEvent->meta, 'grand_total'));
        $this->assertTrue($missingEvent->occurred_at->equalTo($missingDate));

        foreach ([
            $staleDate->toDateString(),
            $refundedDate->toDateString(),
            $missingDate->toDateString(),
        ] as $date) {
            $stat = AnalyticsDailyStat::query()
                ->whereDate('stat_date', $date)
                ->firstOrFail();

            $this->assertNotNull(
                data_get($stat->meta, 'restatement_requested_at')
            );
        }

        $this->assertSame(0, Artisan::call(
            'analytics:restate-dirty',
            ['--limit' => 20]
        ));

        $staleProductStat = AnalyticsProductDailyStat::query()
            ->whereDate('stat_date', $staleDate->toDateString())
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame(75.0, (float) $staleProductStat->revenue_gross);
        $this->assertSame(10.0, (float) $staleProductStat->realized_cogs);
        $this->assertSame(65.0, (float) $staleProductStat->profit_total);
        $this->assertTrue((bool) data_get($staleProductStat->meta, 'profitability_complete'));
    }

    private function purchaseEvents(Order $order)
    {
        return AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->where('entity_type', AnalyticsEvent::ENTITY_ORDER)
            ->where('entity_id', (string) $order->id)
            ->orderBy('id')
            ->get();
    }

    private function order(
        string $number,
        string $paymentStatus,
        float $grandTotal,
        float $refundTotal,
        $deliveredAt = null,
        $placedAt = null,
    ): Order {
        return Order::query()->create([
            'order_number' => $number,
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => $paymentStatus,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => $grandTotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'refund_total' => $refundTotal,
            'customer_name' => 'Analytics Reconciliation Customer',
            'customer_email' => 'analytics-reconcile@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => $placedAt ?: $deliveredAt ?: now(),
            'delivered_at' => $deliveredAt,
        ]);
    }

    private function item(Order $order, Product $product, float $lineTotal): void
    {
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => $lineTotal,
            'unit_cost' => 10,
            'quantity' => 1,
            'line_total' => $lineTotal,
            'profit_amount' => $lineTotal - 10,
        ]);
    }

    private function product(): Product
    {
        $token = Str::lower(Str::random(8));
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Analytics reconciliation '.$token,
            'slug' => 'analytics-reconciliation-'.$token,
            'description' => 'Analytics reconciliation test category',
            'meta_title' => 'Analytics',
            'meta_keyword' => 'analytics',
            'meta_description' => 'Analytics reconciliation test category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Analytics Reconciliation Product',
            'slug' => 'analytics-reconciliation-product-'.$token,
            'sku' => 'AR-'.Str::upper(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 100,
            'quantity' => 5,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
