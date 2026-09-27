<?php

namespace Tests\Feature;

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
        $this->assertStringContainsString('id="paymentDetailNotes" name="notes" rows="4" maxlength="1000"', $view);

        $this->assertStringContainsString("form.setAttribute('data-confirm-message', message);", $view);
        $this->assertStringContainsString("form.setAttribute('data-confirm-ok'", $view);
        $this->assertStringContainsString("form.setAttribute('data-confirm-cancel'", $view);
        $this->assertStringNotContainsString('window.confirm(', $view);

        $this->assertStringContainsString("'status' => ['required', Rule::in(array_keys(Payment::statusOptions()))]", $controller);
        $this->assertStringContainsString("'provider_status' => ['nullable', 'string', 'max:255']", $controller);
        $this->assertStringContainsString("'notes' => ['nullable', 'string', 'max:1000']", $controller);
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
