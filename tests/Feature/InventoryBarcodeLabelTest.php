<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryBarcodeLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_printing_is_admin_only(): void
    {
        $product = $this->product('Private Label Product', '6222000000001', false);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.inventory.labels', ['product' => $product->id]))
            ->assertRedirect('/');
    }

    public function test_simple_product_prints_requested_number_of_real_size_code128_labels(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Printable Product', '6222000000002', false, 'PRINT-001');

        $response = $this->actingAs($admin)
            ->get(route('admin.inventory.labels', [
                'product' => $product->id,
                'copies' => 3,
                'size' => '60x40',
                'show_price' => 1,
            ]))
            ->assertOk()
            ->assertSee(__('Code 128B ready'))
            ->assertSee('60mm', false)
            ->assertSee('40mm', false)
            ->assertSee('PRINT-001')
            ->assertSee('6222000000002')
            ->assertSee('25.00');

        $this->assertSame(3, substr_count($response->getContent(), 'data-barcode-label='));
        $this->assertStringContainsString('class="code128-barcode"', $response->getContent());
    }

    public function test_variant_product_requires_an_exact_variant_for_label_printing(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Variant Label Product', '6222000000003', true);

        $this->actingAs($admin)
            ->get(route('admin.inventory.labels', ['product' => $product->id]))
            ->assertNotFound();
    }

    public function test_variant_label_uses_the_variant_barcode_sku_and_price(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product('Variant Label Product', '6222000000004', true);
        $variant = $this->variant($product, 'LABEL-VAR-001', '6222000000041', 37.50);

        $this->actingAs($admin)
            ->get(route('admin.inventory.labels', [
                'product' => $product->id,
                'variant_id' => $variant->id,
            ]))
            ->assertOk()
            ->assertSee('LABEL-VAR-001')
            ->assertSee('6222000000041')
            ->assertSee('37.50')
            ->assertSee(__('Code 128B ready'));
    }

    public function test_variant_from_another_product_cannot_be_printed_under_this_product(): void
    {
        $admin = $this->createSuperAdmin();
        $first = $this->product('First Label Parent', null, true);
        $second = $this->product('Second Label Parent', null, true);
        $otherVariant = $this->variant($second, 'OTHER-VAR-001', '6222000000051', 20);

        $this->actingAs($admin)
            ->get(route('admin.inventory.labels', [
                'product' => $first->id,
                'variant_id' => $otherVariant->id,
            ]))
            ->assertNotFound();
    }

    public function test_missing_or_unsupported_barcode_shows_safe_non_printable_state(): void
    {
        $admin = $this->createSuperAdmin();
        $missing = $this->product('Missing Barcode Product', null, false);

        $this->actingAs($admin)
            ->get(route('admin.inventory.labels', ['product' => $missing->id]))
            ->assertOk()
            ->assertSee(__('This item does not have a barcode to print.'))
            ->assertDontSee('onclick="window.print()"', false);

        $unsupported = $this->product('Unsupported Barcode Product', 'باركود', false);

        $this->actingAs($admin)
            ->get(route('admin.inventory.labels', ['product' => $unsupported->id]))
            ->assertOk()
            ->assertSee(__('Use printable ASCII characters only.'))
            ->assertDontSee('class="code128-barcode"', false);
    }

    private function product(
        string $name,
        ?string $barcode,
        bool $hasVariants,
        ?string $sku = null
    ): Product {
        $token = Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'Label Category ' . $token,
            'slug' => 'label-category-' . $token,
            'description' => 'Label test category',
            'meta_title' => 'Label test',
            'meta_keyword' => 'label',
            'meta_description' => 'Label test category',
            'status' => false,
        ]);

        return Product::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $token,
            'sku' => $sku,
            'barcode' => $barcode,
            'category_id' => $category->id,
            'base_price' => 25,
            'quantity' => $hasVariants ? 0 : 5,
            'stock_status' => 'in_stock',
            'has_variants' => $hasVariants,
            'status' => true,
        ]);
    }

    private function variant(
        Product $product,
        string $sku,
        string $barcode,
        float $price
    ): ProductVariant {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'barcode' => $barcode,
            'price' => $price,
            'stock' => 4,
            'is_default' => true,
            'status' => true,
        ]);
    }
}
