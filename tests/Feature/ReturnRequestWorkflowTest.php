<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Services\Commerce\ReturnRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeDeliveredPaidOrder(User $user, float $total): Order
    {
        return Order::query()->create([
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
