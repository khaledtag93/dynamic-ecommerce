<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_distinguish_order_value_from_paid_order_share(): void
    {
        $owner = $this->createSuperAdmin();
        foreach ([
            ['order_number' => 'UX-PAID', 'grand_total' => 100, 'payment_status' => Order::PAYMENT_STATUS_PAID],
            ['order_number' => 'UX-UNPAID', 'grand_total' => 50, 'payment_status' => Order::PAYMENT_STATUS_UNPAID],
        ] as $data) {
            Order::create($data + [
                'customer_name' => 'Dashboard test',
                'customer_email' => 'dashboard@example.test',
                'customer_phone' => '01000000000',
                'shipping_address_line_1' => '1 Test Street',
                'shipping_city' => 'Cairo',
            ]);
        }

        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('kpiCards', fn ($cards) => $cards[0]['value'] === 'EGP 150.00'
                && $cards[2]['value'] === '50.0%')
            ->assertSee(route('admin.products.create'))
            ->assertSee('UX-PAID')
            ->assertSee('UX-UNPAID');
    }

    public function test_dashboard_low_stock_snapshot_includes_variant_inventory(): void
    {
        $owner = $this->createSuperAdmin();
        $category = Category::create([
            'name' => 'Variant stock category',
            'slug' => 'variant-stock-category',
            'status' => false,
        ]);
        $product = Product::create([
            'name' => 'Variant dashboard product',
            'slug' => 'variant-dashboard-product',
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 0,
            'low_stock_threshold' => 2,
            'has_variants' => true,
            'status' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-LOW-001',
            'price' => 100,
            'stock' => 1,
            'reorder_point' => 3,
            'status' => true,
        ]);

        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['products_low_stock'] === 1)
            ->assertViewHas('lowStockItems', fn ($items) => $items->count() === 1
                && $items->first()['product']->is($product)
                && $items->first()['variant']->sku === 'VAR-LOW-001'
                && $items->first()['stock'] === 1)
            ->assertSee('VAR-LOW-001')
            ->assertSee('1');
    }

    public function test_dashboard_search_and_navigation_respect_staff_permissions(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $role = Role::create(['name' => 'Dashboard reader', 'slug' => 'dashboard_reader', 'is_system' => false]);
        $role->permissions()->attach(Permission::where('slug', 'dashboard.view')->firstOrFail());
        $staff = User::factory()->create(['role_as' => 1]);
        $staff->roles()->attach($role);
        $customer = User::factory()->create(['role_as' => 0, 'name' => 'OnlyPrivateCustomer']);

        $this->actingAs($staff)->get(route('admin.dashboard', ['q' => 'OnlyPrivateCustomer']))
            ->assertOk()
            ->assertViewHas('searchResults', fn ($results) => $results['customers']->isEmpty())
            ->assertDontSee(route('admin.customers.show', $customer))
            ->assertDontSee(route('admin.products.create'))
            ->assertDontSee('Gross order value');
    }

    public function test_order_detail_hides_mutation_controls_from_read_only_staff(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $staff = User::factory()->create(['role_as' => 1]);
        $staff->roles()->attach(Role::where('slug', 'support_agent')->firstOrFail());
        $order = Order::create([
            'order_number' => 'UX-READ-ONLY',
            'grand_total' => 75,
            'customer_name' => 'Read only',
            'customer_email' => 'readonly@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
        ]);

        $this->actingAs($staff)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('UX-READ-ONLY')
            ->assertDontSee(route('admin.orders.update-status', $order))
            ->assertDontSee(route('admin.orders.refund', $order))
            ->assertDontSee(route('admin.deliveries.update', $order));
    }

    public function test_dashboard_quick_access_stacks_without_page_level_horizontal_overflow_on_mobile(): void
    {
        $source = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString('@media (max-width: 767.98px)', $source);
        $this->assertStringContainsString('.admin-home-links {', $source);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $source);
        $this->assertStringContainsString('overflow-x: clip;', $source);
        $this->assertStringContainsString('.admin-home-links a span {', $source);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $source);
    }


    public function test_dashboard_customer_lookup_never_returns_staff_accounts(): void
    {
        $owner = $this->createSuperAdmin();
        $staff = User::factory()->create(['role_as' => 1, 'name' => 'ScopedNeedle Staff']);
        $customer = User::factory()->create(['role_as' => 0, 'name' => 'ScopedNeedle Customer']);

        $response = $this->actingAs($owner)->get(route('admin.dashboard', ['q' => 'ScopedNeedle']));

        $response->assertOk()
            ->assertSee('ScopedNeedle Customer')
            ->assertDontSee('ScopedNeedle Staff')
            ->assertSee(route('admin.customers.show', $customer))
            ->assertDontSee(route('admin.customers.show', $staff));
    }

    public function test_dashboard_live_search_returns_only_the_search_region_contract(): void
    {
        $owner = $this->createSuperAdmin();

        Order::create([
            'order_number' => 'LIVE-LOOKUP-01',
            'grand_total' => 25,
            'customer_name' => 'Live lookup',
            'customer_email' => 'live@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
        ]);

        $this->actingAs($owner)
            ->withHeader('X-Live-Dashboard-Search', '1')
            ->get(route('admin.dashboard', ['q' => 'LIVE-LOOKUP']))
            ->assertOk()
            ->assertSee('LIVE-LOOKUP-01')
            ->assertDontSee('At a glance')
            ->assertDontSee('Your workspaces');
    }

    public function test_dashboard_priority_links_open_the_matching_filtered_workspaces(): void
    {
        $owner = $this->createSuperAdmin();

        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.orders.index', ['status' => Order::STATUS_PENDING]))
            ->assertSee(route('admin.products.index', ['stockFilter' => 'low']))
            ->assertSee(route('admin.payments.index', ['queue' => 'failed']));
    }

    public function test_dashboard_search_is_bounded_and_progressive_in_source(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/DashBoardController.php'));
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString("mb_substr(trim((string) \$request->string('q')), 0, 80)", $controller);
        $this->assertStringContainsString("where('role_as', 0)", $controller);
        $this->assertStringContainsString("X-Live-Dashboard-Search", $controller);
        $this->assertStringContainsString("X-Live-Dashboard-Search", $view);
        $this->assertStringContainsString("window.setTimeout(() => runSearch(value), 350)", $view);
        $this->assertStringContainsString("window.history.replaceState", $view);
    }

}
