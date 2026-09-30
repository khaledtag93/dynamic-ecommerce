<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCostSummary;
use App\Models\ProductExtraCostItem;
use App\Models\ProductMaterialCostItem;
use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CostCalculatorSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_calculator_uses_theme_aware_sections_and_in_app_delete_confirmation(): void
    {
        $admin = $this->createSuperAdmin();

        RawMaterial::query()->create([
            'name' => 'Test Material',
            'code' => 'MAT-TEST',
            'unit' => 'kg',
            'unit_price' => 10,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.cost-calculator.index'));

        $response
            ->assertOk()
            ->assertSee('Cost calculator sections')
            ->assertSee('Raw Materials')
            ->assertSee('Product Recipe')
            ->assertDontSee('return confirm(', false);

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'id="cost-materials"'));
        $this->assertSame(1, substr_count($html, 'id="cost-recipe"'));
        $this->assertStringContainsString('data-confirm-title="Delete material"', $html);
    }

    public function test_profit_margin_uses_selling_price_not_cost_as_denominator(): void
    {
        $admin = $this->createSuperAdmin();

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Cost Test '.Str::random(6),
            'slug' => 'cost-test-'.Str::lower(Str::random(8)),
            'description' => 'Cost calculator test category',
            'meta_title' => 'Cost',
            'meta_keyword' => 'cost',
            'meta_description' => 'Cost calculator test category',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'name' => 'Margin Product '.Str::random(6),
            'slug' => 'margin-product-'.Str::lower(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 100,
            'cost_price' => 0,
            'inventory_cost_price' => 55,
            'quantity' => 1,
            'status' => true,
            'has_variants' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.cost-calculator.save'), [
                'product_id' => $product->id,
                'selling_price' => 100,
                'extras' => [
                    ['name' => 'Production cost', 'amount' => 80],
                ],
            ]);

        $response->assertRedirect(route('admin.cost-calculator.index', [
            'product_id' => $product->id,
        ]));

        $summary = ProductCostSummary::query()
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame(80.0, (float) $summary->total_cost);
        $this->assertSame(20.0, (float) $summary->profit);
        $this->assertSame(20.0, (float) $summary->profit_margin);
        $this->assertSame(80.0, (float) $product->fresh()->cost_price);
        $this->assertSame(55.0, (float) $product->fresh()->inventory_cost_price);
    }

    public function test_cost_calculator_uses_exact_decimal_arithmetic(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->createCostProduct();

        $response = $this->actingAs($admin)->post(route('admin.cost-calculator.save'), [
            'product_id' => $product->id,
            'selling_price' => '10.00',
            'materials' => [[
                'material_name' => 'Precision material',
                'unit' => 'kg',
                'quantity' => '0.050',
                'unit_price' => '0.10',
            ]],
            'extras' => [
                ['name' => 'Packaging', 'amount' => '0.10'],
                ['name' => 'Handling', 'amount' => '0.20'],
            ],
        ]);

        $response->assertRedirect(route('admin.cost-calculator.index', ['product_id' => $product->id]));

        $summary = ProductCostSummary::where('product_id', $product->id)->firstOrFail();
        $material = ProductMaterialCostItem::where('product_id', $product->id)->firstOrFail();

        $this->assertSame('0.01', $summary->materials_cost);
        $this->assertSame('0.30', $summary->extra_cost);
        $this->assertSame('0.31', $summary->total_cost);
        $this->assertSame('9.69', $summary->profit);
        $this->assertSame('96.90', $summary->profit_margin);
        $this->assertSame('0.01', $material->total_cost);
        $this->assertSame(0.31, (float) $product->fresh()->cost_price);
    }

    public function test_cost_calculator_rejects_money_and_quantity_over_precision(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->createCostProduct();

        $this->actingAs($admin)
            ->from(route('admin.cost-calculator.index'))
            ->post(route('admin.cost-calculator.materials.store'), [
                'name' => 'Over precision',
                'unit' => 'kg',
                'unit_price' => '1.001',
            ])
            ->assertSessionHasErrors('unit_price');

        $this->actingAs($admin)
            ->from(route('admin.cost-calculator.index', ['product_id' => $product->id]))
            ->post(route('admin.cost-calculator.save'), [
                'product_id' => $product->id,
                'selling_price' => '10.001',
                'materials' => [[
                    'material_name' => 'Over precision material',
                    'quantity' => '1.0001',
                    'unit_price' => '2.001',
                ]],
                'extras' => [['name' => 'Over precision extra', 'amount' => '3.001']],
            ])
            ->assertSessionHasErrors([
                'selling_price',
                'materials.0.quantity',
                'materials.0.unit_price',
                'extras.0.amount',
            ]);

        $this->assertDatabaseMissing('raw_materials', ['name' => 'Over precision']);
        $this->assertDatabaseMissing('product_cost_summaries', ['product_id' => $product->id]);
    }

    public function test_derived_cost_overflow_rolls_back_existing_recipe(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->createCostProduct();

        ProductCostSummary::create([
            'product_id' => $product->id,
            'materials_cost' => '0.00',
            'extra_cost' => '1.00',
            'total_cost' => '1.00',
            'selling_price' => '2.00',
            'profit' => '1.00',
            'profit_margin' => '50.00',
        ]);
        ProductExtraCostItem::create([
            'product_id' => $product->id,
            'name' => 'Existing cost',
            'amount' => '1.00',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.cost-calculator.index', ['product_id' => $product->id]))
            ->post(route('admin.cost-calculator.save'), [
                'product_id' => $product->id,
                'selling_price' => '2.00',
                'extras' => [
                    ['name' => 'Large A', 'amount' => '6000000000.00'],
                    ['name' => 'Large B', 'amount' => '5000000000.00'],
                ],
            ])
            ->assertSessionHasErrors('extras');

        $this->assertDatabaseHas('product_cost_summaries', [
            'product_id' => $product->id,
            'total_cost' => '1.00',
        ]);
        $this->assertDatabaseHas('product_extra_cost_items', [
            'product_id' => $product->id,
            'name' => 'Existing cost',
            'amount' => '1.00',
        ]);
        $this->assertSame(1, ProductExtraCostItem::where('product_id', $product->id)->count());
    }

    public function test_profit_margin_overflow_is_rejected_before_persistence(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->createCostProduct();

        $this->actingAs($admin)
            ->from(route('admin.cost-calculator.index', ['product_id' => $product->id]))
            ->post(route('admin.cost-calculator.save'), [
                'product_id' => $product->id,
                'selling_price' => '0.01',
                'extras' => [[
                    'name' => 'Extreme cost',
                    'amount' => '9999999999.99',
                ]],
            ])
            ->assertSessionHasErrors('selling_price');

        $this->assertDatabaseMissing('product_cost_summaries', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('product_extra_cost_items', ['product_id' => $product->id]);
        $this->assertSame(0.0, (float) $product->fresh()->cost_price);
    }

    private function createCostProduct(): Product
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Cost Precision '.Str::random(6),
            'slug' => 'cost-precision-'.Str::lower(Str::random(8)),
            'description' => 'Cost precision test category',
            'meta_title' => 'Cost',
            'meta_keyword' => 'cost',
            'meta_description' => 'Cost precision test category',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->create([
            'name' => 'Cost Product '.Str::random(6),
            'slug' => 'cost-product-'.Str::lower(Str::random(8)),
            'category_id' => $categoryId,
            'base_price' => 100,
            'cost_price' => 0,
            'inventory_cost_price' => 55,
            'quantity' => 1,
            'status' => true,
            'has_variants' => false,
        ]);
    }
}
