<?php

namespace Tests\Feature;

use Tests\TestCase;

class InventoryWorkspaceClosureTest extends TestCase
{
    public function test_inventory_searches_are_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/InventoryController.php'));

        $this->assertSame(2, substr_count($controller, "mb_substr(trim((string) \\$request->string('search')), 0, 100)"));
        $this->assertSame(2, substr_count($controller, "str_replace(['\\\\', '%', '_']"));
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
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
