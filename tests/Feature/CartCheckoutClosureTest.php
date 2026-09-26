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

    public function test_cart_quantity_update_rejects_item_that_became_out_of_stock(): void
    {
        $category = $this->createCategory('Stock Change', 'stock-change');

        $product = Product::create([
            'name' => 'Stock Change Product',
            'slug' => 'stock-change-product',
            'category_id' => $category->id,
            'base_price' => 80,
            'quantity' => 2,
            'stock_status' => 'in_stock',
            'status' => 1,
            'has_variants' => false,
        ]);

        $this->post(route('cart.store', $product), ['quantity' => 1])
            ->assertSessionHasNoErrors();

        $cartItem = \App\Models\CartItem::query()
            ->where('product_id', $product->id)
            ->firstOrFail();

        $product->update([
            'quantity' => 0,
            'stock_status' => 'out_of_stock',
        ]);

        $this->patch(route('cart.update', $cartItem), ['quantity' => 2])
            ->assertSessionHasErrors(['cart']);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 1,
        ]);
    }

    public function test_checkout_core_fields_have_explicit_accessible_labels(): void
    {
        $view = file_get_contents(resource_path('views/frontend/checkout/index.blade.php'));

        foreach ([
            'checkoutCustomerName',
            'checkoutCustomerEmail',
            'checkoutCustomerPhone',
            'checkoutDeliveryMethod',
            'checkoutShippingAddress1',
            'checkoutShippingAddress2',
            'checkoutShippingCity',
            'checkoutShippingState',
            'checkoutShippingPostal',
            'checkoutShippingCountry',
            'checkoutBillingAddress1',
            'checkoutBillingAddress2',
            'checkoutBillingCity',
            'checkoutBillingState',
            'checkoutBillingPostal',
            'checkoutBillingCountry',
            'checkoutNotes',
        ] as $controlId) {
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
        }

        foreach ([
            'checkoutCustomerName',
            'checkoutCustomerEmail',
            'checkoutCustomerPhone',
            'checkoutDeliveryMethod',
            'checkoutShippingAddress1',
            'checkoutShippingCity',
            'checkoutShippingCountry',
        ] as $requiredControlId) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($requiredControlId, '/') . '"[^>]*aria-required="true"/',
                $view
            );
        }

        $this->assertStringContainsString('id="checkoutShippingQuoteStatus"', $view);
        $this->assertStringContainsString('role="status" aria-live="polite"', $view);
    }

    public function test_checkout_saved_address_switching_is_in_place_without_page_reload_links(): void
    {
        $view = file_get_contents(resource_path('views/frontend/checkout/index.blade.php'));

        $this->assertStringContainsString('data-checkout-saved-address', $view);
        $this->assertStringContainsString('data-checkout-new-address', $view);
        $this->assertStringContainsString('setAddressButtonState', $view);
        $this->assertStringContainsString('requestQuote();', $view);
        $this->assertStringContainsString("button.dataset.addressLine1", $view);
        $this->assertStringNotContainsString("route('checkout.index', ['address' => $savedAddress->id])", $view);
        $this->assertStringNotContainsString("route('checkout.index', ['address' => 'new'])", $view);
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
