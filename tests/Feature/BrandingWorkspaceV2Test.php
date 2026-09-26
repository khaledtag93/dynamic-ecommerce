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

    public function test_branding_media_can_be_compacted_without_removing_preview_or_upload_controls(): void
    {
        $source = file_get_contents(resource_path('views/admin/settings/branding.blade.php'));

        $this->assertStringContainsString('data-media-density-toggle', $source);
        $this->assertStringContainsString('data-media-editor', $source);
        $this->assertStringContainsString('branding-panel-preview', $source);
        $this->assertStringContainsString('name="logo_file"', $source);
        $this->assertStringContainsString('name="hero_banner_file"', $source);
        $this->assertStringContainsString('data-preview-badge', $source);
        $this->assertStringContainsString('customer-theme-preview__accent', $source);
        $this->assertStringContainsString("set('--preview-accent', 'brand_accent_color'", $source);
        $this->assertStringContainsString('.branding-side-stack>#branding-panel-preview{position:sticky', $source);
        $this->assertStringNotContainsString('max-height:calc(100vh - 2rem);overflow:auto', $source);
        $this->assertStringContainsString('id="save_as_custom_theme"', $source);
        $this->assertStringContainsString('for="save_as_custom_theme"', $source);
        $this->assertStringContainsString('@media(max-width:767.98px){.branding-side-stack{grid-template-columns:1fr}', $source);
        $this->assertStringContainsString("setAttribute('data-applied-preset', key)", $source);
    }

    public function test_theme_gallery_supports_market_discovery(): void
    {
        $source = file_get_contents(resource_path('views/admin/settings/branding.blade.php'));

        $this->assertStringContainsString('id="themeGallerySearch"', $source);
        $this->assertStringContainsString('data-theme-filter="commerce"', $source);
        $this->assertStringContainsString('data-theme-category=', $source);
        $this->assertStringContainsString('id="themeGalleryEmpty"', $source);
        $this->assertStringContainsString('filterThemeGallery()', $source);
    }

    public function test_branding_workspace_translation_catalogs_cover_every_literal_key(): void
    {
        $source = file_get_contents(resource_path('views/admin/settings/branding.blade.php'));
        preg_match_all("/__\\('([^']+)'\\)/", $source, $matches);

        $keys = array_values(array_unique($matches[1] ?? []));
        $english = json_decode(file_get_contents(base_path('lang/en.json')), true, 512, JSON_THROW_ON_ERROR);
        $arabic = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $english, "Missing English branding translation key: {$key}");
            $this->assertArrayHasKey($key, $arabic, "Missing Arabic branding translation key: {$key}");
        }
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
        $this->assertSame('كحلي ملكي', $translations['Royal Navy'] ?? null);
        $this->assertSame('استوديو زمردي', $translations['Emerald Studio'] ?? null);
        $this->assertSame('برقوقي تحريري', $translations['Plum Editorial'] ?? null);
        $this->assertSame('طي الوسائط', $translations['Compact media'] ?? null);
        $this->assertSame('توسيع الوسائط', $translations['Expand media'] ?? null);
    }
}
