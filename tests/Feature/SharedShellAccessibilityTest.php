<?php

namespace Tests\Feature;

use Tests\TestCase;

class SharedShellAccessibilityTest extends TestCase
{
    public function test_admin_and_storefront_shells_expose_keyboard_skip_targets(): void
    {
        $admin = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $storefront = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('href="#adminMainContent"', $admin);
        $this->assertStringContainsString('<main id="adminMainContent" class="content-wrapper" tabindex="-1">', $admin);
        $this->assertStringContainsString('href="#storefrontMainContent"', $storefront);
        $this->assertStringContainsString('<main id="storefrontMainContent" tabindex="-1">', $storefront);
    }

    public function test_shared_shells_honor_reduced_motion_preferences(): void
    {
        $admin = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $storefront = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $admin);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $storefront);
        $this->assertStringContainsString('animation-iteration-count: 1 !important;', $admin);
        $this->assertStringContainsString('animation-iteration-count:1 !important;', $storefront);
    }

    public function test_mobile_account_navigation_scroll_is_independent_from_search_shortcut_and_respects_motion_preference(): void
    {
        $storefront = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $accountScroll = strpos($storefront, "document.querySelectorAll('.lc-account-nav [aria-current=\"page\"]')");
        $searchShortcut = strpos($storefront, "document.addEventListener('keydown', (event) => {");

        $this->assertNotFalse($accountScroll);
        $this->assertNotFalse($searchShortcut);
        $this->assertTrue($accountScroll < $searchShortcut);
        $this->assertStringContainsString("const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;", $storefront);
        $this->assertStringContainsString("behavior: reduceMotion ? 'auto' : 'smooth'", $storefront);
    }

    public function test_storefront_direction_styles_follow_the_html_direction_owner(): void
    {
        $storefront = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('dir="{{ ($isRtl ?? false) ? \'rtl\' : \'ltr\' }}"', $storefront);
        $this->assertStringNotContainsString('body[dir="rtl"]', $storefront);
        $this->assertStringNotContainsString('body[dir="ltr"]', $storefront);
        $this->assertStringContainsString('html[dir="rtl"] .retail-search', $storefront);
        $this->assertStringContainsString('html[dir="rtl"] .retail-quick-tile', $storefront);
        $this->assertStringContainsString('html[dir="ltr"] .retail-quick-tile', $storefront);
        $this->assertStringContainsString('html[dir="rtl"] .lc-order-step', $storefront);
    }

    public function test_mobile_admin_sidebar_stays_reachable_above_the_backdrop_in_both_directions(): void
    {
        $admin = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $navbar = file_get_contents(resource_path('views/layouts/inc/admin/navbar.blade.php'));

        $this->assertStringContainsString('.navbar .navbar-menu-wrapper .admin-mobile-sidebar-toggle-inline', $admin);
        $this->assertStringContainsString('display: inline-flex !important;', $admin);
        $this->assertStringContainsString('.sidebar-offcanvas.custom-sidebar {', $admin);
        $this->assertStringContainsString('z-index: 1050 !important;', $admin);
        $this->assertStringContainsString("html[dir='ltr'] .sidebar-offcanvas.custom-sidebar.active", $admin);
        $this->assertStringContainsString("html[dir='rtl'] .sidebar-offcanvas.custom-sidebar.active", $admin);
        $this->assertStringContainsString('data-toggle="offcanvas"', $navbar);
        $this->assertStringContainsString('aria-controls="sidebar"', $navbar);
    }

    public function test_mobile_admin_shell_uses_independent_vanilla_drawer_and_html_direction(): void
    {
        $admin = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $navbar = file_get_contents(resource_path('views/layouts/inc/admin/navbar.blade.php'));
        $offcanvas = file_get_contents(public_path('admin/js/off-canvas.js'));

        $this->assertStringNotContainsString("body[dir='rtl']", $admin);
        $this->assertStringNotContainsString("body[dir='rtl']", $navbar);
        $this->assertStringContainsString("html[dir='rtl'] .sidebar-offcanvas.custom-sidebar", $admin);
        $this->assertStringContainsString('height: calc(100dvh - 64px);', $admin);
        $this->assertStringContainsString('overflow-y: auto !important;', $admin);
        $this->assertStringContainsString('touch-action: pan-y;', $admin);
        $this->assertStringContainsString('aria-controls="sidebar"', $navbar);
        $this->assertStringContainsString('admin-language-slot', $navbar);
        $this->assertStringContainsString("filemtime(public_path('admin/js/off-canvas.js'))", $admin);
        $this->assertStringContainsString("const MOBILE_QUERY = '(max-width: 1199.98px), (pointer: coarse) and (max-width: 1366px)'", $offcanvas);
        $this->assertStringContainsString("toggle.addEventListener('click'", $offcanvas);
        $this->assertStringContainsString("document.body.classList.toggle('admin-sidebar-open'", $offcanvas);
        $this->assertStringNotContainsString('jQuery', $offcanvas);
    }

    public function test_mobile_admin_shell_prevents_page_level_horizontal_overflow(): void
    {
        $admin = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('max-width: 100vw !important;', $admin);
        $this->assertStringContainsString('.content-wrapper {', $admin);
        $this->assertStringContainsString('overflow-x: clip;', $admin);
        $this->assertStringContainsString('.content-wrapper > * {', $admin);
    }

    public function test_skip_link_copy_is_bilingual(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Skip to main content', $english['Skip to main content'] ?? null);
        $this->assertSame('تخطَّ إلى المحتوى الرئيسي', $arabic['Skip to main content'] ?? null);
    }
}
