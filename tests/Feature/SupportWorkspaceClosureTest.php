<?php

namespace Tests\Feature;

use Tests\TestCase;

class SupportWorkspaceClosureTest extends TestCase
{
    public function test_support_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/SupportCaseController.php'));

        $this->assertStringContainsString(
            'mb_substr(trim((string) $request->string(\'search\')), 0, 100)',
            $controller
        );
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_support_list_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/support/index.blade.php'));

        foreach ([
            'supportCaseSearch',
            'supportCaseStatus',
            'supportCasePriority',
            'supportCaseOwner',
            'supportCasePerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }
}
