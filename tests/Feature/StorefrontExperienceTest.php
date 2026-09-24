<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_home_uses_real_search_route_without_duplicate_shortcut_strip(): void
    {
        $response = $this->get(route('frontend.home'));

        $response
            ->assertOk()
            ->assertSee(route('frontend.search'))
            ->assertSee('Everything you need from one trusted store.')
            ->assertDontSee('retail-promo-pods', false)
            ->assertDontSee('Exclusive electronics deals', false);
    }

    public function test_storefront_search_returns_matching_visible_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Search Category',
            'slug' => 'search-category-' . Str::lower(Str::random(6)),
            'description' => 'Search test category',
            'meta_title' => 'Search Category',
            'meta_keyword' => 'search',
            'meta_description' => 'Search test category',
            'status' => 0,
        ]);

        $matching = Product::query()->create([
            'name' => 'Ocean Blue Backpack',
            'slug' => 'ocean-blue-backpack-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Durable travel backpack',
            'base_price' => 850,
            'quantity' => 5,
            'status' => true,
            'has_variants' => false,
        ]);

        Product::query()->create([
            'name' => 'Classic Desk Lamp',
            'slug' => 'classic-desk-lamp-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Warm desk light',
            'base_price' => 400,
            'quantity' => 4,
            'status' => true,
            'has_variants' => false,
        ]);

        $response = $this->get(route('frontend.search', ['q' => 'Backpack']));

        $response
            ->assertOk()
            ->assertSee('Ocean Blue Backpack')
            ->assertDontSee('Classic Desk Lamp')
            ->assertSee(route('frontend.products.show', $matching->slug))
            ->assertSee(asset('images/storefront-placeholder.svg'))
            ->assertDontSee('via.placeholder.com', false);
    }

    public function test_product_page_uses_local_placeholder_without_counting_it_as_gallery_media(): void
    {
        $category = Category::query()->create([
            'name' => 'Product Detail Category',
            'slug' => 'product-detail-category-' . Str::lower(Str::random(6)),
            'description' => 'Product detail category',
            'meta_title' => 'Product Detail Category',
            'meta_keyword' => 'product detail',
            'meta_description' => 'Product detail category',
            'status' => 0,
        ]);

        $product = Product::query()->create([
            'name' => 'Product Without Image',
            'slug' => 'product-without-image-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Product without gallery media',
            'base_price' => 250,
            'quantity' => 3,
            'status' => true,
            'has_variants' => false,
        ]);

        $response = $this->get(route('frontend.products.show', $product->slug));

        $response
            ->assertOk()
            ->assertSee(asset('images/storefront-placeholder.svg'))
            ->assertDontSee('via.placeholder.com', false)
            ->assertSee('<div><span>Gallery images</span><strong>0</strong></div>', false);
    }

    public function test_checkout_uses_aligned_billing_toggle_and_real_payment_options(): void
    {
        $user = User::factory()->create();

        $category = Category::query()->create([
            'name' => 'Checkout Category',
            'slug' => 'checkout-category-' . Str::lower(Str::random(6)),
            'description' => 'Checkout test category',
            'meta_title' => 'Checkout Category',
            'meta_keyword' => 'checkout',
            'meta_description' => 'Checkout test category',
            'status' => 0,
        ]);

        $product = Product::query()->create([
            'name' => 'Checkout Product',
            'slug' => 'checkout-product-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Checkout test product',
            'base_price' => 500,
            'quantity' => 5,
            'status' => true,
            'has_variants' => false,
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 500,
            'quantity' => 1,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $response = $this->actingAs($user)->get(route('checkout.index'));

        $response
            ->assertOk()
            ->assertSee('<div class="checkout-toggle-card mb-3">', false)
            ->assertSee('Cash on Delivery')
            ->assertSee('Bank Transfer')
            ->assertSee('Online Payment')
            ->assertDontSee('>Visa<', false)
            ->assertDontSee('>Mastercard<', false);
    }

    public function test_account_orders_reflect_terminal_statuses_and_use_in_app_cancellation_confirmation(): void
    {
        $user = User::factory()->create();

        $completed = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ACCOUNT-COMPLETE',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'customer_name' => 'Account Customer',
            'customer_email' => 'account@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ]);

        $pending = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ACCOUNT-PENDING',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 50,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 50,
            'customer_name' => 'Account Customer',
            'customer_email' => 'account@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ]);

        $list = $this->actingAs($user)->get(route('orders.index'));

        $list
            ->assertOk()
            ->assertSee('<span class="lc-status-badge lc-badge-success">Completed</span>', false)
            ->assertSee('<span class="lc-status-badge lc-badge-success">Paid</span>', false)
            ->assertSee('<span class="lc-status-badge lc-badge-success">Delivered</span>', false);

        $detail = $this->actingAs($user)->get(route('orders.show', $pending));

        $detail
            ->assertOk()
            ->assertSee('id="storefrontConfirmModal"', false)
            ->assertSee('data-confirm-title="Cancel order"', false)
            ->assertSee('data-confirm-ok="Cancel order"', false)
            ->assertDontSee('onclick="return confirm(', false);
    }

    public function test_paymob_result_hides_raw_provider_error_from_customer(): void
    {
        $user = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'PAYMOB-SAFE-ERROR',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'customer_name' => 'Payment Customer',
            'customer_email' => 'payment@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_ONLINE,
            'provider' => 'paymob',
            'provider_status' => 'initiation_failed',
            'status' => Payment::STATUS_FAILED,
            'amount' => 100,
            'currency' => 'EGP',
            'meta' => [
                'checkout_error' => 'HTTP 401 secret provider response',
                'checkout_error_at' => now()->toDateTimeString(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('payments.paymob.result', $order));

        $response
            ->assertOk()
            ->assertSee('Secure payment page could not be opened')
            ->assertSee('We could not start the secure payment session.')
            ->assertDontSee('HTTP 401 secret provider response');
    }

    public function test_contact_page_uses_localized_business_hours_fallback(): void
    {
        \App\Models\WebsiteSetting::setValue('store_contact_hours_en', 'Daily 09:00 - 18:00', 'content');
        \App\Models\WebsiteSetting::setValue('store_contact_hours_ar', '', 'content');
        \App\Models\WebsiteSetting::setValue('contact_show_hours', '1', 'content');

        $response = $this->withSession(['locale' => 'ar'])->get(route('frontend.contact'));

        $response
            ->assertOk()
            ->assertSee('Daily 09:00 - 18:00');
    }

    public function test_category_quick_view_preserves_product_image_ratio(): void
    {
        $category = Category::query()->create([
            'name' => 'Quick View Category',
            'slug' => 'quick-view-category-' . Str::lower(Str::random(6)),
            'description' => 'Quick view category',
            'meta_title' => 'Quick View Category',
            'meta_keyword' => 'quick view',
            'meta_description' => 'Quick view category',
            'status' => 0,
        ]);

        $response = $this->get(route('category.products', $category->id));

        $response
            ->assertOk()
            ->assertSee('.quick-view-image{object-fit:contain;', false)
            ->assertDontSee('.quick-view-image{object-fit:cover;', false);
    }

    public function test_notifications_hide_mark_all_when_inbox_has_no_unread_items(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response
            ->assertOk()
            ->assertDontSee('Mark all as read');
    }

    public function test_storefront_defaults_do_not_expose_demo_contact_details(): void
    {
        $settings = app(StoreSettingsService::class)->all();

        $this->assertSame('', $settings['store_support_email']);
        $this->assertSame('', $settings['store_support_phone']);
        $this->assertSame('', $settings['store_support_whatsapp']);
        $this->assertSame('', $settings['store_contact_address']);
        $this->assertSame('', $settings['store_business_website']);
        $this->assertSame('', $settings['store_contact_hours']);
    }
}
