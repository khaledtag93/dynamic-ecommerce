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

    public function test_customer_order_cancel_live_action_resets_confirmation_and_pending_state(): void
    {
        $view = file_get_contents(resource_path('views/frontend/orders/show.blade.php'));

        $this->assertStringContainsString("if (form.dataset.pending === '1') {", $view);
        $this->assertStringContainsString('delete form.dataset.confirmed;', $view);
        $this->assertStringContainsString("form.dataset.pending = '1';", $view);
        $this->assertStringContainsString("form.setAttribute('aria-busy', 'true');", $view);
        $this->assertStringContainsString('delete form.dataset.pending;', $view);
        $this->assertStringContainsString("form.removeAttribute('aria-busy');", $view);
        $this->assertStringContainsString("button.setAttribute('aria-disabled', 'true');", $view);
        $this->assertStringContainsString("button.removeAttribute('aria-disabled');", $view);
        $this->assertStringContainsString("liveStatus.classList.remove('d-none', 'text-danger');", $view);
        $this->assertStringContainsString("liveStatus.classList.add('text-success');", $view);
        $this->assertStringContainsString('release();', $view);
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

    public function test_order_detail_forms_match_server_validation_contract_and_use_local_placeholder(): void
    {
        $view = file_get_contents(resource_path('views/admin/orders/show.blade.php'));
        $orderController = file_get_contents(app_path('Http/Controllers/Admin/OrderController.php'));
        $deliveryController = file_get_contents(app_path('Http/Controllers/Admin/DeliveryController.php'));

        $this->assertStringContainsString("asset('images/storefront-placeholder.svg')", $view);
        $this->assertStringNotContainsString('via.placeholder.com', $view);

        $this->assertStringContainsString('id="orderStatusSelect" name="status" class="form-select" required aria-required="true"', $view);
        $this->assertStringContainsString('id="orderDeliveryStatus" name="delivery_status" class="form-select" required aria-required="true"', $view);
        $this->assertStringContainsString('id="orderShippingProvider" type="text" name="shipping_provider" maxlength="120"', $view);
        $this->assertStringContainsString('id="orderTrackingNumber" type="text" name="tracking_number" maxlength="120"', $view);
        $this->assertStringContainsString('id="orderDeliveryNotes" name="delivery_notes" rows="3" maxlength="1000"', $view);
        $this->assertStringContainsString('name="amount" class="form-control" value="{{ old(\'amount\', $order->refundable_balance) }}" required aria-required="true"', $view);
        $this->assertStringContainsString('name="reason" maxlength="255" class="form-control" value="{{ old(\'reason\') }}" placeholder="{{ __(\'Refund reason\') }}" required aria-required="true"', $view);
        $this->assertStringContainsString('id="orderRefundNotes" name="notes" rows="3" maxlength="1000"', $view);
        $this->assertStringContainsString('name="refund_idempotency_key"', $view);
        $this->assertStringContainsString("Str::uuid()", $view);

        $this->assertStringContainsString("'status' => ['required', Rule::in(array_keys(Order::statusOptions()))]", $orderController);
        $this->assertStringContainsString("'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99']", $orderController);
        $this->assertStringContainsString("'reason' => ['required', 'string', 'max:255']", $orderController);
        $this->assertStringContainsString("'notes' => ['nullable', 'string', 'max:1000']", $orderController);
        $this->assertStringContainsString("'refund_idempotency_key' => ['required', 'uuid']", $orderController);
        $this->assertStringContainsString("'shipping_provider' => ['nullable', 'string', 'max:120']", $deliveryController);
        $this->assertStringContainsString("'tracking_number' => ['nullable', 'string', 'max:120']", $deliveryController);
        $this->assertStringContainsString("'delivery_notes' => ['nullable', 'string', 'max:1000']", $deliveryController);
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
