<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Commerce\ReturnRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_return_only_for_own_paid_completed_order(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        [$order, $item] = $this->makePaidCompletedOrder($customer, 2);

        $service = app(ReturnRequestService::class);

        $this->assertTrue($service->canCustomerRequest($order, $customer));
        $this->assertFalse($service->canCustomerRequest($order, $other));

        $unpaid = $this->makeOrder($customer, [
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_COMPLETED,
        ]);
        $this->assertFalse($service->canCustomerRequest($unpaid, $customer));

        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DEFECTIVE,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $this->assertSame(ReturnRequest::STATUS_REQUESTED, $return->status);
        $this->assertSame(1, $return->items->first()->requested_quantity);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'return_requested',
            'subject_id' => $return->id,
        ]);
    }

    public function test_active_return_requests_reserve_returnable_quantity_and_cancel_releases_it(): void
    {
        $customer = User::factory()->create();
        [$order, $item] = $this->makePaidCompletedOrder($customer, 2);
        $service = app(ReturnRequestService::class);

        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 2,
            'reason_code' => ReturnRequestItem::REASON_CHANGED_MIND,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $this->assertSame(0, $service->remainingReturnableQuantity($item));

        try {
            $service->createForCustomer($order, $customer, [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'reason_code' => ReturnRequestItem::REASON_OTHER,
                'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
            ]]);
            $this->fail('Active return quantity must prevent over-return.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $service->cancelByCustomer($return, $customer);

        $this->assertSame(2, $service->remainingReturnableQuantity($item));
        $this->assertSame(ReturnRequest::STATUS_CANCELLED, $return->fresh()->status);
    }

    public function test_rejected_return_releases_returnable_quantity(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create();
        $manager = $this->staffWithRole('operations_manager');
        [$order, $item] = $this->makePaidCompletedOrder($customer, 2);
        $service = app(ReturnRequestService::class);

        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 2,
            'reason_code' => ReturnRequestItem::REASON_OTHER,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $service->reject($return, 'Return does not meet the accepted condition.', $manager);

        $this->assertSame(2, $service->remainingReturnableQuantity($item));
        $this->assertSame(ReturnRequest::STATUS_REJECTED, $return->fresh()->status);
    }

    public function test_partial_approval_does_not_restore_inventory_and_only_approved_quantity_remains_reserved(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create();
        $manager = $this->staffWithRole('operations_manager');
        [$order, $item, $product] = $this->makePaidCompletedOrder($customer, 3, 7);
        $service = app(ReturnRequestService::class);

        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 3,
            'reason_code' => ReturnRequestItem::REASON_DAMAGED,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $service->approve(
            $return,
            [$return->items->first()->id => 2],
            'Approve two units.',
            $manager
        );

        $returnItem = $return->fresh('items')->items->first();

        $this->assertSame(2, $returnItem->approved_quantity);
        $this->assertSame(1, $service->remainingReturnableQuantity($item));
        $this->assertSame(7, (int) $product->fresh()->quantity);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovement::TYPE_RETURN_RESTOCK,
        ]);
    }

    public function test_receive_requires_full_approved_quantity_and_restocks_only_explicit_quantity_once(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create();
        $manager = $this->staffWithRole('operations_manager');
        [$order, $item, $product] = $this->makePaidCompletedOrder($customer, 2, 5);
        $service = app(ReturnRequestService::class);

        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 2,
            'reason_code' => ReturnRequestItem::REASON_DEFECTIVE,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $service->approve($return, [$return->items->first()->id => 2], null, $manager);
        $returnItem = $return->fresh('items')->items->first();

        try {
            $service->receive(
                $return->fresh(),
                [$returnItem->id => 1],
                [$returnItem->id => 1],
                $manager
            );
            $this->fail('V1 partial physical receipt must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(5, (int) $product->fresh()->quantity);

        $service->receive(
            $return->fresh(),
            [$returnItem->id => 2],
            [$returnItem->id => 1],
            $manager
        );

        $this->assertSame(6, (int) $product->fresh()->quantity);
        $this->assertSame(ReturnRequest::STATUS_RECEIVED, $return->fresh()->status);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_RETURN_RESTOCK,
            'quantity_change' => 1,
        ]);

        try {
            $service->receive(
                $return->fresh(),
                [$returnItem->id => 2],
                [$returnItem->id => 1],
                $manager
            );
            $this->fail('A received RMA must not restock twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(6, (int) $product->fresh()->quantity);
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('order_id', $order->id)
                ->where('type', InventoryMovement::TYPE_RETURN_RESTOCK)
                ->count()
        );
    }

    public function test_completion_links_explicit_refund_to_rma_and_uses_existing_refund_ledger(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create();
        $manager = $this->staffWithRole('operations_manager');
        [$order, $item] = $this->makePaidCompletedOrder($customer, 1);
        $service = app(ReturnRequestService::class);

        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_WRONG_ITEM,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $service->approve($return, [$return->items->first()->id => 1], null, $manager);
        $returnItem = $return->fresh('items')->items->first();
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);

        $service->complete(
            $return->fresh(),
            50,
            null,
            'Explicit refund approved after physical receipt.',
            $manager
        );

        $fresh = $return->fresh(['refunds']);

        $this->assertSame(ReturnRequest::STATUS_COMPLETED, $fresh->status);
        $this->assertSame(50.0, (float) $fresh->refunds->first()->amount);
        $this->assertSame($return->id, $fresh->refunds->first()->return_request_id);
        $this->assertSame(50.0, (float) $order->fresh()->refund_total);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);
    }

    public function test_completion_can_link_exchange_order_without_assuming_refund_amount(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create();
        $manager = $this->staffWithRole('operations_manager');
        [$order, $item] = $this->makePaidCompletedOrder($customer, 1);
        $exchange = $this->makeOrder($customer, ['order_number' => 'EXCHANGE-' . Str::upper(Str::random(6))]);

        $service = app(ReturnRequestService::class);
        $return = $service->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_DEFECTIVE,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_EXCHANGE,
        ]]);

        $service->approve($return, [$return->items->first()->id => 1], null, $manager);
        $returnItem = $return->fresh('items')->items->first();
        $service->receive($return->fresh(), [$returnItem->id => 1], [$returnItem->id => 0], $manager);
        $service->complete($return->fresh(), 0, $exchange->id, null, $manager);

        $this->assertSame($exchange->id, $return->fresh()->exchange_order_id);
        $this->assertDatabaseCount('order_refunds', 0);
    }

    public function test_admin_return_workspace_is_permission_scoped_and_live_fragment_is_available(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $customer = User::factory()->create();
        $manager = $this->staffWithRole('operations_manager');
        $cashier = $this->staffWithRole('cashier');
        [$order, $item] = $this->makePaidCompletedOrder($customer, 1);

        app(ReturnRequestService::class)->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_OTHER,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $this->actingAs($manager)
            ->get(route('admin.returns.index'))
            ->assertOk()
            ->assertSee('RMA-');

        $this->get(route('admin.returns.index'), ['X-Live-List' => '1'])
            ->assertOk()
            ->assertSee('data-live-results', false);

        $this->actingAs($cashier)
            ->get(route('admin.returns.index'))
            ->assertForbidden();
    }

    public function test_customer_cannot_open_or_cancel_another_customers_return(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        [$order, $item] = $this->makePaidCompletedOrder($customer, 1);

        $return = app(ReturnRequestService::class)->createForCustomer($order, $customer, [[
            'order_item_id' => $item->id,
            'quantity' => 1,
            'reason_code' => ReturnRequestItem::REASON_OTHER,
            'requested_resolution' => ReturnRequestItem::RESOLUTION_REFUND,
        ]]);

        $this->actingAs($other)
            ->get(route('returns.show', $return))
            ->assertForbidden();

        $this->patch(route('returns.cancel', $return))
            ->assertForbidden();

        $this->assertSame(ReturnRequest::STATUS_REQUESTED, $return->fresh()->status);
    }

    private function makePaidCompletedOrder(User $customer, int $quantity, int $stock = 10): array
    {
        $product = $this->makeProduct($stock);
        $order = $this->makeOrder($customer, [
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
            'delivered_at' => now(),
            'subtotal' => 100 * $quantity,
            'grand_total' => 100 * $quantity,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'unit_cost' => 40,
            'quantity' => $quantity,
            'line_total' => 100 * $quantity,
            'profit_amount' => 60 * $quantity,
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => Order::PAYMENT_METHOD_COD,
            'provider' => 'test',
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'PAY-' . Str::upper(Str::random(8)),
            'amount' => $order->grand_total,
            'currency' => 'EGP',
            'paid_at' => now(),
        ]);

        return [$order, $item, $product];
    }

    private function makeOrder(User $customer, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'RMA-ORDER-' . Str::upper(Str::random(8)),
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'delivery_status' => Order::DELIVERY_STATUS_SHIPPED,
            'delivery_method' => Order::DELIVERY_METHOD_STANDARD,
            'currency' => 'EGP',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'refund_total' => 0,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'RMA Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
            'billing_same_as_shipping' => true,
            'placed_at' => now()->subDays(3),
        ], $overrides));
    }

    private function makeProduct(int $quantity): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'RMA Category ' . Str::random(6),
            'slug' => 'rma-category-' . Str::lower(Str::random(8)),
            'description' => 'RMA test category',
            'meta_title' => 'RMA',
            'meta_keyword' => 'rma',
            'meta_description' => 'RMA test category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'RMA Product ' . Str::random(6),
            'slug' => 'rma-product-' . Str::lower(Str::random(8)),
            'sku' => 'RMA-' . Str::upper(Str::random(6)),
            'category_id' => $categoryId,
            'base_price' => 100,
            'cost_price' => 40,
            'quantity' => $quantity,
            'status' => true,
            'has_variants' => false,
        ]);
    }

    private function staffWithRole(string $slug): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }
}
