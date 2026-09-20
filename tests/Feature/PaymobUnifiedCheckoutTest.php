<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymobGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymobUnifiedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://staging.example.test',
            'services.paymob.api_key' => '',
            'services.paymob.secret_key' => 'egy_sk_test_server_secret',
            'services.paymob.public_key' => 'egy_pk_test_public_key',
            'services.paymob.hmac_secret' => 'test-hmac-secret',
            'services.paymob.integration_id' => '4345907',
            'services.paymob.iframe_id' => '',
            'services.paymob.base_url' => 'https://accept.paymob.com/api',
            'services.paymob.unified_base_url' => 'https://accept.paymob.com',
            'services.paymob.currency' => 'EGP',
            'services.paymob.verify_ssl' => true,
        ]);
    }

    public function test_unified_checkout_creates_intention_and_encrypts_client_secret_at_rest(): void
    {
        $order = $this->makeOnlineOrder(100);
        $payment = $this->makePayment($order);

        Http::fake([
            'https://accept.paymob.com/v1/intention/' => Http::response([
                'id' => 'pi_test_123',
                'intention_order_id' => 265715202,
                'client_secret' => 'egy_csk_test_checkout_secret',
            ], 201),
        ]);

        $gateway = app(PaymobGatewayService::class);

        $this->assertSame('unified', $gateway->checkoutMode());

        $url = $gateway->checkoutUrl($order, $payment);

        $this->assertSame(
            'https://accept.paymob.com/unifiedcheckout/?publicKey=egy_pk_test_public_key&clientSecret=egy_csk_test_checkout_secret',
            $url
        );

        Http::assertSent(function (Request $request) use ($order) {
            $payload = $request->data();

            return $request->url() === 'https://accept.paymob.com/v1/intention/'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Token egy_sk_test_server_secret')
                && ($payload['amount'] ?? null) === 10000
                && ($payload['currency'] ?? null) === 'EGP'
                && ($payload['payment_methods'] ?? null) === [4345907]
                && ($payload['special_reference'] ?? null) === (string) $order->id
                && ($payload['notification_url'] ?? null) === route('payments.paymob.callback')
                && ($payload['redirection_url'] ?? null) === route('payments.paymob.result', $order);
        });

        $fresh = $payment->fresh();
        $meta = $fresh->meta ?? [];

        $this->assertSame('paymob', $fresh->provider);
        $this->assertSame('initiated', $fresh->provider_status);
        $this->assertSame('265715202', $fresh->transaction_reference);
        $this->assertSame('unified', $meta['paymob_flow'] ?? null);
        $this->assertSame('pi_test_123', $meta['paymob_intention_id'] ?? null);
        $this->assertSame('265715202', (string) ($meta['paymob_order_id'] ?? ''));
        $this->assertArrayHasKey('encrypted_client_secret', $meta);
        $this->assertNotSame('egy_csk_test_checkout_secret', $meta['encrypted_client_secret']);
        $this->assertSame(
            'egy_csk_test_checkout_secret',
            Crypt::decryptString($meta['encrypted_client_secret'])
        );
        $this->assertArrayNotHasKey('payment_token', $meta);
    }

    public function test_recent_unified_checkout_session_is_reused_without_creating_second_intention(): void
    {
        $order = $this->makeOnlineOrder(75);
        $payment = $this->makePayment($order);

        Http::fake([
            'https://accept.paymob.com/v1/intention/' => Http::response([
                'id' => 'pi_test_reuse',
                'intention_order_id' => 265715203,
                'client_secret' => 'egy_csk_test_reuse_secret',
            ], 201),
        ]);

        $gateway = app(PaymobGatewayService::class);

        $first = $gateway->checkoutUrl($order, $payment);
        $second = $gateway->checkoutUrl($order, $payment->fresh());

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_unified_configuration_is_preferred_over_legacy_if_both_exist(): void
    {
        config([
            'services.paymob.api_key' => 'legacy-api-key',
            'services.paymob.iframe_id' => '999999',
        ]);

        $gateway = app(PaymobGatewayService::class);

        $this->assertSame('unified', $gateway->checkoutMode());
        $this->assertTrue($gateway->isConfigured());

        $diagnostics = $gateway->configurationDiagnostics();
        $this->assertTrue($diagnostics['secret_key_present']);
        $this->assertTrue($diagnostics['public_key_present']);
        $this->assertSame('unified', $diagnostics['checkout_mode']);
    }

    private function makeOnlineOrder(float $grandTotal): Order
    {
        return Order::query()->create([
            'order_number' => 'PAYMOB-'.Str::upper(Str::random(10)),
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => $grandTotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'customer_name' => 'Paymob Test Customer',
            'customer_email' => 'paymob@example.test',
            'customer_phone' => '+201000000000',
            'shipping_address_line_1' => 'Test street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'refund_total' => 0,
            'placed_at' => now(),
        ]);
    }

    private function makePayment(Order $order): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_ONLINE,
            'provider' => 'paymob',
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
        ]);
    }
}
