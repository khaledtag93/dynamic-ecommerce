<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Permission;
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
            ->assertDontSee(route('admin.products.create'));
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

}
