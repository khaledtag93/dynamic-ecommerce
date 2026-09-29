<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\WebsiteSetting;
use App\Services\Commerce\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWorkspaceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/PaymentController.php'));

        $this->assertStringContainsString('mb_substr(trim((string) $request->string(\'search\')), 0, 100)', $controller);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_payment_list_and_settings_controls_have_explicit_labels(): void
    {
        $list = file_get_contents(resource_path('views/admin/payments/index.blade.php'));
        $settings = file_get_contents(resource_path('views/admin/settings/payments.blade.php'));

        foreach ([
            'paymentSearch',
            'paymentStatus',
            'paymentMethod',
            'paymentPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $list);
            $this->assertStringContainsString('id="' . $controlId . '"', $list);
        }

        foreach ([
            'paymobProcessedCallback',
            'paymobResponseCallback',
            'paymentGatewayProvider',
            'paymentGatewayMode',
            'paymentStockReservationMinutes',
            'paymobIntegrationId',
            'paymobIframeId',
            'bankTransferInstructionsEn',
            'bankTransferInstructionsAr',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $settings);
            $this->assertStringContainsString('id="' . $controlId . '"', $settings);
        }

        $this->assertStringContainsString('aria-required="true"', $settings);
    }

    public function test_payment_detail_controls_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/payments/show.blade.php'));

        foreach ([
            'paymentDetailStatus',
            'paymentDetailProviderStatus',
            'paymentDetailBankTransferReference',
            'paymentDetailNotes',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        $this->assertMatchesRegularExpression(
            '/id="paymentDetailStatus"[^>]*aria-required="true"/',
            $view
        );
    }

    public function test_payment_detail_form_matches_server_limits_and_uses_shared_admin_confirmation(): void
    {
        $view = file_get_contents(resource_path('views/admin/payments/show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/PaymentController.php'));

        $this->assertStringContainsString('id="paymentDetailStatus" class="form-select" name="status" required aria-required="true"', $view);
        $this->assertStringContainsString('id="paymentDetailProviderStatus" type="text" name="provider_status" maxlength="255"', $view);
        $this->assertStringContainsString('id="paymentDetailBankTransferReference" type="text" name="bank_transfer_reference" maxlength="255"', $view);
        $this->assertStringContainsString('id="paymentDetailNotes" name="notes" rows="4" maxlength="1000"', $view);

        $this->assertStringContainsString("form.setAttribute('data-confirm-message', message);", $view);
        $this->assertStringContainsString("form.setAttribute('data-confirm-ok'", $view);
        $this->assertStringContainsString("form.setAttribute('data-confirm-cancel'", $view);
        $this->assertStringNotContainsString('window.confirm(', $view);

        $this->assertStringContainsString("'status' => ['required', Rule::in(array_keys(Payment::statusOptions()))]", $controller);
        $this->assertStringContainsString("'provider_status' => ['nullable', 'string', 'max:255']", $controller);
        $this->assertStringContainsString("'bank_transfer_reference' => ['nullable', 'string', 'max:255']", $controller);
        $this->assertStringContainsString("Bank transfer reference is required before marking this payment as paid.", $controller);
        $this->assertStringContainsString("'notes' => ['nullable', 'string', 'max:1000']", $controller);
        $index = file_get_contents(resource_path('views/admin/payments/index.blade.php'));
        $this->assertStringContainsString("__('Captured amount')", $index);
        $this->assertStringContainsString('does not prove that a gateway payout or bank settlement reached the merchant account.', $index);
        $this->assertStringContainsString('Gateway capture does not prove merchant payout or bank settlement.', $view);
        $this->assertStringNotContainsString("__('Paid amount')", $index);
        $this->assertStringContainsString("->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])", $controller);
        $this->assertStringContainsString("selectRaw('currency, SUM(amount) AS captured_amount')", $controller);
        $this->assertStringContainsString("->groupBy('currency')", $controller);
        $this->assertStringContainsString("'captured_by_currency' => \$capturedByCurrency", $controller);
        $this->assertStringContainsString("\$stats['captured_by_currency']", $index);
        $this->assertStringNotContainsString("\$stats['paid_amount']", $index);
    }

    public function test_captured_amount_keeps_historical_refunded_captures(): void
    {
        $owner = $this->createSuperAdmin();
        $order = Order::query()->create([
            'order_number' => 'CAPTURE-HISTORY-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'grand_total' => 100,
            'refund_total' => 100,
            'currency' => 'EGP',
            'customer_name' => 'Capture History',
            'customer_email' => 'capture-history@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_ONLINE,
            'provider' => 'test',
            'status' => Payment::STATUS_REFUNDED,
            'transaction_reference' => 'CAPTURE-REFUNDED-001',
            'amount' => 100,
            'currency' => 'EGP',
            'paid_at' => now()->subDay(),
            'refunded_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                $egp = $stats['captured_by_currency']->firstWhere('currency', 'EGP');

                return $stats['paid'] === 0
                    && (float) data_get($egp, 'amount', 0) === 100.0;
            });
    }

    public function test_unreconciled_provider_reversal_stays_in_attention_queue_until_resolved(): void
    {
        $owner = $this->createSuperAdmin();
        $order = Order::query()->create([
            'order_number' => 'REVERSAL-ATTENTION-001',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'grand_total' => 100,
            'refund_total' => 0,
            'currency' => 'EGP',
            'customer_name' => 'Reversal Attention',
            'customer_email' => 'reversal-attention@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_ONLINE,
            'provider' => 'paymob',
            'provider_status' => 'refunded',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'REVERSAL-ATTENTION-PAY-001',
            'amount' => 100,
            'currency' => 'EGP',
            'paid_at' => now()->subDay(),
            'meta' => [
                'provider_reversal_evidence' => [
                    'type' => 'refunded',
                    'transaction_id' => 'PAYMOB-REVERSAL-ATTENTION-001',
                    'provider_refunded_amount_cents' => 4000,
                    'canonical_refund_recorded' => false,
                    'observed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->get(route('admin.payments.index', ['queue' => 'attention']))
            ->assertOk()
            ->assertViewHas('queueStats', fn (array $stats): bool => $stats['attention'] === 1)
            ->assertViewHas('payments', fn ($payments): bool => $payments->getCollection()->contains('id', $payment->id));

        $this->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSee(__('Needs attention'))
            ->assertSee('EGP 40.00');

        $meta = $payment->meta;
        data_set($meta, 'provider_reversal_evidence.canonical_refund_recorded', true);
        data_set($meta, 'provider_reversal_evidence.reconciled_at', now()->toIso8601String());
        $payment->update(['meta' => $meta]);

        $this->get(route('admin.payments.index', ['queue' => 'attention']))
            ->assertOk()
            ->assertViewHas('queueStats', fn (array $stats): bool => $stats['attention'] === 0)
            ->assertViewHas('payments', fn ($payments): bool => $payments->getCollection()->isEmpty());

        $this->get(route('admin.payments.show', $payment->fresh()))
            ->assertOk()
            ->assertDontSee('EGP 40.00');
    }

    public function test_unconfigured_online_gateway_is_not_offered_to_checkout(): void
    {
        config([
            'services.paymob.api_key' => '',
            'services.paymob.secret_key' => '',
            'services.paymob.public_key' => '',
            'services.paymob.hmac_secret' => '',
            'services.paymob.integration_id' => '',
            'services.paymob.iframe_id' => '',
        ]);

        $service = app(PaymentService::class);

        $this->assertFalse($service->onlineGatewayConfigured());
        $this->assertArrayNotHasKey(Order::PAYMENT_METHOD_ONLINE, $service->paymentOptionsForCheckout());
        $this->assertNotContains(Order::PAYMENT_METHOD_ONLINE, $service->enabledMethods());
    }

    public function test_configured_online_gateway_is_offered_to_checkout(): void
    {
        config([
            'services.paymob.api_key' => '',
            'services.paymob.secret_key' => 'egy_sk_test_server_secret',
            'services.paymob.public_key' => 'egy_pk_test_public_key',
            'services.paymob.hmac_secret' => 'test-hmac-secret',
            'services.paymob.integration_id' => '4345907',
            'services.paymob.iframe_id' => '',
        ]);

        $service = app(PaymentService::class);

        $this->assertTrue($service->onlineGatewayConfigured());
        $this->assertArrayHasKey(Order::PAYMENT_METHOD_ONLINE, $service->paymentOptionsForCheckout());
        $this->assertContains(Order::PAYMENT_METHOD_ONLINE, $service->enabledMethods());
    }

    public function test_disabling_all_payment_methods_does_not_force_cash_on_delivery(): void
    {
        WebsiteSetting::setValue('payment_cod_enabled', '0', 'payment');
        WebsiteSetting::setValue('payment_bank_transfer_enabled', '0', 'payment');
        WebsiteSetting::setValue('payment_online_enabled', '0', 'payment');

        $service = app(PaymentService::class);

        $this->assertSame([], $service->paymentOptionsForCheckout());
        $this->assertSame([], $service->enabledMethods());
    }

    public function test_checkout_stays_blocked_when_no_payment_method_is_available(): void
    {
        $view = file_get_contents(resource_path('views/frontend/checkout/index.blade.php'));
        $service = file_get_contents(app_path('Services/Commerce/PaymentService.php'));

        $this->assertStringContainsString('No payment methods are currently available. Please contact support or try again later.', $view);
        $this->assertStringContainsString('const paymentMethodsAvailable =', $view);
        $this->assertStringContainsString('if (!checkoutSubmitting && paymentMethodsAvailable)', $view);
        $this->assertStringContainsString('if (!paymentMethodsAvailable)', $view);
        $this->assertStringNotContainsString('Fallback method kept active to avoid blocking checkout.', $service);
    }

    public function test_payment_gateway_mode_rejects_forged_values(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->put(route('admin.settings.payments.update'), [
                'payment_gateway_mode' => 'unexpected-mode',
                'payment_stock_reservation_minutes' => 30,
            ])
            ->assertSessionHasErrors('payment_gateway_mode');
    }

    public function test_terminal_payment_business_rule_errors_are_localized_in_arabic(): void
    {
        $source = file_get_contents(app_path('Services/Commerce/PaymentService.php'));
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ([
            'Record refunds from the order refund action so the financial ledger stays consistent.',
            'A refunded payment is terminal and cannot be changed manually.',
        ] as $message) {
            $this->assertStringContainsString("__('{$message}')", $source);
            $this->assertArrayHasKey($message, $translations);
            $this->assertNotSame('', trim((string) $translations[$message]));
        }
    }
}
