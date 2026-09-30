<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PromotionRule;
use App\Services\Commerce\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionMoneyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_admin_rejects_money_over_precision(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->from(route('admin.promotions.create'))
            ->post(route('admin.promotions.store'), [
                'name' => 'Precision Guard',
                'type' => PromotionRule::TYPE_ORDER_FIXED,
                'discount_value' => '10.001',
                'min_subtotal' => '20.001',
                'priority' => 0,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors([
                'discount_value',
                'min_subtotal',
            ]);

        $this->assertDatabaseMissing('promotion_rules', ['name' => 'Precision Guard']);
    }

    public function test_percentage_promotion_cannot_exceed_one_hundred_percent(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->from(route('admin.promotions.create'))
            ->post(route('admin.promotions.store'), [
                'name' => 'Too Large Percent',
                'type' => PromotionRule::TYPE_ORDER_PERCENTAGE,
                'discount_value' => '100.01',
                'priority' => 0,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('discount_value');

        $this->assertDatabaseMissing('promotion_rules', ['name' => 'Too Large Percent']);
    }

    public function test_minimum_subtotal_uses_exact_cent_boundary(): void
    {
        PromotionRule::query()->create([
            'name' => 'Exact Threshold',
            'type' => PromotionRule::TYPE_ORDER_FIXED,
            'discount_value' => '0.05',
            'min_subtotal' => '0.30',
            'is_active' => true,
        ]);

        $engine = app(PromotionEngine::class);

        $atThreshold = $engine->resolve(collect(), 0.1 + 0.2);
        $belowThreshold = $engine->resolve(collect(), 0.29);

        $this->assertSame(0.05, $atThreshold['discount']);
        $this->assertSame('Exact Threshold', $atThreshold['label']);
        $this->assertSame(0.0, $belowThreshold['discount']);
        $this->assertNull($belowThreshold['rule']);
    }

    public function test_order_percentage_rounds_half_up_to_exact_cents(): void
    {
        PromotionRule::query()->create([
            'name' => 'Half Cent Order',
            'type' => PromotionRule::TYPE_ORDER_PERCENTAGE,
            'discount_value' => '12.50',
            'is_active' => true,
        ]);

        $result = app(PromotionEngine::class)->resolve(collect(), 0.20);

        $this->assertSame(0.03, $result['discount']);
    }

    public function test_category_percentage_rounds_half_up_from_exact_eligible_subtotal(): void
    {
        $category = $this->category('Exact Category', 'exact-category');

        PromotionRule::query()->create([
            'name' => 'Half Cent Category',
            'type' => PromotionRule::TYPE_CATEGORY_PERCENTAGE,
            'discount_value' => '12.50',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $items = collect([
            (object) [
                'product' => (object) ['category_id' => $category->id],
                'quantity' => 1,
                'unit_price' => 0.20,
                'line_total' => 0.20,
            ],
            (object) [
                'product' => (object) ['category_id' => $category->id + 1000],
                'quantity' => 1,
                'unit_price' => 9.99,
                'line_total' => 9.99,
            ],
        ]);

        $result = app(PromotionEngine::class)->resolve($items, 10.19);

        $this->assertSame(0.03, $result['discount']);
    }

    public function test_buy_x_get_y_accumulates_cheapest_free_units_in_exact_cents(): void
    {
        PromotionRule::query()->create([
            'name' => 'Exact Buy 2 Get 1',
            'type' => PromotionRule::TYPE_BUY_X_GET_Y,
            'buy_quantity' => 2,
            'get_quantity' => 1,
            'is_active' => true,
        ]);

        $items = collect([
            (object) ['product' => null, 'quantity' => 1, 'unit_price' => 0.30, 'line_total' => 0.30],
            (object) ['product' => null, 'quantity' => 1, 'unit_price' => 0.10, 'line_total' => 0.10],
            (object) ['product' => null, 'quantity' => 1, 'unit_price' => 0.20, 'line_total' => 0.20],
        ]);

        $result = app(PromotionEngine::class)->resolve($items, 0.60);

        $this->assertSame(0.10, $result['discount']);
    }

    public function test_legacy_percentage_over_one_hundred_is_clamped_to_subtotal(): void
    {
        PromotionRule::query()->create([
            'name' => 'Legacy 250 Percent',
            'type' => PromotionRule::TYPE_ORDER_PERCENTAGE,
            'discount_value' => '250.00',
            'is_active' => true,
        ]);

        $result = app(PromotionEngine::class)->resolve(collect(), 0.20);

        $this->assertSame(0.20, $result['discount']);
    }

    private function category(string $name, string $slug): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
            'meta_title' => $name,
            'meta_keyword' => $slug,
            'meta_description' => $name,
            'status' => false,
        ]);
    }
}
