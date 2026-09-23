<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Product\Index;
use App\Http\Livewire\Admin\Product\ProductForm;
use App\Models\Product;
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
        $category = $this->createCategory('Retail Test', 'retail-test');

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

    public function test_publish_readiness_is_advisory_and_does_not_change_current_activation_rules(): void
    {
        $category = $this->createCategory('Advisory Readiness', 'advisory-readiness');

        Livewire::test(ProductForm::class)
            ->set('name', 'Active Product Under Review')
            ->set('category_id', $category->id)
            ->set('base_price', '99.90')
            ->set('quantity', 1)
            ->set('stock_status', 'in_stock')
            ->set('status', 1)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Active Product Under Review',
            'status' => 1,
        ]);
    }

    public function test_catalog_bulk_visibility_and_featured_actions_update_selected_products(): void
    {
        $category = $this->createCategory('Catalog Actions', 'catalog-actions');

        $first = Product::create([
            'name' => 'Catalog First',
            'slug' => 'catalog-first',
            'category_id' => $category->id,
            'base_price' => 20,
            'quantity' => 4,
            'stock_status' => 'in_stock',
            'status' => 0,
            'is_featured' => 0,
        ]);

        $second = Product::create([
            'name' => 'Catalog Second',
            'slug' => 'catalog-second',
            'category_id' => $category->id,
            'base_price' => 30,
            'quantity' => 5,
            'stock_status' => 'in_stock',
            'status' => 0,
            'is_featured' => 0,
        ]);

        Livewire::test(Index::class)
            ->set('selectedProducts', [(string) $first->id, (string) $second->id])
            ->call('bulkSetStatus', true)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['id' => $first->id, 'status' => 1]);
        $this->assertDatabaseHas('products', ['id' => $second->id, 'status' => 1]);

        Livewire::test(Index::class)
            ->set('selectedProducts', [(string) $first->id, (string) $second->id])
            ->call('bulkSetFeatured', true)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['id' => $first->id, 'is_featured' => 1]);
        $this->assertDatabaseHas('products', ['id' => $second->id, 'is_featured' => 1]);
    }

    public function test_bulk_delete_requires_explicit_confirmation_state(): void
    {
        $category = $this->createCategory('Delete Safety', 'delete-safety');

        $product = Product::create([
            'name' => 'Delete Me Carefully',
            'slug' => 'delete-me-carefully',
            'category_id' => $category->id,
            'base_price' => 15,
            'quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 0,
            'is_featured' => 0,
        ]);

        $component = Livewire::test(Index::class)
            ->set('selectedProducts', [(string) $product->id])
            ->call('confirmBulkDelete');

        $component->assertHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        Livewire::test(Index::class)
            ->set('selectedProducts', [(string) $product->id])
            ->call('requestBulkDelete')
            ->assertSet('pendingBulkDeleteCount', 1)
            ->call('confirmBulkDelete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    private function createCategory(string $name, string $slug): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' category',
            'meta_title' => $name,
            'meta_keyword' => str_replace(' ', ',', strtolower($name)),
            'meta_description' => $name . ' category',
            'status' => false,
        ]);
    }
}
