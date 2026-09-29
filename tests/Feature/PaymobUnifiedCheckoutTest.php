<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commerce\PaymentService;
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

    public function test_paymob_callback_exposes_cumulative_provider_refund_amount(): void
    {
        $order = $this->makeOnlineOrder(100);
        $payment = $this->makePayment($order);
        $payment->update(['transaction_reference' => '265715299']);

        $createdAt = '2026-09-29T16:00:00.000000+00:00';
        $payload = [
            'amount_cents' => 10000,
            'created_at' => $createdAt,
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 'PAYMOB-REFUND-AMOUNT-001',
            'integration_id' => 4345907,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => true,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => 265715299,
            'owner' => 123,
            'pending' => false,
            'source_data.pan' => '1234',
            'source_data.sub_type' => 'MasterCard',
            'source_data.type' => 'card',
            'success' => true,
            'refunded_amount_cents' => 4000,
        ];

        $payload['hmac'] = hash_hmac('sha512', implode('', [
            '10000',
            $createdAt,
            'EGP',
            'false',
            'false',
            'PAYMOB-REFUND-AMOUNT-001',
            '4345907',
            'true',
            'false',
            'false',
            'true',
            'true',
            'false',
            '265715299',
            '123',
            'false',
            '1234',
            'MasterCard',
            'card',
            'true',
        ]), 'test-hmac-secret');

        $result = app(PaymobGatewayService::class)->handleCallback($payload);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['provider_refunded']);
        $this->assertSame(4000, $result['provider_refunded_amount_cents']);
        $this->assertSame($payment->id, $result['payment']->id);
    }

    public function test_provider_refund_callback_never_marks_pending_payment_as_paid_even_when_provider_success_is_true(): void
    {
        $order = $this->makeOnlineOrder(100);
        $payment = $this->makePayment($order);

        $gateway = \Mockery::mock(PaymobGatewayService::class);
        $gateway->shouldReceive('handleCallback')
            ->once()
            ->andReturn([
                'valid' => true,
                'success' => true,
                'pending' => false,
                'message' => 'Provider refund observed.',
                'order' => $order,
                'payment' => $payment,
                'provider_status' => 'refunded',
                'provider_refunded' => true,
                'provider_voided' => false,
                'provider_refunded_amount_cents' => 10000,
                'transaction_id' => 'PAYMOB-REFUND-SUCCESS-001',
                'paymob_order_id' => '265715299',
                'merchant_order_id' => (string) $order->id,
                'response_code' => '00',
                'response_message' => 'Refunded',
                'hmac_valid' => true,
            ]);
        $this->app->instance(PaymobGatewayService::class, $gateway);

        $this->postJson(route('payments.paymob.callback'), ['hmac' => 'signed-test-callback'])
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'payment_status' => Payment::STATUS_FAILED,
                'order_payment_status' => Order::PAYMENT_STATUS_FAILED,
                'provider_status' => 'refunded',
            ]);

        $freshPayment = $payment->fresh();
        $freshOrder = $order->fresh();

        $this->assertSame(Payment::STATUS_FAILED, $freshPayment->status);
        $this->assertSame(Order::PAYMENT_STATUS_FAILED, $freshOrder->payment_status);
        $this->assertSame('refunded', data_get($freshPayment->meta, 'provider_reversal_evidence.type'));
        $this->assertSame(
            'PAYMOB-REFUND-SUCCESS-001',
            data_get($freshPayment->meta, 'provider_reversal_evidence.transaction_id')
        );
        $this->assertSame(10000, (int) data_get($freshPayment->meta, 'provider_reversal_evidence.provider_refunded_amount_cents'));
        $this->assertFalse((bool) data_get($freshPayment->meta, 'provider_reversal_evidence.canonical_refund_recorded'));
        $this->assertDatabaseCount('order_refunds', 0);
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

    public function test_online_checkout_initiation_claim_blocks_duplicate_gateway_start_until_release_or_expiry(): void
    {
        $order = $this->makeOnlineOrder(90);
        $payment = $this->makePayment($order);
        $service = app(PaymentService::class);

        $firstToken = $service->claimOnlineCheckoutInitiation($payment);

        $this->assertNotNull($firstToken);
        $this->assertNull($service->claimOnlineCheckoutInitiation($payment->fresh()));

        $service->releaseOnlineCheckoutInitiation($payment, 'wrong-token');
        $this->assertNull($service->claimOnlineCheckoutInitiation($payment->fresh()));

        $service->releaseOnlineCheckoutInitiation($payment, $firstToken);

        $secondToken = $service->claimOnlineCheckoutInitiation($payment->fresh());
        $this->assertNotNull($secondToken);
        $this->assertNotSame($firstToken, $secondToken);

        $fresh = $payment->fresh();
        $meta = $fresh->meta ?? [];
        $meta['online_checkout_initiation_claim']['expires_at'] = now()->subSecond()->toIso8601String();
        $fresh->update(['meta' => $meta]);

        $thirdToken = $service->claimOnlineCheckoutInitiation($fresh->fresh());

        $this->assertNotNull($thirdToken);
        $this->assertNotSame($secondToken, $thirdToken);
    }

    public function test_second_customer_retry_request_does_not_create_another_paymob_intention_while_claim_is_active(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOnlineOrder(110);
        $order->update(['user_id' => $user->id]);
        $payment = $this->makePayment($order);
        $service = app(PaymentService::class);

        $claimToken = $service->claimOnlineCheckoutInitiation($payment);
        $this->assertNotNull($claimToken);

        Http::fake();

        $response = $this->actingAs($user)->get(route('payments.paymob.redirect', $order));

        $response
            ->assertRedirect(route('payments.paymob.result', $order))
            ->assertSessionHas(
                'status',
                __('A secure payment session is already being prepared. Please wait a moment and try again.')
            );

        Http::assertNothingSent();

        $this->assertSame(
            $claimToken,
            data_get($payment->fresh()->meta, 'online_checkout_initiation_claim.token')
        );
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
