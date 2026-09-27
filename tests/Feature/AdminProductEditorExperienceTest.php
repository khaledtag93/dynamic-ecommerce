<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Product\Index;
use App\Http\Livewire\Admin\Product\ProductForm;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Services\Admin\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductEditorExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_editor_renders_publish_readiness_and_retail_identifiers(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('productPublishReadiness', false)
            ->assertSee('data-readiness-key="identifier"', false)
            ->assertSee('wire:model="sku"', false)
            ->assertSee('wire:model="barcode"', false);
    }

    public function test_product_editor_protects_unsaved_navigation_and_restores_dirty_state_after_failed_save(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/product/product-form.blade.php'));

        $this->assertStringContainsString('data-product-editor-exit', $view);
        $this->assertStringContainsString('function initProductUnsavedChangesGuard()', $view);
        $this->assertStringContainsString("root.dataset.dirty = '1';", $view);
        $this->assertStringContainsString('window.adminConfirmAction(leave', $view);
        $this->assertStringContainsString("window.addEventListener('beforeunload'", $view);
        $this->assertStringContainsString('Discard unsaved product changes?', $view);
        $this->assertStringNotContainsString('window.confirm(', $view);
        $this->assertStringNotContainsString('confirm(', $view);
    }

    public function test_product_editor_core_fields_have_explicit_accessible_labels(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/product/product-form.blade.php'));

        foreach ([
            'productName',
            'productSlug',
            'productSku',
            'productBarcode',
            'productVideoUrl',
            'productCategory',
            'productBrand',
            'productDescription',
            'productBasePrice',
            'productSalePrice',
            'productQuantity',
            'productLowStockThreshold',
            'productStockStatus',
            'productStorefrontStatus',
            'productFeatured',
            'productMetaTitle',
            'productMetaDescription',
            'productImagesInput',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        foreach ([
            'productName',
            'productCategory',
            'productBasePrice',
            'productQuantity',
            'productStockStatus',
            'productStorefrontStatus',
            'productFeatured',
        ] as $requiredControlId) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($requiredControlId, '/') . '"[^>]*aria-required="true"/',
                $view
            );
        }
    }

    public function test_product_editor_tracks_non_field_draft_mutations_as_unsaved(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/product/product-form.blade.php'));

        foreach ([
            'addVariant',
            'duplicateVariant',
            'removeVariant',
            'setDefaultVariant',
            'applyBulkToVariants',
            'addAovRelation',
            'removeAovRelation',
            'toggleAovRelationActive',
            'removeNewImage',
        ] as $method) {
            $this->assertStringContainsString("'" . $method . "'", $view);
        }

        $this->assertStringContainsString("action.startsWith(method + '(')", $view);
        $this->assertStringContainsString('markProductFormDirty();', $view);
    }

    public function test_product_crud_uses_livewire_without_legacy_noop_mutation_routes(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ProductController.php'));

        $this->assertStringContainsString("Route::get('/products', 'index')->name('products.index');", $routes);
        $this->assertStringContainsString("Route::get('/products/create', 'create')->name('products.create');", $routes);
        $this->assertStringContainsString("Route::get('/products/{product}/edit', 'edit')->name('products.edit');", $routes);

        $this->assertStringNotContainsString("Route::post('/products', 'store')->name('products.store');", $routes);
        $this->assertStringNotContainsString("Route::put('/products/{product}', 'update')->name('products.update');", $routes);
        $this->assertStringNotContainsString("Route::delete('/products/{product}', 'destroy')->name('products.destroy');", $routes);

        $this->assertStringNotContainsString('function store(', $controller);
        $this->assertStringNotContainsString('function update(', $controller);
        $this->assertStringNotContainsString('function destroy(', $controller);
        $this->assertStringNotContainsString('handled elsewhere currently', $controller);
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

    public function test_variant_products_are_included_in_low_stock_catalog_filter_and_health(): void
    {
        $category = $this->createCategory('Variant Low Stock', 'variant-low-stock');
        $product = Product::create([
            'name' => 'Variant Low Stock Product',
            'slug' => 'variant-low-stock-product',
            'category_id' => $category->id,
            'base_price' => 25,
            'quantity' => 0,
            'has_variants' => true,
            'status' => 1,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'LOW-VAR-001',
            'price' => 25,
            'stock' => 2,
            'reorder_point' => 3,
            'status' => true,
        ]);

        $component = Livewire::test(Index::class)
            ->set('stockFilter', 'low')
            ->assertSee('Variant Low Stock Product')
            ->assertSee('LOW-VAR-001');

        $this->assertSame(1, $component->instance()->catalogHealth['low_stock']);
    }

    public function test_product_sku_cannot_duplicate_another_product_sku(): void
    {
        $category = $this->createCategory('SKU Collision', 'sku-collision');

        Product::create([
            'name' => 'Existing SKU Product',
            'slug' => 'existing-sku-product',
            'sku' => 'SKU-CONFLICT-001',
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 2,
            'stock_status' => 'in_stock',
            'status' => 1,
        ]);

        Livewire::test(ProductForm::class)
            ->set('name', 'Conflicting SKU Product')
            ->set('sku', 'SKU-CONFLICT-001')
            ->set('category_id', $category->id)
            ->set('base_price', 110)
            ->set('quantity', 2)
            ->set('stock_status', 'in_stock')
            ->set('status', 0)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasErrors(['sku']);

        $this->assertDatabaseMissing('products', [
            'name' => 'Conflicting SKU Product',
        ]);
    }

    public function test_variant_sku_cannot_duplicate_product_sku(): void
    {
        $category = $this->createCategory('Variant SKU Collision', 'variant-sku-collision');

        Product::create([
            'name' => 'SKU Owner Product',
            'slug' => 'sku-owner-product',
            'sku' => 'SKU-OWNER-001',
            'category_id' => $category->id,
            'base_price' => 90,
            'quantity' => 2,
            'stock_status' => 'in_stock',
            'status' => 1,
        ]);

        Livewire::test(ProductForm::class)
            ->set('name', 'Variant SKU Collision Product')
            ->set('category_id', $category->id)
            ->set('hasVariants', true)
            ->set('status', 0)
            ->set('is_featured', 0)
            ->set('variants', [[
                'id' => null,
                'sku' => 'SKU-OWNER-001',
                'barcode' => '6229876543210',
                'price' => 130,
                'sale_price' => '',
                'stock' => 3,
                'is_default' => true,
                'status' => true,
                'attributes' => [],
            ]])
            ->call('save')
            ->assertHasErrors(['variants.0.sku']);

        $this->assertDatabaseMissing('products', [
            'name' => 'Variant SKU Collision Product',
        ]);
    }

    public function test_catalog_search_finds_parent_product_by_variant_identifier(): void
    {
        $category = $this->createCategory('Variant Search', 'variant-search');

        $product = Product::create([
            'name' => 'Lookup Parent Product',
            'slug' => 'lookup-parent-product',
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 0,
            'stock_status' => 'in_stock',
            'status' => 1,
            'has_variants' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'POS-VAR-001',
            'barcode' => '6227777777777',
            'price' => 100,
            'stock' => 2,
            'is_default' => true,
            'status' => true,
        ]);

        Livewire::test(Index::class)
            ->set('search', 'POS-VAR-001')
            ->assertSee('Lookup Parent Product')
            ->set('search', '6227777777777')
            ->assertSee('Lookup Parent Product');
    }

    public function test_product_duplication_clears_retail_identifiers(): void
    {
        $category = $this->createCategory('Safe Copy', 'safe-copy');

        $product = Product::create([
            'name' => 'Copy Source Product',
            'slug' => 'copy-source-product',
            'sku' => 'COPY-SKU-001',
            'barcode' => '6228888888888',
            'category_id' => $category->id,
            'base_price' => 120,
            'quantity' => 5,
            'stock_status' => 'in_stock',
            'status' => 1,
        ]);

        $copy = app(ProductService::class)->duplicateProduct($product);

        $this->assertNotSame($product->id, $copy->id);
        $this->assertNull($copy->sku);
        $this->assertNull($copy->barcode);
        $this->assertSame(0, (int) $copy->quantity);
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
