<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandingWorkspaceV2Test extends TestCase
{
    public function test_branding_summary_uses_shared_stat_cards(): void
    {
        $source = file_get_contents(resource_path('views/admin/settings/branding.blade.php'));

        $this->assertSame(3, substr_count($source, '<x-admin.stat-card'));
        $this->assertStringNotContainsString('admin-card admin-stat-card h-100', $source);
    }

    public function test_homepage_cms_is_split_into_focused_collapsible_sections(): void
    {
        $source = file_get_contents(resource_path('views/admin/settings/branding.blade.php'));

        $this->assertStringContainsString("__('Visibility & order')", $source);
        $this->assertStringContainsString("__('Hero content')", $source);
        $this->assertStringContainsString("__('Merchandising sections')", $source);
        $this->assertStringContainsString("__('Manual featured products')", $source);
        $this->assertStringContainsString("__('Trust & legacy content')", $source);
        $this->assertGreaterThanOrEqual(5, substr_count($source, 'branding-home-section'));
    }

    public function test_promo_and_trust_editors_are_collapsed_instead_of_fully_open_cards(): void
    {
        $source = file_get_contents(resource_path('views/admin/settings/branding.blade.php'));

        $this->assertStringContainsString('<details class="admin-promo-card border rounded-4', $source);
        $this->assertStringContainsString("__('Untitled banner')", $source);
        $this->assertStringContainsString("__('Untitled trust block')", $source);
        $this->assertStringContainsString("__('Trust block')", $source);
    }

    public function test_arabic_branding_cleanup_labels_are_available(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('الظهور والترتيب', $translations['Visibility & order'] ?? null);
        $this->assertSame('محتوى الهيرو', $translations['Hero content'] ?? null);
        $this->assertSame('أقسام العرض والمنتجات', $translations['Merchandising sections'] ?? null);
        $this->assertSame('محتوى الثقة والمحتوى القديم', $translations['Trust & legacy content'] ?? null);
        $this->assertSame('بانر بدون عنوان', $translations['Untitled banner'] ?? null);
        $this->assertSame('عنصر ثقة بدون عنوان', $translations['Untitled trust block'] ?? null);
    }
}
