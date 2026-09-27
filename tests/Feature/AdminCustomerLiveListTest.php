<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_live_list_preserves_filters_permissions_and_escaped_customer_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = $this->createSuperAdmin();
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        $match = User::factory()->create([
            'name' => '<script>Live Customer</script>',
            'email' => 'live-match@example.test',
            'role_as' => 0,
        ]);
        $other = User::factory()->create([
            'name' => 'Other Customer',
            'email' => 'other@example.test',
            'role_as' => 0,
        ]);

        $this->orderFor($match, 'CUST-LIVE-1', 75);
        $this->orderFor($other, 'CUST-OTHER-1', 25);

        $url = route('admin.customers.index', [
            'search' => 'live-match',
            'role' => '0',
            'activity' => 'buyers',
            'per_page' => 12,
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-search', false)
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('data-live-reset', false)
            ->assertSee('live-match@example.test')
            ->assertSee('&lt;script&gt;Live Customer&lt;/script&gt;', false)
            ->assertDontSee('other@example.test');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('live-match@example.test')
            ->assertSee('&lt;script&gt;Live Customer&lt;/script&gt;', false)
            ->assertDontSee('other@example.test')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }

    public function test_customer_value_filters_only_use_commercially_realized_orders(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = $this->createSuperAdmin();
        $realized = User::factory()->create([
            'name' => 'Realized Buyer',
            'email' => 'realized@example.test',
            'role_as' => 0,
        ]);
        $unrealized = User::factory()->create([
            'name' => 'Unrealized Customer',
            'email' => 'unrealized@example.test',
            'role_as' => 0,
        ]);

        $realizedOrder = $this->orderFor($realized, 'REALIZED-CUST-1', 100);
        $realizedOrder->update([
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'refund_total' => 25,
        ]);

        Order::create([
            'user_id' => $unrealized->id,
            'order_number' => 'UNREALIZED-CUST-1',
            'customer_name' => $unrealized->name,
            'customer_email' => $unrealized->email,
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'grand_total' => 500,
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ]);

        $response = $this->actingAs($owner)->get(route('admin.customers.index', ['activity' => 'buyers']));

        $response
            ->assertOk()
            ->assertSee('realized@example.test')
            ->assertSee('EGP 75.00')
            ->assertDontSee('unrealized@example.test');

        $profile = $this->actingAs($owner)->get(route('admin.customers.show', $realized));

        $profile
            ->assertOk()
            ->assertSee('EGP 100.00')
            ->assertSee('EGP 25.00')
            ->assertSee('EGP 75.00');
    }

    private function orderFor(User $user, string $number, float $total): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => $number,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'grand_total' => $total,
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);
    }
}
