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
            ->assertSet('saveErrorMessage', '');

        $this->assertDatabaseHas('products', [
            'name' => 'Retail SKU Product',
            'sku' => 'SKU-RETAIL-001',
            'barcode' => '6221234567890',
            'category_id' => $category->id,
        ]);
    }

    public function test_variant_barcode_is_saved_from_livewire_editor(): void
    {
        $category = $this->createCategory('Variant Barcode', 'variant-barcode');

        Livewire::test(ProductForm::class)
            ->set('name', 'Variant Barcode Product')
            ->set('category_id', $category->id)
            ->set('hasVariants', true)
            ->set('status', 0)
            ->set('is_featured', 0)
            ->set('variants', [[
                'id' => null,
                'sku' => 'VAR-BAR-001',
                'barcode' => '6221234567001',
                'price' => 125,
                'sale_price' => '',
                'stock' => 4,
                'is_default' => true,
                'status' => true,
                'attributes' => [],
            ]])
            ->call('save')
            ->assertSet('saveErrorMessage', '')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'VAR-BAR-001',
            'barcode' => '6221234567001',
            'stock' => 4,
        ]);
    }

    public function test_product_barcode_cannot_duplicate_another_product_barcode(): void
    {
        $category = $this->createCategory('Barcode Collision', 'barcode-collision');

        Product::create([
            'name' => 'Existing Barcode Product',
            'slug' => 'existing-barcode-product',
            'barcode' => '6221234567002',
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 2,
            'stock_status' => 'in_stock',
            'status' => 1,
        ]);

        Livewire::test(ProductForm::class)
            ->set('name', 'Conflicting Barcode Product')
            ->set('barcode', '6221234567002')
            ->set('category_id', $category->id)
            ->set('base_price', 110)
            ->set('quantity', 2)
            ->set('stock_status', 'in_stock')
            ->set('status', 0)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasErrors(['barcode']);

        $this->assertDatabaseMissing('products', [
            'name' => 'Conflicting Barcode Product',
        ]);
    }

    public function test_variant_barcode_cannot_duplicate_product_barcode(): void
    {
        $category = $this->createCategory('Variant Collision', 'variant-collision');

        Product::create([
            'name' => 'Barcode Owner Product',
            'slug' => 'barcode-owner-product',
            'barcode' => '6221234567003',
            'category_id' => $category->id,
            'base_price' => 90,
            'quantity' => 2,
            'stock_status' => 'in_stock',
            'status' => 1,
        ]);

        Livewire::test(ProductForm::class)
            ->set('name', 'Variant Collision Product')
            ->set('category_id', $category->id)
            ->set('hasVariants', true)
            ->set('status', 0)
            ->set('is_featured', 0)
            ->set('variants', [[
                'id' => null,
                'sku' => 'VAR-CONFLICT-001',
                'barcode' => '6221234567003',
                'price' => 130,
                'sale_price' => '',
                'stock' => 3,
                'is_default' => true,
                'status' => true,
                'attributes' => [],
            ]])
            ->call('save')
            ->assertHasErrors(['variants.0.barcode']);

        $this->assertDatabaseMissing('products', [
            'name' => 'Variant Collision Product',
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
            ->assertSet('saveErrorMessage', '');

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

    public function test_bulk_activation_keeps_current_visibility_rules_and_warns_for_incomplete_content(): void
    {
        $category = $this->createCategory('Bulk Visibility', 'bulk-visibility');

        $first = Product::create([
            'name' => 'Incomplete One',
            'slug' => 'incomplete-one',
            'category_id' => $category->id,
            'base_price' => 10,
            'quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 0,
        ]);

        $second = Product::create([
            'name' => 'Incomplete Two',
            'slug' => 'incomplete-two',
            'category_id' => $category->id,
            'base_price' => 20,
            'quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 0,
        ]);

        Livewire::test(Index::class)
            ->set('selectedProducts', [$first->id, $second->id])
            ->call('bulkSetStatus', true)
            ->assertSet('bulkFeedbackType', 'warning')
            ->assertSet('bulkFeedbackMessage', '2 selected product(s) activated; 2 still need content review.');

        $this->assertTrue((bool) $first->fresh()->status);
        $this->assertTrue((bool) $second->fresh()->status);
    }

    public function test_bulk_hide_only_updates_selected_products(): void
    {
        $category = $this->createCategory('Bulk Hide', 'bulk-hide');

        $selected = Product::create([
            'name' => 'Selected Active',
            'slug' => 'selected-active',
            'category_id' => $category->id,
            'base_price' => 10,
            'quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 1,
        ]);

        $untouched = Product::create([
            'name' => 'Untouched Active',
            'slug' => 'untouched-active',
            'category_id' => $category->id,
            'base_price' => 20,
            'quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 1,
        ]);

        Livewire::test(Index::class)
            ->set('selectedProducts', [$selected->id])
            ->call('bulkSetStatus', false)
            ->assertSet('bulkFeedbackType', 'message')
            ->assertSet('bulkFeedbackMessage', '1 selected product(s) hidden.');

        $this->assertFalse((bool) $selected->fresh()->status);
        $this->assertTrue((bool) $untouched->fresh()->status);
    }

    public function test_catalog_operational_views_apply_expected_work_queues(): void
    {
        $category = $this->createCategory('Operational Views', 'operational-views');

        Product::create([
            'name' => 'Needs Content Queue',
            'slug' => 'needs-content-queue',
            'category_id' => $category->id,
            'base_price' => 10,
            'quantity' => 2,
            'low_stock_threshold' => 3,
            'stock_status' => 'in_stock',
            'status' => 0,
            'is_featured' => 0,
        ]);

        Product::create([
            'name' => 'Featured Storefront Product',
            'slug' => 'featured-storefront-product',
            'category_id' => $category->id,
            'description' => 'Complete catalog content',
            'sku' => 'OPS-001',
            'base_price' => 20,
            'quantity' => 5,
            'stock_status' => 'in_stock',
            'status' => 1,
            'is_featured' => 1,
        ]);

        Livewire::test(Index::class)
            ->call('applySavedView', 'attention')
            ->assertSet('savedView', 'attention')
            ->assertSet('readinessFilter', 'needs_attention')
            ->call('applySavedView', 'inventory')
            ->assertSet('savedView', 'inventory')
            ->assertSet('stockFilter', 'low')
            ->call('applySavedView', 'storefront')
            ->assertSet('savedView', 'storefront')
            ->assertSet('statusFilter', '1')
            ->call('applySavedView', 'featured')
            ->assertSet('savedView', 'featured')
            ->assertSet('featuredFilter', '1');
    }

    public function test_reset_filters_exits_operational_view(): void
    {
        Livewire::test(Index::class)
            ->call('applySavedView', 'featured')
            ->assertSet('savedView', 'featured')
            ->call('resetFilters')
            ->assertSet('savedView', '')
            ->assertSet('featuredFilter', '')
            ->assertSet('perPage', 10);
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
