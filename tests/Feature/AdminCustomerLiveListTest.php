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

        $owner = User::factory()->create(['role_as' => 1]);
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
            'status' => Order::STATUS_PENDING,
        ]);
    }
}
