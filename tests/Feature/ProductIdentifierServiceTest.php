<?php

namespace Tests\Feature;

use App\Exceptions\ProductIdentifierAmbiguityException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\ProductIdentifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductIdentifierServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_product_barcode_resolves_without_guessing(): void
    {
        $category = $this->createCategory();
        $product = Product::query()->create([
            'name' => 'Scanner Product',
            'slug' => 'scanner-product-' . Str::lower(Str::random(6)),
            'barcode' => '6221111111111',
            'category_id' => $category->id,
            'base_price' => 75,
            'quantity' => 5,
            'stock_status' => 'in_stock',
            'status' => true,
            'has_variants' => false,
        ]);

        $match = app(ProductIdentifierService::class)->resolveBarcode(' 6221111111111 ');

        $this->assertNotNull($match);
        $this->assertTrue($product->is($match['product']));
        $this->assertNull($match['variant']);
        $this->assertSame('product', $match['match_type']);
        $this->assertFalse($match['requires_variant_selection']);
    }

    public function test_exact_variant_barcode_returns_variant_and_parent_product(): void
    {
        $category = $this->createCategory();
        $product = Product::query()->create([
            'name' => 'Variant Scanner Product',
            'slug' => 'variant-scanner-product-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 0,
            'stock_status' => 'in_stock',
            'status' => true,
            'has_variants' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SCAN-VAR-001',
            'barcode' => '6222222222222',
            'price' => 110,
            'stock' => 7,
            'is_default' => true,
            'status' => true,
        ]);

        $match = app(ProductIdentifierService::class)->resolveBarcode('6222222222222');

        $this->assertNotNull($match);
        $this->assertTrue($product->is($match['product']));
        $this->assertTrue($variant->is($match['variant']));
        $this->assertSame('variant', $match['match_type']);
        $this->assertFalse($match['requires_variant_selection']);
    }

    public function test_parent_barcode_for_variant_product_requires_variant_selection(): void
    {
        $category = $this->createCategory();
        Product::query()->create([
            'name' => 'Parent Barcode Product',
            'slug' => 'parent-barcode-product-' . Str::lower(Str::random(6)),
            'barcode' => '6223333333333',
            'category_id' => $category->id,
            'base_price' => 120,
            'quantity' => 0,
            'stock_status' => 'in_stock',
            'status' => true,
            'has_variants' => true,
        ]);

        $match = app(ProductIdentifierService::class)->resolveBarcode('6223333333333');

        $this->assertNotNull($match);
        $this->assertSame('product', $match['match_type']);
        $this->assertTrue($match['requires_variant_selection']);
    }

    public function test_exact_product_sku_resolves_without_guessing(): void
    {
        $category = $this->createCategory();
        $product = Product::query()->create([
            'name' => 'SKU Lookup Product',
            'slug' => 'sku-lookup-product-' . Str::lower(Str::random(6)),
            'sku' => 'SKU-LOOKUP-001',
            'category_id' => $category->id,
            'base_price' => 80,
            'quantity' => 4,
            'stock_status' => 'in_stock',
            'status' => true,
            'has_variants' => false,
        ]);

        $match = app(ProductIdentifierService::class)->resolveSku(' SKU-LOOKUP-001 ');

        $this->assertNotNull($match);
        $this->assertTrue($product->is($match['product']));
        $this->assertNull($match['variant']);
        $this->assertSame('product', $match['match_type']);
        $this->assertSame('sku', $match['matched_by']);
        $this->assertFalse($match['requires_variant_selection']);
    }

    public function test_exact_variant_sku_returns_variant_and_parent_product(): void
    {
        $category = $this->createCategory();
        $product = Product::query()->create([
            'name' => 'Variant SKU Lookup Product',
            'slug' => 'variant-sku-lookup-product-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 0,
            'stock_status' => 'in_stock',
            'status' => true,
            'has_variants' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-VARIANT-LOOKUP-001',
            'barcode' => '6225555555555',
            'price' => 105,
            'stock' => 6,
            'is_default' => true,
            'status' => true,
        ]);

        $match = app(ProductIdentifierService::class)->resolveSku('SKU-VARIANT-LOOKUP-001');

        $this->assertNotNull($match);
        $this->assertTrue($product->is($match['product']));
        $this->assertTrue($variant->is($match['variant']));
        $this->assertSame('variant', $match['match_type']);
        $this->assertSame('sku', $match['matched_by']);
        $this->assertFalse($match['requires_variant_selection']);
    }

    public function test_unknown_or_blank_barcode_returns_null(): void
    {
        $service = app(ProductIdentifierService::class);

        $this->assertNull($service->resolveBarcode(''));
        $this->assertNull($service->resolveBarcode('6229999999999'));
        $this->assertNull($service->resolveSku(''));
        $this->assertNull($service->resolveSku('SKU-NOT-FOUND'));
    }

    public function test_legacy_cross_table_collision_is_rejected_as_ambiguous(): void
    {
        $category = $this->createCategory();
        $first = Product::query()->create([
            'name' => 'Legacy Barcode Owner',
            'slug' => 'legacy-barcode-owner-' . Str::lower(Str::random(6)),
            'barcode' => '6224444444444',
            'category_id' => $category->id,
            'base_price' => 90,
            'quantity' => 2,
            'stock_status' => 'in_stock',
            'status' => true,
            'has_variants' => false,
        ]);

        $second = Product::query()->create([
            'name' => 'Variant Parent',
            'slug' => 'variant-parent-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 0,
            'stock_status' => 'in_stock',
            'status' => true,
            'has_variants' => true,
        ]);

        ProductVariant::query()->create([
            'product_id' => $second->id,
            'sku' => 'LEGACY-COLLISION',
            'barcode' => $first->barcode,
            'price' => 100,
            'stock' => 3,
            'is_default' => true,
            'status' => true,
        ]);

        $this->expectException(ProductIdentifierAmbiguityException::class);

        app(ProductIdentifierService::class)->resolveBarcode('6224444444444');
    }

    private function createCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Scanner Category',
            'slug' => 'scanner-category-' . Str::lower(Str::random(6)),
            'description' => 'Scanner test category',
            'meta_title' => 'Scanner Category',
            'meta_keyword' => 'scanner',
            'meta_description' => 'Scanner test category',
            'status' => false,
        ]);
    }
}
