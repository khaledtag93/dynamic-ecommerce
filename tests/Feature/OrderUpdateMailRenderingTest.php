<?php

namespace Tests\Feature;

use App\Mail\OrderUpdateMail;
use App\Models\Order;
use App\Services\Commerce\OrderNotificationService;
use Tests\TestCase;

class OrderUpdateMailRenderingTest extends TestCase
{
    public function test_order_update_email_renders_escaped_body_in_english_and_arabic(): void
    {
        // Rendering only: no database fixture, transport, queue, or external delivery.
        $order = new Order([
            'order_number' => 'MAIL-RENDER-001',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.test',
            'status' => Order::STATUS_PROCESSING,
            'delivery_status' => Order::DELIVERY_STATUS_PREPARING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'grand_total' => '150.50',
            'currency' => 'EGP',
        ]);
        $order->id = 501;

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            $mail = new OrderUpdateMail(
                $order,
                OrderNotificationService::EVENT_STATUS_UPDATED,
                'Order updated',
                'Shipment <ready> & confirmed'
            );

            // Use the in-memory array transport, not Mail::fake(): a fake would
            // skip the real mail view render path that previously failed on QAS.
            // No network or external message is sent.
            \Illuminate\Support\Facades\Mail::mailer('array')
                ->to('render-recipient@example.test')
                ->send($mail);

            $rendered = $mail->render();

            $this->assertStringContainsString('Shipment &lt;ready&gt; &amp; confirmed', $rendered);
            $this->assertStringContainsString('MAIL-RENDER-001', $rendered);
            $this->assertStringContainsString('150.50 EGP', $rendered);
        }
    }
}
