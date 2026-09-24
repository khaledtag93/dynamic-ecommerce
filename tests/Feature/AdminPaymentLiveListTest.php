<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_live_list_preserves_filters_permissions_and_escaped_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = User::factory()->create(['role_as' => 1]);
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        $matchOrder = $this->order('PAY-LIVE-MATCH', '<script>Payment Customer</script>');
        $otherOrder = $this->order('PAY-LIVE-OTHER', 'Other Customer');

        Payment::create([
            'order_id' => $matchOrder->id,
            'method' => Order::PAYMENT_CASH,
            'provider' => '<script>Cash Desk</script>',
            'status' => Payment::STATUS_FAILED,
            'transaction_reference' => 'TX-LIVE-MATCH',
            'amount' => 75,
            'currency' => 'EGP',
        ]);
        Payment::create([
            'order_id' => $otherOrder->id,
            'method' => Order::PAYMENT_CASH,
            'provider' => 'Other Provider',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'TX-LIVE-OTHER',
            'amount' => 50,
            'currency' => 'EGP',
        ]);

        $url = route('admin.payments.index', [
            'search' => 'TX-LIVE-MATCH',
            'status' => Payment::STATUS_FAILED,
            'method' => Order::PAYMENT_CASH,
            'queue' => 'attention',
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-search', false)
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('data-live-reset', false)
            ->assertSee('TX-LIVE-MATCH')
            ->assertSee('&lt;script&gt;Cash Desk&lt;/script&gt;', false)
            ->assertDontSee('TX-LIVE-OTHER');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('TX-LIVE-MATCH')
            ->assertSee('&lt;script&gt;Cash Desk&lt;/script&gt;', false)
            ->assertDontSee('TX-LIVE-OTHER')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }

    private function order(string $number, string $customer): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_name' => $customer,
            'customer_email' => strtolower(str_replace(' ', '-', strip_tags($customer))).'@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'grand_total' => 100,
            'status' => Order::STATUS_PENDING,
        ]);
    }
}
