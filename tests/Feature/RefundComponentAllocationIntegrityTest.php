<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\OrderActionService;
use App\Services\Commerce\OrderRevenueAllocationService;
use App\Services\Commerce\RefundAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundComponentAllocationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_refund_preserves_merchandise_first_product_allocation(): void
    {
        $order = $this->shippingOrder();
        $this->addMerchandiseLines($order);
        $order->refunds()->create([
            'amount' => 30,
            'reason' => 'Legacy refund',
            'processed_at' => now(),
        ]);
        $order->update([
            'refund_total' => 30,
            'commercial_refund_total' => 30,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ]);

        $allocations = app(OrderRevenueAllocationService::class)
            ->allocateCents($order->fresh(['items']));

        $this->assertSame(6000, array_sum($allocations));
        $this->assertSame([3600, 2400], array_values($allocations));
    }

    public function test_shipping_and_tax_refunds_do_not_reduce_product_revenue(): void
    {
        $shippingRefund = $this->shippingOrder();
        $this->addMerchandiseLines($shippingRefund);
        $shippingRefund->refunds()->create([
            'amount' => 20,
            'allocation' => $this->allocation('shipping', shipping: 20),
            'reason' => 'Shipping refund',
            'processed_at' => now(),
        ]);
        $shippingRefund->update([
            'refund_total' => 20,
            'commercial_refund_total' => 20,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ]);

        $shippingAllocations = app(OrderRevenueAllocationService::class)
            ->allocateCents($shippingRefund->fresh(['items']));

        $this->assertSame(9000, array_sum($shippingAllocations));
        $this->assertSame(95.0, $shippingRefund->fresh()->realized_revenue);

        $taxRefund = $this->shippingOrder();
        $this->addMerchandiseLines($taxRefund);
        $taxRefund->refunds()->create([
            'amount' => 5,
            'allocation' => $this->allocation('tax', tax: 5),
            'reason' => 'Tax refund',
            'processed_at' => now(),
        ]);
        $taxRefund->update([
            'refund_total' => 5,
            'commercial_refund_total' => 5,
            'payment_status' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ]);

        $taxAllocations = app(OrderRevenueAllocationService::class)
            ->allocateCents($taxRefund->fresh(['items']));

        $this->assertSame(9000, array_sum($taxAllocations));
        $this->assertSame(110.0, $taxRefund->fresh()->realized_revenue);
    }

    public function test_explicit_payment_excess_refund_preserves_commercial_revenue(): void
    {
        $order = $this->makeOrder(100);
        $order->update([
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_ONLINE,
        ]);
        $this->addLine($order, 'OVERPAY', 100, 30);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_ONLINE,
            'provider' => 'test',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'OVERPAY-'.Str::upper(Str::random(8)),
            'amount' => 120,
            'currency' => 'EGP',
            'paid_at' => now(),
        ]);

        $result = app(OrderActionService::class)->refund(
            $order,
            20,
            'Return payment excess',
            null,
            null,
            null,
            null,
            RefundAllocationService::SCOPE_PAYMENT_EXCESS,
        );

        $refund = $result['refund']->fresh();
        $freshOrder = $order->fresh(['items']);

        $this->assertSame('20.00', data_get($refund->allocation, 'payment_excess_amount'));
        $this->assertSame('0.00', data_get($refund->allocation, 'merchandise_amount'));
        $this->assertSame(20.0, (float) $freshOrder->refund_total);
        $this->assertSame(0.0, (float) $freshOrder->commercial_refund_total);
        $this->assertSame(100.0, $freshOrder->realized_revenue);
        $this->assertSame(
            10000,
            array_sum(app(OrderRevenueAllocationService::class)->allocateCents($freshOrder))
        );
    }

    public function test_automatic_refund_preserves_historical_order_value_sequence(): void
    {
        $order = $this->shippingOrder();
        $this->addMerchandiseLines($order);
        Payment::query()->create([
            'order_id' => $order->id,
            'method' => $order->payment_method,
            'provider' => 'test',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'AUTO-'.Str::upper(Str::random(8)),
            'amount' => 115,
            'currency' => 'EGP',
            'paid_at' => now(),
        ]);

        $allocation = app(RefundAllocationService::class)->allocateNewRefund(
            $order,
            100,
            RefundAllocationService::SCOPE_ORDER,
        );

        $this->assertSame('90.00', $allocation['merchandise_amount']);
        $this->assertSame('10.00', $allocation['shipping_amount']);
        $this->assertSame('0.00', $allocation['tax_amount']);
        $this->assertSame('0.00', $allocation['payment_excess_amount']);
    }

    public function test_specific_refund_component_cannot_exceed_remaining_value(): void
    {
        $order = $this->shippingOrder();
        $this->addMerchandiseLines($order);

        try {
            app(RefundAllocationService::class)->allocateNewRefund(
                $order,
                20.01,
                RefundAllocationService::SCOPE_SHIPPING,
            );
            $this->fail('Shipping refund must not exceed the remaining shipping value.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocation_scope', $exception->errors());
        }
    }

    private function shippingOrder(): Order
    {
        $order = $this->makeOrder(115);
        $order->update([
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'subtotal' => 100,
            'discount_total' => 10,
            'shipping_total' => 20,
            'tax_total' => 5,
            'grand_total' => 115,
        ]);

        return $order;
    }

    private function makeOrder(float $grandTotal): Order
    {
        return Order::query()->create([
            'order_number' => 'REF-COMP-'.Str::upper(Str::random(8)),
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => $grandTotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'refund_total' => 0,
            'commercial_refund_total' => 0,
            'customer_name' => 'Refund Component Customer',
            'customer_email' => Str::lower(Str::random(8)).'@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now(),
        ]);
    }

    private function addMerchandiseLines(Order $order): void
    {
        $this->addLine($order, 'MERCH-A', 60, 10);
        $this->addLine($order, 'MERCH-B', 40, 10);
    }

    private function addLine(Order $order, string $sku, float $lineTotal, float $unitCost): void
    {
        $order->items()->create([
            'product_name' => 'Refund component '.$sku,
            'sku' => $sku,
            'unit_price' => $lineTotal,
            'unit_cost' => $unitCost,
            'quantity' => 1,
            'line_total' => $lineTotal,
            'profit_amount' => round($lineTotal - $unitCost, 2),
        ]);
    }

    private function allocation(
        string $scope,
        float $merchandise = 0,
        float $shipping = 0,
        float $tax = 0,
        float $paymentExcess = 0,
    ): array {
        return [
            'version' => 1,
            'scope' => $scope,
            'merchandise_amount' => number_format($merchandise, 2, '.', ''),
            'shipping_amount' => number_format($shipping, 2, '.', ''),
            'tax_amount' => number_format($tax, 2, '.', ''),
            'payment_excess_amount' => number_format($paymentExcess, 2, '.', ''),
        ];
    }
}
