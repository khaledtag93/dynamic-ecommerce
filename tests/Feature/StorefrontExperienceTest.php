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
            ->assertSee('background:var(--lc-secondary)', false)
            ->assertSee('background:linear-gradient(135deg,var(--lc-primary),var(--lc-secondary));color:var(--lc-btn-text)', false)
            ->assertSee('[dir="rtl"] .retail-hero-slider__nav i{transform:scaleX(-1)}', false)
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
            ->assertDontSee('Gallery images')
            ->assertDontSee('More product details will be added soon.')
            ->assertDontSee('Stock is updated from the product availability settings.')
            ->assertSee('Availability is checked again before checkout.');
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
            ->assertSee('Order details reviewed before submission')
            ->assertSee('Payment method shown clearly')
            ->assertSee('Delivery address confirmed before order')
            ->assertDontSee('Encrypted checkout messaging')
            ->assertDontSee('Last-minute boost')
            ->assertDontSee('Personalized offers')
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

        $detail = $this->actingAs($user)->get(route('orders.show', $order));

        $detail
            ->assertOk()
            ->assertSee('Secure payment session could not be opened.')
            ->assertSee('You can try opening the secure payment page again.')
            ->assertDontSee('HTTP 401 secret provider response')
            ->assertDontSee('initiation_failed');

        $success = $this->actingAs($user)->get(route('orders.success', $order));

        $success
            ->assertOk()
            ->assertSee('Online payment is not completed yet.')
            ->assertSee('If the payment page does not open, contact support with your order number.')
            ->assertDontSee('HTTP 401 secret provider response')
            ->assertDontSee('Latest gateway start issue');
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

    public function test_contact_page_hides_empty_business_card_and_links_configured_whatsapp(): void
    {
        $blank = $this->get(route('frontend.contact'));

        $blank
            ->assertOk()
            ->assertDontSee('Business details');

        \App\Models\WebsiteSetting::setValue('store_support_whatsapp', '+20 100 123 4567', 'content');
        \App\Models\WebsiteSetting::setValue('contact_show_whatsapp', '1', 'content');

        $configured = $this->get(route('frontend.contact'));

        $configured
            ->assertOk()
            ->assertSee('href="https://wa.me/201001234567"', false)
            ->assertSee('+20 100 123 4567');
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
            ->assertDontSee('.quick-view-image{object-fit:cover;', false)
            ->assertSee('<strong>0</strong>', false)
            ->assertSee('Products found')
            ->assertDontSee('0 products found');
    }

    public function test_notifications_hide_mark_all_when_inbox_has_no_unread_items(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response
            ->assertOk()
            ->assertDontSee('Mark all as read');
    }

    public function test_cart_uses_customer_facing_offer_labels(): void
    {
        $user = User::factory()->create();

        $category = Category::query()->create([
            'name' => 'Cart Offer Category',
            'slug' => 'cart-offer-category-' . Str::lower(Str::random(6)),
            'description' => 'Cart offer category',
            'meta_title' => 'Cart Offer Category',
            'meta_keyword' => 'cart offer',
            'meta_description' => 'Cart offer category',
            'status' => 0,
        ]);

        $product = Product::query()->create([
            'name' => 'Cart Offer Product',
            'slug' => 'cart-offer-product-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Cart offer product',
            'base_price' => 180,
            'quantity' => 5,
            'status' => true,
            'has_variants' => false,
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 180,
            'quantity' => 1,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response
            ->assertOk()
            ->assertDontSee('Personalized offers')
            ->assertDontSee('Smart offers')
            ->assertDontSee('Return path');
    }

    public function test_cart_removal_uses_storefront_confirmation_flow(): void
    {
        $user = User::factory()->create();

        $category = Category::query()->create([
            'name' => 'Cart Category',
            'slug' => 'cart-category-' . Str::lower(Str::random(6)),
            'description' => 'Cart category',
            'meta_title' => 'Cart Category',
            'meta_keyword' => 'cart',
            'meta_description' => 'Cart category',
            'status' => 0,
        ]);

        $product = Product::query()->create([
            'name' => 'Cart Product',
            'slug' => 'cart-product-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Cart product',
            'base_price' => 150,
            'quantity' => 5,
            'status' => true,
            'has_variants' => false,
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 150,
            'quantity' => 1,
            'meta' => ['product_slug' => $product->slug],
        ]);

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response
            ->assertOk()
            ->assertSee('id="storefrontConfirmModal"', false)
            ->assertSee('data-confirm-title="Remove item"', false)
            ->assertSee('data-confirm-message="Remove this item from your cart?"', false)
            ->assertDontSee('onclick="return confirm(', false);
    }

    public function test_default_legal_pages_do_not_publish_assumed_policy_terms(): void
    {
        $response = $this->get(route('frontend.refund'));

        $response
            ->assertOk()
            ->assertSee('This policy has not been published yet')
            ->assertSee(route('frontend.contact'))
            ->assertDontSee('Orders cancelled before shipping may qualify for a full refund.')
            ->assertDontSee('Delivered items may require inspection before approval.');

        $settings = app(StoreSettingsService::class)->all();

        $this->assertSame('', $settings['legal_privacy_body']);
        $this->assertSame('', $settings['legal_terms_body']);
        $this->assertSame('', $settings['legal_refund_body']);
        $this->assertSame('', $settings['legal_shipping_body']);
    }

    public function test_shared_product_card_uses_defined_storefront_theme_tokens(): void
    {
        $category = Category::query()->create([
            'name' => 'Theme Card Category',
            'slug' => 'theme-card-category-' . Str::lower(Str::random(6)),
            'description' => 'Theme card category',
            'meta_title' => 'Theme Card Category',
            'meta_keyword' => 'theme',
            'meta_description' => 'Theme card category',
            'status' => 0,
        ]);

        Product::query()->create([
            'name' => 'Theme Card Product',
            'slug' => 'theme-card-product-' . Str::lower(Str::random(6)),
            'category_id' => $category->id,
            'description' => 'Theme-aware product card',
            'base_price' => 250,
            'quantity' => 3,
            'status' => true,
            'has_variants' => false,
        ]);

        $response = $this->get(route('category.products', $category->id));

        $response
            ->assertOk()
            ->assertSee('var(--lc-surface)', false)
            ->assertSee('var(--lc-btn-text)', false)
            ->assertDontSee('--lc-success-bg', false)
            ->assertDontSee('--lc-success-text', false);
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

    public function test_customer_account_access_pages_use_storefront_ui(): void
    {
        $login = $this->get(route('login'));

        $login
            ->assertOk()
            ->assertSee('Login to your account')
            ->assertSee('lc-card p-4 p-lg-5', false);

        $reset = $this->get(route('password.request'));

        $reset
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee('Send reset link')
            ->assertSee('lc-form-control', false)
            ->assertDontSee('<div class="card-header">', false);
    }

    public function test_small_screen_navigation_exposes_account_and_login_paths(): void
    {
        $guest = $this->get(route('frontend.home'));

        $guest
            ->assertOk()
            ->assertSee('class="d-md-none" href="'.route('login').'"', false)
            ->assertSee(route('register'));

        $user = User::factory()->create();

        $authenticated = $this->actingAs($user)->get(route('frontend.home'));

        $authenticated
            ->assertOk()
            ->assertSee('class="d-md-none" href="'.route('orders.index').'"', false)
            ->assertSee('class="d-md-none" href="'.route('notifications.index').'"', false);
    }

    public function test_storefront_flash_feedback_uses_global_dismissible_toasts(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('id="storefrontToastStack"', $layout);
        $this->assertStringContainsString('data-storefront-toast-close', $layout);
        $this->assertStringContainsString("__('Dismiss notification')", $layout);
        $this->assertStringContainsString("window.setTimeout(() => dismiss(toast), 4200)", $layout);
        $this->assertStringContainsString('.lc-flash-toast.is-leaving', $layout);
        $this->assertStringContainsString("if (\$errors->any())", $layout);
        $this->assertStringContainsString("Please review the highlighted fields.", $layout);
    }

    public function test_storefront_navigation_exposes_active_and_mobile_focus_states(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("routeIs('frontend.home')", $layout);
        $this->assertStringContainsString("routeIs('frontend.contact')", $layout);
        $this->assertStringContainsString('.retail-links a[aria-current="page"]', $layout);
        $this->assertStringContainsString("retailNav.addEventListener('shown.bs.collapse'", $layout);
        $this->assertStringContainsString("event.key !== 'Escape'", $layout);
        $this->assertStringContainsString("bootstrap.Collapse.getOrCreateInstance(retailNav).hide()", $layout);
    }

    public function test_storefront_search_has_accessible_keyboard_shortcut(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertSame(2, substr_count($layout, 'data-storefront-search'));
        $this->assertStringContainsString("event.key !== '/'", $layout);
        $this->assertStringContainsString("target.matches('input, textarea, select')", $layout);
        $this->assertStringContainsString("search.focus()", $layout);
        $this->assertStringContainsString("search.select()", $layout);
    }

    public function test_storefront_dropdowns_expose_menu_semantics(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertGreaterThanOrEqual(2, substr_count($layout, 'aria-haspopup="menu"'));
        $this->assertGreaterThanOrEqual(2, substr_count($layout, 'role="menu"'));
        $this->assertGreaterThanOrEqual(6, substr_count($layout, 'role="menuitem"'));
        $this->assertStringContainsString('retail-account-menu dropdown-menu-end" role="menu"', $layout);
        $this->assertStringContainsString('retail-mega-menu" role="menu"', $layout);
    }

    public function test_storefront_dropdowns_support_keyboard_navigation(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("dropdown.addEventListener('shown.bs.dropdown'", $layout);
        $this->assertStringContainsString("event.key === 'Escape'", $layout);
        $this->assertStringContainsString("event.key !== 'ArrowDown' && event.key !== 'ArrowUp'", $layout);
        $this->assertStringContainsString("bootstrap.Dropdown.getOrCreateInstance(trigger).hide()", $layout);
        $this->assertStringContainsString("items[nextIndex].focus()", $layout);
    }

    public function test_storefront_utility_navigation_exposes_current_page_state(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("routeIs('notifications.*')", $layout);
        $this->assertStringContainsString("routeIs('cart.*')", $layout);
        $this->assertStringContainsString('.retail-action[aria-current="page"]', $layout);
    }

    public function test_storefront_footer_navigation_exposes_focus_and_current_states(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('.storefront-footer__column a:focus-visible', $layout);
        $this->assertStringContainsString('.storefront-footer__column a[aria-current="page"]', $layout);
        $this->assertStringContainsString("routeIs('frontend.privacy')", $layout);
        $this->assertStringContainsString("routeIs('frontend.shipping')", $layout);
        $this->assertStringContainsString("routeIs('checkout.*')", $layout);
    }

}
