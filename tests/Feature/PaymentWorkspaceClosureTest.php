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
}
