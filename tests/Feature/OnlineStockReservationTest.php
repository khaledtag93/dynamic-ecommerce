<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\InventoryLotMovement;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStockReservation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Commerce\OrderActionService;
use App\Services\Commerce\PaymentService;
use App\Services\Commerce\StockReservationService;
use App\Services\Frontend\CheckoutService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OnlineStockReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_online_checkout_reserves_stock_instead_of_committing_sale_immediately(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);

        $order = $this->placeOnlineOrder($user, $product, 2);

        $reservation = OrderStockReservation::query()->firstOrFail();

        $this->assertSame(OrderStockReservation::STATUS_RESERVED, $reservation->status);
        $this->assertSame(2, $reservation->quantity);
        $this->assertNotNull($reservation->expires_at);
        $this->assertSame(3, (int) $product->fresh()->quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_ORDER_RESERVATION,
            'quantity_change' => -2,
        ]);

        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_ORDER_OUT,
        ]);
    }

    public function test_order_reservation_rejects_cross_order_item_ownership_in_service_and_database(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $productA = $this->makeProduct(5, 100);
        $productB = $this->makeProduct(5, 120);
        $orderA = $this->placeOnlineOrder($userA, $productA, 1);
        $orderB = $this->placeOnlineOrder($userB, $productB, 1);
        $itemB = $orderB->items()->firstOrFail();
        $stockBefore = (int) $productB->fresh()->quantity;

        try {
            app(StockReservationService::class)->reserveOrderItem($orderA, $itemB, $productB);
            $this->fail('Stock reservation must reject an order item owned by another order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertSame($stockBefore, (int) $productB->fresh()->quantity);

        $reservationA = OrderStockReservation::query()
            ->where('order_id', $orderA->id)
            ->firstOrFail();

        try {
            $reservationA->forceFill(['order_id' => $orderB->id])->save();
            $this->fail('Database must reject a reservation whose order item belongs to another order.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertSame($orderA->id, (int) $reservationA->fresh()->order_id);
    }

    public function test_database_preserves_lot_allocation_provenance_from_direct_reservation_deletion(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 1);
        $reservation = OrderStockReservation::query()
            ->where('order_id', $order->id)
            ->firstOrFail();
        $lotMovement = InventoryLotMovement::query()
            ->where('order_stock_reservation_id', $reservation->id)
            ->firstOrFail();

        try {
            $reservation->delete();
            $this->fail('Database must preserve reservations referenced by lot allocation history.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('order_stock_reservations', ['id' => $reservation->id]);
        $this->assertDatabaseHas('inventory_lot_movements', [
            'id' => $lotMovement->id,
            'order_stock_reservation_id' => $reservation->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_database_preserves_stock_reservation_history_from_direct_order_item_deletion(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 1);
        $item = $order->items()->firstOrFail();
        $reservation = OrderStockReservation::query()
            ->where('order_id', $order->id)
            ->where('order_item_id', $item->id)
            ->firstOrFail();

        try {
            $item->delete();
            $this->fail('Database must preserve order items that own stock reservation history.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('order_items', ['id' => $item->id]);
        $this->assertDatabaseHas('order_stock_reservations', ['id' => $reservation->id]);
    }

    public function test_database_rejects_cross_order_lot_movement_ownership(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $productA = $this->makeProduct(5, 100);
        $productB = $this->makeProduct(5, 120);
        $orderA = $this->placeOnlineOrder($userA, $productA, 1);
        $orderB = $this->placeOnlineOrder($userB, $productB, 1);

        $lotMovement = InventoryLotMovement::query()
            ->where('order_id', $orderA->id)
            ->whereNotNull('order_item_id')
            ->whereNotNull('order_stock_reservation_id')
            ->firstOrFail();

        try {
            $lotMovement->forceFill(['order_id' => $orderB->id])->save();
            $this->fail('Database must reject lot movement ownership that crosses orders.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertSame($orderA->id, (int) $lotMovement->fresh()->order_id);
    }

    public function test_successful_online_payment_commits_reservation_without_second_stock_decrease(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        app(PaymentService::class)->markAsPaid($payment, [
            'transaction_id' => 'TX-PAID-1',
            'provider_status' => 'success',
        ]);

        $reservation = OrderStockReservation::query()->firstOrFail();

        $this->assertSame(OrderStockReservation::STATUS_COMMITTED, $reservation->status);
        $this->assertNotNull($reservation->committed_at);
        $this->assertNull($reservation->expires_at);
        $this->assertSame(3, (int) $product->fresh()->quantity);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('order_id', $order->id)
                ->where('type', InventoryMovement::TYPE_ORDER_RESERVATION)
                ->count()
        );
    }

    public function test_variant_reservation_release_fails_safe_when_variant_was_deleted(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(0, 100);
        $product->update(['has_variants' => true]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'RES-VAR-'.Str::upper(Str::random(6)),
            'barcode' => '6223'.random_int(100000000, 999999999),
            'price' => 100,
            'cost_price' => 20,
            'stock' => 5,
            'is_default' => true,
            'status' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->sku,
            'sku' => $variant->sku,
            'unit_price' => 100,
            'quantity' => 2,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $order = app(CheckoutService::class)->place([
            'customer_name' => 'Variant Reservation Customer',
            'customer_email' => 'variant-reservation@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Reservation Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ], $user);

        $this->assertSame(3, (int) $variant->fresh()->stock);

        $variant->delete();

        $reservation = OrderStockReservation::query()->firstOrFail();
        $this->assertNull($reservation->fresh()->product_variant_id);
        $this->assertNull($order->items()->firstOrFail()->product_variant_id);

        try {
            app(StockReservationService::class)->releaseForOrder($order->fresh(), 'Gateway failed.');
            $this->fail('Variant reservation release should fail instead of restoring stock to product-level quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertSame(0, (int) $product->fresh()->quantity);
        $this->assertSame(OrderStockReservation::STATUS_RESERVED, $reservation->fresh()->status);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_RESERVATION_RELEASE,
        ]);
    }

    public function test_paid_callback_flags_unfulfillable_variant_reservation_instead_of_committing_it(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(0, 100);
        $product->update(['has_variants' => true]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'RES-PAID-'.Str::upper(Str::random(6)),
            'barcode' => '6224'.random_int(100000000, 999999999),
            'price' => 100,
            'cost_price' => 20,
            'stock' => 5,
            'is_default' => true,
            'status' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($user);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->sku,
            'sku' => $variant->sku,
            'unit_price' => 100,
            'quantity' => 2,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $order = app(CheckoutService::class)->place([
            'customer_name' => 'Paid Variant Reservation Customer',
            'customer_email' => 'paid-variant-reservation@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Reservation Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ], $user);

        $payment = $order->payments()->firstOrFail();
        $variant->delete();

        app(PaymentService::class)->markAsPaid($payment, [
            'transaction_id' => 'TX-MISSING-VARIANT',
            'provider_status' => 'success',
        ]);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame(
            OrderStockReservation::STATUS_RESERVED,
            OrderStockReservation::query()->firstOrFail()->status
        );
        $this->assertSame(
            'paid_without_fulfillable_reservation',
            data_get($order->fresh()->meta, 'stock_reservation_exception.code')
        );
        $this->assertSame(
            'paid_without_fulfillable_reservation',
            data_get($payment->fresh()->meta, 'stock_reservation_exception.code')
        );
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'paid_order_stock_reservation_exception',
            'subject_id' => $order->id,
        ]);
    }

    public function test_failed_online_payment_cannot_enter_fulfillment_after_stock_release(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        app(PaymentService::class)->markAsFailed($payment, [
            'transaction_id' => 'TX-FAIL-NO-FULFILLMENT',
            'provider_status' => 'failed',
        ]);

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(Order::PAYMENT_STATUS_FAILED, $order->fresh()->payment_status);

        try {
            app(OrderActionService::class)->updateStatus($order->fresh(), Order::STATUS_PROCESSING);
            $this->fail('An unpaid online order with released stock must not enter fulfillment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(5, (int) $product->fresh()->quantity);
    }

    public function test_paid_online_order_with_stock_exception_cannot_enter_fulfillment(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        WebsiteSetting::setValue('payment_stock_reservation_minutes', '5', 'payment');

        $user = User::factory()->create();
        $product = $this->makeProduct(2, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        Carbon::setTestNow('2026-09-25 12:06:00');
        app(PaymentService::class)->expireStockReservation($order->fresh());
        Product::query()->whereKey($product->id)->update(['quantity' => 0]);

        app(PaymentService::class)->markAsPaid($payment->fresh(), [
            'transaction_id' => 'TX-PAID-STOCK-EXCEPTION',
            'provider_status' => 'success',
        ]);

        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame(
            'paid_without_fulfillable_reservation',
            data_get($order->fresh()->meta, 'stock_reservation_exception.code')
        );

        try {
            app(OrderActionService::class)->updateStatus($order->fresh(), Order::STATUS_PROCESSING);
            $this->fail('A paid online order with unresolved stock exception must not enter fulfillment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_failed_online_payment_releases_stock_once_and_cancellation_does_not_double_restock(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        app(PaymentService::class)->markAsFailed($payment, [
            'transaction_id' => 'TX-FAIL-1',
            'provider_status' => 'failed',
        ]);

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(
            OrderStockReservation::STATUS_RELEASED,
            OrderStockReservation::query()->firstOrFail()->status
        );

        app(PaymentService::class)->markAsFailed($payment->fresh(), [
            'transaction_id' => 'TX-FAIL-1',
            'provider_status' => 'failed',
        ]);

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('order_id', $order->id)
                ->where('type', InventoryMovement::TYPE_RESERVATION_RELEASE)
                ->count()
        );

        app(OrderActionService::class)->cancel($order->fresh(), 'Customer cancelled after failed payment.', $user->id);

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }


    public function test_cancelled_order_rejects_manual_payment_reopen(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        app(PaymentService::class)->markAsFailed($payment, [
            'transaction_id' => 'TX-CANCEL-MANUAL-FAIL',
            'provider_status' => 'failed',
        ]);

        app(OrderActionService::class)->cancel(
            $order->fresh(),
            'Customer cancelled after failed payment.',
            $user->id,
        );

        try {
            app(PaymentService::class)->updateStatus($payment->fresh(), Payment::STATUS_PAID);
            $this->fail('Cancelled orders must not allow manual payment reopening.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_FAILED, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
    }

    public function test_late_paid_callback_after_cancellation_preserves_payment_truth_without_reactivating_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        app(PaymentService::class)->markAsFailed($payment, [
            'transaction_id' => 'TX-CANCEL-LATE-FAIL',
            'provider_status' => 'failed',
        ]);

        app(OrderActionService::class)->cancel(
            $order->fresh(),
            'Customer cancelled before gateway confirmation.',
            $user->id,
        );

        $reservation = OrderStockReservation::query()->firstOrFail();
        $this->assertSame(OrderStockReservation::STATUS_RELEASED, $reservation->status);
        $this->assertSame(5, (int) $product->fresh()->quantity);

        app(PaymentService::class)->markAsPaid($payment->fresh(), [
            'transaction_id' => 'TX-CANCEL-LATE-PAID',
            'provider_status' => 'success',
        ]);

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(OrderStockReservation::STATUS_RELEASED, $reservation->fresh()->status);
        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame('paid_after_cancellation', data_get($order->fresh()->meta, 'payment_exception.code'));
        $this->assertTrue((bool) data_get($order->fresh()->meta, 'payment_exception.refund_required'));
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'paid_cancelled_order_refund_required',
            'subject_id' => $order->id,
        ]);

        app(OrderActionService::class)->refund(
            $order->fresh(),
            (float) $order->grand_total,
            'Refund late payment after cancellation.',
            null,
            $user->id,
        );

        $refundedOrder = $order->fresh();
        $this->assertSame(Order::STATUS_CANCELLED, $refundedOrder->status);
        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $refundedOrder->payment_status);
        $this->assertFalse((bool) data_get($refundedOrder->meta, 'payment_exception.refund_required'));
        $this->assertNotEmpty(data_get($refundedOrder->meta, 'payment_exception.resolved_at'));
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
    }

    public function test_cancellation_fails_safe_when_product_was_deleted_before_required_restock(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        app(PaymentService::class)->markAsPaid($payment, [
            'transaction_id' => 'TX-PAID-MISSING-PRODUCT',
            'provider_status' => 'success',
        ]);

        $this->assertSame(
            OrderStockReservation::STATUS_COMMITTED,
            OrderStockReservation::query()->firstOrFail()->status
        );

        DB::table('products')->where('id', $product->id)->delete();
        $this->assertNull($order->items()->firstOrFail()->product_id);

        try {
            app(OrderActionService::class)->cancel($order->fresh(), 'Legacy product disappeared.', $user->id);
            $this->fail('Cancellation must fail instead of silently skipping required stock restoration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertNotSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_REFUND_RESTOCK,
        ]);
    }

    public function test_cancellation_allows_missing_product_after_reservation_was_already_released(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        app(PaymentService::class)->markAsFailed($payment, [
            'transaction_id' => 'TX-FAIL-MISSING-PRODUCT',
            'provider_status' => 'failed',
        ]);

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(
            OrderStockReservation::STATUS_RELEASED,
            OrderStockReservation::query()->firstOrFail()->status
        );

        DB::table('products')->where('id', $product->id)->delete();
        $this->assertNull($order->items()->firstOrFail()->product_id);

        app(OrderActionService::class)->cancel(
            $order->fresh(),
            'Cancel after reservation was safely released.',
            $user->id,
        );

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('order_id', $order->id)
                ->where('type', InventoryMovement::TYPE_RESERVATION_RELEASE)
                ->count()
        );
    }

    public function test_payment_retry_re_reserves_stock_and_fails_cleanly_when_stock_is_no_longer_available(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(3, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        app(PaymentService::class)->markAsFailed($payment, [
            'transaction_id' => 'TX-FAIL-RETRY',
        ]);

        $this->assertSame(3, (int) $product->fresh()->quantity);

        $retried = app(PaymentService::class)->prepareOnlineRetry($order->fresh(), $payment->fresh());

        $this->assertSame(Payment::STATUS_PENDING, $retried->status);
        $this->assertSame(1, (int) $product->fresh()->quantity);
        $this->assertSame(
            OrderStockReservation::STATUS_RESERVED,
            OrderStockReservation::query()->firstOrFail()->status
        );

        app(PaymentService::class)->markAsFailed($retried, [
            'transaction_id' => 'TX-FAIL-RETRY-2',
        ]);

        Product::query()->whereKey($product->id)->update(['quantity' => 1]);

        try {
            app(PaymentService::class)->prepareOnlineRetry($order->fresh(), $retried->fresh());
            $this->fail('Retry should fail when the released stock has been consumed elsewhere.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        $this->assertSame(OrderStockReservation::STATUS_RELEASED, OrderStockReservation::query()->firstOrFail()->status);
    }

    public function test_expiry_command_releases_pending_online_reservation_and_marks_payment_failed(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        WebsiteSetting::setValue('payment_stock_reservation_minutes', '5', 'payment');

        $user = User::factory()->create();
        $product = $this->makeProduct(5, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);

        $this->assertSame(3, (int) $product->fresh()->quantity);

        Carbon::setTestNow('2026-09-25 12:06:00');

        Artisan::call('payments:expire-stock-reservations');

        $reservation = OrderStockReservation::query()->firstOrFail();
        $payment = $order->payments()->firstOrFail();

        $this->assertSame(OrderStockReservation::STATUS_EXPIRED, $reservation->status);
        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('reservation_expired', $payment->fresh()->provider_status);
        $this->assertSame(Order::PAYMENT_STATUS_FAILED, $order->fresh()->payment_status);
        $this->assertNotEmpty(data_get($order->fresh()->meta, 'stock_reservation_expired_at'));
    }

    public function test_late_paid_callback_after_expiry_preserves_financial_truth_and_flags_stock_exception_if_re_reservation_fails(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        WebsiteSetting::setValue('payment_stock_reservation_minutes', '5', 'payment');

        $user = User::factory()->create();
        $product = $this->makeProduct(2, 100);
        $order = $this->placeOnlineOrder($user, $product, 2);
        $payment = $order->payments()->firstOrFail();

        Carbon::setTestNow('2026-09-25 12:06:00');
        app(PaymentService::class)->expireStockReservation($order->fresh());

        Product::query()->whereKey($product->id)->update(['quantity' => 0]);

        app(PaymentService::class)->markAsPaid($payment->fresh(), [
            'transaction_id' => 'TX-LATE-PAID',
            'provider_status' => 'success',
        ]);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame(OrderStockReservation::STATUS_EXPIRED, OrderStockReservation::query()->firstOrFail()->status);
        $this->assertSame(
            'paid_without_fulfillable_reservation',
            data_get($order->fresh()->meta, 'stock_reservation_exception.code')
        );
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'paid_order_stock_reservation_exception',
            'subject_id' => $order->id,
        ]);
    }

    private function placeOnlineOrder(User $user, Product $product, int $quantity): Order
    {
        $this->actingAs($user);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => $product->base_price,
            'quantity' => $quantity,
            'meta' => ['product_slug' => $product->slug],
        ]);

        return app(CheckoutService::class)->place([
            'customer_name' => 'Reservation Customer',
            'customer_email' => 'reservation@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Reservation Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ], $user);
    }

    private function makeProduct(int $quantity, float $price): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Reservation Category ' . Str::random(6),
            'slug' => 'reservation-category-' . Str::lower(Str::random(8)),
            'description' => 'Reservation test category',
            'meta_title' => 'Reservation',
            'meta_keyword' => 'reservation',
            'meta_description' => 'Reservation test category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Reservation Product ' . Str::random(6),
            'slug' => 'reservation-product-' . Str::lower(Str::random(8)),
            'sku' => 'RES-' . Str::upper(Str::random(6)),
            'category_id' => $categoryId,
            'base_price' => $price,
            'cost_price' => 20,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
