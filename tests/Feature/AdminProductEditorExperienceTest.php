<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Product\ProductForm;
use App\Models\Category;
use App\Models\Product;
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
    public function test_active_simple_product_cannot_publish_without_storefront_readiness(): void
    {
        $category = Category::create([
            'name' => 'Publish Readiness',
            'slug' => 'publish-readiness',
            'description' => 'Publish readiness test category',
            'status' => false,
        ]);

        Livewire::test(ProductForm::class)
            ->set('name', 'Incomplete Active Product')
            ->set('category_id', $category->id)
            ->set('base_price', '99.90')
            ->set('quantity', 1)
            ->set('stock_status', 'in_stock')
            ->set('status', 1)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasErrors(['description', 'newImages', 'sku']);

        $this->assertDatabaseMissing('products', [
            'name' => 'Incomplete Active Product',
        ]);
    }

    public function test_inactive_product_can_be_saved_as_draft_while_storefront_details_are_incomplete(): void
    {
        $category = Category::create([
            'name' => 'Draft Products',
            'slug' => 'draft-products',
            'description' => 'Draft product test category',
            'status' => false,
        ]);

        Livewire::test(ProductForm::class)
            ->set('name', 'Draft Product')
            ->set('category_id', $category->id)
            ->set('base_price', '49.90')
            ->set('quantity', 0)
            ->set('stock_status', 'out_of_stock')
            ->set('status', 0)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Draft Product',
            'status' => 0,
        ]);
    }

    public function test_product_identifiers_must_be_unique(): void
    {
        $category = Category::create([
            'name' => 'Identifiers',
            'slug' => 'identifiers',
            'description' => 'Identifier test category',
            'status' => false,
        ]);

        Product::create([
            'name' => 'Existing Product',
            'slug' => 'existing-product',
            'sku' => 'SKU-UNIQUE-001',
            'barcode' => '6220000000001',
            'category_id' => $category->id,
            'base_price' => 10,
            'quantity' => 1,
            'status' => 0,
        ]);

        Livewire::test(ProductForm::class)
            ->set('name', 'Duplicate Identifier Product')
            ->set('sku', 'SKU-UNIQUE-001')
            ->set('barcode', '6220000000001')
            ->set('category_id', $category->id)
            ->set('base_price', '20.00')
            ->set('quantity', 1)
            ->set('stock_status', 'in_stock')
            ->set('status', 0)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasErrors(['sku', 'barcode']);
    }

}
