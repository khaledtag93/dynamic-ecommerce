<?php

namespace Tests\Feature;

use Tests\TestCase;

class OrderWorkspaceClosureTest extends TestCase
{
    public function test_order_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/OrderController.php'));

        $this->assertStringContainsString('mb_substr(trim((string) $request->string(\'search\')), 0, 100)', $controller);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_order_list_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/orders/index.blade.php'));

        foreach ([
            'orderSearch',
            'orderStatusFilter',
            'orderPaymentStatusFilter',
            'orderPaymentMethodFilter',
            'orderPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }

    public function test_order_detail_lifecycle_controls_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/orders/show.blade.php'));

        foreach ([
            'orderStatusSelect',
            'orderDeliveryStatus',
            'orderShippingProvider',
            'orderTrackingNumber',
            'orderEstimatedDeliveryDate',
            'orderDeliveryNotes',
            'orderRefundAmount',
            'orderRefundReason',
            'orderRefundNotes',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }
}
