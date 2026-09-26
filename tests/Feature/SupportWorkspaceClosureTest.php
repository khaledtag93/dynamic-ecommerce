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

    public function test_customer_support_request_and_reply_controls_have_explicit_labels(): void
    {
        $create = file_get_contents(resource_path('views/frontend/support/create.blade.php'));
        $show = file_get_contents(resource_path('views/frontend/support/show.blade.php'));

        foreach ([
            'supportOrder',
            'supportSubject',
            'supportCategory',
            'supportMessage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $create);
            $this->assertStringContainsString('id="' . $controlId . '"', $create);
        }

        foreach (['supportSubject', 'supportMessage'] as $controlId) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($controlId, '/') . '"[^>]*aria-required="true"/',
                $create
            );
        }

        $this->assertStringContainsString('for="supportReplyMessage"', $show);
        $this->assertStringContainsString('id="supportReplyMessage"', $show);
        $this->assertMatchesRegularExpression(
            '/id="supportReplyMessage"[^>]*aria-required="true"/',
            $show
        );
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
