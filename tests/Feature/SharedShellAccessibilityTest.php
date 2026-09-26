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

    public function test_skip_link_copy_is_bilingual(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Skip to main content', $english['Skip to main content'] ?? null);
        $this->assertSame('تخطَّ إلى المحتوى الرئيسي', $arabic['Skip to main content'] ?? null);
    }
}
