<?php

namespace Tests\Feature;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DeliveryStatusUpdatedNotification;
use App\Services\Commerce\DeliveryService;
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

        $result = (new DeliveryService($whatsApp))->update($order, [
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

        (new DeliveryService($whatsApp))->update($order, [
            'delivery_status' => Order::DELIVERY_STATUS_OUT_FOR_DELIVERY,
        ]);
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
            (new DeliveryService($whatsApp))->update($order, [
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

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldReceive('queueDeliveryUpdate')->times(5);

        $service = new DeliveryService($whatsApp);

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

        $service->update($order->fresh(), ['delivery_status' => Order::DELIVERY_STATUS_RETURNED]);

        $fresh = $order->fresh();
        $this->assertSame(Order::DELIVERY_STATUS_RETURNED, $fresh->delivery_status);
        $this->assertTrue($fresh->shipped_at->equalTo($shippedAt));
        $this->assertTrue($fresh->delivered_at->equalTo($deliveredAt));

        Notification::assertSentToTimes($customer, DeliveryStatusUpdatedNotification::class, 5);
        Notification::assertSentToTimes($admin, DeliveryStatusUpdatedNotification::class, 5);
    }

    public function test_store_pickup_skips_shipping_only_states(): void
    {
        $order = $this->createOrder([
            'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
        ]);

        $this->assertTrue($order->canTransitionDeliveryTo(Order::DELIVERY_STATUS_PREPARING));
        $this->assertFalse($order->canTransitionDeliveryTo(Order::DELIVERY_STATUS_SHIPPED));
        $this->assertFalse($order->canTransitionDeliveryTo(Order::DELIVERY_STATUS_OUT_FOR_DELIVERY));

        $whatsApp = Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldReceive('queueDeliveryUpdate')->twice();

        $service = new DeliveryService($whatsApp);
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
