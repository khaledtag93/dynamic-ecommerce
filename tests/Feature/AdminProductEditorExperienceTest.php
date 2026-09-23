<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Product\ProductForm;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductEditorExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_editor_renders_publish_readiness_and_retail_identifiers(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);

        $this->actingAs($owner)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('productPublishReadiness', false)
            ->assertSee('data-readiness-key="identifier"', false)
            ->assertSee('wire:model.defer="sku"', false)
            ->assertSee('wire:model.defer="barcode"', false);
    }

    public function test_simple_product_sku_is_saved_from_livewire_editor(): void
    {
        $category = Category::create([
            'name' => 'Retail Test',
            'slug' => 'retail-test',
            'description' => 'Retail test category',
            'meta_title' => 'Retail Test',
            'meta_keyword' => 'retail,test',
            'meta_description' => 'Retail test category',
            'status' => false,
        ]);

        Livewire::test(ProductForm::class)
            ->set('name', 'Retail SKU Product')
            ->set('sku', 'SKU-RETAIL-001')
            ->set('barcode', '6221234567890')
            ->set('category_id', $category->id)
            ->set('base_price', '199.90')
            ->set('quantity', 3)
            ->set('stock_status', 'in_stock')
            ->set('status', 0)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Retail SKU Product',
            'sku' => 'SKU-RETAIL-001',
            'barcode' => '6221234567890',
            'category_id' => $category->id,
        ]);
    }
}
