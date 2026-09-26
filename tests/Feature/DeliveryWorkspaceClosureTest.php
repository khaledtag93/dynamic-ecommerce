<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeliveryWorkspaceClosureTest extends TestCase
{
    public function test_delivery_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/DeliveryController.php'));

        $this->assertStringContainsString('mb_substr(trim((string) $request->string(\'search\')), 0, 100)', $controller);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_delivery_list_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/deliveries/index.blade.php'));

        foreach ([
            'deliverySearch',
            'deliveryStatus',
            'deliveryMethod',
            'deliveryPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }
}
