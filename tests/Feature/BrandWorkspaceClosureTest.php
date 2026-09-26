<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandWorkspaceClosureTest extends TestCase
{
    public function test_brand_search_is_bounded_and_escaped(): void
    {
        $component = file_get_contents(app_path('Http/Livewire/Admin/Brand/Index.php'));

        $this->assertStringContainsString('mb_substr(trim((string) $this->search), 0, 100)', $component);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $component);
        $this->assertStringNotContainsString("'%' . trim($this->search) . '%'", $component);
    }

    public function test_brand_workspace_has_accessible_filters_form_controls_and_actions(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/brand/index.blade.php'));

        foreach ([
            'brandSearch',
            'brandVisibility',
            'brandUsage',
            'brandPerPage',
            'brandName',
            'brandSlug',
            'brandStatusCheck',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        foreach (['brandName', 'brandSlug'] as $requiredControlId) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($requiredControlId, '/') . '"[^>]*aria-required="true"/',
                $view
            );
        }

        $this->assertStringContainsString("aria-label=\"{{ __('Edit brand :name'", $view);
        $this->assertStringContainsString("aria-label=\"{{ __('Delete brand :name'", $view);
        $this->assertStringContainsString('wire:loading.attr="disabled" wire:target="saveBrand"', $view);
        $this->assertStringContainsString("{{ __('Saving...') }}", $view);
    }
}
