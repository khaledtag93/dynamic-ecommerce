<?php

namespace Tests\Feature;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DeliveryStatusUpdatedNotification;
use App\Services\Commerce\DeliveryService;
use App\Services\Commerce\OrderActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class DeliveryHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_only_update_preserves_delivered_timestamp_and_sends_no_status_message(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $deliveredAt = now()->subHour()->startOfSecond();
        $order = $this->createOrder([
            'user_id' => $customer->id,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'shipped_at' => now()->subDay(),
            'delivered_at' => $deliveredAt,
        ]);

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldNotReceive('queueDeliveryUpdate');

        $result = (new DeliveryService($whatsApp, app(OrderActionService::class)))->update($order, [
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'shipping_provider' => 'Updated Courier',
            'tracking_number' => 'TRK-200',
            'estimated_delivery_date' => now()->addDay()->toDateString(),
            'delivery_notes' => 'Metadata only',
        ]);

        $fresh = $order->fresh();

        $this->assertFalse($result['status_changed']);
        $this->assertSame('Updated Courier', $fresh->shipping_provider);
        $this->assertTrue($fresh->delivered_at->equalTo($deliveredAt));
        Notification::assertNothingSent();
    }

    public function test_delivery_transition_matrix_rejects_skipping_directly_to_out_for_delivery(): void
    {
        $order = $this->createOrder([
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
        ]);

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldNotReceive('queueDeliveryUpdate');

        $this->expectException(ValidationException::class);

        (new DeliveryService($whatsApp, app(OrderActionService::class)))->update($order, [
            'delivery_status' => Order::DELIVERY_STATUS_OUT_FOR_DELIVERY,
        ]);
    }


    public function test_delivery_cannot_be_cancelled_independently_from_order_cancellation(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_PROCESSING,
            'delivery_status' => Order::DELIVERY_STATUS_PREPARING,
        ]);

        $this->assertFalse($order->canTransitionDeliveryTo(Order::DELIVERY_STATUS_CANCELLED));

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldNotReceive('queueDeliveryUpdate');

        try {
            (new DeliveryService($whatsApp, app(OrderActionService::class)))->update($order, [
                'delivery_status' => Order::DELIVERY_STATUS_CANCELLED,
            ]);
            $this->fail('Delivery cancellation must go through the order cancellation flow so inventory is restored consistently.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delivery_status', $exception->errors());
        }

        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_PROCESSING, $fresh->status);
        $this->assertSame(Order::DELIVERY_STATUS_PREPARING, $fresh->delivery_status);
    }

    public function test_delivery_cannot_advance_while_order_is_still_pending(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_PENDING,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
        ]);

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldNotReceive('queueDeliveryUpdate');

        try {
            (new DeliveryService($whatsApp, app(OrderActionService::class)))->update($order, [
                'delivery_status' => Order::DELIVERY_STATUS_PREPARING,
            ]);
            $this->fail('Delivery should not advance before the order enters Processing.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delivery_status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(Order::DELIVERY_STATUS_PENDING, $order->fresh()->delivery_status);
    }

    public function test_delivered_delivery_completes_order_and_settles_cod_payment(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $order = $this->createOrder([
            'user_id' => $customer->id,
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_PREPARING,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ]);

        $payment = $order->payments()->create([
            'method' => Order::PAYMENT_METHOD_COD,
            'status' => \App\Models\Payment::STATUS_PENDING,
            'transaction_reference' => 'COD-DELIVERED',
            'amount' => $order->grand_total,
            'currency' => $order->currency,
        ]);

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldReceive('queueDeliveryUpdate')->once();

        (new DeliveryService($whatsApp, app(OrderActionService::class)))->update($order, [
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
        ]);

        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_COMPLETED, $fresh->status);
        $this->assertSame(Order::DELIVERY_STATUS_DELIVERED, $fresh->delivery_status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $fresh->payment_status);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertSame(\App\Models\Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_cod_delivery_completion_requires_matching_payment_ledger(): void
    {
        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldNotReceive('queueDeliveryUpdate');
        $service = new DeliveryService($whatsApp, app(OrderActionService::class));

        $missingLedger = $this->createOrder([
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_PREPARING,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ]);

        try {
            $service->update($missingLedger, [
                'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            ]);
            $this->fail('COD delivery completion must require a payment ledger record.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PROCESSING, $missingLedger->fresh()->status);
        $this->assertSame(Order::DELIVERY_STATUS_PREPARING, $missingLedger->fresh()->delivery_status);
        $this->assertSame(Order::PAYMENT_STATUS_UNPAID, $missingLedger->fresh()->payment_status);
        $this->assertNull($missingLedger->fresh()->delivered_at);

        $mismatchedLedger = $this->createOrder([
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_PREPARING,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ]);
        $payment = $mismatchedLedger->payments()->create([
            'method' => Order::PAYMENT_METHOD_COD,
            'status' => \App\Models\Payment::STATUS_PENDING,
            'transaction_reference' => 'COD-MISMATCH',
            'amount' => 99,
            'currency' => $mismatchedLedger->currency,
        ]);

        try {
            $service->update($mismatchedLedger, [
                'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            ]);
            $this->fail('COD delivery completion must reject a mismatched payment ledger.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(Order::STATUS_PROCESSING, $mismatchedLedger->fresh()->status);
        $this->assertSame(Order::DELIVERY_STATUS_PREPARING, $mismatchedLedger->fresh()->delivery_status);
        $this->assertSame(Order::PAYMENT_STATUS_UNPAID, $mismatchedLedger->fresh()->payment_status);
        $this->assertNull($mismatchedLedger->fresh()->delivered_at);
        $this->assertSame(\App\Models\Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
    }

    public function test_cod_delivery_completion_rejects_multiple_active_payment_ledgers(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_PREPARING,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
        ]);

        $first = $order->payments()->create([
            'method' => Order::PAYMENT_METHOD_COD,
            'status' => \App\Models\Payment::STATUS_PENDING,
            'transaction_reference' => 'COD-DUP-1',
            'amount' => $order->grand_total,
            'currency' => $order->currency,
        ]);
        $second = $order->payments()->create([
            'method' => Order::PAYMENT_METHOD_COD,
            'status' => \App\Models\Payment::STATUS_AUTHORIZED,
            'transaction_reference' => 'COD-DUP-2',
            'amount' => $order->grand_total,
            'currency' => $order->currency,
        ]);

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldNotReceive('queueDeliveryUpdate');

        try {
            (new DeliveryService($whatsApp, app(OrderActionService::class)))->update($order, [
                'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            ]);
            $this->fail('COD completion must reject ambiguous active payment ledgers.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_PROCESSING, $fresh->status);
        $this->assertSame(Order::DELIVERY_STATUS_PREPARING, $fresh->delivery_status);
        $this->assertSame(Order::PAYMENT_STATUS_UNPAID, $fresh->payment_status);
        $this->assertNull($fresh->delivered_at);
        $this->assertSame(\App\Models\Payment::STATUS_PENDING, $first->fresh()->status);
        $this->assertSame(\App\Models\Payment::STATUS_AUTHORIZED, $second->fresh()->status);
        $this->assertNull($first->fresh()->paid_at);
        $this->assertNull($second->fresh()->paid_at);
    }

    public function test_out_for_delivery_requires_a_recorded_shipment_timestamp(): void
    {
        $order = $this->createOrder([
            'delivery_status' => Order::DELIVERY_STATUS_SHIPPED,
            'shipped_at' => null,
        ]);

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldNotReceive('queueDeliveryUpdate');

        try {
            (new DeliveryService($whatsApp, app(OrderActionService::class)))->update($order, [
                'delivery_status' => Order::DELIVERY_STATUS_OUT_FOR_DELIVERY,
            ]);

            $this->fail('Out for delivery should require shipped_at.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delivery_status', $e->errors());
        }

        $this->assertSame(Order::DELIVERY_STATUS_SHIPPED, $order->fresh()->delivery_status);
    }

    public function test_valid_status_changes_set_lifecycle_timestamps_and_notify_once_per_change(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $admin = User::factory()->create(['role_as' => 1]);
        $order = $this->createOrder([
            'user_id' => $customer->id,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
        ]);
        $order->payments()->create([
            'method' => Order::PAYMENT_METHOD_COD,
            'status' => \App\Models\Payment::STATUS_PENDING,
            'transaction_reference' => 'COD-LIFECYCLE',
            'amount' => $order->grand_total,
            'currency' => $order->currency,
        ]);

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldReceive('queueDeliveryUpdate')->times(4);

        $service = new DeliveryService($whatsApp, app(OrderActionService::class));

        $service->update($order, ['delivery_status' => Order::DELIVERY_STATUS_PREPARING]);
        $service->update($order->fresh(), ['delivery_status' => Order::DELIVERY_STATUS_SHIPPED]);

        $shippedAt = $order->fresh()->shipped_at;
        $this->assertNotNull($shippedAt);

        $service->update($order->fresh(), ['delivery_status' => Order::DELIVERY_STATUS_OUT_FOR_DELIVERY]);
        $service->update($order->fresh(), ['delivery_status' => Order::DELIVERY_STATUS_DELIVERED]);

        $deliveredAt = $order->fresh()->delivered_at;
        $this->assertNotNull($deliveredAt);

        $sameStatus = $service->update($order->fresh(), [
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_notes' => 'Leave at reception',
        ]);

        $this->assertFalse($sameStatus['status_changed']);
        $this->assertTrue($order->fresh()->delivered_at->equalTo($deliveredAt));
        $this->assertFalse($order->fresh()->canTransitionDeliveryTo(Order::DELIVERY_STATUS_RETURNED));

        Notification::assertSentToTimes($customer, DeliveryStatusUpdatedNotification::class, 4);
        Notification::assertSentToTimes($admin, DeliveryStatusUpdatedNotification::class, 4);
    }

    public function test_return_to_sender_can_be_reprepared_without_mutating_inventory(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $order = $this->createOrder([
            'user_id' => $customer->id,
            'status' => Order::STATUS_PROCESSING,
            'delivery_status' => Order::DELIVERY_STATUS_SHIPPED,
            'shipped_at' => now()->subHour()->startOfSecond(),
            'shipping_provider' => 'Courier A',
            'tracking_number' => 'RTS-100',
        ]);

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldReceive('queueDeliveryUpdate')->twice();

        $service = new DeliveryService($whatsApp, app(OrderActionService::class));
        $service->update($order, ['delivery_status' => Order::DELIVERY_STATUS_RETURNED]);

        $returned = $order->fresh();
        $this->assertSame(Order::STATUS_PROCESSING, $returned->status);
        $this->assertSame(Order::DELIVERY_STATUS_RETURNED, $returned->delivery_status);
        $this->assertTrue($returned->canTransitionDeliveryTo(Order::DELIVERY_STATUS_PREPARING));
        $this->assertSame('RTS-100', data_get($returned->meta, 'delivery_return_history.0.tracking_number'));
        $this->assertSame('shipped', data_get($returned->meta, 'delivery_return_history.0.from_status'));

        $service->update($returned, ['delivery_status' => Order::DELIVERY_STATUS_PREPARING]);

        $retried = $order->fresh();
        $this->assertSame(Order::DELIVERY_STATUS_PREPARING, $retried->delivery_status);
        $this->assertNull($retried->shipped_at);
        $this->assertNull($retried->delivered_at);
        $this->assertTrue($retried->canTransitionDeliveryTo(Order::DELIVERY_STATUS_SHIPPED));
    }

    public function test_delivered_and_pickup_orders_cannot_bypass_rma_through_delivery_returned(): void
    {
        $delivered = $this->createOrder([
            'status' => Order::STATUS_COMPLETED,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $pickup = $this->createOrder([
            'status' => Order::STATUS_COMPLETED,
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->assertFalse($delivered->canTransitionDeliveryTo(Order::DELIVERY_STATUS_RETURNED));
        $this->assertFalse($pickup->canTransitionDeliveryTo(Order::DELIVERY_STATUS_RETURNED));
    }

    public function test_store_pickup_skips_shipping_only_states(): void
    {
        $order = $this->createOrder([
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
        ]);
        $order->payments()->create([
            'method' => Order::PAYMENT_METHOD_COD,
            'status' => \App\Models\Payment::STATUS_PENDING,
            'transaction_reference' => 'COD-PICKUP',
            'amount' => $order->grand_total,
            'currency' => $order->currency,
        ]);

        $this->assertTrue($order->canTransitionDeliveryTo(Order::DELIVERY_STATUS_PREPARING));
        $this->assertFalse($order->canTransitionDeliveryTo(Order::DELIVERY_STATUS_SHIPPED));
        $this->assertFalse($order->canTransitionDeliveryTo(Order::DELIVERY_STATUS_OUT_FOR_DELIVERY));

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldReceive('queueDeliveryUpdate')->twice();

        $service = new DeliveryService($whatsApp, app(OrderActionService::class));
        $service->update($order, ['delivery_status' => Order::DELIVERY_STATUS_PREPARING]);
        $service->update($order->fresh(), ['delivery_status' => Order::DELIVERY_STATUS_DELIVERED]);

        $this->assertNotNull($order->fresh()->delivered_at);
        $this->assertNull($order->fresh()->shipped_at);
    }

    protected function createOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'ORD-DEL-'.uniqid(),
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'customer_name' => 'Delivery Customer',
            'customer_email' => 'delivery@example.com',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Delivery Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ], $overrides));
    }
}
