<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReturnWorkspaceClosureTest extends TestCase
{
    public function test_admin_return_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReturnRequestController.php'));

        $this->assertStringContainsString("mb_substr(trim((string) $request->string('search')), 0, 100)", $controller);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_admin_return_lifecycle_controls_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/returns/show.blade.php'));

        foreach ([
            'approvedQuantity-{{ $item->id }}',
            'approveReviewNotes',
            'rejectReviewNotes',
            'receivedQuantity-{{ $item->id }}',
            'restockQuantity-{{ $item->id }}',
            'returnRefundAmount',
            'returnExchangeOrderId',
            'returnCompletionNotes',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        $this->assertStringContainsString('id="approvedQuantity-{{ $item->id }}" type="number"', $view);
        $this->assertStringContainsString('id="rejectReviewNotes"', $view);
        $this->assertStringContainsString('aria-required="true"', $view);
    }

    public function test_return_list_filters_and_customer_request_controls_have_explicit_labels(): void
    {
        $admin = file_get_contents(resource_path('views/admin/returns/index.blade.php'));
        $customer = file_get_contents(resource_path('views/frontend/returns/create.blade.php'));

        foreach (['returnSearch', 'returnStatus', 'returnPerPage'] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $admin);
            $this->assertStringContainsString('id="' . $controlId . '"', $admin);
        }

        foreach ([
            'returnQuantity-{{ $item->id }}',
            'returnReason-{{ $item->id }}',
            'returnResolution-{{ $item->id }}',
            'returnReasonDetails-{{ $item->id }}',
            'returnCustomerNotes',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $customer);
            $this->assertStringContainsString('id="' . $controlId . '"', $customer);
        }
    }
}
