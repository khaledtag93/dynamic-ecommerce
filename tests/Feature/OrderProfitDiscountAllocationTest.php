<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Commerce\ProfitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderProfitDiscountAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_level_discount_is_allocated_into_line_profit(): void
    {
        $order = $this->makeOrder([
            'subtotal' => 200,
            'discount_total' => 20,
            'grand_total' => 180,
        ]);

        $first = $this->addItem($order, 'FIRST', 100, 40, 60);
        $second = $this->addItem($order, 'SECOND', 100, 60, 40);

        $refreshed = app(ProfitService::class)->refreshOrderTotals($order);

        $this->assertSame('50.00', $first->fresh()->profit_amount);
        $this->assertSame('30.00', $second->fresh()->profit_amount);
        $this->assertSame(80.0, (float) $refreshed->profit_total);
        $this->assertSame(
            80.0,
            (float) $refreshed->items()->sum('profit_amount')
        );
    }

    public function test_discount_allocation_keeps_cent_remainder_deterministic(): void
    {
        $order = $this->makeOrder([
            'subtotal' => 100,
            'discount_total' => 0.01,
            'grand_total' => 99.99,
        ]);

        $first = $this->addItem($order, 'ROUND-A', 50, 0, 50);
        $second = $this->addItem($order, 'ROUND-B', 50, 0, 50);

        app(ProfitService::class)->refreshOrderTotals($order);

        $this->assertSame('50.00', $first->fresh()->profit_amount);
        $this->assertSame('49.99', $second->fresh()->profit_amount);
        $this->assertSame(99.99, (float) $order->fresh()->profit_total);
    }

    public function test_already_net_pos_line_total_does_not_receive_discount_twice(): void
    {
        $order = $this->makeOrder([
            'sales_channel' => Order::SALES_CHANNEL_POS,
            'subtotal' => 100,
            'discount_total' => 10,
            'grand_total' => 90,
        ]);

        $item = $this->addItem($order, 'POS-NET', 90, 30, 60);

        $refreshed = app(ProfitService::class)->refreshOrderTotals($order);

        $this->assertSame('60.00', $item->fresh()->profit_amount);
        $this->assertSame(60.0, (float) $refreshed->profit_total);
    }

    public function test_historical_discounted_line_profit_reconciliation_is_bounded_and_dry_run_safe(): void
    {
        $discounted = $this->makeOrder([
            'subtotal' => 200,
            'discount_total' => 20,
            'grand_total' => 180,
        ]);
        $first = $this->addItem($discounted, 'HISTORY-A', 100, 40, 60);
        $second = $this->addItem($discounted, 'HISTORY-B', 100, 60, 40);

        $undiscounted = $this->makeOrder();
        $this->addItem($undiscounted, 'NO-DISCOUNT', 100, 20, 80);

        $this->assertSame(0, Artisan::call('commerce:reconcile-order-line-profit', [
            '--after-id' => max(0, $discounted->id - 1),
            '--limit' => 10,
        ]));
        $dryRunOutput = Artisan::output();

        $this->assertStringContainsString('DRY-RUN complete', $dryRunOutput);
        $this->assertStringContainsString('scanned=1', $dryRunOutput);
        $this->assertStringContainsString('changed=1', $dryRunOutput);
        $this->assertStringContainsString('line-profit-changes=2', $dryRunOutput);
        $this->assertStringContainsString('applied=0', $dryRunOutput);
        $this->assertSame('60.00', $first->fresh()->profit_amount);
        $this->assertSame('40.00', $second->fresh()->profit_amount);

        $this->assertSame(0, Artisan::call('commerce:reconcile-order-line-profit', [
            '--after-id' => max(0, $discounted->id - 1),
            '--limit' => 1,
            '--apply' => true,
        ]));
        $applyOutput = Artisan::output();

        $this->assertStringContainsString('APPLY complete', $applyOutput);
        $this->assertStringContainsString('scanned=1', $applyOutput);
        $this->assertStringContainsString('applied=1', $applyOutput);
        $this->assertSame('50.00', $first->fresh()->profit_amount);
        $this->assertSame('30.00', $second->fresh()->profit_amount);
        $this->assertSame('80.00', $undiscounted->items()->firstOrFail()->profit_amount);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'PROFIT-DISCOUNT-'.Str::upper(Str::random(8)),
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
            'customer_name' => 'Profit Test Customer',
            'customer_email' => Str::lower(Str::random(8)).'@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ], $overrides));
    }

    private function addItem(
        Order $order,
        string $sku,
        float $lineTotal,
        float $unitCost,
        float $profitAmount
    ) {
        return $order->items()->create([
            'product_name' => 'Profit item '.$sku,
            'sku' => $sku,
            'unit_price' => $lineTotal,
            'unit_cost' => $unitCost,
            'quantity' => 1,
            'line_total' => $lineTotal,
            'profit_amount' => $profitAmount,
        ]);
    }
}
