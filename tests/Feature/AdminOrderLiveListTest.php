<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_list_fragment_keeps_read_permissions_filters_and_escaped_customer_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();
        $reader = User::factory()->create(['role_as' => 1]);
        $reader->roles()->sync([Role::where('slug', 'support_agent')->firstOrFail()->id]);
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        $match = $this->order('LIVE-MATCH', '<script>alert(1)</script>', 'live-match@example.test', 20, Order::STATUS_PROCESSING);
        $this->order('LIVE-OTHER', 'Other Customer', 'other@example.test', 30, Order::STATUS_COMPLETED);
        $url = route('admin.orders.index', ['search' => 'live-match', 'status' => Order::STATUS_PROCESSING]);

        $this->actingAs($reader)->get($url)
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-results', false)
            ->assertSee('LIVE-MATCH')
            ->assertDontSee('LIVE-OTHER')
            ->assertDontSee(route('admin.coupons.index'))
            ->assertDontSee(route('admin.orders.quick-status', $match));

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('LIVE-MATCH')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('LIVE-OTHER')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee(route('admin.coupons.index'))
            ->assertDontSee(route('admin.orders.quick-status', $match))
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }

    public function test_order_queue_sort_and_pagination_return_matching_full_and_live_results(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
        for ($number = 1; $number <= 13; $number++) {
            $this->order('LIVE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'Queue Customer', 'queue@example.test', $number, Order::STATUS_PENDING);
        }
        $this->order('LIVE-COMPLETE', 'Complete Customer', 'complete@example.test', 100, Order::STATUS_COMPLETED);

        $url = route('admin.orders.index', [
            'queue' => 'action', 'sort' => 'grand_total', 'direction' => 'asc', 'per_page' => 12, 'page' => 2,
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('LIVE-13')
            ->assertDontSee('LIVE-01')
            ->assertDontSee('LIVE-COMPLETE')
            ->assertSee('data-live-link', false)
            ->assertSee('data-live-reset', false);

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('LIVE-13')
            ->assertDontSee('LIVE-01')
            ->assertDontSee('LIVE-COMPLETE')
            ->assertSee('data-live-link', false)
            ->assertDontSee('data-live-filter', false);
    }

    private function order(string $number, string $name, string $email, float $total, string $status): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'grand_total' => $total,
            'status' => $status,
        ]);
    }
}
