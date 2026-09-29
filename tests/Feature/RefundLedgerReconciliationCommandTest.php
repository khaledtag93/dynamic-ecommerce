<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\GrowthAttributionTouch;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundLedgerReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_ledger_reconciliation_is_dry_run_by_default_and_repairs_dependents_on_apply(): void
    {
        $ledgerOrder = $this->makeOrder('LEDGER');
        $this->addItemAndPayment($ledgerOrder);
        app(AnalyticsTracker::class)->syncRealizedPurchase($ledgerOrder->fresh(['items']));
        $ledgerAttribution = $this->addAttribution($ledgerOrder, 100, 60);

        $ledgerOrder->refunds()->create([
            'amount' => 25,
            'reason' => 'Historical ledger-only refund',
            'processed_at' => now(),
        ]);
        $snapshotOrder = $this->makeOrder('SNAPSHOT');
        $this->addItemAndPayment($snapshotOrder);
        $snapshotOrder->forceFill([
            'refund_total' => 40,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'profit_total' => 20,
        ])->save();
        app(AnalyticsTracker::class)->syncRealizedPurchase($snapshotOrder->fresh(['items']));
        $snapshotAttribution = $this->addAttribution($snapshotOrder, 60, 20);

        $this->assertSame(0, Artisan::call('commerce:reconcile-refund-ledger', [
            '--after-id' => max(0, $ledgerOrder->id - 1),
            '--limit' => 10,
        ]));

        $dryRunOutput = Artisan::output();
        $this->assertStringContainsString('DRY-RUN complete', $dryRunOutput);
        $this->assertStringContainsString('changed=2', $dryRunOutput);
        $this->assertStringContainsString('refund-snapshot-changes=2', $dryRunOutput);
        $this->assertStringContainsString('payment-status-changes=2', $dryRunOutput);
        $this->assertStringContainsString('applied=0', $dryRunOutput);

        $this->assertSame(0.0, (float) $ledgerOrder->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $ledgerOrder->fresh()->payment_status);
        $this->assertSame(60.0, (float) $ledgerOrder->fresh()->profit_total);
        $this->assertSame('100.00', $ledgerAttribution->fresh()->revenue);
        $this->assertSame(40.0, (float) $snapshotOrder->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $snapshotOrder->fresh()->payment_status);
        $this->assertSame(20.0, (float) $snapshotOrder->fresh()->profit_total);
        $this->assertSame('60.00', $snapshotAttribution->fresh()->revenue);

        $this->assertSame(0, Artisan::call('commerce:reconcile-refund-ledger', [
            '--after-id' => max(0, $ledgerOrder->id - 1),
            '--limit' => 10,
            '--apply' => true,
        ]));

        $applyOutput = Artisan::output();
        $this->assertStringContainsString('APPLY complete', $applyOutput);
        $this->assertStringContainsString('changed=2', $applyOutput);
        $this->assertStringContainsString('applied=2', $applyOutput);

        $ledgerFresh = $ledgerOrder->fresh();
        $this->assertSame(25.0, (float) $ledgerFresh->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $ledgerFresh->payment_status);
        $this->assertSame(35.0, (float) $ledgerFresh->profit_total);
        $this->assertSame('75.00', $ledgerAttribution->fresh()->revenue);
        $this->assertSame('35.00', $ledgerAttribution->fresh()->profit_total);
        $ledgerEvent = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->where('entity_type', AnalyticsEvent::ENTITY_ORDER)
            ->where('entity_id', (string) $ledgerOrder->id)
            ->firstOrFail();
        $this->assertSame(75.0, (float) data_get($ledgerEvent->meta, 'grand_total'));
        $this->assertSame(25.0, (float) data_get($ledgerEvent->meta, 'refund_total'));

        $snapshotFresh = $snapshotOrder->fresh();
        $this->assertSame(0.0, (float) $snapshotFresh->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $snapshotFresh->payment_status);
        $this->assertSame(60.0, (float) $snapshotFresh->profit_total);
        $this->assertSame('100.00', $snapshotAttribution->fresh()->revenue);
        $this->assertSame('60.00', $snapshotAttribution->fresh()->profit_total);

        $snapshotEvent = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->where('entity_type', AnalyticsEvent::ENTITY_ORDER)
            ->where('entity_id', (string) $snapshotOrder->id)
            ->firstOrFail();
        $this->assertSame(100.0, (float) data_get($snapshotEvent->meta, 'grand_total'));
        $this->assertSame(0.0, (float) data_get($snapshotEvent->meta, 'refund_total'));
    }
    private function makeOrder(string $prefix): Order
    {
        return Order::query()->create([
            'order_number' => $prefix.'-'.Str::upper(Str::random(8)),
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'cost_total' => 40,
            'profit_total' => 60,
            'refund_total' => 0,
            'customer_name' => 'Refund Reconciliation Customer',
            'customer_email' => Str::lower(Str::random(8)).'@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Reconciliation Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now()->subDay(),
            'delivered_at' => now()->subDay(),
        ]);
    }
    private function addItemAndPayment(Order $order): void
    {
        $order->items()->create([
            'product_name' => 'Reconciliation Item',
            'sku' => 'REFUND-RECON-'.Str::upper(Str::random(6)),
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_ONLINE,
            'provider' => 'test',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'PAY-'.Str::upper(Str::random(8)),
            'amount' => 100,
            'currency' => 'EGP',
            'paid_at' => now()->subDay(),
        ]);
    }

    private function addAttribution(Order $order, float $revenue, float $profit): GrowthAttributionTouch
    {
        return GrowthAttributionTouch::query()->create([
            'order_id' => $order->id,
            'touch_type' => 'last_touch',
            'status' => 'attributed',
            'attribution_weight' => 1,
            'revenue' => $revenue,
            'discount_total' => 0,
            'profit_total' => $profit,
            'occurred_at' => $order->placed_at,
            'attributed_at' => now()->subMinute(),
        ]);
    }
}
