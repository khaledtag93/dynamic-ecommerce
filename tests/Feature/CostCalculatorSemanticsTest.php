<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCostSummary;
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
    }
}
