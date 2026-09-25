<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commerce\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_gateway_payment_cannot_be_downgraded_by_late_failure(): void
    {
        $order = $this->createOrder([
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_ONLINE,
            'provider' => 'paymob',
            'status' => Payment::STATUS_PENDING,
            'transaction_reference' => 'PAY-TEST-001',
            'amount' => $order->grand_total,
            'currency' => 'EGP',
            'meta' => [],
        ]);

        $service = app(PaymentService::class);

        $service->markAsPaid($payment, [
            'transaction_id' => 'TX-PAID-001',
            'provider_status' => 'success',
        ]);

        $service->markAsFailed($payment->fresh(), [
            'provider_status' => 'failed',
        ]);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
    }

    public function test_admin_cannot_permanently_delete_cancelled_order(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $order = $this->createOrder([
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAYMENT_STATUS_FAILED,
        ]);

        $response = $this
            ->actingAs($superAdmin)
            ->delete(route('admin.orders.destroy', $order));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    protected function createOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'ORD-TEST-'.uniqid(),
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ], $overrides));
    }
}
