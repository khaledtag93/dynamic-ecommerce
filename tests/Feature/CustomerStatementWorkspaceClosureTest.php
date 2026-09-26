<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\CustomerAccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerStatementWorkspaceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_uses_placed_at_with_created_at_fallback_and_keeps_customer_scope(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();

        $inside = $this->order($customer, 'STATEMENT-IN', 100, 'EGP', '2026-09-15 12:00:00');
        $inside->forceFill([
            'created_at' => '2026-09-01 08:00:00',
            'updated_at' => '2026-09-01 08:00:00',
        ])->saveQuietly();

        $outsideByPlacedAt = $this->order($customer, 'STATEMENT-OUT', 50, 'EGP', '2026-08-31 23:00:00');
        $outsideByPlacedAt->forceFill([
            'created_at' => '2026-09-15 08:00:00',
            'updated_at' => '2026-09-15 08:00:00',
        ])->saveQuietly();

        $fallback = $this->order($customer, 'STATEMENT-FALLBACK', 30, 'EGP', null);
        $fallback->forceFill([
            'created_at' => '2026-09-20 10:00:00',
            'updated_at' => '2026-09-20 10:00:00',
        ])->saveQuietly();

        $otherOrder = $this->order($other, 'STATEMENT-OTHER', 999, 'EGP', '2026-09-18 12:00:00');

        $statement = app(CustomerAccountStatementService::class)->build($customer, [
            'type' => 'order',
            'date_from' => '2026-09-10',
            'date_to' => '2026-09-30',
        ]);

        $references = $statement['movements']->pluck('reference');

        $this->assertTrue($references->contains('STATEMENT-IN'));
        $this->assertTrue($references->contains('STATEMENT-FALLBACK'));
        $this->assertFalse($references->contains('STATEMENT-OUT'));
        $this->assertFalse($references->contains('STATEMENT-OTHER'));
        $this->assertSame(2, $statement['counts']['orders']);

        $egp = $statement['totals_by_currency']->firstWhere('currency', 'EGP');

        $this->assertNotNull($egp);
        $this->assertSame(130.0, $egp['order_value']);
    }

    public function test_statement_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/customers/statement.blade.php'));

        foreach ([
            'statementMovementType',
            'statementDateFrom',
            'statementDateTo',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }

    private function order(
        User $customer,
        string $number,
        float $amount,
        string $currency,
        ?string $placedAt,
    ): Order {
        return Order::query()->create([
            'user_id' => $customer->id,
            'sales_channel' => Order::SALES_CHANNEL_STOREFRONT,
            'order_number' => $number,
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => $currency,
            'subtotal' => $amount,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $amount,
            'customer_name' => 'Statement Customer',
            'customer_email' => 'statement@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Statement Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => $placedAt,
        ]);
    }
}
