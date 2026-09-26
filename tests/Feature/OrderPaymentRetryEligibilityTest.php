<?php

namespace Tests\Feature;

use App\Models\Order;
use Tests\TestCase;

class OrderPaymentRetryEligibilityTest extends TestCase
{
    public function test_online_payment_retry_eligibility_matrix(): void
    {
        $base = [
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
        ];

        $this->assertTrue((new Order($base))->canRetryOnlinePayment());

        $this->assertFalse((new Order(array_merge($base, [
            'status' => Order::STATUS_CANCELLED,
        ])))->canRetryOnlinePayment());

        foreach ([
            Order::PAYMENT_STATUS_PAID,
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            Order::PAYMENT_STATUS_REFUNDED,
        ] as $terminalPaymentStatus) {
            $this->assertFalse((new Order(array_merge($base, [
                'payment_status' => $terminalPaymentStatus,
            ])))->canRetryOnlinePayment());
        }

        $this->assertFalse((new Order(array_merge($base, [
            'payment_method' => Order::PAYMENT_METHOD_COD,
        ])))->canRetryOnlinePayment());
    }

    public function test_customer_order_view_uses_shared_retry_eligibility_contract(): void
    {
        $view = file_get_contents(resource_path('views/frontend/orders/show.blade.php'));

        $this->assertStringContainsString('$order->can_retry_online_payment', $view);
        $this->assertStringNotContainsString(
            '$order->payment_status !== \\App\\Models\\Order::PAYMENT_STATUS_PAID',
            $view
        );
    }
}
