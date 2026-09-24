<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryBarcodeScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_scanner_is_admin_only_and_renders_scanner_ready_state(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.inventory.scan'))
            ->assertRedirect('/');

        $admin = User::factory()->create(['role_as' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.inventory.scan'))
            ->assertOk()
            ->assertSee('id="barcodeScanInput"', false)
            ->assertSee(__('Ready for a barcode'));
    }

    public function test_exact_simple_product_barcode_opens_the_correct_stock_item(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('Simple Scan Product', '6221000000001', 7, false);

        $this->actingAs($admin)
            ->get(route('admin.inventory.scan', ['barcode' => $product->barcode]))
            ->assertOk()
            ->assertSee('Simple Scan Product')
            ->assertSee(__('Exact barcode match'))
            ->assertSee(__('Current stock'))
            ->assertSee('7')
            ->assertSee('product_id=' . $product->id, false)
            ->assertSee(__('Adjust this stock'));
    }

    public function test_exact_variant_barcode_targets_that_variant_without_guessing(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('Variant Scan Product', null, 0, true);
        $variant = $this->variant($product, 'SCAN-VAR-001', '6221000000002', 9);

        $this->actingAs($admin)
            ->get(route('admin.inventory.scan', ['barcode' => $variant->barcode]))
            ->assertOk()
            ->assertSee('Variant Scan Product')
            ->assertSee('SCAN-VAR-001')
            ->assertSee(__('Variant barcode matched'))
            ->assertSee('variant_id=' . $variant->id, false)
            ->assertSee('9');
    }

    public function test_parent_product_barcode_requires_explicit_variant_selection(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $product = $this->product('Parent Scan Product', '6221000000003', 0, true);
        $first = $this->variant($product, 'PARENT-RED', '6221000000031', 4);
        $second = $this->variant($product, 'PARENT-BLUE', '6221000000032', 6);

        $response = $this->actingAs($admin)
            ->get(route('admin.inventory.scan', ['barcode' => $product->barcode]))
            ->assertOk()
            ->assertSee(__('Choose the exact variant'))
            ->assertSee('PARENT-RED')
            ->assertSee('PARENT-BLUE')
            ->assertSee('variant_id=' . $first->id, false)
            ->assertSee('variant_id=' . $second->id, false);

        $response->assertSee(__('This barcode identifies the parent product, not one exact stock variant.'));
    }

    public function test_unknown_barcode_returns_a_safe_no_match_state(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.inventory.scan', ['barcode' => '6221999999999']))
            ->assertOk()
            ->assertSee(__('No exact barcode match'))
            ->assertDontSee(__('Adjust this stock'));
    }

    public function test_legacy_cross_table_barcode_collision_is_reported_without_stock_action(): void
    {
        $admin = User::factory()->create(['role_as' => 1]);
        $barcode = '6221000000004';

        $this->product('Legacy Barcode Owner', $barcode, 2, false);
        $variantParent = $this->product('Legacy Variant Parent', null, 0, true);
        $this->variant($variantParent, 'LEGACY-VAR-001', $barcode, 3);

        $this->actingAs($admin)
            ->get(route('admin.inventory.scan', ['barcode' => $barcode]))
            ->assertOk()
            ->assertSee(__('Barcode needs attention'))
            ->assertSee(__('This barcode matches multiple catalog records. Resolve the duplicate identifiers before using scanner lookup.'))
            ->assertDontSee(__('Adjust this stock'));
    }

    private function product(string $name, ?string $barcode, int $quantity, bool $hasVariants): Product
    {
        $token = Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'Scanner Category ' . $token,
            'slug' => 'scanner-category-' . $token,
            'description' => 'Scanner test category',
            'meta_title' => 'Scanner test',
            'meta_keyword' => 'scanner',
            'meta_description' => 'Scanner test category',
            'status' => false,
        ]);

        return Product::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $token,
            'barcode' => $barcode,
            'category_id' => $category->id,
            'base_price' => 25,
            'quantity' => $quantity,
            'stock_status' => $quantity > 0 ? 'in_stock' : 'out_of_stock',
            'has_variants' => $hasVariants,
            'status' => true,
        ]);
    }

    private function variant(Product $product, string $sku, string $barcode, int $stock): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'barcode' => $barcode,
            'price' => 25,
            'stock' => $stock,
            'is_default' => $product->variants()->doesntExist(),
            'status' => true,
        ]);
    }
}
