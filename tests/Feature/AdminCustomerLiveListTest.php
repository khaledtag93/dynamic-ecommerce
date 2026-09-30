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

        $index
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                $totals = collect($stats['revenue_by_currency'] ?? [])
                    ->mapWithKeys(fn (array $row) => [$row['currency'] => (float) $row['amount']])
                    ->all();

                return $totals === ['EGP' => 150.0, 'USD' => 1010.0];
            })
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

    private function orderFor(User $user, string $number, float $total, string $currency = 'EGP'): Order
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
