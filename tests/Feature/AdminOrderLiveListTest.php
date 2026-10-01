<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Payment;
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
        $owner = $this->createSuperAdmin();
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

    public function test_order_finance_cards_use_exact_ledger_money_and_normalized_currencies(): void
    {
        $owner = $this->createSuperAdmin();

        $egpOrder = $this->order('LEDGER-EGP', 'EGP Customer', 'egp@example.test', '1.00', Order::STATUS_COMPLETED);
        $egpOrder->update([
            'currency' => 'EGP',
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'refund_total' => '0.25',
        ]);
        Payment::query()->create([
            'order_id' => $egpOrder->id,
            'method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
            'provider' => 'bank',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'PAY-EGP-100',
            'amount' => '1.00',
            'currency' => 'EGP',
            'paid_at' => now(),
        ]);
        OrderRefund::query()->create([
            'order_id' => $egpOrder->id,
            'amount' => '0.25',
            'reason' => 'Ledger-backed EGP refund',
            'processed_at' => now(),
        ]);

        $legacyUsdOrder = $this->order('LEDGER-USD-LEGACY', 'Legacy USD', 'legacy-usd@example.test', '0.10', Order::STATUS_COMPLETED);
        $legacyUsdOrder->update([
            'currency' => 'USD',
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);
        Payment::query()->create([
            'order_id' => $legacyUsdOrder->id,
            'method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
            'provider' => 'bank',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'PAY-USD-010-BLANK',
            'amount' => '0.10',
            'currency' => '',
            'paid_at' => now(),
        ]);

        $lowercaseUsdOrder = $this->order('LEDGER-USD-LOWER', 'Lower USD', 'lower-usd@example.test', '0.20', Order::STATUS_COMPLETED);
        $lowercaseUsdOrder->update([
            'currency' => 'USD',
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            'refund_total' => '0.10',
        ]);
        Payment::query()->create([
            'order_id' => $lowercaseUsdOrder->id,
            'method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
            'provider' => 'bank',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'PAY-USD-020-LOWER',
            'amount' => '0.20',
            'currency' => 'usd',
            'paid_at' => now(),
        ]);
        OrderRefund::query()->create([
            'order_id' => $lowercaseUsdOrder->id,
            'amount' => '0.10',
            'reason' => 'Ledger-backed USD refund',
            'processed_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('EGP 0.75 · USD 0.20')
            ->assertSee('EGP 0.25 · USD 0.10');
    }

    private function order(string $number, string $name, string $email, float|int|string $total, string $status): Order
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
