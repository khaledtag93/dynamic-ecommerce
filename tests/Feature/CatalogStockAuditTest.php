<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Product\Index;
use App\Http\Livewire\Admin\Product\ProductForm;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Admin\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogStockAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_editor_records_opening_and_changed_stock_without_recording_a_noop(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $this->actingAs($admin);
        $category = $this->category();

        $form = Livewire::test(ProductForm::class)
            ->set('name', 'Catalog stock example')
            ->set('category_id', $category->id)
            ->set('base_price', 20)
            ->set('quantity', 5)
            ->set('stock_status', 'in_stock')
            ->set('status', 1)
            ->set('is_featured', 0)
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Catalog stock example')->firstOrFail();
        $this->assertMovement($product->id, null, 5, 5, 'catalog_editor', $admin->id);

        $form->set('quantity', 2)->call('save')->assertHasNoErrors();
        $this->assertSame(2, $product->fresh()->quantity);
        $this->assertMovement($product->id, null, -3, 2, 'catalog_editor', $admin->id);

        $form->set('base_price', 30)->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseCount('admin_activity_logs', 2);
    }

    public function test_stale_editor_stock_rolls_back_other_product_changes(): void
    {
        $this->actingAs(User::factory()->create(['role_as' => 1]));
        $product = $this->product(5);
        $form = Livewire::test(ProductForm::class, ['productId' => $product->id]);

        $product->update(['quantity' => 4]);
        $form->set('name', 'Should not persist')->set('quantity', 8)
            ->call('save')->assertHasErrors(['stock']);

        $this->assertSame(4, $product->fresh()->quantity);
        $this->assertNotSame('Should not persist', $product->fresh()->name);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_variant_editor_audits_new_and_changed_rows_and_blocks_removal_with_stock(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $this->actingAs($admin);
        $product = $this->product(0, true);
        $variant = $this->variant($product, 3, 'STOCK-FIRST');
        $form = Livewire::test(ProductForm::class, ['productId' => $product->id]);
        $first = $form->get('variants')[0];

        $form->set('variants.0.stock', 5)
            ->call('save')->assertHasNoErrors();
        $this->assertSame(5, (int) $variant->fresh()->stock);
        $this->assertMovement($product->id, $variant->id, 2, 5, 'catalog_editor', $admin->id);

        $current = $form->get('variants')[0];
        $form->set('variants', [array_merge($first, ['id' => null, 'sku' => 'STOCK-SECOND', 'stock' => 2])])
            ->call('save')->assertHasErrors(['variants']);
        $this->assertSame(5, (int) $variant->fresh()->stock);
        $this->assertDatabaseCount('product_variants', 1);

        $form->set('variants', [$current, [
            'id' => null, 'sku' => 'STOCK-SECOND', 'barcode' => '', 'price' => 20,
            'sale_price' => '', 'stock' => 2, 'is_default' => false, 'status' => true,
            'attributes' => [],
        ]])->call('save')->assertHasNoErrors();

        $second = ProductVariant::where('sku', 'STOCK-SECOND')->firstOrFail();
        $this->assertMovement($product->id, $second->id, 2, 2, 'catalog_editor', $admin->id);
        $form->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_inline_quantity_creates_one_movement_and_rejects_a_stale_value(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $this->actingAs($admin);
        $product = $this->product(5);

        Livewire::test(Index::class)
            ->call('startEditQty', $product->id, 5)
            ->set('inlineQty.' . $product->id, 8)
            ->call('saveInlineQty', $product->id)->assertHasNoErrors();

        $this->assertMovement($product->id, null, 3, 8, 'catalog_inline', $admin->id);

        $editor = Livewire::test(Index::class)->call('startEditQty', $product->id, 8);
        $product->update(['quantity' => 7]);
        $editor->set('inlineQty.' . $product->id, 9)
            ->call('saveInlineQty', $product->id)->assertHasErrors(['inlineQty.' . $product->id]);
        $this->assertSame(7, $product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_variant_stock_must_be_zero_before_deletion_and_stale_variant_edits_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role_as' => 1]));
        $product = $this->product(0, true);
        $this->variant($product, 0, 'KEEP');
        $removed = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'REMOVE', 'price' => 20,
            'stock' => 2, 'status' => true, 'is_default' => false,
        ]);
        $form = Livewire::test(ProductForm::class, ['productId' => $product->id]);

        $removed->update(['stock' => 3]);
        $form->set('variants.1.stock', 0)->call('save')->assertHasErrors(['stock']);
        $this->assertSame(3, (int) $removed->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);

        $form = Livewire::test(ProductForm::class, ['productId' => $product->id]);
        $form->set('variants.1.stock', 0)->call('save')->assertHasNoErrors();
        $this->assertMovement($product->id, $removed->id, -3, 0, 'catalog_editor', auth()->id());

        $form->set('variants', [$form->get('variants')[0]])->call('save')->assertHasNoErrors();
        $this->assertDatabaseMissing('product_variants', ['id' => $removed->id]);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_switching_a_stocked_simple_product_to_variants_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role_as' => 1]));
        $product = $this->product(4);

        Livewire::test(ProductForm::class, ['productId' => $product->id])
            ->set('hasVariants', true)
            ->set('variants', [[
                'id' => null, 'sku' => 'CONVERSION', 'barcode' => '', 'price' => 20,
                'sale_price' => '', 'stock' => 4, 'is_default' => true, 'status' => true,
                'attributes' => [],
            ]])
            ->call('save')->assertHasErrors(['variants']);

        $this->assertFalse($product->fresh()->has_variants);
        $this->assertSame(4, $product->fresh()->quantity);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_product_duplication_starts_with_zero_independent_stock(): void
    {
        $original = $this->product(6);
        $copy = app(ProductService::class)->duplicateProduct($original);

        $this->assertSame(6, $original->fresh()->quantity);
        $this->assertSame(0, $copy->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    private function assertMovement(int $productId, ?int $variantId, int $change, int $balance, string $source, int $actorId): void
    {
        $movement = InventoryMovement::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('quantity_change', $change)
            ->where('balance_after', $balance)
            ->where('type', InventoryMovement::TYPE_ADJUSTMENT)
            ->firstOrFail();

        $this->assertSame($source, $movement->meta['source']);
        $this->assertSame($actorId, $movement->meta['admin_user_id']);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $actorId, 'action' => 'stock_adjusted', 'subject_id' => $movement->id,
        ]);
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Catalog Stock', 'slug' => 'catalog-stock', 'status' => false,
            'description' => 'Catalog stock category', 'meta_title' => 'Catalog Stock',
            'meta_keyword' => 'catalog,stock', 'meta_description' => 'Catalog stock category',
        ]);
    }

    private function product(int $quantity, bool $variants = false): Product
    {
        return Product::create([
            'name' => 'Catalog Stock Product', 'slug' => 'catalog-stock-product',
            'category_id' => $this->category()->id, 'base_price' => 20,
            'quantity' => $quantity, 'stock_status' => 'in_stock',
            'has_variants' => $variants, 'status' => true,
        ]);
    }

    private function variant(Product $product, int $stock, string $sku): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id, 'sku' => $sku, 'price' => 20,
            'stock' => $stock, 'status' => true, 'is_default' => true,
        ]);
    }
}
