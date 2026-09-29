<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Commerce\ProfitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesCogsReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_cogs_reconciliation_is_dry_run_by_default_and_applies_explicitly(): void
    {
        $tracked = $this->makeOrder('LOT-COST');
        $tracked->forceFill([
            'cost_total' => 60,
            'profit_total' => 40,
        ])->save();

        $tracked->items()->create([
            'product_name' => 'Historical lot item',
            'sku' => 'LOT-COST-ITEM',
            'unit_price' => 50,
            'unit_cost' => 30,
            'quantity' => 2,
            'line_total' => 100,
            'profit_amount' => 40,
            'meta' => [
                'inventory_lot_allocations' => [
                    ['lot_id' => 101, 'quantity' => 1, 'unit_cost' => 10],
                    ['lot_id' => 102, 'quantity' => 1, 'unit_cost' => 20],
                ],
            ],
        ]);
        $legacy = $this->makeOrder('LEGACY-COST');
        $legacy->forceFill([
            'cost_total' => 25,
            'profit_total' => 75,
        ])->save();

        $legacy->items()->create([
            'product_name' => 'Legacy item',
            'sku' => 'LEGACY-COST-ITEM',
            'unit_price' => 100,
            'unit_cost' => 25,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 75,
        ]);

        $tracked->refresh();
        $trackedItem = $tracked->items()->firstOrFail();
        $this->assertSame(2, count(data_get($trackedItem->meta, 'inventory_lot_allocations', [])));
        $this->assertTrue(
            Order::query()
                ->whereKey($tracked->id)
                ->whereHas('items', fn ($query) => $query->whereNotNull('meta'))
                ->exists()
        );
        $this->assertSame([
            'cost_total' => '30.00',
            'profit_total' => '70.00',
        ], app(ProfitService::class)->calculateOrderTotals($tracked));

        $this->assertSame(0, Artisan::call('commerce:reconcile-lot-cogs', [
            '--after-id' => max(0, $tracked->id - 1),
            '--limit' => 10,
        ]));
        $this->assertStringContainsString(
            'DRY-RUN complete',
            Artisan::output()
        );
        $this->assertStringContainsString(
            'changed=1',
            Artisan::output()
        );
        $this->assertSame(60.0, (float) $tracked->fresh()->cost_total);
        $this->assertSame(40.0, (float) $tracked->fresh()->profit_total);
        $this->assertSame(25.0, (float) $legacy->fresh()->cost_total);
        $this->assertSame(75.0, (float) $legacy->fresh()->profit_total);

        $this->assertSame(0, Artisan::call('commerce:reconcile-lot-cogs', [
            '--after-id' => max(0, $tracked->id - 1),
            '--limit' => 10,
            '--apply' => true,
        ]));
        $this->assertStringContainsString(
            'APPLY complete',
            Artisan::output()
        );
        $this->assertStringContainsString(
            'applied=1',
            Artisan::output()
        );
        $this->assertSame(30.0, (float) $tracked->fresh()->cost_total);
        $this->assertSame(70.0, (float) $tracked->fresh()->profit_total);
        $this->assertSame(25.0, (float) $legacy->fresh()->cost_total);
        $this->assertSame(75.0, (float) $legacy->fresh()->profit_total);
    }

    private function makeOrder(string $prefix): Order
    {
        return Order::query()->create([
            'order_number' => $prefix.'-'.Str::upper(Str::random(8)),
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'refund_total' => 0,
            'customer_name' => 'Historical Customer',
            'customer_email' => Str::lower(Str::random(8)).'@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Historical Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now()->subDay(),
            'delivered_at' => now()->subDay(),
        ]);
    }
}
