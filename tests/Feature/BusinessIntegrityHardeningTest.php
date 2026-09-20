<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\CouponService;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\OrderActionService;
use App\Services\Commerce\OrderNotificationService;
use App\Services\Commerce\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class BusinessIntegrityHardeningTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_repeated_cancellation_does_not_restore_stock_twice(): void
    {
        $product = $this->makeProduct(0);
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

        $service = new OrderActionService($notifications);

        $service->cancel($order, 'test cancellation', 1);
        $service->cancel($order->fresh(), 'duplicate cancellation', 1);

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(2, (int) $product->fresh()->quantity);
    }

    public function test_refund_balance_is_rechecked_and_cannot_be_overrun(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PAID, 100);
        $actor = User::factory()->create();

        $notifications = Mockery::mock(OrderNotificationService::class);
        $notifications->shouldReceive('notifyRefundRecorded')->once();

        $service = new OrderActionService($notifications);
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
    }

    public function test_cod_completion_marks_payment_ledger_paid(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications);

        $service->updateStatus($order, Order::STATUS_PROCESSING);
        $service->updateStatus($order->fresh(), Order::STATUS_COMPLETED);

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_cancellation_marks_pending_payment_ledger_failed(): void
    {
        $order = $this->makeOrder(Order::PAYMENT_STATUS_PENDING, 100);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $notifications = Mockery::mock(OrderNotificationService::class)->shouldIgnoreMissing();
        $service = new OrderActionService($notifications);

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
