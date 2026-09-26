<?php

namespace Tests\Feature;

use Tests\TestCase;

class AttributeWorkspaceClosureTest extends TestCase
{
    public function test_attribute_searches_are_bounded_and_escaped(): void
    {
        $index = file_get_contents(app_path('Http/Livewire/Admin/Attribute/Index.php'));
        $values = file_get_contents(app_path('Http/Livewire/Admin/Attribute/Values.php'));

        $this->assertStringContainsString('mb_substr(trim((string) $this->search), 0, 100)', $index);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $index);

        $this->assertStringContainsString('mb_substr(trim((string) $this->search), 0, 100)', $values);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $values);
    }

    public function test_attribute_values_are_paginated_without_per_row_usage_queries(): void
    {
        $component = file_get_contents(app_path('Http/Livewire/Admin/Attribute/Values.php'));

        $this->assertStringContainsString('use WithPagination;', $component);
        $this->assertStringContainsString('->paginate($this->perPage)', $component);
        $this->assertStringContainsString('selectSub(function ($query)', $component);
        $this->assertStringContainsString('->whereExists(function ($query)', $component);

        $renderStart = strpos($component, 'public function render()');
        $render = $renderStart === false ? '' : substr($component, $renderStart);

        $this->assertStringNotContainsString('->get()->map(', $render);
        $this->assertStringNotContainsString('$this->variantUsageCount($value)', $render);
    }

    public function test_attribute_workspaces_expose_accessible_controls_and_pagination(): void
    {
        $index = file_get_contents(resource_path('views/livewire/admin/attribute/index.blade.php'));
        $values = file_get_contents(resource_path('views/livewire/admin/attribute/values.blade.php'));

        foreach (['attributeSearch', 'attributeCoverage', 'attributePerPage', 'attributeName'] as $controlId) {
            $this->assertStringContainsString('id="' . $controlId . '"', $index);
            $this->assertStringContainsString('for="' . $controlId . '"', $index);
        }

        foreach (['attributeValueSearch', 'attributeValuePerPage', 'attributeValueInput'] as $controlId) {
            $this->assertStringContainsString('id="' . $controlId . '"', $values);
            $this->assertStringContainsString('for="' . $controlId . '"', $values);
        }

        $this->assertStringContainsString('$values->links()', $values);
        $this->assertStringContainsString("aria-label=\"{{ __('Edit attribute :name'", $index);
        $this->assertStringContainsString("aria-label=\"{{ __('Manage values for :name'", $index);
        $this->assertStringContainsString("aria-label=\"{{ __('Edit value :value'", $values);
        $this->assertStringContainsString("aria-label=\"{{ __('Delete value :value'", $values);
    }
}
