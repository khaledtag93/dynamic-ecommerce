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

    public function test_customer_value_stays_currency_safe_and_rankings_require_a_currency(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $owner = $this->createSuperAdmin();

        $egpLeader = User::factory()->create([
            'name' => 'EGP Leader',
            'email' => 'rank-egp@example.test',
            'role_as' => 0,
        ]);
        $usdLeader = User::factory()->create([
            'name' => 'USD Leader',
            'email' => 'rank-usd@example.test',
            'role_as' => 0,
        ]);

        $this->orderFor($egpLeader, 'RANK-EGP-1', 100, 'EGP');
        $this->orderFor($egpLeader, 'RANK-USD-1', 10, 'USD');
        $this->orderFor($usdLeader, 'RANK-EGP-2', 50, 'EGP');
        $this->orderFor($usdLeader, 'RANK-USD-2', 1000, 'USD');

        $index = $this->actingAs($owner)->get(route('admin.customers.index'));

        $index->assertOk();

        $revenueRows = collect($index->viewData('stats')['revenue_by_currency'] ?? []);
        $this->assertSame(
            '150.00',
            data_get($revenueRows->firstWhere('currency', 'EGP'), 'amount'),
            'Unexpected EGP customer revenue rows: '.json_encode($revenueRows->values()->all())
        );
        $this->assertSame(
            '1010.00',
            data_get($revenueRows->firstWhere('currency', 'USD'), 'amount'),
            'Unexpected USD customer revenue rows: '.json_encode($revenueRows->values()->all())
        );

        $index
            ->assertSee('EGP 100.00 · USD 10.00')
            ->assertSee('EGP 50.00 · USD 1,000.00');

        $this->actingAs($owner)
            ->get(route('admin.customers.index', ['value' => 'high_value']))
            ->assertOk()
            ->assertSee('Choose a currency to rank customer spend safely.');

        $this->actingAs($owner)
            ->get(route('admin.customers.index', ['value' => 'high_value', 'value_currency' => 'EGP']))
            ->assertOk()
            ->assertSeeInOrder(['rank-egp@example.test', 'rank-usd@example.test']);

        $this->actingAs($owner)
            ->get(route('admin.customers.index', ['value' => 'high_value', 'value_currency' => 'USD']))
            ->assertOk()
            ->assertSeeInOrder(['rank-usd@example.test', 'rank-egp@example.test']);

        $profile = $this->actingAs($owner)->get(route('admin.customers.show', $egpLeader));

        $profile
            ->assertOk()
            ->assertSee('EGP 100.00 · USD 10.00')
            ->assertSee('EGP 0.00 · USD 0.00')
            ->assertSee('USD 10.00');
    }

    public function test_customer_commercial_kpis_preserve_exact_cents_and_half_up_average(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $owner = $this->createSuperAdmin();

        $customer = User::factory()->create([
            'name' => 'Exact Cent Customer',
            'email' => 'exact-cent-customer@example.test',
            'role_as' => 0,
        ]);

        $orders = [
            $this->orderFor($customer, 'KPI-001', '0.01'),
            $this->orderFor($customer, 'KPI-002', '0.02'),
            $this->orderFor($customer, 'KPI-003', '0.03'),
            $this->orderFor($customer, 'KPI-004', '0.14'),
        ];

        $orders[3]->update([
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'refund_total' => '0.10',
            'commercial_refund_total' => '0.10',
        ]);

        $profile = $this->actingAs($owner)->get(route('admin.customers.show', $customer));
        $profile->assertOk();

        $egp = collect($profile->viewData('summary')['spend_by_currency'] ?? [])
            ->firstWhere('currency', 'EGP');

        $this->assertNotNull($egp);
        $this->assertSame('0.20', $egp['gross_total']);
        $this->assertSame('0.10', $egp['refund_total']);
        $this->assertSame('0.10', $egp['net_total']);
        $this->assertSame('0.03', $egp['average_order_value']);
        $profile->assertSee('EGP 0.03');

        $index = $this->actingAs($owner)->get(route('admin.customers.index'));
        $index->assertOk();

        $listedCustomer = $index->viewData('users')->getCollection()->firstWhere('id', $customer->id);
        $listedEgp = collect($listedCustomer?->realized_spend_by_currency ?? [])
            ->firstWhere('currency', 'EGP');

        $this->assertNotNull($listedEgp);
        $this->assertSame('0.10', $listedEgp['amount']);

        $revenueEgp = collect($index->viewData('stats')['revenue_by_currency'] ?? [])
            ->firstWhere('currency', 'EGP');

        $this->assertNotNull($revenueEgp);
        $this->assertSame('0.10', $revenueEgp['amount']);
    }

    public function test_customer_profile_orders_follow_business_time_not_insert_id(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $owner = $this->createSuperAdmin();
        $customer = User::factory()->create([
            'name' => 'Chronology Customer',
            'email' => 'chronology@example.test',
            'role_as' => 0,
        ]);

        $newer = $this->orderFor($customer, 'CHRONO-NEW', 100);
        $newer->update([
            'placed_at' => now()->subDay()->setTime(12, 0),
            'created_at' => now()->subDay()->setTime(12, 0),
        ]);

        $olderInsertedLater = $this->orderFor($customer, 'CHRONO-OLD', 50);
        $olderInsertedLater->update([
            'placed_at' => now()->subDays(10)->setTime(12, 0),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('admin.customers.show', $customer));

        $response
            ->assertOk()
            ->assertSeeInOrder(['CHRONO-NEW', 'CHRONO-OLD'])
            ->assertSee($newer->placed_at->format('d M Y'));
    }

    private function orderFor(User $user, string $number, float|string $total, string $currency = 'EGP'): Order
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
            'currency' => $currency,
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);
    }
}
