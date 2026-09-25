<?php

namespace Tests\Feature;

use Tests\TestCase;

class CategoryWorkspaceV2Test extends TestCase
{
    public function test_categories_index_uses_shared_admin_v2_primitives(): void
    {
        $source = file_get_contents(resource_path('views/admin/category/index.blade.php'));

        $this->assertStringContainsString('<x-admin.page-header', $source);
        $this->assertStringContainsString('<x-admin.stat-card', $source);
        $this->assertStringContainsString('data-live-list', $source);
        $this->assertStringContainsString('data-live-filter', $source);
        $this->assertStringNotContainsString('admin-card admin-stat-card h-100', $source);
    }

    public function test_category_editor_is_split_into_focused_tabs(): void
    {
        $source = file_get_contents(resource_path('views/admin/category/_form.blade.php'));

        $this->assertStringContainsString('data-admin-section-tabs="category-editor"', $source);
        $this->assertStringContainsString('<x-admin.section-tabs id="category-editor"', $source);
        $this->assertStringContainsString('data-admin-section-panel="basic"', $source);
        $this->assertStringContainsString('data-admin-section-panel="translations"', $source);
        $this->assertStringContainsString('data-admin-section-panel="seo"', $source);
        $this->assertStringContainsString('data-admin-section-panel="media"', $source);
    }

    public function test_arabic_translation_editor_is_rtl_and_slug_stays_ltr(): void
    {
        $source = file_get_contents(resource_path('views/admin/category/_form.blade.php'));

        $this->assertStringContainsString("$translationDirection = $locale === 'ar' ? 'rtl' : 'ltr'", $source);
        $this->assertStringContainsString('dir="{{ $translationDirection }}" lang="{{ $locale }}"', $source);
        $this->assertStringContainsString('category-translation-pane', $source);
        $this->assertStringContainsString('dir="ltr"', $source);
    }

    public function test_category_image_preview_has_no_external_placeholder_dependency(): void
    {
        $source = file_get_contents(resource_path('views/admin/category/_form.blade.php'));

        $this->assertStringNotContainsString('via.placeholder.com', $source);
        $this->assertStringContainsString('categoryImageEmpty', $source);
        $this->assertStringContainsString("__('No category image selected yet.')", $source);
    }

    public function test_category_results_use_valid_str_namespace_and_rtl_action_alignment(): void
    {
        $source = file_get_contents(resource_path('views/admin/category/_results.blade.php'));

        $this->assertStringContainsString('\\Illuminate\\Support\\Str::limit', $source);
        $this->assertStringNotContainsString('IlluminateSupportStr::limit', $source);
        $this->assertStringContainsString('rtl-text-start', $source);
        $this->assertStringContainsString('rtl-justify-start', $source);
    }

    public function test_arabic_category_workspace_copy_is_complete_for_new_sections(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('الترجمات', $translations['Translations'] ?? null);
        $this->assertSame('الوسائط والظهور', $translations['Media & visibility'] ?? null);
        $this->assertSame('الإنجليزية', $translations['English'] ?? null);
        $this->assertSame('لم يتم اختيار صورة للتصنيف بعد.', $translations['No category image selected yet.'] ?? null);
        $this->assertSame('جاهز للحفظ؟', $translations['Ready to save?'] ?? null);
    }
}
