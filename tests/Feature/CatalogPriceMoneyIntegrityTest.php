<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Product\Index as ProductIndex;
use App\Http\Livewire\Admin\Product\ProductForm;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogPriceMoneyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_product_editor_rejects_price_over_precision(): void
    {
        $admin = $this->createSuperAdmin();
        $category = $this->category('Simple Precision', 'simple-precision');

        $this->actingAs($admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'Over Precision Simple Product')
            ->set('category_id', $category->id)
            ->set('base_price', '10.001')
            ->set('sale_price', '9.999')
            ->set('quantity', 1)
            ->set('stock_status', 'in_stock')
            ->set('status', 1)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasErrors(['base_price', 'sale_price']);

        $this->assertDatabaseMissing('products', [
            'name' => 'Over Precision Simple Product',
        ]);
    }

    public function test_catalog_price_accepts_exact_schema_maximum_and_exact_sale_boundary(): void
    {
        $admin = $this->createSuperAdmin();
        $category = $this->category('Price Maximum', 'price-maximum');

        $this->actingAs($admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'Maximum Price Product')
            ->set('category_id', $category->id)
            ->set('base_price', '99999999.99')
            ->set('sale_price', '99999999.99')
            ->set('quantity', 1)
            ->set('stock_status', 'in_stock')
            ->set('status', 1)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saveErrorMessage', '');

        $this->assertDatabaseHas('products', [
            'name' => 'Maximum Price Product',
            'base_price' => '99999999.99',
            'sale_price' => '99999999.99',
        ]);
    }

    public function test_catalog_price_rejects_values_above_decimal_ten_two_range(): void
    {
        $admin = $this->createSuperAdmin();
        $category = $this->category('Price Overflow', 'price-overflow');

        $this->actingAs($admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'Overflow Price Product')
            ->set('category_id', $category->id)
            ->set('base_price', '100000000.00')
            ->set('quantity', 1)
            ->set('stock_status', 'in_stock')
            ->set('status', 1)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasErrors(['base_price']);

        $this->assertDatabaseMissing('products', [
            'name' => 'Overflow Price Product',
        ]);
    }

    public function test_variant_editor_rejects_price_over_precision(): void
    {
        $admin = $this->createSuperAdmin();
        $category = $this->category('Variant Precision', 'variant-precision');

        $this->actingAs($admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'Over Precision Variant Product')
            ->set('category_id', $category->id)
            ->set('hasVariants', true)
            ->set('status', 1)
            ->set('is_featured', 0)
            ->set('variants', [[
                'id' => null,
                'sku' => 'VAR-PRECISION-001',
                'barcode' => '',
                'price' => '10.001',
                'sale_price' => '9.999',
                'stock' => 1,
                'is_default' => true,
                'status' => true,
                'attributes' => [],
            ]])
            ->call('save')
            ->assertHasErrors([
                'variants.0.price',
                'variants.0.sale_price',
            ]);

        $this->assertDatabaseMissing('products', [
            'name' => 'Over Precision Variant Product',
        ]);
    }

    public function test_variant_bulk_price_rejects_over_precision_before_mutating_rows(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        Livewire::test(ProductForm::class)
            ->set('hasVariants', true)
            ->set('variants', [[
                'id' => null,
                'sku' => 'BULK-001',
                'barcode' => '',
                'price' => '10.00',
                'sale_price' => '9.00',
                'stock' => 1,
                'is_default' => true,
                'status' => true,
                'attributes' => [],
            ]])
            ->set('variantBulk.price', '10.001')
            ->call('applyBulkToVariants')
            ->assertHasErrors(['variantBulk.price'])
            ->assertSet('variants.0.price', '10.00');
    }

    public function test_variant_sale_price_comparison_uses_exact_decimal_values(): void
    {
        $admin = $this->createSuperAdmin();
        $category = $this->category('Variant Boundary', 'variant-boundary');

        $this->actingAs($admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'Exact Variant Boundary Product')
            ->set('category_id', $category->id)
            ->set('hasVariants', true)
            ->set('status', 1)
            ->set('is_featured', 0)
            ->set('variants', [[
                'id' => null,
                'sku' => 'VAR-BOUNDARY-001',
                'barcode' => '',
                'price' => '0.30',
                'sale_price' => '0.30',
                'stock' => 1,
                'is_default' => true,
                'status' => true,
                'attributes' => [],
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'VAR-BOUNDARY-001',
            'price' => '0.30',
            'sale_price' => '0.30',
        ]);
    }

    public function test_inline_catalog_price_edits_reject_over_precision_without_persistence(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Inline Precision Product', 'inline-precision-product', '100.00', '90.00');

        $this->actingAs($admin);

        Livewire::test(ProductIndex::class)
            ->set("inlineBasePrice.{$product->id}", '100.001')
            ->call('saveInlineBasePrice', $product->id)
            ->assertHasErrors(["inlineBasePrice.{$product->id}"]);

        $this->assertSame('100.00', $product->fresh()->base_price);

        Livewire::test(ProductIndex::class)
            ->set("inlineSalePrice.{$product->id}", '89.999')
            ->call('saveInlineSalePrice', $product->id)
            ->assertHasErrors(["inlineSalePrice.{$product->id}"]);

        $this->assertSame('90.00', $product->fresh()->sale_price);
    }

    public function test_inline_sale_and_base_comparison_is_exact_at_same_cent(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Inline Exact Product', 'inline-exact-product', '0.30', '0.20');

        $this->actingAs($admin);

        Livewire::test(ProductIndex::class)
            ->set("inlineSalePrice.{$product->id}", '0.30')
            ->call('saveInlineSalePrice', $product->id)
            ->assertHasNoErrors();

        $this->assertSame('0.30', $product->fresh()->sale_price);

        Livewire::test(ProductIndex::class)
            ->set("inlineBasePrice.{$product->id}", '0.30')
            ->call('saveInlineBasePrice', $product->id)
            ->assertHasNoErrors();

        $this->assertSame('0.30', $product->fresh()->base_price);
    }

    private function category(string $name, string $slug): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
            'meta_title' => $name,
            'meta_keyword' => $slug,
            'meta_description' => $name,
            'status' => false,
        ]);
    }

    private function product(string $name, string $slug, string $basePrice, ?string $salePrice): Product
    {
        $category = $this->category($name.' Category', $slug.'-category');

        return Product::query()->create([
            'name' => $name,
            'slug' => $slug,
            'category_id' => $category->id,
            'base_price' => $basePrice,
            'sale_price' => $salePrice,
            'quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
