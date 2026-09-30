<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Services\Commerce\OrderActionService;
use App\Services\Commerce\OrderRevenueAllocationService;
use App\Services\Commerce\PosReturnService;
use App\Services\Commerce\ProfitService;
use App\Services\Commerce\ReturnRequestService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_preserves_return_request_item_history_from_direct_parent_deletion(): void
    {
        $customer = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $return = app(ReturnRequestService::class)->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DEFECTIVE,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);
        $returnItemId = $return->items()->firstOrFail()->id;

        try {
            DB::table('return_requests')->where('id', $return->id)->delete();
            $this->fail('Database must preserve RMA item history from direct parent deletion.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('return_requests', ['id' => $return->id]);
        $this->assertDatabaseHas('return_request_items', [
            'id' => $returnItemId,
            'return_request_id' => $return->id,
        ]);
    }

    public function test_received_rma_item_lifecycle_history_is_immutable(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DEFECTIVE,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);
        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);

        $receivedItem = $returnItem->fresh();

        try {
            $receivedItem->update(['received_quantity' => 0]);
            $this->fail('Received RMA quantities must be immutable after physical receipt.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        try {
            $receivedItem->fresh()->delete();
            $this->fail('RMA item history must not be deletable after receipt.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('return_request_items', [
            'id' => $returnItem->id,
            'received_quantity' => 1,
            'restock_quantity' => 0,
        ]);
        $this->assertSame(ReturnRequest::STATUS_RECEIVED, $return->fresh()->status);
    }

    public function test_refund_requires_same_order_rma_and_idempotency_includes_rma_identity(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeDeliveredPaidOrder($customer, 200);
        $otherOrder = $this->makeDeliveredPaidOrder($customer, 200);

        $rmaA = ReturnRequest::query()->create([
            'reference' => 'RMA-OWN-A-'.Str::upper(Str::random(6)),
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => ReturnRequest::STATUS_RECEIVED,
            'requested_at' => now(),
            'received_at' => now(),
        ]);
        $rmaB = ReturnRequest::query()->create([
            'reference' => 'RMA-OWN-B-'.Str::upper(Str::random(6)),
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => ReturnRequest::STATUS_RECEIVED,
            'requested_at' => now(),
            'received_at' => now(),
        ]);
        $foreignRma = ReturnRequest::query()->create([
            'reference' => 'RMA-FOREIGN-'.Str::upper(Str::random(6)),
            'order_id' => $otherOrder->id,
            'user_id' => $customer->id,
            'status' => ReturnRequest::STATUS_RECEIVED,
            'requested_at' => now(),
            'received_at' => now(),
        ]);

        $service = app(OrderActionService::class);

        try {
            $service->refund($order, 10, 'Cross-order RMA', null, null, $foreignRma->id, (string) Str::uuid());
            $this->fail('Refund must reject an RMA owned by another order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }

        $key = (string) Str::uuid();
        $first = $service->refund($order, 10, 'RMA refund', null, null, $rmaA->id, $key);
        $this->assertTrue($first['created']);

        try {
            $service->refund($order->fresh(), 10, 'RMA refund', null, null, $rmaB->id, $key);
            $this->fail('Refund idempotency must include the linked RMA identity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }

        $this->assertDatabaseCount('order_refunds', 1);
        $this->assertDatabaseHas('order_refunds', [
            'order_id' => $order->id,
            'return_request_id' => $rmaA->id,
        ]);
    }

    public function test_database_rejects_return_item_owned_by_another_order(): void
    {
        $customer = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $return = app(ReturnRequestService::class)->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DEFECTIVE,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $otherOrder = $this->makeDeliveredPaidOrder($customer, 120);
        $otherItem = $otherOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 120,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 120,
            'profit_amount' => 80,
        ]);

        try {
            ReturnRequestItem::query()->create([
                'return_request_id' => $return->id,
                'order_id' => $order->id,
                'order_item_id' => $otherItem->id,
                'requested_quantity' => 1,
                'approved_quantity' => null,
                'received_quantity' => 0,
                'restock_quantity' => 0,
                'reason_code' => ReturnRequestItem::REASON_DEFECTIVE,
                'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
            ]);
            $this->fail('Database must reject an RMA item owned by another order.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('return_request_items', 1);
    }

    public function test_customer_return_lifecycle_restock_and_refund_are_linked_to_the_rma(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 200);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 2,
            'line_total' => 200,
            'profit_amount' => 120,
        ]);

        $service = app(ReturnRequestService::class);

        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 2,
            'reason_code' => ReturnRequestItem::REASON_DEFECTIVE,
            'reason_details' => 'Both units stopped working.',
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]], 'Please refund the returned units.');

        $this->assertSame(ReturnRequest::STATUS_REQUESTED, $return->status);
        $this->assertSame(0, $service->remainingReturnableQuantity($item));

        $returnItem = $return->items()->firstOrFail();

        $service->approve($return, [$returnItem->id => 2], 'Approved after review.', $manager);
        $service->receive($return->fresh(), [$returnItem->id => 2], [$returnItem->id => 1], $manager);

        $this->assertSame(1, (int) $product->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_RETURN_RESTOCK,
            'quantity_change' => 1,
        ]);

        $service->complete($return->fresh(), 200, null, 'Refunded after receiving the goods.', $manager);

        $this->assertSame(ReturnRequest::STATUS_COMPLETED, $return->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(200.0, (float) $order->fresh()->refund_total);
        $this->assertDatabaseHas('order_refunds', [
            'order_id' => $order->id,
            'return_request_id' => $return->id,
            'amount' => 200,
        ]);
    }

    public function test_rma_refund_cannot_exceed_received_refund_item_value(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 200);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 2,
            'line_total' => 200,
            'profit_amount' => 120,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);

        try {
            $service->complete($return->fresh(), 150, null, 'Attempted over-refund.', $manager);
            $this->fail('RMA refund above received item value should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund_amount', $exception->errors());
        }

        $this->assertSame(ReturnRequest::STATUS_RECEIVED, $return->fresh()->status);
        $this->assertSame(0.0, (float) $order->fresh()->refund_total);
        $this->assertDatabaseCount('order_refunds', 0);
    }

    public function test_rma_refund_respects_storefront_order_level_discount(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 160);
        $order->update([
            'subtotal' => 200,
            'discount_total' => 40,
            'grand_total' => 160,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 2,
            'line_total' => 200,
            'profit_amount' => 120,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);
        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);

        try {
            $service->complete($return->fresh(), 100, null, 'Attempted gross-price refund.', $manager);
            $this->fail('RMA refund should not exceed the discounted net value of the received unit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund_amount', $exception->errors());
        }

        $service->complete($return->fresh(), 80, null, 'Refunded at discounted net value.', $manager);

        $this->assertSame(80.0, (float) $order->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);
    }

    public function test_sequential_partial_rmas_cannot_round_past_the_paid_line_value(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 1.02);
        $order->update([
            'subtotal' => 0.02,
            'shipping_total' => 1.00,
            'grand_total' => 1.02,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 0.01,
            'unit_cost' => 0,
            'quantity' => 3,
            'line_total' => 0.02,
            'profit_amount' => 0.02,
        ]);

        $service = app(ReturnRequestService::class);
        $makeReceivedReturn = function () use ($service, $order, $customer, $manager, $item) {
            $return = $service->createForCustomer($order->fresh(), $customer, [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'reason_code' => ReturnRequestItem::REASON_DAMAGED,
                'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
            ]]);
            $returnItem = $return->items()->firstOrFail();
            $service->approve($return, [$returnItem->id => 1], null, $manager);
            $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);

            return $return->fresh();
        };

        $first = $makeReceivedReturn();
        $service->complete($first, 0.01, null, 'First rounded cent.', $manager);

        $second = $makeReceivedReturn();
        try {
            $service->complete($second, 0.01, null, 'Attempted second rounded cent.', $manager);
            $this->fail('Sequential partial RMAs must not round beyond cumulative paid line value.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund_amount', $exception->errors());
        }
        $service->complete($second->fresh(), 0, null, 'No refundable cent remains yet.', $manager);

        $third = $makeReceivedReturn();
        $service->complete($third, 0.01, null, 'Final cumulative cent.', $manager);

        $this->assertSame(0.02, (float) $order->fresh()->refund_total);
        $this->assertDatabaseCount('order_refunds', 2);
        $this->assertSame(1.00, (float) $order->fresh()->realized_revenue);
    }

    public function test_rma_refund_does_not_double_apply_pos_discounts(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 80);
        $order->update([
            'sales_channel' => Order::SALES_CHANNEL_POS,
            'subtotal' => 100,
            'discount_total' => 20,
            'grand_total' => 80,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 80,
            'profit_amount' => 40,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);
        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);
        $service->complete($return->fresh(), 80, null, 'Refunded POS net line value.', $manager);

        $this->assertSame(80.0, (float) $order->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->fresh()->payment_status);
    }

    public function test_rma_restock_fails_safe_when_variant_was_deleted(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $product->update(['has_variants' => true]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'RMA-VAR-'.Str::upper(Str::random(6)),
            'price' => 100,
            'cost_price' => 40,
            'stock' => 0,
            'is_default' => true,
            'status' => true,
            'sort_order' => 0,
        ]);

        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->sku,
            'sku' => $variant->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);

        $variant->delete();

        try {
            $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 1], $manager);
            $this->fail('RMA should not restore deleted variant stock into the product-level bucket.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(ReturnRequest::STATUS_APPROVED, $return->fresh()->status);
        $this->assertSame(0, (int) $product->fresh()->quantity);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_RETURN_RESTOCK,
        ]);
    }

    public function test_pos_return_fails_safe_when_variant_was_deleted(): void
    {
        $customer = User::factory()->create();
        $cashier = User::factory()->create();
        $product = $this->makeProduct(0);
        $product->update(['has_variants' => true]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'POS-RET-'.Str::upper(Str::random(6)),
            'price' => 100,
            'cost_price' => 40,
            'stock' => 0,
            'is_default' => true,
            'status' => true,
            'sort_order' => 0,
        ]);

        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $order->update(['sales_channel' => Order::SALES_CHANNEL_POS]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->sku,
            'sku' => $variant->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $variant->delete();

        try {
            app(PosReturnService::class)->process(
                $order->fresh(),
                [$item->id => 1],
                'Customer return',
                null,
                $cashier->id,
            );
            $this->fail('POS return should not restore deleted variant stock into the product-level bucket.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('return', $exception->errors());
        }

        $this->assertSame(0, (int) $product->fresh()->quantity);
        $this->assertDatabaseCount('order_refunds', 0);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_REFUND_RESTOCK,
        ]);
    }

    public function test_rma_cannot_reuse_quantity_already_returned_through_pos(): void
    {
        $customer = User::factory()->create();
        $cashier = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 200);
        $order->update(['sales_channel' => Order::SALES_CHANNEL_POS]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 2,
            'line_total' => 200,
            'profit_amount' => 120,
        ]);

        app(PosReturnService::class)->process(
            $order,
            [$item->id => 1],
            'Customer return',
            null,
            $cashier->id,
        );

        $service = app(ReturnRequestService::class);
        $this->assertSame(1, $service->remainingReturnableQuantity($item->fresh()));

        try {
            $service->createForCustomer($order->fresh(), $customer, [[
                'order_item_id' => $item->id,
                'quantity' => 2,
                'reason_code' => ReturnRequestItem::REASON_DAMAGED,
                'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
            ]]);
            $this->fail('RMA should not reuse quantity already returned through POS.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_mixed_pos_and_rma_returns_preserve_revenue_and_restock_cogs_economics(): void
    {
        $customer = User::factory()->create();
        $cashier = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 200);
        $order->update(['sales_channel' => Order::SALES_CHANNEL_POS]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 2,
            'line_total' => 200,
            'profit_amount' => 120,
        ]);

        app(PosReturnService::class)->process(
            $order,
            [$item->id => 1],
            'First unit returned at POS',
            null,
            $cashier->id,
        );

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order->fresh(), $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);
        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);
        $service->complete($return->fresh(), 100, null, 'Second unit refunded without restock.', $manager);

        $economics = app(ProfitService::class)->calculateOrderEconomics($order->fresh());
        $allocations = app(OrderRevenueAllocationService::class)->allocateCents($order->fresh(['items']));

        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(200.0, (float) $order->fresh()->refund_total);
        $this->assertSame('0.00', $economics['realized_revenue']);
        $this->assertSame('40.00', $economics['recovered_restock_cost']);
        $this->assertSame('40.00', $economics['realized_cogs']);
        $this->assertSame('-40.00', $economics['profit_total']);
        $this->assertSame(0, array_sum($allocations));
        $this->assertSame(1, (int) $product->fresh()->quantity);
    }

    public function test_sequential_pos_returns_cannot_round_past_the_paid_line_value(): void
    {
        $customer = User::factory()->create();
        $cashier = User::factory()->create();
        $tinyProduct = $this->makeProduct(0);
        $otherProduct = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 1.02);
        $order->update(['sales_channel' => Order::SALES_CHANNEL_POS]);

        $tinyItem = $order->items()->create([
            'product_id' => $tinyProduct->id,
            'product_name' => $tinyProduct->name,
            'sku' => $tinyProduct->sku,
            'unit_price' => 0.01,
            'unit_cost' => 0,
            'quantity' => 3,
            'line_total' => 0.02,
            'profit_amount' => 0.02,
        ]);
        $order->items()->create([
            'product_id' => $otherProduct->id,
            'product_name' => $otherProduct->name,
            'sku' => $otherProduct->sku,
            'unit_price' => 1.00,
            'unit_cost' => 0,
            'quantity' => 1,
            'line_total' => 1.00,
            'profit_amount' => 1.00,
        ]);

        $service = app(PosReturnService::class);
        $service->process($order, [$tinyItem->id => 1], 'First tiny-unit return', null, $cashier->id);
        $service->process($order->fresh(), [$tinyItem->id => 1], 'Second tiny-unit return', null, $cashier->id);
        $service->process($order->fresh(), [$tinyItem->id => 1], 'Third tiny-unit return', null, $cashier->id);

        $amounts = $order->fresh()->refunds()->orderBy('id')->pluck('amount')->map(fn ($amount) => (float) $amount)->all();

        $this->assertSame([0.01, 0.0, 0.01], $amounts);
        $this->assertSame(0.02, (float) $order->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(3, (int) $tinyProduct->fresh()->quantity);
        $this->assertSame(3, (int) $order->refunds()->count());
        $this->assertSame(0.02, (float) $order->refunds()->sum('amount'));
        $this->assertSame(0.02, (float) \App\Models\PosReturnItem::query()
            ->where('order_item_id', $tinyItem->id)
            ->sum('amount'));
    }

    public function test_fully_refunded_order_can_record_physical_rma_return_without_second_refund(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        app(OrderActionService::class)->refund(
            $order,
            100,
            'Refund issued before goods arrived',
            null,
            $manager->id,
            null,
            (string) Str::uuid()
        );

        $service = app(ReturnRequestService::class);
        $this->assertTrue($service->canCustomerRequest($order->fresh(), $customer));

        $return = $service->createForCustomer($order->fresh(), $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);
        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 1], $manager);
        $service->complete($return->fresh(), 0, null, 'Physical goods received after prior full refund.', $manager);

        $this->assertSame(ReturnRequest::STATUS_COMPLETED, $return->fresh()->status);
        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(100.0, (float) $order->fresh()->refund_total);
        $this->assertSame(1, (int) $product->fresh()->quantity);
        $this->assertSame(1, (int) $order->refunds()->count());
    }

    public function test_fully_refunded_pos_sale_can_restock_physical_return_without_second_cash_refund(): void
    {
        $customer = User::factory()->create();
        $cashier = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $order->update([
            'sales_channel' => Order::SALES_CHANNEL_POS,
            'payment_method' => Order::PAYMENT_METHOD_POS_CASH,
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        app(OrderActionService::class)->refund(
            $order,
            100,
            'Refund issued before counter return',
            null,
            $cashier->id,
            null,
            (string) Str::uuid()
        );

        app(PosReturnService::class)->process(
            $order->fresh(),
            [$item->id => 1],
            'Physical item returned after refund',
            null,
            $cashier->id,
        );

        $refunds = $order->fresh()->refunds()->orderBy('id')->pluck('amount')->map(fn ($amount) => (float) $amount)->all();

        $this->assertSame([100.0, 0.0], $refunds);
        $this->assertSame(Order::PAYMENT_STATUS_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(100.0, (float) $order->fresh()->refund_total);
        $this->assertSame(1, (int) $product->fresh()->quantity);
        $this->assertSame(1, (int) \App\Models\PosReturnItem::query()->where('order_item_id', $item->id)->sum('quantity'));
    }

    public function test_pos_return_cannot_reuse_quantity_reserved_by_rma(): void
    {
        $customer = User::factory()->create();
        $cashier = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 200);
        $order->update(['sales_channel' => Order::SALES_CHANNEL_POS]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 2,
            'line_total' => 200,
            'profit_amount' => 120,
        ]);

        app(ReturnRequestService::class)->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        try {
            app(PosReturnService::class)->process(
                $order->fresh(),
                [$item->id => 2],
                'Customer return',
                null,
                $cashier->id,
            );
            $this->fail('POS return should not reuse quantity reserved by an RMA.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertDatabaseCount('order_refunds', 0);
    }

    public function test_exchange_order_must_belong_to_same_customer(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $otherOrder = $this->makeDeliveredPaidOrder($otherCustomer, 100);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_WRONG_ITEM,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_EXCHANGE,
        ]]);

        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);

        try {
            $service->complete($return->fresh(), 0, $otherOrder->id, null, $manager);
            $this->fail('Exchange order owned by another customer should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('exchange_order_id', $exception->errors());
        }

        $this->assertSame(ReturnRequest::STATUS_RECEIVED, $return->fresh()->status);
        $this->assertNull($return->fresh()->exchange_order_id);
    }



    public function test_cancelled_order_cannot_be_used_as_exchange_order(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $cancelledExchange = $this->makeDeliveredPaidOrder($customer, 100);
        $cancelledExchange->update(['status' => Order::STATUS_CANCELLED]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_WRONG_ITEM,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_EXCHANGE,
        ]]);

        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);

        try {
            $service->complete($return->fresh(), 0, $cancelledExchange->id, null, $manager);
            $this->fail('Cancelled orders must not be accepted as exchange orders.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('exchange_order_id', $exception->errors());
        }

        $this->assertSame(ReturnRequest::STATUS_RECEIVED, $return->fresh()->status);
        $this->assertNull($return->fresh()->exchange_order_id);
    }

    public function test_database_preserves_completed_rma_exchange_order_link(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $exchangeOrder = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_WRONG_ITEM,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_EXCHANGE,
        ]]);
        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);
        $service->complete($return->fresh(), 0, $exchangeOrder->id, null, $manager);

        try {
            Order::query()->whereKey($exchangeOrder->id)->delete();
            $this->fail('Database must preserve exchange orders linked to completed RMAs.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('orders', ['id' => $exchangeOrder->id]);
        $this->assertSame($exchangeOrder->id, $return->fresh()->exchange_order_id);
    }

    public function test_completed_return_request_history_is_immutable(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $exchangeOrder = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_WRONG_ITEM,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_EXCHANGE,
        ]]);
        $returnItem = $return->items()->firstOrFail();
        $service->approve($return, [$returnItem->id => 1], null, $manager);
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);
        $service->complete($return->fresh(), 0, $exchangeOrder->id, null, $manager);

        $completed = $return->fresh();

        foreach ([
            ['status' => ReturnRequest::STATUS_RECEIVED],
            ['exchange_order_id' => null],
            ['completed_at' => null],
        ] as $mutation) {
            try {
                $completed->fresh()->update($mutation);
                $this->fail('Completed return request history must be immutable.');
            } catch (\LogicException) {
                $this->assertTrue(true);
            }
        }

        try {
            $completed->fresh()->delete();
            $this->fail('Completed return request history must not be deletable.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        $fresh = $return->fresh();
        $this->assertSame(ReturnRequest::STATUS_COMPLETED, $fresh->status);
        $this->assertSame($exchangeOrder->id, $fresh->exchange_order_id);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_exchange_order_cannot_settle_multiple_return_requests(): void
    {
        $customer = User::factory()->create();
        $manager = User::factory()->create();
        $product = $this->makeProduct(0);
        $firstOrder = $this->makeDeliveredPaidOrder($customer, 100);
        $secondOrder = $this->makeDeliveredPaidOrder($customer, 100);
        $exchangeOrder = $this->makeDeliveredPaidOrder($customer, 100);

        $makeItem = function (Order $order) use ($product) {
            return $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => 100,
                'unit_cost' => 40,
                'quantity' => 1,
                'line_total' => 100,
                'profit_amount' => 60,
            ]);
        };

        $service = app(ReturnRequestService::class);
        $returns = collect([$firstOrder, $secondOrder])->map(function (Order $order) use ($service, $customer, $manager, $makeItem) {
            $item = $makeItem($order);
            $return = $service->createForCustomer($order, $customer, [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'reason_code' => ReturnRequestItem::REASON_WRONG_ITEM,
                'requested_resolution' => ReturnRequestItem::RESOLUTION_EXCHANGE,
            ]]);
            $returnItem = $return->items()->firstOrFail();
            $service->approve($return, [$returnItem->id => 1], null, $manager);
            $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);

            return $return->fresh();
        });

        $firstReturn = $returns->get(0);
        $secondReturn = $returns->get(1);
        $service->complete($firstReturn, 0, $exchangeOrder->id, null, $manager);

        try {
            $service->complete($secondReturn, 0, $exchangeOrder->id, null, $manager);
            $this->fail('An exchange order must not settle more than one return request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('exchange_order_id', $exception->errors());
        }

        $this->assertSame($exchangeOrder->id, $firstReturn->fresh()->exchange_order_id);
        $this->assertSame(ReturnRequest::STATUS_RECEIVED, $secondReturn->fresh()->status);
        $this->assertNull($secondReturn->fresh()->exchange_order_id);

        try {
            ReturnRequest::query()
                ->whereKey($secondReturn->id)
                ->update(['exchange_order_id' => $exchangeOrder->id]);
            $this->fail('Database uniqueness must prevent exchange order reuse.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertNull($secondReturn->fresh()->exchange_order_id);
    }

    public function test_customer_can_create_return_through_live_endpoint(): void
    {
        $customer = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $response = $this->actingAs($customer)->postJson(route('returns.store', $order), [
            'customer_notes' => 'Live return request.',
            'items' => [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'reason_code' => ReturnRequestItem::REASON_DAMAGED,
                'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
            ]],
        ], ['X-Return-Live' => '1']);

        $response->assertCreated()
            ->assertJsonPath('message', 'Return request submitted successfully.')
            ->assertJsonPath('return.status', ReturnRequest::STATUS_REQUESTED);
        $this->assertDatabaseHas('return_requests', [
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'status' => ReturnRequest::STATUS_REQUESTED,
        ]);
    }

    public function test_customer_can_cancel_requested_return_through_live_endpoint(): void
    {
        $customer = User::factory()->create();
        $product = $this->makeProduct(0);
        $order = $this->makeDeliveredPaidOrder($customer, 100);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => 1,
            'line_total' => 100,
            'profit_amount' => 60,
        ]);

        $return = app(ReturnRequestService::class)->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $response = $this->actingAs($customer)
            ->patchJson(route('returns.cancel', $return), [], ['X-Return-Cancel-Live' => '1']);

        $response->assertOk()
            ->assertJsonPath('message', 'Return request cancelled.')
            ->assertJsonPath('return.status', ReturnRequest::STATUS_CANCELLED)
            ->assertJsonPath('return.status_label', $return->fresh()->status_label);

        $this->assertSame(ReturnRequest::STATUS_CANCELLED, $return->fresh()->status);
    }


    public function test_return_create_live_submission_has_duplicate_guard_and_integer_quantity_semantics(): void
    {
        $view = file_get_contents(resource_path('views/frontend/returns/create.blade.php'));

        $this->assertStringContainsString('step="1" inputmode="numeric" data-return-quantity', $view);
        $this->assertStringContainsString('data-return-reason', $view);
        $this->assertStringContainsString('const syncReasonRequirement = (quantityInput) => {', $view);
        $this->assertStringContainsString('reason.required = required;', $view);
        $this->assertStringContainsString("reason.setAttribute('aria-required', required ? 'true' : 'false');", $view);
        $this->assertStringContainsString("if (form.dataset.pending === '1') return;", $view);
        $this->assertStringContainsString("form.dataset.pending = '1';", $view);
        $this->assertStringContainsString("form.setAttribute('aria-busy', 'true');", $view);
        $this->assertStringContainsString("button.setAttribute('aria-disabled', 'true');", $view);
        $this->assertStringContainsString("delete form.dataset.pending;", $view);
        $this->assertStringContainsString("form.removeAttribute('aria-busy');", $view);
        $this->assertStringContainsString("button.dataset.loadingText || @json(__('Submitting...'))", $view);
    }


    public function test_return_cancel_live_submission_resets_confirmation_and_recovers_without_duplicate_patch(): void
    {
        $view = file_get_contents(resource_path('views/frontend/returns/show.blade.php'));

        $this->assertStringContainsString('data-loading-text="{{ __(\'Cancelling...\') }}"', $view);
        $this->assertStringContainsString("if (!form || typeof window.fetch !== 'function') return;", $view);
        $this->assertStringContainsString("if (form.dataset.pending === '1') {", $view);
        $this->assertStringContainsString('delete form.dataset.confirmed;', $view);
        $this->assertStringContainsString("form.dataset.pending = '1';", $view);
        $this->assertStringContainsString("form.setAttribute('aria-busy', 'true');", $view);
        $this->assertStringContainsString("form.classList.add('lc-loading');", $view);
        $this->assertStringContainsString("button.setAttribute('aria-disabled', 'true');", $view);
        $this->assertStringContainsString('delete form.dataset.pending;', $view);
        $this->assertStringContainsString("form.removeAttribute('aria-busy');", $view);
        $this->assertStringContainsString("button.removeAttribute('aria-disabled');", $view);
        $this->assertStringContainsString('button.innerHTML = originalButtonHtml;', $view);
        $this->assertStringContainsString("const payload = await response.json().catch(() => ({}));", $view);
        $this->assertStringContainsString("Object.values(payload.errors || {}).flat()[0]", $view);
        $this->assertStringNotContainsString("throw new Error('return-cancel-failed');", $view);
        $this->assertStringNotContainsString('form.submit();', $view);

        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(
            'تعذر إلغاء طلب الإرجاع. حاول مرة أخرى.',
            $arabic['Could not cancel the return request. Please try again.'] ?? null
        );
    }

    private function makeDeliveredPaidOrder(User $user, float $total): Order
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'RMA-ORDER-'.Str::upper(Str::random(8)),
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => $total,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'RMA Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'refund_total' => 0,
            'placed_at' => now(),
            'delivered_at' => now(),
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_COD,
            'provider' => 'test',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'RMA-PAY-'.Str::upper(Str::random(10)),
            'amount' => $total,
            'currency' => 'EGP',
            'paid_at' => now(),
        ]);

        return $order;
    }

    private function makeProduct(int $quantity): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'RMA Category '.Str::random(6),
            'slug' => 'rma-category-'.Str::lower(Str::random(8)),
            'description' => 'RMA test category',
            'meta_title' => 'RMA',
            'meta_keyword' => 'rma',
            'meta_description' => 'RMA test category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'RMA Product '.Str::random(6),
            'slug' => 'rma-product-'.Str::lower(Str::random(8)),
            'sku' => 'RMA-'.Str::upper(Str::random(6)),
            'category_id' => $categoryId,
            'base_price' => 100,
            'cost_price' => 40,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
