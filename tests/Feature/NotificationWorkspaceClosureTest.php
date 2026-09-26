<?php

namespace Tests\Feature;

use Tests\TestCase;

class NotificationWorkspaceClosureTest extends TestCase
{
    public function test_notification_dispatch_search_is_bounded_and_escaped(): void
    {
        $model = file_get_contents(app_path('Models/NotificationDispatchLog.php'));

        $this->assertStringContainsString(
            'mb_substr(trim((string) $search), 0, 100)',
            $model
        );
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $model);
        $this->assertStringNotContainsString('"%{$search}%"', $model);
    }

    public function test_notification_center_navigation_exposes_current_page_semantics(): void
    {
        $layout = file_get_contents(resource_path('views/admin/settings/notification-center/layout.blade.php'));

        $this->assertStringContainsString('aria-current="page"', $layout);
        $this->assertStringContainsString('($currentSection ?? \'overview\') === $key', $layout);
    }
}
