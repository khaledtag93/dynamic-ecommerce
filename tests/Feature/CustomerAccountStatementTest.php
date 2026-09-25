<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_statement_uses_canonical_movements_without_running_balance(): void
    {
        $admin = $this->createSuperAdmin();
        $customer = User::factory()->create(['role_as' => 0]);

        $order = $this->orderFor($customer, 'STAT-001', 150, 'EGP');
        $order->forceFill([
            'placed_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ])->save();

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_ONLINE,
            'provider' => 'paymob',
            'status' => Payment::STATUS_PAID,
            'amount' => 150,
            'currency' => 'EGP',
            'paid_at' => now()->subDays(4),
        ]);

        OrderRefund::query()->create([
            'order_id' => $order->id,
            'amount' => 25,
            'reason' => 'Partial goodwill refund',
            'processed_by' => $admin->id,
            'processed_at' => now()->subDays(2),
        ]);

        ReturnRequest::query()->create([
            'reference' => 'RMA-STAT-001',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => ReturnRequest::STATUS_REQUESTED,
            'requested_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.customers.statement', [
                'user' => $customer,
                'date_from' => now()->subMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $response
            ->assertOk()
            ->assertSee('STAT-001')
            ->assertSee('RMA-STAT-001')
            ->assertSee('Partial goodwill refund')
            ->assertSee('150.00')
            ->assertSee('25.00')
            ->assertSee('not an accounting ledger')
            ->assertDontSee('Running balance');

        $this->assertStringContainsString(
            'without inventing a running balance',
            file_get_contents(resource_path('views/admin/customers/statement.blade.php'))
        );
    }

    public function test_statement_filters_movement_type_and_period(): void
    {
        $admin = $this->createSuperAdmin();
        $customer = User::factory()->create(['role_as' => 0]);

        $oldOrder = $this->orderFor($customer, 'STAT-OLD', 80, 'USD');
        $oldOrder->forceFill([
            'placed_at' => now()->subYears(2),
            'created_at' => now()->subYears(2),
            'updated_at' => now()->subYears(2),
        ])->save();

        $recentOrder = $this->orderFor($customer, 'STAT-NEW', 120, 'USD');
        $recentOrder->forceFill([
            'placed_at' => now()->subDays(3),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        Payment::query()->create([
            'order_id' => $recentOrder->id,
            'method' => Order::PAYMENT_METHOD_COD,
            'status' => Payment::STATUS_PAID,
            'amount' => 120,
            'currency' => 'USD',
            'paid_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.statement', [
                'user' => $customer,
                'type' => 'payment',
                'date_from' => now()->subMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('120.00')
            ->assertDontSee('STAT-OLD');
    }

    public function test_statement_keeps_currency_totals_separate_and_exports_csv(): void
    {
        $admin = $this->createSuperAdmin();
        $customer = User::factory()->create(['role_as' => 0]);

        $egpOrder = $this->orderFor($customer, 'STAT-EGP', 100, 'EGP');
        $usdOrder = $this->orderFor($customer, 'STAT-USD', 20, 'USD');

        foreach ([[$egpOrder, 100, 'EGP'], [$usdOrder, 20, 'USD']] as [$order, $amount, $currency]) {
            Payment::query()->create([
                'order_id' => $order->id,
                'method' => Order::PAYMENT_METHOD_ONLINE,
                'status' => Payment::STATUS_PAID,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.customers.statement', ['user' => $customer]));

        $response
            ->assertOk()
            ->assertSee('EGP')
            ->assertSee('USD')
            ->assertSee('Amounts are grouped by their recorded currency and are not converted.');

        $export = $this->get(route('admin.customers.statement.export', ['user' => $customer]));

        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $this->assertStringContainsString('customer-statement-'.$customer->id, (string) $export->headers->get('content-disposition'));
    }

    public function test_statement_routes_require_customer_management_permission(): void
    {
        $customer = User::factory()->create(['role_as' => 0]);
        $regularUser = User::factory()->create(['role_as' => 0]);

        $this->actingAs($regularUser)
            ->get('/admin/customers/'.$customer->id.'/statement')
            ->assertRedirect('/login');
    }

    private function orderFor(User $customer, string $number, float $total, string $currency): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => $number,
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'currency' => $currency,
            'grand_total' => $total,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
        ]);
    }
}
