<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceivingProgress;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseBarcodeReceivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_barcode_receiving_is_admin_only_and_scan_does_not_change_stock(): void
    {
        $product = $this->product('Simple Receiving Product', '6223000000001', 5, false);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.purchases.receiving', $purchase))
            ->assertRedirect('/');

        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->get(route('admin.purchases.receiving', $purchase))
            ->assertOk()
            ->assertSee('id="purchaseReceivingBarcode"', false)
            ->assertSee(__('Ordered units'))
            ->assertSee(__('Verified units'));

        $this->post(route('admin.purchases.receiving.scan', $purchase), [
            'barcode' => $product->barcode,
        ])->assertRedirect(route('admin.purchases.receiving', $purchase))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_receiving_progress', [
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $item->id,
            'verified_quantity' => 1,
        ]);
        $this->assertSame(5, (int) $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_each_scan_counts_one_unit_over_scan_is_ignored_and_verified_receipt_is_replay_safe(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Counted Receiving Product', '6223000000002', 5, false);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 12);

        $this->actingAs($admin);

        foreach ([1, 2] as $expected) {
            $this->post(route('admin.purchases.receiving.scan', $purchase), [
                'barcode' => $product->barcode,
            ])->assertSessionHas('success');

            $this->assertDatabaseHas('purchase_receiving_progress', [
                'purchase_item_id' => $item->id,
                'verified_quantity' => $expected,
            ]);
        }

        $this->post(route('admin.purchases.receiving.scan', $purchase), [
            'barcode' => $product->barcode,
        ])->assertSessionHas('warning');

        $this->assertSame(2, PurchaseReceivingProgress::where('purchase_item_id', $item->id)->value('verified_quantity'));
        $this->assertSame(5, (int) $product->fresh()->quantity);

        $this->post(route('admin.purchases.receive-verified', $purchase))
            ->assertRedirect(route('admin.purchases.show', $purchase))
            ->assertSessionHas('success');

        $this->assertSame(7, (int) $product->fresh()->quantity);
        $this->assertSame(Purchase::STATUS_RECEIVED, $purchase->fresh()->status);
        $this->assertDatabaseHas('inventory_movements', [
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity_change' => 2,
        ]);

        $this->post(route('admin.purchases.receive-verified', $purchase))
            ->assertSessionHas('warning');

        $this->assertSame(7, (int) $product->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::where('purchase_id', $purchase->id)->count());
    }

    public function test_variant_receiving_requires_exact_variant_barcode_and_changes_only_that_variant_on_final_receipt(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Variant Receiving Product', '6223000000003', 0, true);
        $variant = $this->variant($product, 'RECV-VAR-001', '6223000000031', 3);
        $other = $this->variant($product, 'RECV-VAR-002', '6223000000032', 8);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 1, 18, $variant);

        $this->actingAs($admin)
            ->post(route('admin.purchases.receiving.scan', $purchase), [
                'barcode' => $product->barcode,
            ])->assertSessionHasErrors('barcode');

        $this->assertDatabaseMissing('purchase_receiving_progress', [
            'purchase_item_id' => $item->id,
        ]);

        $this->post(route('admin.purchases.receiving.scan', $purchase), [
            'barcode' => $variant->barcode,
        ])->assertSessionHas('success');

        $this->assertSame(3, (int) $variant->fresh()->stock);
        $this->assertSame(8, (int) $other->fresh()->stock);

        $this->post(route('admin.purchases.receive-verified', $purchase))
            ->assertSessionHas('success');

        $this->assertSame(4, (int) $variant->fresh()->stock);
        $this->assertSame(8, (int) $other->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'purchase_id' => $purchase->id,
            'product_variant_id' => $variant->id,
            'quantity_change' => 1,
        ]);
    }

    public function test_barcode_outside_purchase_and_unknown_barcode_are_rejected_without_progress(): void
    {
        $admin = $this->createSuperAdmin();
        $ordered = $this->product('Ordered Product', '6223000000004', 1, false);
        $outside = $this->product('Outside Product', '6223000000005', 1, false);
        $purchase = $this->purchase();
        $this->item($purchase, $ordered, 1, 10);

        $this->actingAs($admin)
            ->post(route('admin.purchases.receiving.scan', $purchase), [
                'barcode' => $outside->barcode,
            ])->assertSessionHasErrors('barcode');

        $this->post(route('admin.purchases.receiving.scan', $purchase), [
            'barcode' => '6223999999999',
        ])->assertSessionHasErrors('barcode');

        $this->assertDatabaseCount('purchase_receiving_progress', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_duplicate_purchase_lines_require_explicit_line_selection_before_counting(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Duplicate Line Product', '6223000000006', 2, false);
        $purchase = $this->purchase();
        $first = $this->item($purchase, $product, 1, 10, null, '2027-01-01');
        $second = $this->item($purchase, $product, 1, 12, null, '2027-06-01');

        $this->actingAs($admin)
            ->post(route('admin.purchases.receiving.scan', $purchase), [
                'barcode' => $product->barcode,
            ])
            ->assertRedirect(route('admin.purchases.receiving', $purchase))
            ->assertSessionHas('receiving_choices')
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('purchase_receiving_progress', 0);

        $this->post(route('admin.purchases.receiving.scan', $purchase), [
            'barcode' => $product->barcode,
            'purchase_item_id' => $first->id,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_receiving_progress', [
            'purchase_item_id' => $first->id,
            'verified_quantity' => 1,
        ]);
        $this->assertDatabaseMissing('purchase_receiving_progress', [
            'purchase_item_id' => $second->id,
        ]);
    }

    public function test_undo_one_corrects_a_scan_and_incomplete_verified_receipt_is_blocked(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Undo Receiving Product', '6223000000007', 4, false);
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);

        $this->actingAs($admin)
            ->post(route('admin.purchases.receiving.scan', $purchase), [
                'barcode' => $product->barcode,
            ])->assertSessionHas('success');

        $this->post(route('admin.purchases.receiving.undo', [
            'purchase' => $purchase->id,
            'purchaseItem' => $item->id,
        ]))->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_receiving_progress', [
            'purchase_item_id' => $item->id,
            'verified_quantity' => 0,
        ]);

        $this->post(route('admin.purchases.receive-verified', $purchase))
            ->assertSessionHasErrors('purchase');

        $this->assertSame(4, (int) $product->fresh()->quantity);
        $this->assertSame(Purchase::STATUS_ORDERED, $purchase->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_scan_is_rejected_after_purchase_is_received(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Closed Receiving Product', '6223000000008', 1, false);
        $purchase = $this->purchase(Purchase::STATUS_RECEIVED);
        $this->item($purchase, $product, 1, 10);

        $this->actingAs($admin)
            ->post(route('admin.purchases.receiving.scan', $purchase), [
                'barcode' => $product->barcode,
            ])->assertSessionHasErrors('purchase');

        $this->assertDatabaseCount('purchase_receiving_progress', 0);
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Barcode Receiving Supplier ' . Str::upper(Str::random(5))]);
    }

    private function purchase(string $status = Purchase::STATUS_ORDERED): Purchase
    {
        return Purchase::create([
            'supplier_id' => $this->supplier()->id,
            'status' => $status,
        ]);
    }

    private function product(
        string $name,
        ?string $barcode,
        int $quantity,
        bool $hasVariants
    ): Product {
        $token = Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'Receiving Category ' . $token,
            'slug' => 'receiving-category-' . $token,
            'description' => 'Receiving test category',
            'meta_title' => 'Receiving test',
            'meta_keyword' => 'receiving',
            'meta_description' => 'Receiving test category',
            'status' => false,
        ]);

        return Product::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $token,
            'sku' => 'RECV-' . Str::upper(Str::random(6)),
            'barcode' => $barcode,
            'category_id' => $category->id,
            'base_price' => 30,
            'cost_price' => 5,
            'quantity' => $quantity,
            'has_variants' => $hasVariants,
            'stock_status' => $quantity > 0 ? 'in_stock' : 'out_of_stock',
            'status' => true,
        ]);
    }

    private function variant(
        Product $product,
        string $sku,
        string $barcode,
        int $stock
    ): ProductVariant {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'barcode' => $barcode,
            'price' => 30,
            'cost_price' => 5,
            'stock' => $stock,
            'is_default' => $product->variants()->doesntExist(),
            'status' => true,
        ]);
    }

    private function item(
        Purchase $purchase,
        Product $product,
        int $quantity,
        int $unitCost,
        ?ProductVariant $variant = null,
        ?string $expirationDate = null
    ): PurchaseItem {
        return $purchase->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'variant_name' => $variant?->sku,
            'sku' => $variant?->sku ?? $product->sku,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => $quantity * $unitCost,
            'expiration_date' => $expirationDate,
        ]);
    }
}
