<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReceivingProgress;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Commerce\InventoryAdjustmentService;
use App\Services\Commerce\PurchaseReceiptReversalService;
use App\Services\Commerce\PurchaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseReceivingHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_adds_each_line_once_and_replay_does_not_change_stock_or_cost(): void
    {
        $product = $this->product(5);
        $purchase = $this->purchase();
        $first = $this->item($purchase, $product, 2, 10);
        $second = $this->item($purchase, $product, 3, 12);

        $service = app(PurchaseService::class);
        $this->assertTrue($service->receive($purchase));
        $receivedDate = $purchase->fresh()->received_date->toDateString();

        $this->assertSame(10, $product->fresh()->quantity);
        $this->assertSame('5.00', $product->fresh()->cost_price);
        $this->assertSame('8.10', $product->fresh()->inventory_cost_price);
        $this->assertSame(Purchase::STATUS_RECEIVED, $purchase->fresh()->status);
        $movements = InventoryMovement::where('purchase_id', $purchase->id)->orderBy('id')->get();
        $this->assertSame([2, 3], $movements->pluck('quantity_change')->all());
        $this->assertSame([7, 10], $movements->pluck('balance_after')->all());
        $this->assertSame([$first->id, $second->id], $movements->pluck('meta')->map(fn ($meta) => $meta['purchase_item_id'])->all());
        $this->assertSame(['10.00', '12.00'], $movements->pluck('unit_cost')->all());
        $this->assertSame([6.43, 8.10], $movements->pluck('meta')->map(fn ($meta) => (float) $meta['valuation_cost_after'])->all());
        $this->assertTrue($movements->every(fn ($movement) => $movement->meta['valuation_method'] === 'moving_weighted_average'));
        $this->assertTrue($movements->every(fn ($movement) => $movement->type === InventoryMovement::TYPE_PURCHASE_IN));

        $this->assertFalse($service->receive($purchase));
        $this->assertSame(10, $product->fresh()->quantity);
        $this->assertSame('5.00', $product->fresh()->cost_price);
        $this->assertSame('8.10', $product->fresh()->inventory_cost_price);
        $this->assertSame($receivedDate, $purchase->fresh()->received_date->toDateString());
        $this->assertSame(2, InventoryMovement::where('purchase_id', $purchase->id)->count());
    }

    public function test_partial_receiving_service_rejects_fractional_scientific_and_overflow_quantities(): void
    {
        $product = $this->product(5);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);
        $service = app(PurchaseService::class);

        foreach (['1.9', '1e1', '4294967296'] as $invalidQuantity) {
            try {
                $service->receivePartial(
                    $purchase,
                    [$item->id => $invalidQuantity],
                    (string) Str::uuid()
                );
                $this->fail('Invalid partial receipt quantity must not be coerced into an integer.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey("items.{$item->id}", $exception->errors());
            }
        }

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(0, (int) $item->fresh()->received_quantity);
        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->assertTrue($service->receivePartial(
            $purchase,
            [$item->id => '1'],
            (string) Str::uuid()
        ));
        $this->assertSame(6, (int) $product->fresh()->quantity);
        $this->assertSame(1, (int) $item->fresh()->received_quantity);
    }

    public function test_receiving_rejects_purchase_whose_order_date_is_still_in_the_future(): void
    {
        $product = $this->product(5);
        $purchase = $this->purchase();
        $purchase->update(['purchase_date' => now()->addDay()->toDateString()]);
        $item = $this->item($purchase, $product, 2, 10);

        try {
            app(PurchaseService::class)->receive($purchase);
            $this->fail('Stock cannot be received before the purchase order date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase', $exception->errors());
        }

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(0, (int) $item->fresh()->received_quantity);
        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_variant_receipt_changes_only_the_selected_variant(): void
    {
        $product = $this->product(4, true);
        $variant = $this->variant($product, 3);
        $other = $this->variant($product, 7);
        $purchase = $this->purchase();
        $this->item($purchase, $product, 2, 18, $variant);

        $this->assertTrue(app(PurchaseService::class)->receive($purchase));

        $this->assertSame(5, (int) $variant->fresh()->stock);
        $this->assertSame(7, (int) $other->fresh()->stock);
        $this->assertSame(4, $product->fresh()->quantity);
        $this->assertSame('5.00', $variant->fresh()->cost_price);
        $this->assertSame('10.20', $variant->fresh()->inventory_cost_price);
        $this->assertDatabaseHas('inventory_movements', [
            'purchase_id' => $purchase->id,
            'product_variant_id' => $variant->id,
            'quantity_change' => 2,
            'balance_after' => 5,
        ]);
    }

    public function test_draft_cancelled_and_empty_orders_cannot_receive_stock(): void
    {
        $product = $this->product(5);
        foreach ([Purchase::STATUS_DRAFT, Purchase::STATUS_CANCELLED] as $status) {
            $purchase = $this->purchase($status);
            $this->item($purchase, $product, 2, 10);
            $this->assertInvalidReceipt($purchase);
        }
        $empty = $this->purchase();
        $this->assertInvalidReceipt($empty);

        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_invalid_later_line_aborts_whole_receipt_without_partial_stock(): void
    {
        $product = $this->product(5);
        $missingProduct = $this->product(1);
        $purchase = $this->purchase();
        $this->item($purchase, $product, 2, 10);
        $this->item($purchase, $missingProduct, 3, 11);
        $missingProduct->delete();

        $this->assertInvalidReceipt($purchase);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertSame(Purchase::STATUS_ORDERED, $purchase->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_mismatched_or_missing_variant_aborts_receipt(): void
    {
        $product = $this->product(0, true);
        $anotherProduct = $this->product(0, true);
        $wrongVariant = $this->variant($anotherProduct, 2);
        $purchase = $this->purchase();
        $this->item($purchase, $product, 2, 10, $wrongVariant);

        $this->assertInvalidReceipt($purchase);
        $this->assertSame(2, (int) $wrongVariant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);

        $wrongVariant->delete();
        $this->assertInvalidReceipt($purchase);
        $this->assertSame(Purchase::STATUS_ORDERED, $purchase->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_admin_create_rejects_variant_from_another_product_and_requires_variant_when_applicable(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(0, true);
        $anotherProduct = $this->product(0, true);
        $wrongVariant = $this->variant($anotherProduct, 1);
        $supplier = $this->supplier();
        $input = [
            'supplier_id' => $supplier->id,
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $wrongVariant->id,
                'quantity' => 2,
                'unit_cost' => 10,
            ]],
        ];

        $this->actingAs($admin)->post(route('admin.purchases.store'), $input)
            ->assertSessionHasErrors('items.0.product_variant_id');
        $input['items'][0]['product_variant_id'] = '';
        $this->post(route('admin.purchases.store'), $input)
            ->assertSessionHasErrors('items.0.product_variant_id');
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_purchase_creation_rejects_future_purchase_date(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $supplier = $this->supplier();

        $this->actingAs($admin)
            ->post(route('admin.purchases.store'), [
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->addDay()->toDateString(),
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_cost' => '10.00',
                ]],
            ])
            ->assertSessionHasErrors('purchase_date');

        $this->assertDatabaseCount('purchases', 0);

        $this->actingAs($admin)
            ->get(route('admin.purchases.create'))
            ->assertOk()
            ->assertSee('max="'.now()->toDateString().'"', false);
    }

    public function test_purchase_creation_rejects_inactive_supplier_even_if_id_is_submitted_directly(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $supplier = Supplier::create([
            'name' => 'Inactive Purchase Supplier',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.purchases.store'), [
                'supplier_id' => $supplier->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_cost' => '10.00',
                ]],
            ])
            ->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_purchase_creation_preserves_exact_cents_and_rejects_storage_overflow(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $supplier = $this->supplier();

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'shipping_total' => '0.20',
            'tax_total' => '0.10',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 3,
                'unit_cost' => '0.10',
            ]],
        ])->assertSessionHasNoErrors();

        $purchase = Purchase::query()->latest('id')->firstOrFail();
        $item = $purchase->items()->firstOrFail();

        $this->assertSame('0.30', $purchase->subtotal);
        $this->assertSame('0.60', $purchase->grand_total);
        $this->assertSame('0.10', $item->unit_cost);
        $this->assertSame('0.30', $item->line_total);

        $before = Purchase::count();

        $this->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_cost' => '6000000000.00',
            ]],
        ])->assertSessionHasErrors('items.0.unit_cost');

        $this->assertSame($before, Purchase::count());

        $this->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'shipping_total' => '0.01',
            'tax_total' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_cost' => '9999999999.99',
            ]],
        ])->assertSessionHasErrors('shipping_total');

        $this->assertSame($before, Purchase::count());

        $this->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_cost' => '1.001',
            ]],
        ])->assertSessionHasErrors('items.0.unit_cost');

        $this->assertSame($before, Purchase::count());
    }

    public function test_admin_receive_replay_is_informational_and_cancelled_order_has_no_receive_action(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $purchase = $this->purchase();
        $this->item($purchase, $product, 2, 10);

        $this->actingAs($admin)->get(route('admin.purchases.show', $purchase))
            ->assertOk()
            ->assertSee(__('Confirm remaining stock receipt'));
        $this->post(route('admin.purchases.receive', $purchase))->assertSessionHas('success');
        $this->post(route('admin.purchases.receive', $purchase))->assertSessionHas('warning');
        $this->assertSame(7, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);

        $cancelled = $this->purchase(Purchase::STATUS_CANCELLED);
        $this->get(route('admin.purchases.show', $cancelled))
            ->assertOk()
            ->assertDontSee(__('Receive stock'));
        $this->post(route('admin.purchases.receive', $cancelled))->assertSessionHasErrors('purchase');
    }

    public function test_partial_receipt_tracks_progress_and_duplicate_request_is_idempotent(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 5, 11);
        $receiptKey = (string) Str::uuid();
        $service = app(PurchaseService::class);

        $this->assertTrue($service->receivePartial($purchase, [$item->id => 2], $receiptKey, $admin->id));

        $this->assertSame(7, $product->fresh()->quantity);
        $this->assertSame(2, $item->fresh()->received_quantity);
        $this->assertSame(Purchase::STATUS_PARTIALLY_RECEIVED, $purchase->fresh()->status);
        $this->assertNull($purchase->fresh()->received_date);
        $this->assertDatabaseHas('purchase_receipts', [
            'purchase_id' => $purchase->id,
            'idempotency_key' => $receiptKey,
            'received_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_item_id' => $item->id,
            'quantity' => 2,
            'unit_cost' => '11.00',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity_change' => 2,
            'balance_after' => 7,
        ]);

        $this->assertFalse($service->receivePartial($purchase, [$item->id => 2], $receiptKey, $admin->id));

        try {
            $service->receivePartial($purchase, [$item->id => 1], $receiptKey, $admin->id);
            $this->fail('A receipt key cannot be replayed with different quantities.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('receipt_key', $exception->errors());
        }

        $this->assertSame(7, $product->fresh()->quantity);
        $this->assertSame(2, $item->fresh()->received_quantity);
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseCount('purchase_receipt_items', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_partial_receipt_rejects_over_receipt_and_full_receive_only_adds_remaining_units(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(4);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 5, 9);
        $service = app(PurchaseService::class);

        $this->assertTrue($service->receivePartial(
            $purchase,
            [$item->id => 3],
            (string) Str::uuid(),
            $admin->id
        ));

        try {
            $service->receivePartial(
                $purchase,
                [$item->id => 3],
                (string) Str::uuid(),
                $admin->id
            );
            $this->fail('Over-receiving a purchase line should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey("items.{$item->id}", $exception->errors());
        }

        $this->assertSame(7, $product->fresh()->quantity);
        $this->assertSame(3, $item->fresh()->received_quantity);
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->assertTrue($service->receive($purchase, $admin->id));

        $this->assertSame(9, $product->fresh()->quantity);
        $this->assertSame(5, $item->fresh()->received_quantity);
        $this->assertSame(Purchase::STATUS_RECEIVED, $purchase->fresh()->status);
        $this->assertNotNull($purchase->fresh()->received_date);
        $this->assertSame([3, 2], InventoryMovement::where('purchase_id', $purchase->id)->orderBy('id')->pluck('quantity_change')->all());
        $this->assertFalse($service->receive($purchase, $admin->id));
        $this->assertSame(9, $product->fresh()->quantity);
        $this->assertDatabaseCount('purchase_receipts', 2);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_purchase_service_enforces_cancellation_and_reversal_reason_bounds(): void
    {
        $product = $this->product(5);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);

        try {
            app(PurchaseService::class)->cancel($purchase, str_repeat('C', 1001));
            $this->fail('Purchase cancellation reason must respect the service contract boundary.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cancellation_reason', $exception->errors());
        }

        $this->assertSame(Purchase::STATUS_ORDERED, $purchase->fresh()->status);
        $this->assertNull($purchase->fresh()->cancelled_at);

        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 1],
            (string) Str::uuid()
        );
        $receipt = PurchaseReceipt::query()->where('purchase_id', $purchase->id)->firstOrFail();
        $stockBefore = (int) $product->fresh()->quantity;

        try {
            app(PurchaseReceiptReversalService::class)->reverse(
                $purchase,
                $receipt,
                str_repeat('R', 1001)
            );
            $this->fail('Purchase receipt reversal reason must respect the service contract boundary.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reversal_reason', $exception->errors());
        }

        $this->assertSame($stockBefore, (int) $product->fresh()->quantity);
        $this->assertNull($receipt->fresh()->reversed_at);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_ordered_purchase_can_be_cancelled_once_without_changing_inventory(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(6);
        $purchase = $this->purchase();
        $this->item($purchase, $product, 3, 14);

        $this->actingAs($admin)
            ->post(route('admin.purchases.cancel', $purchase), [
                'cancellation_reason' => 'Supplier cannot fulfill the order.',
            ])
            ->assertRedirect(route('admin.purchases.show', $purchase))
            ->assertSessionHas('success');

        $cancelled = $purchase->fresh();

        $this->assertSame(Purchase::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame($admin->id, (int) $cancelled->cancelled_by);
        $this->assertSame('Supplier cannot fulfill the order.', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(6, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'purchase_cancelled',
            'subject_id' => $purchase->id,
        ]);

        $this->post(route('admin.purchases.cancel', $purchase), [
            'cancellation_reason' => 'Duplicate click.',
        ])->assertSessionHas('warning');

        $this->assertSame('Supplier cannot fulfill the order.', $purchase->fresh()->cancellation_reason);
        $this->assertSame(1, \App\Models\AdminActivityLog::where('action', 'purchase_cancelled')->count());
    }

    public function test_database_rejects_receipt_item_from_another_purchase(): void
    {
        $product = $this->product(5);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 1, 10);
        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 1],
            (string) Str::uuid()
        );

        $receipt = PurchaseReceipt::query()->where('purchase_id', $purchase->id)->firstOrFail();
        $otherPurchase = $this->purchase();
        $otherItem = $this->item($otherPurchase, $product, 1, 10);

        try {
            PurchaseReceiptItem::query()->create([
                'purchase_receipt_id' => $receipt->id,
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $otherItem->id,
                'quantity' => 1,
                'unit_cost' => '10.00',
            ]);
            $this->fail('Database must reject a receipt item owned by another purchase.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseMissing('purchase_receipt_items', [
            'purchase_receipt_id' => $receipt->id,
            'purchase_item_id' => $otherItem->id,
        ]);
    }

    public function test_database_preserves_purchase_and_receipt_ledger_from_direct_deletion(): void
    {
        $product = $this->product(5);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);

        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 1],
            (string) Str::uuid()
        );

        $receipt = PurchaseReceipt::query()->where('purchase_id', $purchase->id)->firstOrFail();
        $receiptItemId = $receipt->items()->value('id');

        try {
            PurchaseReceipt::query()->whereKey($receipt->id)->delete();
            $this->fail('Database foreign keys must preserve purchase receipt history.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            Purchase::query()->whereKey($purchase->id)->delete();
            $this->fail('Database foreign keys must preserve purchase history.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('purchases', ['id' => $purchase->id]);
        $this->assertDatabaseHas('purchase_items', ['id' => $item->id]);
        $this->assertDatabaseHas('purchase_receipts', ['id' => $receipt->id]);
        $this->assertDatabaseHas('purchase_receipt_items', ['id' => $receiptItemId]);
    }

    public function test_purchase_can_be_cancelled_after_all_receipts_are_reversed(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);

        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 2],
            (string) Str::uuid(),
            $admin->id
        );

        $receipt = PurchaseReceipt::query()->where('purchase_id', $purchase->id)->firstOrFail();
        $this->assertTrue(app(PurchaseReceiptReversalService::class)->reverse(
            $purchase,
            $receipt,
            'Supplier delivery fully rejected.',
            $admin->id
        ));

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(0, (int) $item->fresh()->received_quantity);
        $this->assertNotNull($receipt->fresh()->reversed_at);
        $this->assertSame(Purchase::STATUS_ORDERED, $purchase->fresh()->status);

        $this->assertTrue(app(PurchaseService::class)->cancel(
            $purchase,
            'Cancel order after all received stock was reversed.',
            $admin->id
        ));

        $this->assertSame(Purchase::STATUS_CANCELLED, $purchase->fresh()->status);
        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertNotNull($receipt->fresh()->reversed_at);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'purchase_cancelled',
            'subject_id' => $purchase->id,
        ]);
    }

    public function test_purchase_with_any_received_stock_cannot_be_cancelled(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(4);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 5, 9);

        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 2],
            (string) Str::uuid(),
            $admin->id
        );

        $this->actingAs($admin)
            ->post(route('admin.purchases.cancel', $purchase), [
                'cancellation_reason' => 'Try to cancel after receipt.',
            ])
            ->assertSessionHasErrors('purchase');

        $this->assertSame(Purchase::STATUS_PARTIALLY_RECEIVED, $purchase->fresh()->status);
        $this->assertSame(6, $product->fresh()->quantity);
        $this->assertSame(2, $item->fresh()->received_quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseMissing('admin_activity_logs', [
            'action' => 'purchase_cancelled',
            'subject_id' => $purchase->id,
        ]);
    }

    public function test_awaiting_purchase_filter_includes_ordered_and_partially_received_only(): void
    {
        $admin = $this->createSuperAdmin();

        $ordered = $this->purchase(Purchase::STATUS_ORDERED);
        $partial = $this->purchase(Purchase::STATUS_PARTIALLY_RECEIVED);
        $received = $this->purchase(Purchase::STATUS_RECEIVED);
        $cancelled = $this->purchase(Purchase::STATUS_CANCELLED);

        $response = $this->actingAs($admin)
            ->get(route('admin.purchases.index', ['status' => 'awaiting']));

        $response
            ->assertOk()
            ->assertSee($ordered->reference)
            ->assertSee($partial->reference)
            ->assertDontSee($received->reference)
            ->assertDontSee($cancelled->reference);
    }

    public function test_procurement_value_excludes_drafts_and_cancelled_purchases(): void
    {
        $admin = $this->createSuperAdmin();

        $this->purchase(Purchase::STATUS_ORDERED)->update(['grand_total' => 100]);
        $this->purchase(Purchase::STATUS_PARTIALLY_RECEIVED)->update(['grand_total' => 200]);
        $this->purchase(Purchase::STATUS_RECEIVED)->update(['grand_total' => 300]);
        $this->purchase(Purchase::STATUS_DRAFT)->update(['grand_total' => 400]);
        $this->purchase(Purchase::STATUS_CANCELLED)->update(['grand_total' => 500]);

        $this->actingAs($admin)
            ->get(route('admin.purchases.index'))
            ->assertOk()
            ->assertSee('EGP 600.00')
            ->assertSee(__('Open and received purchase value by currency; drafts and cancelled orders are excluded.'));
    }

    public function test_procurement_value_never_sums_different_currencies_together(): void
    {
        $admin = $this->createSuperAdmin();

        $this->purchase(Purchase::STATUS_ORDERED)->update(['currency' => 'EGP', 'grand_total' => 100]);
        $this->purchase(Purchase::STATUS_RECEIVED)->update(['currency' => 'USD', 'grand_total' => 25]);

        $this->actingAs($admin)
            ->get(route('admin.purchases.index'))
            ->assertOk()
            ->assertSee('EGP 100.00')
            ->assertSee('USD 25.00')
            ->assertDontSee('EGP 125.00');
    }

    public function test_purchase_details_preserve_purchase_currency_for_item_and_receipt_costs(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(0);
        $purchase = $this->purchase(Purchase::STATUS_ORDERED);
        $purchase->update(['currency' => 'USD']);
        $item = $this->item($purchase, $product, 2, 10);

        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 1],
            (string) Str::uuid(),
            $admin->id
        );

        $this->actingAs($admin)
            ->get(route('admin.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('USD 10.00')
            ->assertSee('USD 20.00')
            ->assertDontSee('EGP 10.00')
            ->assertDontSee('EGP 20.00');
    }

    public function test_latest_partial_receipt_can_be_reversed_exactly_and_replay_is_safe(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $product->forceFill(['expiration_date' => '2026-12-31'])->save();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 5, 10, null, '2027-06-01');

        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 2],
            (string) Str::uuid(),
            $admin->id
        );

        $receipt = PurchaseReceipt::query()->where('purchase_id', $purchase->id)->firstOrFail();
        $purchaseLot = InventoryLot::query()
            ->where('purchase_receipt_id', $receipt->id)
            ->firstOrFail();

        $this->assertSame(2, (int) $purchaseLot->quantity_on_hand);
        $this->assertSame($item->id, (int) $purchaseLot->purchase_item_id);
        $this->assertSame(10.0, (float) $purchaseLot->unit_cost);
        $this->assertSame('2027-06-01', $purchaseLot->expiration_date->toDateString());

        $this->assertSame(7, (int) $product->fresh()->quantity);
        $this->assertSame(6.43, (float) $product->fresh()->inventory_cost_price);
        $this->assertSame('2027-06-01', $product->fresh()->expiration_date->toDateString());

        $this->actingAs($admin)
            ->get(route('admin.purchases.show', $purchase))
            ->assertOk()
            ->assertSee(__('Receiving history'))
            ->assertSee(__('Manual partial receipt'));

        $this->post(route('admin.purchases.receipts.reverse', [
            'purchase' => $purchase->id,
            'purchaseReceipt' => $receipt->id,
        ]), [
            'reversal_reason' => 'Supplier sent the wrong batch.',
        ])->assertRedirect(route('admin.purchases.show', $purchase))
            ->assertSessionHas('success');

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(5.0, (float) $product->fresh()->inventory_cost_price);
        $this->assertSame('2026-12-31', $product->fresh()->expiration_date->toDateString());
        $this->assertSame(0, (int) $item->fresh()->received_quantity);
        $this->assertSame(Purchase::STATUS_ORDERED, $purchase->fresh()->status);
        $this->assertNull($purchase->fresh()->received_date);

        $reversedReceipt = $receipt->fresh();
        $this->assertNotNull($reversedReceipt->reversed_at);
        $this->assertSame($admin->id, (int) $reversedReceipt->reversed_by);
        $this->assertSame('Supplier sent the wrong batch.', $reversedReceipt->reversal_reason);

        $movements = InventoryMovement::query()
            ->where('purchase_id', $purchase->id)
            ->orderBy('id')
            ->get();
        $this->assertSame(
            [InventoryMovement::TYPE_PURCHASE_IN, InventoryMovement::TYPE_PURCHASE_REVERSAL],
            $movements->pluck('type')->all()
        );
        $this->assertSame([2, -2], $movements->pluck('quantity_change')->all());
        $this->assertSame([7, 5], $movements->pluck('balance_after')->all());
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'purchase_receipt_reversed',
            'subject_id' => $receipt->id,
        ]);

        $this->post(route('admin.purchases.receipts.reverse', [
            'purchase' => $purchase->id,
            'purchaseReceipt' => $receipt->id,
        ]), [
            'reversal_reason' => 'Duplicate click.',
        ])->assertSessionHas('warning');

        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame('Supplier sent the wrong batch.', $receipt->fresh()->reversal_reason);
    }

    public function test_receipt_reversal_is_blocked_after_newer_inventory_activity(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 4, 10);

        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 2],
            (string) Str::uuid(),
            $admin->id
        );

        $receipt = PurchaseReceipt::query()->where('purchase_id', $purchase->id)->firstOrFail();

        app(InventoryAdjustmentService::class)->setStock(
            $product->id,
            null,
            7,
            8,
            'Physical count after receipt',
            $admin->id
        );

        try {
            app(PurchaseReceiptReversalService::class)->reverse(
                $purchase,
                $receipt,
                'Attempt retroactive reversal.',
                $admin->id
            );
            $this->fail('A receipt with newer stock history should not be reversed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('receipt', $exception->errors());
        }

        $this->assertSame(8, (int) $product->fresh()->quantity);
        $this->assertSame(2, (int) $item->fresh()->received_quantity);
        $this->assertSame(Purchase::STATUS_PARTIALLY_RECEIVED, $purchase->fresh()->status);
        $this->assertNull($receipt->fresh()->reversed_at);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_barcode_receipt_reversal_resets_verification_for_rescan(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(3);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 9);

        PurchaseReceivingProgress::query()->create([
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $item->id,
            'verified_quantity' => 2,
            'last_scanned_by' => $admin->id,
            'last_scanned_at' => now(),
        ]);

        app(PurchaseService::class)->receiveVerified($purchase, $admin->id);
        $receipt = PurchaseReceipt::query()->where('purchase_id', $purchase->id)->firstOrFail();

        $this->assertSame(PurchaseReceipt::METHOD_BARCODE_VERIFIED, $receipt->receipt_method);
        $this->assertSame(5, (int) $product->fresh()->quantity);

        $this->assertTrue(app(PurchaseReceiptReversalService::class)->reverse(
            $purchase,
            $receipt,
            'Barcode receipt needs recount.',
            $admin->id
        ));

        $this->assertSame(3, (int) $product->fresh()->quantity);
        $this->assertSame(0, (int) $item->fresh()->received_quantity);
        $this->assertSame(0, (int) PurchaseReceivingProgress::query()
            ->where('purchase_item_id', $item->id)
            ->value('verified_quantity'));
        $this->assertSame(Purchase::STATUS_ORDERED, $purchase->fresh()->status);
    }

    public function test_multiple_receipts_can_be_reversed_safely_in_lifo_order(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 4, 10);
        $purchaseService = app(PurchaseService::class);
        $reversalService = app(PurchaseReceiptReversalService::class);

        $purchaseService->receivePartial(
            $purchase,
            [$item->id => 2],
            (string) Str::uuid(),
            $admin->id
        );
        $firstReceipt = PurchaseReceipt::query()->latest('id')->firstOrFail();

        $purchaseService->receivePartial(
            $purchase,
            [$item->id => 2],
            (string) Str::uuid(),
            $admin->id
        );
        $secondReceipt = PurchaseReceipt::query()->latest('id')->firstOrFail();

        $this->assertSame(9, (int) $product->fresh()->quantity);
        $this->assertSame(4, (int) $item->fresh()->received_quantity);
        $this->assertSame(Purchase::STATUS_RECEIVED, $purchase->fresh()->status);

        $this->assertTrue($reversalService->reverse(
            $purchase,
            $secondReceipt,
            'Undo latest delivery.',
            $admin->id
        ));

        $this->assertSame(7, (int) $product->fresh()->quantity);
        $this->assertSame(6.43, (float) $product->fresh()->inventory_cost_price);
        $this->assertSame(2, (int) $item->fresh()->received_quantity);
        $this->assertSame(Purchase::STATUS_PARTIALLY_RECEIVED, $purchase->fresh()->status);

        $this->assertTrue($reversalService->reverse(
            $purchase,
            $firstReceipt,
            'Undo first delivery after latest was reversed.',
            $admin->id
        ));

        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertSame(5.0, (float) $product->fresh()->inventory_cost_price);
        $this->assertSame(0, (int) $item->fresh()->received_quantity);
        $this->assertSame(Purchase::STATUS_ORDERED, $purchase->fresh()->status);
        $this->assertSame(
            [
                InventoryMovement::TYPE_PURCHASE_IN,
                InventoryMovement::TYPE_PURCHASE_IN,
                InventoryMovement::TYPE_PURCHASE_REVERSAL,
                InventoryMovement::TYPE_PURCHASE_REVERSAL,
            ],
            InventoryMovement::query()
                ->where('purchase_id', $purchase->id)
                ->orderBy('id')
                ->pluck('type')
                ->all()
        );
    }

    private function assertInvalidReceipt(Purchase $purchase): void
    {
        try {
            app(PurchaseService::class)->receive($purchase);
            $this->fail('An invalid purchase should not change inventory.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase', $exception->errors());
        }
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Purchase Test Supplier']);
    }

    private function purchase(string $status = Purchase::STATUS_ORDERED): Purchase
    {
        return Purchase::create(['supplier_id' => $this->supplier()->id, 'status' => $status]);
    }

    private function product(int $quantity, bool $hasVariants = false): Product
    {
        $token = Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'Purchase Category '.$token,
            'slug' => 'purchase-category-'.$token,
            'description' => 'Purchase test category',
            'meta_title' => 'Purchase test',
            'meta_keyword' => 'purchase',
            'meta_description' => 'Purchase test category',
            'status' => false,
        ]);

        return Product::create([
            'name' => 'Purchase Product '.$token,
            'slug' => 'purchase-product-'.$token,
            'category_id' => $category->id,
            'base_price' => 30,
            'cost_price' => 5,
            'quantity' => $quantity,
            'has_variants' => $hasVariants,
            'status' => true,
        ]);
    }

    private function variant(Product $product, int $stock): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PUR-'.Str::upper(Str::random(8)),
            'price' => 30,
            'cost_price' => 5,
            'stock' => $stock,
            'status' => true,
        ]);
    }

    private function item(Purchase $purchase, Product $product, int $quantity, int $unitCost, ?ProductVariant $variant = null, ?string $expirationDate = null)
    {
        return $purchase->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'variant_name' => $variant?->sku,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => $quantity * $unitCost,
            'expiration_date' => $expirationDate,
        ]);
    }
}
