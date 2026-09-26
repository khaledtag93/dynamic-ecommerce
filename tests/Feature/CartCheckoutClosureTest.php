<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_product_cannot_be_added_to_cart_through_direct_route(): void
    {
        $category = $this->createCategory('Hidden Cart', 'hidden-cart');

        $product = Product::create([
            'name' => 'Hidden Product',
            'slug' => 'hidden-product',
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 5,
            'stock_status' => 'in_stock',
            'status' => 0,
            'has_variants' => false,
        ]);

        $this->post(route('cart.store', $product), ['quantity' => 1])
            ->assertSessionHasErrors(['cart']);

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $product->id,
        ]);
    }

    public function test_disabled_variant_cannot_be_added_to_cart_through_direct_route(): void
    {
        $category = $this->createCategory('Variant Cart', 'variant-cart');

        $product = Product::create([
            'name' => 'Variant Product',
            'slug' => 'variant-product',
            'category_id' => $category->id,
            'base_price' => 120,
            'quantity' => 0,
            'stock_status' => 'in_stock',
            'status' => 1,
            'has_variants' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'DISABLED-VAR-001',
            'price' => 120,
            'stock' => 5,
            'status' => false,
            'is_default' => true,
        ]);

        $this->post(route('cart.store', $product), [
            'quantity' => 1,
            'variant_id' => $variant->id,
        ])->assertSessionHasErrors(['cart']);

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
        ]);
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
