<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminSidebarThemeContrastTest extends TestCase
{
    public function test_admin_sidebar_navigation_contrast_follows_white_label_color(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertNotFalse($layout);
        $this->assertStringContainsString("['admin_sidebar_color']", $layout);
        $this->assertStringContainsString('$sidebarLuminance', $layout);
        $this->assertStringContainsString('--admin-sidebar-contrast:', $layout);
        $this->assertStringContainsString('--admin-sidebar-active-ink:', $layout);
    }

    public function test_hover_focus_and_current_page_have_distinct_styles(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $sidebar = file_get_contents(resource_path('views/layouts/inc/admin/sidebar.blade.php'));

        $this->assertNotFalse($layout);
        $this->assertNotFalse($sidebar);
        $this->assertStringContainsString('.nav-item:not(.sidebar-current) > .nav-link:hover', $layout);
        $this->assertStringContainsString('.nav-item.sidebar-current > .nav-link', $layout);
        $this->assertStringContainsString('.nav .nav-item > .nav-link:focus-visible', $layout);
        $this->assertStringContainsString('inset-inline-start: 0', $layout);
        $this->assertStringContainsString('aria-current=', $sidebar);
    }
}
