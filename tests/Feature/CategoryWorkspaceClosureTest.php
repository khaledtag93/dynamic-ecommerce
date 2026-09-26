<?php

namespace Tests\Feature;

use Tests\TestCase;

class CategoryWorkspaceClosureTest extends TestCase
{
    public function test_category_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/CategoryController.php'));

        $this->assertStringContainsString('mb_substr(trim((string) $search), 0, 100)', $controller);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_category_editor_has_accessible_core_labels_and_unsaved_change_protection(): void
    {
        $form = file_get_contents(resource_path('views/admin/category/_form.blade.php'));
        $create = file_get_contents(resource_path('views/admin/category/create.blade.php'));
        $edit = file_get_contents(resource_path('views/admin/category/edit.blade.php'));

        foreach ([
            'categoryName',
            'categorySlug',
            'categoryDescription',
            'categoryMetaTitle',
            'categoryMetaKeyword',
            'categoryMetaDescription',
            'categoryImageInput',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $form);
            $this->assertStringContainsString('id="' . $controlId . '"', $form);
        }

        foreach ([
            'categoryName',
            'categorySlug',
            'categoryMetaTitle',
            'categoryMetaKeyword',
            'categoryMetaDescription',
        ] as $requiredControlId) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($requiredControlId, '/') . '"[^>]*aria-required="true"/',
                $form
            );
        }

        $this->assertStringContainsString('id="categoryEditorForm"', $create);
        $this->assertStringContainsString('id="categoryEditorForm"', $edit);
        $this->assertStringContainsString('data-initial-dirty="{{ $errors->any() ? \'1\' : \'0\' }}"', $create);
        $this->assertStringContainsString('data-initial-dirty="{{ $errors->any() ? \'1\' : \'0\' }}"', $edit);

        $this->assertStringContainsString('window.adminConfirmAction(leave', $form);
        $this->assertStringContainsString("window.addEventListener('beforeunload'", $form);
        $this->assertStringContainsString('Discard unsaved category changes?', $form);
        $this->assertStringNotContainsString('window.confirm(', $form);
    }
}
