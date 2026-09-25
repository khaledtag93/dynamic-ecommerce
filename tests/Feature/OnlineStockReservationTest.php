<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStockReservation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Commerce\OrderActionService;
use App\Services\Commerce\PaymentService;
use App\Services\Frontend\CheckoutService;
use Carbon\Carbon;
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
