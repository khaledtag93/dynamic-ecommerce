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
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

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

    public function test_cart_coupon_live_actions_expose_and_restore_accessible_busy_state(): void
    {
        $view = file_get_contents(resource_path('views/frontend/cart/index.blade.php'));

        $this->assertStringContainsString("form.dataset.cartCouponPending = '1';", $view);
        $this->assertStringContainsString("form.setAttribute('aria-busy', 'true');", $view);
        $this->assertStringContainsString("form.classList.add('lc-loading');", $view);
        $this->assertStringContainsString('aria-hidden="true"', $view);
        $this->assertStringContainsString("button.setAttribute('aria-disabled', 'true');", $view);
        $this->assertStringContainsString("button.removeAttribute('aria-disabled');", $view);
        $this->assertStringContainsString("form.removeAttribute('aria-busy');", $view);
        $this->assertStringContainsString("form.classList.remove('lc-loading');", $view);
        $this->assertStringContainsString('delete form.dataset.cartCouponPending;', $view);
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
        $this->assertStringContainsString('button.dataset.addressLine1', $view);
        $this->assertStringNotContainsString("route('checkout.index', ['address' => " . '$savedAddress->id' . "])", $view);
        $this->assertStringNotContainsString("route('checkout.index', ['address' => 'new'])", $view);
    }

    public function test_checkout_input_limits_billing_requirements_and_submit_guard_match_server_contract(): void
    {
        $view = file_get_contents(resource_path('views/frontend/checkout/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Frontend/CheckoutController.php'));

        foreach ([
            'checkoutCustomerName' => 255,
            'checkoutCustomerEmail' => 255,
            'checkoutCustomerPhone' => 50,
            'checkoutShippingAddress1' => 255,
            'checkoutShippingAddress2' => 255,
            'checkoutShippingCity' => 255,
            'checkoutShippingState' => 255,
            'checkoutShippingPostal' => 50,
            'checkoutShippingCountry' => 120,
            'checkoutBillingAddress1' => 255,
            'checkoutBillingAddress2' => 255,
            'checkoutBillingCity' => 255,
            'checkoutBillingState' => 255,
            'checkoutBillingPostal' => 50,
            'checkoutBillingCountry' => 120,
            'checkoutNotes' => 1000,
        ] as $controlId => $maxLength) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($controlId, '/') . '"[^>]*maxlength="' . $maxLength . '"/',
                $view
            );
        }

        $this->assertStringContainsString('role="radiogroup" aria-label="{{ __(\'Payment method\') }}" aria-required="true"', $view);
        $this->assertMatchesRegularExpression('/name="payment_method"[^>]*required/', $view);
        $this->assertStringContainsString('data-billing-same', $view);
        $this->assertStringContainsString('const syncBillingRequired = () => {', $view);
        $this->assertStringContainsString("field.required = !sameAsShipping;", $view);
        $this->assertStringContainsString('let checkoutSubmitting = false;', $view);
        $this->assertStringContainsString('if (checkoutSubmitting) {', $view);
        $this->assertStringContainsString('controller?.abort();', $view);
        $this->assertStringContainsString('const paymentMethodsAvailable =', $view);
        $this->assertStringContainsString('if (!checkoutSubmitting && paymentMethodsAvailable) {', $view);

        foreach ([
            "'customer_name' => ['required', 'string', 'max:255']",
            "'customer_email' => ['required', 'email', 'max:255']",
            "'customer_phone' => ['required', 'string', 'max:50']",
            "'shipping_postal_code' => ['nullable', 'string', 'max:50']",
            "'shipping_country' => ['required', 'string', 'max:120']",
            "'notes' => ['nullable', 'string', 'max:1000']",
        ] as $serverRule) {
            $this->assertStringContainsString($serverRule, $controller);
        }
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
