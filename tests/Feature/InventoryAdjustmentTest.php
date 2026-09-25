<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_counted_stock_creates_one_audited_movement_and_can_decrease_safely(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $service = app(InventoryAdjustmentService::class);

        $movement = $service->setStock($product->id, null, 5, 8, 'Physical count correction', $admin->id);

        $this->assertSame(8, $product->fresh()->quantity);
        $this->assertSame(3, $movement->quantity_change);
        $this->assertSame(8, $movement->balance_after);
        $this->assertSame(InventoryMovement::TYPE_ADJUSTMENT, $movement->type);
        $this->assertSame('Physical count correction', $movement->reason);
        $this->assertSame([
            'source' => 'manual_adjustment',
            'stock_before' => 5,
            'stock_after' => 8,
            'admin_user_id' => $admin->id,
        ], $movement->meta);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'stock_adjusted',
            'subject_id' => $movement->id,
        ]);

        $decrease = $service->setStock($product->id, null, 8, 2, 'Damaged units counted', $admin->id);
        $this->assertSame(-6, $decrease->quantity_change);
        $this->assertSame(2, $decrease->balance_after);
        $this->assertSame(2, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_stale_or_replayed_count_does_not_change_stock_twice(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $service = app(InventoryAdjustmentService::class);
        $service->setStock($product->id, null, 5, 8, 'Physical count correction', $admin->id);

        $this->assertInvalidAdjustment(fn () => $service->setStock($product->id, null, 5, 8, 'Physical count correction', $admin->id), 'expected_stock');
        $this->assertNull($service->setStock($product->id, null, 8, 8, 'Physical count correction', $admin->id));
        $this->assertSame(8, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('admin_activity_logs', 1);
    }

    public function test_legacy_negative_stock_can_be_corrected_to_zero(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(-2);

        $movement = app(InventoryAdjustmentService::class)->setStock($product->id, null, -2, 0, 'Physical count correction', $admin->id);

        $this->assertSame(0, $product->fresh()->quantity);
        $this->assertSame(2, $movement->quantity_change);
        $this->assertSame(-2, $movement->meta['stock_before']);
    }

    public function test_variant_count_changes_only_its_own_stock_and_rejects_other_product_variant(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(4, true);
        $variant = $this->variant($product, 3);
        $otherVariant = $this->variant($product, 7);
        $unrelated = $this->variant($this->product(0, true), 9);
        $service = app(InventoryAdjustmentService::class);

        $this->assertInvalidAdjustment(fn () => $service->setStock($product->id, null, 4, 5, 'Physical count correction', $admin->id), 'variant_id');
        $this->assertInvalidAdjustment(fn () => $service->setStock($product->id, $unrelated->id, 9, 5, 'Physical count correction', $admin->id), 'variant_id');
        $movement = $service->setStock($product->id, $variant->id, 3, 1, 'Physical count correction', $admin->id);

        $this->assertSame(-2, $movement->quantity_change);
        $this->assertSame($variant->id, $movement->product_variant_id);
        $this->assertSame(1, (int) $variant->fresh()->stock);
        $this->assertSame(7, (int) $otherVariant->fresh()->stock);
        $this->assertSame(4, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_inventory_adjustment_is_admin_only_and_validates_nonnegative_count(): void
    {
        $product = $this->product(5);
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.inventory.adjust'))->assertRedirect('/');
        $this->post(route('admin.inventory.adjust.store'), [
            'product_id' => $product->id,
            'expected_stock' => 5,
            'new_stock' => 9,
            'reason' => 'Physical count correction',
        ])->assertRedirect('/');

        $admin = $this->createSuperAdmin();
        $this->actingAs($admin)->get(route('admin.inventory.adjust', ['product_id' => $product->id]))
            ->assertOk()->assertSee(__('Current stock'))->assertSee('name="expected_stock" value="5"', false);
        $this->post(route('admin.inventory.adjust.store'), [
            'product_id' => $product->id,
            'expected_stock' => 5,
            'new_stock' => -1,
            'reason' => 'Physical count correction',
        ])->assertSessionHasErrors('new_stock');
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_admin_post_rechecks_count_and_reports_a_stale_form(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product(5);
        $data = [
            'product_id' => $product->id,
            'expected_stock' => 5,
            'new_stock' => 8,
            'reason' => 'Physical count correction',
        ];

        $this->actingAs($admin)->from(route('admin.inventory.adjust', ['product_id' => $product->id]))
            ->post(route('admin.inventory.adjust.store'), $data)
            ->assertRedirect(route('admin.inventory.index'))->assertSessionHas('success');
        $this->from(route('admin.inventory.adjust', ['product_id' => $product->id]))
            ->post(route('admin.inventory.adjust.store'), $data)
            ->assertRedirect(route('admin.inventory.adjust', ['product_id' => $product->id]))
            ->assertSessionHasErrors('expected_stock');

        $this->assertSame(8, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    private function assertInvalidAdjustment(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Invalid stock adjustment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function product(int $quantity, bool $hasVariants = false): Product
    {
        $token = Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'Stock Category '.$token,
            'slug' => 'stock-category-'.$token,
            'description' => 'Stock test category',
            'meta_title' => 'Stock test',
            'meta_keyword' => 'stock',
            'meta_description' => 'Stock test category',
            'status' => false,
        ]);

        return Product::create([
            'name' => 'Stock Product '.$token,
            'slug' => 'stock-product-'.$token,
            'category_id' => $category->id,
            'base_price' => 20,
            'quantity' => $quantity,
            'has_variants' => $hasVariants,
            'status' => true,
        ]);
    }

    private function variant(Product $product, int $stock): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'STOCK-'.Str::upper(Str::random(8)),
            'price' => 20,
            'stock' => $stock,
            'status' => true,
        ]);
    }
}
