<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeliveryLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_live_list_preserves_filters_permissions_and_escaped_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $reader = User::factory()->create(['role_as' => 1]);
        $reader->roles()->sync([Role::where('slug', 'support_agent')->firstOrFail()->id]);
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        $this->order('DEL-LIVE-MATCH', '<script>Delivery Customer</script>', Order::DELIVERY_STATUS_PENDING, Order::DELIVERY_METHOD_STANDARD);
        $this->order('DEL-LIVE-OTHER', 'Other Customer', Order::DELIVERY_STATUS_DELIVERED, Order::DELIVERY_METHOD_PICKUP);

        $url = route('admin.deliveries.index', [
            'search' => 'DEL-LIVE-MATCH',
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'queue' => 'action',
        ]);

        $this->actingAs($reader)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('DEL-LIVE-MATCH')
            ->assertSee('&lt;script&gt;Delivery Customer&lt;/script&gt;', false)
            ->assertDontSee('DEL-LIVE-OTHER');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('DEL-LIVE-MATCH')
            ->assertDontSee('DEL-LIVE-OTHER')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }

    private function order(string $number, string $customer, string $deliveryStatus, string $deliveryMethod): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_name' => $customer,
            'customer_email' => 'delivery@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Delivery Street',
            'shipping_city' => 'Cairo',
            'grand_total' => 100,
            'status' => Order::STATUS_PROCESSING,
            'delivery_status' => $deliveryStatus,
            'delivery_method' => $deliveryMethod,
        ]);
    }
}
