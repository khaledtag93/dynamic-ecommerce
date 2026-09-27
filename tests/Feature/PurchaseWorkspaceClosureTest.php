<?php

namespace Tests\Feature;

use Tests\TestCase;

class PurchaseWorkspaceClosureTest extends TestCase
{
    public function test_purchase_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/PurchaseController.php'));

        $this->assertStringContainsString('mb_substr(trim((string) $request->string(\'search\')), 0, 100)', $controller);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_purchase_create_controls_are_accessibly_named(): void
    {
        $view = file_get_contents(resource_path('views/admin/purchases/create.blade.php'));

        foreach ([
            'purchaseSupplierId',
            'purchaseDate',
            'purchaseShippingTotal',
            'purchaseTaxTotal',
            'purchaseNotes',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        $this->assertStringContainsString('id="purchaseSupplierId"', $view);
        $this->assertStringContainsString('aria-required="true"', $view);
        $this->assertStringContainsString('aria-label="{{ __(\'Product\') }}"', $view);
        $this->assertStringContainsString('aria-label="{{ __(\'Variant\') }}"', $view);
        $this->assertStringContainsString('aria-label="{{ __(\'Quantity\') }}"', $view);
        $this->assertStringContainsString('aria-label="{{ __(\'Unit cost\') }}"', $view);
        $this->assertStringContainsString('aria-label="{{ __(\'Expiration date\') }}"', $view);
        $this->assertStringContainsString('id="purchaseShippingTotal"', $view);
        $this->assertStringContainsString('max="9999999999.99"', $view);
        $this->assertStringContainsString('max="4294967295"', $view);
    }

    public function test_purchase_money_validation_matches_decimal_storage_contract(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/PurchaseController.php'));

        $this->assertStringContainsString("'shipping_total' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99']", $controller);
        $this->assertStringContainsString("'tax_total' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99']", $controller);
        $this->assertStringContainsString("'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::PURCHASE_QUANTITY_MAX]", $controller);
        $this->assertStringContainsString("'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99']", $controller);
        $this->assertStringContainsString('private const MONEY_MAX_CENTS = 999999999999;', $controller);
        $this->assertStringContainsString('protected function moneyToCents', $controller);
        $this->assertStringContainsString('protected function centsToMoney', $controller);
    }

    public function test_purchase_list_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/purchases/index.blade.php'));

        foreach ([
            'purchaseSearch',
            'purchaseStatus',
            'purchaseSupplier',
            'purchasePerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }
}
