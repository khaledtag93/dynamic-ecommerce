<?php

namespace Tests\Feature;

use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\Coupon;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Payment;
use App\Models\PosReturnItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Analytics\AnalyticsAggregationService;
use App\Services\Analytics\AnalyticsDashboardService;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Commerce\BehaviorTrackingService;
use App\Services\Commerce\CouponService;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\OrderActionService;
use App\Services\Commerce\OrderNotificationService;
use App\Services\Commerce\PaymentService;
use App\Services\Commerce\ProfitService;
use App\Services\Commerce\StockReservationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class BusinessIntegrityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_completion_tracks_order_placement_without_claiming_realized_purchase(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app(BehaviorTrackingService::class)->track(BehaviorTrackingService::EVENT_ORDER_COMPLETE, null, [
            'order_id' => 98765,
            'order_number' => 'PLACED-98765',
            'grand_total' => 100,
        ]);

        $this->assertDatabaseHas('user_behaviors', [
            'user_id' => $user->id,
            'event' => BehaviorTrackingService::EVENT_ORDER_COMPLETE,
        ]);
        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $user->id,
            'event_type' => AnalyticsEvent::EVENT_ORDER_PLACED,
            'entity_type' => AnalyticsEvent::ENTITY_ORDER,
            'entity_id' => '98765',
        ]);
        $this->assertDatabaseMissing('analytics_events', [
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'entity_type' => AnalyticsEvent::ENTITY_ORDER,
            'entity_id' => '98765',
        ]);
    }

    public function test_realized_purchase_event_is_idempotent_and_follows_refund_state(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_UNPAID, 100);
        $tracker = app(AnalyticsTracker::class);

        $tracker->syncRealizedPurchase($order);

        $this->assertDatabaseMissing('analytics_events', [
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'entity_type' => AnalyticsEvent::ENTITY_ORDER,
            'entity_id' => (string) $order->id,
        ]);

        $order->update([
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $tracker->syncRealizedPurchase($order->fresh());
        $tracker->syncRealizedPurchase($order->fresh());

        $events = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->where('entity_type', AnalyticsEvent::ENTITY_ORDER)
            ->where('entity_id', (string) $order->id)
            ->get();

        $this->assertCount(1, $events);
        $this->assertSame(100.0, (float) data_get($events->first()->meta, 'grand_total'));

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'refund_total' => 25,
        ]);
        $tracker->syncRealizedPurchase($order->fresh());

        $event = $events->first()->fresh();
        $this->assertSame(75.0, (float) data_get($event->meta, 'grand_total'));
        $this->assertSame(25.0, (float) data_get($event->meta, 'refund_total'));

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            'refund_total' => 100,
        ]);
        $tracker->syncRealizedPurchase($order->fresh());

        $this->assertDatabaseMissing('analytics_events', [
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'entity_type' => AnalyticsEvent::ENTITY_ORDER,
            'entity_id' => (string) $order->id,
        ]);
    }

    public function test_late_refunds_mark_historical_analytics_dirty_and_restate_net_revenue(): void
    {
        $deliveredAt = now()->subDays(10)->startOfDay()->addHours(12);
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $order->update([
            'status' => Order::STATUS_COMPLETED,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'placed_at' => $deliveredAt->copy()->subDay(),
            'delivered_at' => $deliveredAt,
        ]);

        $tracker = app(AnalyticsTracker::class);
        $aggregation = app(AnalyticsAggregationService::class);
        $dashboard = app(AnalyticsDashboardService::class);

        $tracker->syncRealizedPurchase($order->fresh());

        $dirtyStat = AnalyticsDailyStat::query()
            ->whereDate('stat_date', $deliveredAt->toDateString())
            ->firstOrFail();

        $this->assertNotNull(data_get($dirtyStat->meta, 'restatement_requested_at'));

        $aggregation->aggregateDay($deliveredAt);

        $cleanStat = $dirtyStat->fresh();
        $this->assertNull(data_get($cleanStat->meta, 'restatement_requested_at'));
        $this->assertSame(100.0, (float) $cleanStat->revenue_gross);

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'refund_total' => 25,
        ]);
        $tracker->syncRealizedPurchase($order->fresh());

        $dirtyStat = $cleanStat->fresh();
        $this->assertNotNull(data_get($dirtyStat->meta, 'restatement_requested_at'));
        $this->assertSame(100.0, (float) $dirtyStat->revenue_gross);

        $snapshot = $dashboard->buildSnapshot(
            $deliveredAt->copy()->startOfDay(),
            $deliveredAt->copy()->endOfDay()
        );

        $this->assertFalse($snapshot['current']['is_aggregated']);
        $this->assertSame(75.0, (float) $snapshot['current']['totals']['revenue_gross']);

        $this->assertSame(0, Artisan::call('analytics:restate-dirty', ['--limit' => 30]));

        $restated = $dirtyStat->fresh();
        $this->assertNull(data_get($restated->meta, 'restatement_requested_at'));
        $this->assertSame(1, (int) $restated->purchases);
        $this->assertSame(75.0, (float) $restated->revenue_gross);

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            'refund_total' => 100,
        ]);
        $tracker->syncRealizedPurchase($order->fresh());

        $this->assertNotNull(data_get(
            $restated->fresh()->meta,
            'restatement_requested_at'
        ));

        $this->assertSame(0, Artisan::call('analytics:restate-dirty', ['--limit' => 30]));

        $fullyRestated = $restated->fresh();
        $this->assertSame(0, (int) $fullyRestated->purchases);
        $this->assertSame(0.0, (float) $fullyRestated->revenue_gross);
        $this->assertNull(data_get($fullyRestated->meta, 'restatement_requested_at'));
    }

    public function test_profit_uses_net_order_value_and_reverses_cogs_only_for_restocked_units(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 90);
        $order->update([
            'status' => Order::STATUS_COMPLETED,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'subtotal' => 100,
            'discount_total' => 10,
            'grand_total' => 90,
        ]);

        $item = $order->items()->create([
            'product_name' => 'Profit Test Item',
            'sku' => 'PROFIT-TEST',
            'unit_price' => 50,
            'unit_cost' => 30,
            'quantity' => 2,
            'line_total' => 100,
            'profit_amount' => 40,
        ]);

        $service = app(ProfitService::class);
        $fresh = $service->refreshOrderTotals($order->fresh());

        $this->assertSame(60.0, (float) $fresh->cost_total);
        $this->assertSame(30.0, (float) $fresh->profit_total);

        $refund = OrderRefund::query()->create([
            'order_id' => $order->id,
            'amount' => 45,
            'reason' => 'Financial adjustment',
            'processed_at' => now(),
        ]);
        $order->update([
            'refund_total' => 45,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ]);

        $fresh = $service->refreshOrderTotals($order->fresh());
        $this->assertSame(-15.0, (float) $fresh->profit_total);

        PosReturnItem::query()->create([
            'order_refund_id' => $refund->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
            'amount' => 45,
            'restocked' => true,
        ]);

        $fresh = $service->refreshOrderTotals($order->fresh());

        $this->assertSame(60.0, (float) $fresh->cost_total);
        $this->assertSame(15.0, (float) $fresh->profit_total);
    }

    public function test_inventory_decrement_refuses_oversell_and_records_successful_movement(): void
    {
        $product = $this->makeProduct(1);
        $service = app(InventoryService::class);

        try {
            $service->decrease($product, null, 2, InventoryMovement::TYPE_ORDER_OUT);
            $this->fail('Overselling should have been rejected.');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(1, (int) $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);

        $service->decrease($product->fresh(), null, 1, InventoryMovement::TYPE_ORDER_OUT);

        $this->assertSame(0, (int) $product->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_ORDER_OUT,
            'quantity_change' => -1,
            'balance_after' => 0,
        ]);
    }

    public function test_coupon_usage_limit_cannot_be_consumed_twice(): void
    {
        $coupon = Coupon::query()->create([
            'name' => 'One use only',
            'code' => 'ONCE',
            'type' => Coupon::TYPE_FIXED,
            'value' => 10,
            'usage_limit' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $service = app(CouponService::class);
        $service->markCouponAsUsed($coupon);

        $this->assertSame(1, (int) $coupon->fresh()->used_count);

        try {
            $service->markCouponAsUsed($coupon->fresh());
            $this->fail('A coupon beyond its usage limit should have been rejected.');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_cancellation_releases_counted_coupon_usage_once(): void
    {
        $coupon = Coupon::query()->create([
            'name' => 'Cancellation coupon',
            'code' => 'CANCEL-ONCE',
            'type' => Coupon::TYPE_FIXED,
            'value' => 10,
            'usage_limit' => 5,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $product = $this->makeProduct(0);
        $order = $this->makeOrder(Order::PAYMENT_STATUS_UNPAID, 100);
        $order->update([
            'coupon_code' => $coupon->code,
            'coupon_snapshot' => app(CouponService::class)->couponSnapshot($coupon, 100),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 50,
            'unit_cost' => 20,
            'quantity' => 2,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        app(CouponService::class)->markCouponAsUsed($coupon, $order);
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
        $this->assertNotNull(data_get($order->fresh()->meta, 'coupon_usage.counted_at'));

        $notifications = Mockery::mock(OrderNotificationService::class);
        $notifications->shouldReceive('notifyCancelled')->once();
        $notifications->shouldReceive('notifyDeliveryUpdated')->once();

        $service = new OrderActionService(
            $notifications,
            app(InventoryService::class),
            app(StockReservationService::class),
            app(CouponService::class),
            app(AnalyticsTracker::class),
            app(ProfitService::class)
        );

        $service->cancel($order->fresh(), 'Coupon order cancelled.');
        $service->cancel($order->fresh(), 'Repeated cancellation.');

        $this->assertSame(0, (int) $coupon->fresh()->used_count);
        $this->assertNotNull(data_get($order->fresh()->meta, 'coupon_usage.released_at'));
    }

    public function test_paid_order_cannot_be_cancelled_until_remaining_balance_is_refunded(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $product = $this->makeProduct(0);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService(
            $notifications,
            app(InventoryService::class),
            app(StockReservationService::class),
            app(CouponService::class),
            app(AnalyticsTracker::class),
            app(ProfitService::class)
        );

        try {
            $service->cancel($order, 'Paid order cancellation attempt.');
            $this->fail('Paid orders with refundable value must be refunded before cancellation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame(0.0, (float) $order->fresh()->refund_total);
        $this->assertSame(0, (int) $product->fresh()->quantity);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_REFUND_RESTOCK,
        ]);
    }

    public function test_repeated_cancellation_does_not_restore_stock_twice(): void
    {
        $product = $this->makeProduct(0);
        $product->forceFill(['cost_price' => 35])->save();
        $order = $this->makeOrder(Order::PAYMENT_STATUS_UNPAID, 100);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 50,
            'unit_cost' => 20,
            'quantity' => 2,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $notifications = Mockery::mock(OrderNotificationService::class);
        $notifications->shouldReceive('notifyCancelled')->once();
        $notifications->shouldReceive('notifyDeliveryUpdated')->once();

        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));

        $service->cancel($order, 'test cancellation', 1);
        $service->cancel($order->fresh(), 'duplicate cancellation', 1);

        $cancelledOrder = $order->fresh();
        $this->assertSame(Order::STATUS_CANCELLED, $cancelledOrder->status);
        $this->assertSame(40.0, (float) $cancelledOrder->cost_total);
        $this->assertSame(0.0, (float) $cancelledOrder->profit_total);
        $this->assertSame(2, (int) $product->fresh()->quantity);
        $this->assertSame(35.0, (float) $product->fresh()->cost_price);
        $this->assertSame(20.0, (float) $product->fresh()->inventory_cost_price);
        $this->assertSame(
            2,
            (int) InventoryLot::query()->where('product_id', $product->id)->sum('quantity_on_hand')
        );
        $this->assertDatabaseHas('inventory_lots', [
            'product_id' => $product->id,
            'source_type' => 'legacy_restock',
            'quantity_on_hand' => 2,
        ]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_REFUND_RESTOCK,
            'quantity_change' => 2,
            'balance_after' => 2,
            'unit_cost' => 20,
        ]);
    }

    public function test_cancellation_fails_safe_when_variant_stock_target_was_deleted(): void
    {
        $product = $this->makeProduct(0);
        $product->update(['has_variants' => true]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'CANCEL-VAR-'.Str::upper(Str::random(6)),
            'price' => 50,
            'cost_price' => 20,
            'stock' => 0,
            'is_default' => true,
            'status' => true,
            'sort_order' => 0,
        ]);

        $order = $this->makeOrder(Order::PAYMENT_STATUS_UNPAID, 100);
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->sku,
            'sku' => $variant->sku,
            'unit_price' => 50,
            'unit_cost' => 20,
            'quantity' => 2,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $variant->delete();

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService(
            $notifications,
            app(InventoryService::class),
            app(StockReservationService::class),
            app(CouponService::class),
            app(AnalyticsTracker::class),
            app(ProfitService::class)
        );

        try {
            $service->cancel($order, 'Variant was removed before cancellation.');
            $this->fail('Cancellation should fail rather than restoring variant stock to the product-level bucket.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, (int) $product->fresh()->quantity);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_REFUND_RESTOCK,
        ]);
    }

    public function test_refund_balance_is_rechecked_and_cannot_be_overrun(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PAID);
        $actor = User::factory()->create();

        $notifications = Mockery::mock(OrderNotificationService::class);
        $notifications->shouldReceive('notifyRefundRecorded')->once();

        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));
        $service->refund($order, 70, 'partial refund', null, $actor->id);

        try {
            $service->refund($order->fresh(), 40, 'would exceed balance', null, $actor->id);
            $this->fail('Refunding beyond the remaining balance should have been rejected.');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertDatabaseCount('order_refunds', 1);
        $this->assertSame(70.0, (float) $order->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->refunded_at);
    }

    public function test_database_preserves_inventory_movement_order_history_from_direct_order_deletion(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_UNPAID, 100);
        $movement = InventoryMovement::query()->create([
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'reason' => 'Audit provenance preservation',
            'quantity_change' => 0,
            'balance_after' => 0,
            'unit_cost' => 0,
        ]);

        try {
            Order::query()->whereKey($order->id)->delete();
            $this->fail('Database must preserve orders referenced by inventory movement history.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('inventory_movements', [
            'id' => $movement->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_database_preserves_order_item_history_from_direct_order_deletion(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_UNPAID, 100);
        $item = $order->items()->create([
            'product_name' => 'Audit Line Item',
            'sku' => 'AUDIT-LINE-ITEM',
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        try {
            Order::query()->whereKey($order->id)->delete();
            $this->fail('Database must preserve orders that own sales line history.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['id' => $item->id]);
    }

    public function test_database_preserves_order_payment_history_from_direct_order_deletion(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PAID);

        try {
            Order::query()->whereKey($order->id)->delete();
            $this->fail('Database must preserve orders that own payment ledger history.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_database_preserves_order_refund_history_from_direct_order_deletion(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $refund = OrderRefund::query()->create([
            'order_id' => $order->id,
            'amount' => 10,
            'reason' => 'Audit history preservation',
            'processed_at' => now(),
        ]);

        try {
            Order::query()->whereKey($order->id)->delete();
            $this->fail('Database must preserve orders that own refund ledger history.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_refunds', ['id' => $refund->id]);
    }

    public function test_direct_refund_idempotency_prevents_replayed_financial_mutation(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PAID);
        $actor = User::factory()->create();
        $key = (string) Str::uuid();

        $notifications = Mockery::mock(OrderNotificationService::class);
        $notifications->shouldReceive('notifyRefundRecorded')->once();

        $service = new OrderActionService(
            $notifications,
            app(InventoryService::class),
            app(StockReservationService::class),
            app(CouponService::class),
            app(AnalyticsTracker::class),
            app(ProfitService::class)
        );

        $first = $service->refund($order, 20, 'duplicate safe refund', 'same request', $actor->id, null, $key);
        $replay = $service->refund($order->fresh(), 20, 'duplicate safe refund', 'same request', $actor->id, null, $key);

        $this->assertTrue($first['created']);
        $this->assertFalse($replay['created']);
        $this->assertSame($first['refund']->id, $replay['refund']->id);
        $this->assertDatabaseCount('order_refunds', 1);
        $this->assertSame(20.0, (float) $order->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);

        try {
            $service->refund($order->fresh(), 25, 'changed replay', 'same request', $actor->id, null, $key);
            $this->fail('Reusing a refund idempotency key with different details must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }

        $this->assertDatabaseCount('order_refunds', 1);
        $this->assertSame(20.0, (float) $order->fresh()->refund_total);
    }

    public function test_full_refund_marks_payment_ledger_refunded_and_blocks_late_gateway_downgrade(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PAID);

        $notifications = Mockery::mock(OrderNotificationService::class);
        $notifications->shouldReceive('notifyRefundRecorded')->once();

        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));
        $service->refund($order, 100, 'full refund');

        $this->assertSame(100.0, (float) $order->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->refunded_at);

        app(PaymentService::class)->markAsFailed($payment->fresh(), [
            'provider_status' => 'late_failed_callback',
        ]);

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->fresh()->payment_status);
    }

    public function test_bank_transfer_cannot_enter_fulfillment_until_payment_is_confirmed(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 100);
        $order->update(['payment_method' => Order::PAYMENT_METHOD_BANK_TRANSFER]);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));

        try {
            $service->updateStatus($order, Order::STATUS_PROCESSING);
            $this->fail('Pending bank transfer orders must not enter fulfillment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);

        app(PaymentService::class)->updateStatus($payment, Payment::STATUS_PAID);
        $service->updateStatus($order->fresh(), Order::STATUS_PROCESSING);

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
    }

    public function test_non_cod_fulfillment_requires_paid_ledger_evidence(): void
    {
        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));

        $missingLedger = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $missingLedger->update(['payment_method' => Order::PAYMENT_METHOD_BANK_TRANSFER]);

        try {
            $service->updateStatus($missingLedger, Order::STATUS_PROCESSING);
            $this->fail('Paid order status without a paid ledger must not enter fulfillment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PENDING, $missingLedger->fresh()->status);

        $mismatchedLedger = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $mismatchedLedger->update(['payment_method' => Order::PAYMENT_METHOD_BANK_TRANSFER]);
        $payment = $this->makePayment($mismatchedLedger, Payment::STATUS_PAID);
        $payment->update(['amount' => 99]);

        try {
            $service->updateStatus($mismatchedLedger, Order::STATUS_PROCESSING);
            $this->fail('A paid ledger that does not cover the order must not enter fulfillment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PENDING, $mismatchedLedger->fresh()->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_non_cod_storefront_order_cannot_complete_before_delivery(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $order->update([
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'status' => Order::STATUS_PROCESSING,
            'delivery_status' => Order::DELIVERY_STATUS_PREPARING,
        ]);
        $this->makePayment($order, Payment::STATUS_PAID);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));

        try {
            $service->updateStatus($order->fresh(), Order::STATUS_COMPLETED);
            $this->fail('A storefront order must not complete before delivery is marked Delivered.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(Order::DELIVERY_STATUS_PREPARING, $order->fresh()->delivery_status);
    }

    public function test_non_cod_completion_requires_delivery_timestamp(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $order->update([
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'status' => Order::STATUS_PROCESSING,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivered_at' => null,
        ]);
        $this->makePayment($order, Payment::STATUS_PAID);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));

        try {
            $service->updateStatus($order, Order::STATUS_COMPLETED);
            $this->fail('Delivered storefront orders require a delivery timestamp before completion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertNull($order->fresh()->delivered_at);

        $order->update(['delivered_at' => now()]);
        $service->updateStatus($order->fresh(), Order::STATUS_COMPLETED);

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_cod_completion_marks_payment_ledger_paid(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));

        $service->updateStatus($order, Order::STATUS_PROCESSING);
        $service->updateStatus($order->fresh(), Order::STATUS_COMPLETED);

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertSame(1, AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->where('entity_type', AnalyticsEvent::ENTITY_ORDER)
            ->where('entity_id', (string) $order->id)
            ->count());
    }

    public function test_cancellation_is_blocked_after_shipping_starts_without_restock(): void
    {
        $product = $this->makeProduct(0);
        $order = $this->makeOrder(Order::PAYMENT_STATUS_UNPAID, 100);
        $order->update([
            'status' => Order::STATUS_PROCESSING,
            'delivery_status' => Order::DELIVERY_STATUS_SHIPPED,
            'shipped_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 50,
            'unit_cost' => 20,
            'quantity' => 2,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $this->assertFalse($order->fresh()->can_user_cancel);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));

        try {
            $service->cancel($order->fresh(), 'Attempted cancellation after shipping.');
            $this->fail('A shipped order must not be cancelled through the stock-restoring cancellation flow.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(Order::DELIVERY_STATUS_SHIPPED, $order->fresh()->delivery_status);
        $this->assertSame(0, (int) $product->fresh()->quantity);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_REFUND_RESTOCK,
        ]);
    }

    public function test_cancellation_marks_pending_payment_ledger_failed(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications, app(InventoryService::class), app(StockReservationService::class), app(CouponService::class), app(AnalyticsTracker::class), app(ProfitService::class));

        $service->cancel($order, 'cancelled for test');

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_FAILED, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->failed_at);
    }

    public function test_manual_payment_refund_cannot_bypass_order_refund_ledger(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PAID);

        $this->expectException(ValidationException::class);

        app(PaymentService::class)->updateStatus($payment, Payment::STATUS_REFUNDED);
    }

    public function test_paid_payment_cannot_be_manually_downgraded(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PAID);

        try {
            app(PaymentService::class)->updateStatus($payment, Payment::STATUS_FAILED);
            $this->fail('Paid payment should not be downgraded manually.');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
    }

    public function test_manual_payment_transition_matrix_blocks_invalid_regressions(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 100);
        $payment = $this->makePayment($order, Payment::STATUS_AUTHORIZED);

        try {
            app(PaymentService::class)->updateStatus($payment, Payment::STATUS_PENDING);
            $this->fail('Authorized payment should not regress to pending manually.');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(Payment::STATUS_AUTHORIZED, $payment->fresh()->status);

        app(PaymentService::class)->updateStatus($payment->fresh(), Payment::STATUS_PAID);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
    }

    public function test_replayed_gateway_callback_is_idempotent(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);
        $service = app(PaymentService::class);

        $context = [
            'transaction_id' => 'PAYMOB-TXN-1001',
            'provider_status' => 'pending',
            'hmac_valid' => true,
        ];

        $service->markAsPending($payment, $context);
        $first = $payment->fresh();
        $firstEvents = count(data_get($first->meta, 'events', []));

        $service->markAsPending($first, $context);
        $second = $payment->fresh();

        $this->assertSame($firstEvents, count(data_get($second->meta, 'events', [])));
        $this->assertSame('PAYMOB-TXN-1001', data_get($second->meta, 'last_gateway_transition.transaction_id'));
        $this->assertSame(Payment::STATUS_PENDING, $second->status);
    }

    public function test_paid_gateway_confirmation_is_terminal_against_late_failure(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);
        $service = app(PaymentService::class);

        $service->markAsPaid($payment, [
            'transaction_id' => 'PAYMOB-TXN-PAID',
            'provider_status' => 'paid',
            'hmac_valid' => true,
        ]);

        $service->markAsFailed($payment->fresh(), [
            'transaction_id' => 'PAYMOB-TXN-LATE-FAIL',
            'provider_status' => 'declined',
            'hmac_valid' => true,
        ]);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame('PAYMOB-TXN-PAID', $payment->fresh()->transaction_reference);
    }

    public function test_refund_ledger_remains_authoritative_during_payment_sync(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $this->makePayment($order, Payment::STATUS_PAID);

        $order->refunds()->create([
            'amount' => 40,
            'reason' => 'partial refund',
            'processed_at' => now(),
        ]);

        app(PaymentService::class)->syncOrderPaymentStatus($order);

        $fresh = $order->fresh();
        $this->assertSame(40.0, (float) $fresh->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $fresh->payment_status);
    }

    public function test_online_reservation_uses_fefo_and_release_restores_original_lots(): void
    {
        $product = $this->makeProduct(5);
        $product->forceFill(['inventory_cost_price' => 20])->save();

        $earlyLot = InventoryLot::query()->create([
            'product_id' => $product->id,
            'lot_code' => 'RES-EARLY-'.Str::upper(Str::random(6)),
            'source_type' => 'test_seed',
            'initial_quantity' => 2,
            'quantity_on_hand' => 2,
            'unit_cost' => 20,
            'expiration_date' => today()->addDays(4),
            'received_at' => now()->subDay(),
        ]);
        $laterLot = InventoryLot::query()->create([
            'product_id' => $product->id,
            'lot_code' => 'RES-LATE-'.Str::upper(Str::random(6)),
            'source_type' => 'test_seed',
            'initial_quantity' => 3,
            'quantity_on_hand' => 3,
            'unit_cost' => 20,
            'expiration_date' => today()->addDays(25),
            'received_at' => now(),
        ]);

        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 150);
        $order->update(['payment_method' => Order::PAYMENT_METHOD_ONLINE]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 50,
            'unit_cost' => 20,
            'quantity' => 3,
            'line_total' => 150,
            'profit_amount' => 90,
        ]);

        $service = app(StockReservationService::class);
        $reservation = $service->reserveOrderItem(
            $order,
            $item,
            $product,
            null,
            now()->addMinutes(30)
        );

        $allocations = $item->fresh()->meta['inventory_lot_allocations'] ?? [];

        $this->assertSame($earlyLot->id, (int) $allocations[0]['lot_id']);
        $this->assertSame(2, (int) $allocations[0]['quantity']);
        $this->assertSame($laterLot->id, (int) $allocations[1]['lot_id']);
        $this->assertSame(1, (int) $allocations[1]['quantity']);
        $this->assertSame(2, (int) $product->fresh()->quantity);
        $this->assertSame(0, (int) $earlyLot->fresh()->quantity_on_hand);
        $this->assertSame(2, (int) $laterLot->fresh()->quantity_on_hand);

        $this->assertSame(1, $service->releaseForOrder($order, 'Payment window expired.', true));

        $this->assertSame(\App\Models\OrderStockReservation::STATUS_EXPIRED, $reservation->fresh()->status);
        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(2, (int) $earlyLot->fresh()->quantity_on_hand);
        $this->assertSame(3, (int) $laterLot->fresh()->quantity_on_hand);
        $this->assertSame(
            5,
            (int) InventoryLot::query()->where('product_id', $product->id)->sum('quantity_on_hand')
        );
    }

    private function makeProduct(int $quantity): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Test Category '.Str::random(6),
            'slug' => 'test-category-'.Str::lower(Str::random(8)),
            'description' => 'Test category',
            'meta_title' => 'Test',
            'meta_keyword' => 'test',
            'meta_description' => 'Test category',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Test Product '.Str::random(6),
            'slug' => 'test-product-'.Str::lower(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 50,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }

    private function makeOrder(string $paymentStatus, float $grandTotal): Order
    {
        return Order::query()->create([
            'order_number' => 'TEST-'.Str::upper(Str::random(10)),
            'status' => Order::STATUS_PENDING,
            'payment_status' => $paymentStatus,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => $grandTotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'refund_total' => 0,
            'placed_at' => now(),
        ]);
    }

    private function makePayment(Order $order, string $status): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id,
            'method' => $order->payment_method,
            'provider' => 'test',
            'status' => $status,
            'transaction_reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'paid_at' => $status === Payment::STATUS_PAID ? now() : null,
        ]);
    }
}
