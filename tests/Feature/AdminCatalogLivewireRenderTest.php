<?php

namespace Tests\Feature;

use App\Models\ProductAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogLivewireRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_and_attribute_livewire_pages_render_for_super_admin(): void
    {
        $admin = $this->createSuperAdmin();
        $attribute = ProductAttribute::create([
            'name' => 'Color',
            'type' => 'select',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->assertSee('Brands');

        $this->actingAs($admin)
            ->get(route('admin.attributes.index'))
            ->assertOk()
            ->assertSee('Product Attributes');

        $this->actingAs($admin)
            ->get(route('admin.attributes.values', $attribute->id))
            ->assertOk()
            ->assertSee('Color');
    }
}
