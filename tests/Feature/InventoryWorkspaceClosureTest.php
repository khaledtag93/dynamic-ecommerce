<?php

namespace Tests\Feature;

use Tests\TestCase;

class InventoryWorkspaceClosureTest extends TestCase
{
    public function test_inventory_searches_are_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/InventoryController.php'));

        $this->assertSame(2, substr_count($controller, 'mb_substr(trim((string) $request->string(\'search\')), 0, 100)'));
        $this->assertSame(2, substr_count($controller, "str_replace(['\\\\', '%', '_']"));
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_inventory_adjust_form_matches_server_input_contract(): void
    {
        $view = file_get_contents(resource_path('views/admin/inventory/adjust.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/InventoryController.php'));

        $this->assertStringContainsString('name="search" value="{{ $search }}" maxlength="100"', $view);
        $this->assertStringContainsString('id="new_stock" name="new_stock" type="number" min="0" max="999999999" step="1" required aria-required="true"', $view);
        $this->assertStringContainsString('id="reason" name="reason" type="text" minlength="5" maxlength="255" required aria-required="true"', $view);

        $this->assertStringContainsString("'new_stock' => ['required', 'integer', 'min:0', 'max:999999999']", $controller);
        $this->assertStringContainsString("'reason' => ['required', 'string', 'min:5', 'max:255']", $controller);
    }

    public function test_inventory_list_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/inventory/index.blade.php'));

        foreach ([
            'inventorySearch',
            'inventoryMovementType',
            'inventorySource',
            'inventoryPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }
}
