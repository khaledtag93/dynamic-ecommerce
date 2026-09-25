<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Commerce\PurchaseService;
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
        $this->assertSame('12.00', $product->fresh()->cost_price);
        $this->assertSame(Purchase::STATUS_RECEIVED, $purchase->fresh()->status);
        $movements = InventoryMovement::where('purchase_id', $purchase->id)->orderBy('id')->get();
        $this->assertSame([2, 3], $movements->pluck('quantity_change')->all());
        $this->assertSame([7, 10], $movements->pluck('balance_after')->all());
        $this->assertSame([$first->id, $second->id], $movements->pluck('meta')->map(fn ($meta) => $meta['purchase_item_id'])->all());
        $this->assertTrue($movements->every(fn ($movement) => $movement->type === InventoryMovement::TYPE_PURCHASE_IN));

        $this->assertFalse($service->receive($purchase));
        $this->assertSame(10, $product->fresh()->quantity);
        $this->assertSame('12.00', $product->fresh()->cost_price);
        $this->assertSame($receivedDate, $purchase->fresh()->received_date->toDateString());
        $this->assertSame(2, InventoryMovement::where('purchase_id', $purchase->id)->count());
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
        $this->assertSame('18.00', $variant->fresh()->cost_price);
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

    public function test_admin_receive_replay_is_informational_and_cancelled_order_has_no_receive_action(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $purchase = $this->purchase();
        $this->item($purchase, $product, 2, 10);

        $this->actingAs($admin)->get(route('admin.purchases.show', $purchase))
            ->assertOk()
            ->assertSee(__('Confirm stock receipt'));
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

    private function item(Purchase $purchase, Product $product, int $quantity, int $unitCost, ?ProductVariant $variant = null)
    {
        return $purchase->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'variant_name' => $variant?->sku,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => $quantity * $unitCost,
        ]);
    }
}
