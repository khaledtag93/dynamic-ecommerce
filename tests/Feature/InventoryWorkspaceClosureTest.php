<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryWorkspaceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_searches_are_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/InventoryController.php'));

        $this->assertSame(2, substr_count($controller, 'mb_substr(trim((string) $request->string(\'search\')), 0, 100)'));
        $this->assertSame(2, substr_count($controller, "str_replace(['\\\\', '%', '_']"));
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_inventory_adjust_form_matches_server_input_contract(): void
    {
        $view = file_get_contents(resource_path('views/admin/inventory/adjust.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/InventoryController.php'));

        $this->assertStringContainsString('name="search" value="{{ $search }}" maxlength="100"', $view);
        $this->assertStringContainsString('id="new_stock" name="new_stock" type="number" min="0" max="999999999" step="1" required aria-required="true"', $view);
        $this->assertStringContainsString('id="reason" name="reason" type="text" minlength="5" maxlength="255" required aria-required="true"', $view);

        $this->assertStringContainsString("'new_stock' => ['required', 'integer', 'min:0', 'max:999999999']", $controller);
        $this->assertStringContainsString("'reason' => ['required', 'string', 'min:5', 'max:255']", $controller);
    }

    public function test_inventory_attention_includes_variant_stock_and_expiry_risks(): void
    {
        $admin = $this->createSuperAdmin();
        $category = Category::create([
            'name' => 'Inventory attention',
            'slug' => 'inventory-attention-'.Str::lower(Str::random(8)),
            'description' => 'Inventory attention test category',
            'meta_title' => 'Inventory attention',
            'meta_keyword' => 'inventory',
            'meta_description' => 'Inventory attention test category',
            'status' => false,
        ]);

        $simple = Product::create([
            'name' => 'Simple low stock',
            'slug' => 'simple-low-stock-'.Str::lower(Str::random(8)),
            'category_id' => $category->id,
            'base_price' => 20,
            'quantity' => 10,
            'low_stock_threshold' => 2,
            'expiration_date' => today()->addDays(10),
            'has_variants' => false,
            'status' => true,
        ]);
        $simple->forceFill(['reorder_point' => 0])->save();

        InventoryLot::query()->create([
            'product_id' => $simple->id,
            'lot_code' => 'INV-SIMPLE-EXPIRED',
            'source_type' => 'test_seed',
            'initial_quantity' => 9,
            'quantity_on_hand' => 9,
            'unit_cost' => 10,
            'expiration_date' => today()->subDay(),
            'received_at' => now()->subDays(5),
        ]);
        InventoryLot::query()->create([
            'product_id' => $simple->id,
            'lot_code' => 'INV-SIMPLE-UPCOMING',
            'source_type' => 'test_seed',
            'initial_quantity' => 1,
            'quantity_on_hand' => 1,
            'unit_cost' => 10,
            'expiration_date' => today()->addDays(10),
            'received_at' => now()->subDay(),
        ]);

        $variantParent = Product::create([
            'name' => 'Variant inventory product',
            'slug' => 'variant-inventory-product-'.Str::lower(Str::random(8)),
            'category_id' => $category->id,
            'base_price' => 20,
            'quantity' => 50,
            'low_stock_threshold' => 0,
            'has_variants' => true,
            'status' => true,
        ]);

        $expiredVariant = ProductVariant::create([
            'product_id' => $variantParent->id,
            'sku' => 'LOW-EXPIRED-VARIANT',
            'price' => 20,
            'stock' => 1,
            'reorder_point' => 2,
            'expiration_date' => today()->subDay(),
            'status' => true,
        ]);

        $healthyVariant = ProductVariant::create([
            'product_id' => $variantParent->id,
            'sku' => 'HEALTHY-FUTURE-VARIANT',
            'price' => 20,
            'stock' => 10,
            'reorder_point' => 2,
            'expiration_date' => today()->addDays(90),
            'status' => true,
        ]);

        InventoryLot::query()->create([
            'product_id' => $variantParent->id,
            'product_variant_id' => $expiredVariant->id,
            'lot_code' => 'INV-VAR-EXPIRED',
            'source_type' => 'test_seed',
            'initial_quantity' => 1,
            'quantity_on_hand' => 1,
            'unit_cost' => 10,
            'expiration_date' => today()->subDay(),
            'received_at' => now()->subDays(3),
        ]);
        InventoryLot::query()->create([
            'product_id' => $variantParent->id,
            'product_variant_id' => $healthyVariant->id,
            'lot_code' => 'INV-VAR-HEALTHY',
            'source_type' => 'test_seed',
            'initial_quantity' => 10,
            'quantity_on_hand' => 10,
            'unit_cost' => 10,
            'expiration_date' => today()->addDays(90),
            'received_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Simple low stock')
            ->assertSee('LOW-EXPIRED-VARIANT')
            ->assertSee('INV-SIMPLE-EXPIRED')
            ->assertSee('INV-SIMPLE-UPCOMING')
            ->assertSee(__('Quantity') . ': 9')
            ->assertSee(__('Quantity') . ': 1')
            ->assertSee(__('Expired'))
            ->assertSee(__('Upcoming'))
            ->assertDontSee('HEALTHY-FUTURE-VARIANT');
    }

    public function test_inventory_list_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/inventory/index.blade.php'));

        foreach ([
            'inventorySearch',
            'inventoryMovementType',
            'inventorySource',
            'inventoryPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }
}
