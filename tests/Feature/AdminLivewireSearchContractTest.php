<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLivewireSearchContractTest extends TestCase
{
    public function test_catalog_livewire_search_controls_match_bounded_query_contracts(): void
    {
        $controls = [
            'views/livewire/admin/brand/index.blade.php' => 'brandSearch',
            'views/livewire/admin/attribute/index.blade.php' => 'attributeSearch',
            'views/livewire/admin/attribute/values.blade.php' => 'attributeValueSearch',
            'views/livewire/admin/product/index.blade.php' => 'catalogSearch',
        ];

        foreach ($controls as $view => $id) {
            $source = file_get_contents(resource_path($view));

            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($id, '/') . '"[^>]*maxlength="100"/s',
                $source,
                $view . ' must expose the same 100-character bound used by its Livewire query.'
            );
        }

        foreach ([
            'Brand/Index.php',
            'Attribute/Index.php',
            'Attribute/Values.php',
            'Product/Index.php',
        ] as $component) {
            $source = file_get_contents(app_path('Http/Livewire/Admin/' . $component));

            $this->assertMatchesRegularExpression('/mb_substr\([^;]+,\s*0,\s*100\)/s', $source);
            $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $source);
        }
    }
}
