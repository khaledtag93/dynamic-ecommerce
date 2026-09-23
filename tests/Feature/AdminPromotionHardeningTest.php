<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Services\Commerce\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminPromotionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_uses_only_currently_active_promotions(): void
    {
        PromotionRule::create([
            'name' => 'Expired 90%',
            'type' => PromotionRule::TYPE_ORDER_PERCENTAGE,
            'discount_value' => 90,
            'is_active' => true,
            'ends_at' => now()->subMinute(),
        ]);

        PromotionRule::create([
            'name' => 'Current 10%',
            'type' => PromotionRule::TYPE_ORDER_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $result = app(PromotionEngine::class)->resolve(collect(), 200);

        $this->assertSame(20.0, $result['discount']);
        $this->assertSame('Current 10%', $result['label']);
    }

    public function test_minimum_subtotal_is_respected(): void
    {
        PromotionRule::create([
            'name' => 'Spend 500',
            'type' => PromotionRule::TYPE_ORDER_FIXED,
            'discount_value' => 100,
            'min_subtotal' => 500,
            'is_active' => true,
        ]);

        $result = app(PromotionEngine::class)->resolve(collect(), 499.99);

        $this->assertSame(0.0, $result['discount']);
        $this->assertNull($result['rule']);
    }

    public function test_fixed_discount_never_exceeds_order_subtotal(): void
    {
        PromotionRule::create([
            'name' => 'Large fixed offer',
            'type' => PromotionRule::TYPE_ORDER_FIXED,
            'discount_value' => 500,
            'is_active' => true,
        ]);

        $result = app(PromotionEngine::class)->resolve(collect(), 120);

        $this->assertSame(120.0, $result['discount']);
    }

    public function test_category_percentage_only_discounts_matching_category_lines(): void
    {
        $eligibleCategory = $this->category('Eligible', 'eligible');
        $otherCategory = $this->category('Other', 'other');

        $eligibleProduct = $this->product($eligibleCategory, 'Eligible Product', 'eligible-product');
        $otherProduct = $this->product($otherCategory, 'Other Product', 'other-product');

        PromotionRule::create([
            'name' => 'Category 25%',
            'type' => PromotionRule::TYPE_CATEGORY_PERCENTAGE,
            'discount_value' => 25,
            'category_id' => $eligibleCategory->id,
            'is_active' => true,
        ]);

        $items = collect([
            $this->item($eligibleProduct, 2, 100),
            $this->item($otherProduct, 1, 400),
        ]);

        $result = app(PromotionEngine::class)->resolve($items, 600);

        $this->assertSame(50.0, $result['discount']);
    }

    public function test_buy_x_get_y_uses_cheapest_eligible_units(): void
    {
        $category = $this->category('Bundle', 'bundle');
        $cheap = $this->product($category, 'Cheap', 'cheap');
        $expensive = $this->product($category, 'Expensive', 'expensive');

        PromotionRule::create([
            'name' => 'Buy 2 Get 1',
            'type' => PromotionRule::TYPE_BUY_X_GET_Y,
            'buy_quantity' => 2,
            'get_quantity' => 1,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $items = collect([
            $this->item($expensive, 2, 100),
            $this->item($cheap, 1, 40),
        ]);

        $result = app(PromotionEngine::class)->resolve($items, 240);

        $this->assertSame(40.0, $result['discount']);
    }

    public function test_engine_selects_largest_discount_instead_of_priority_alone(): void
    {
        PromotionRule::create([
            'name' => 'High priority small discount',
            'type' => PromotionRule::TYPE_ORDER_FIXED,
            'discount_value' => 10,
            'priority' => 100,
            'is_active' => true,
        ]);

        PromotionRule::create([
            'name' => 'Lower priority better discount',
            'type' => PromotionRule::TYPE_ORDER_PERCENTAGE,
            'discount_value' => 20,
            'priority' => 1,
            'is_active' => true,
        ]);

        $result = app(PromotionEngine::class)->resolve(collect(), 200);

        $this->assertSame(40.0, $result['discount']);
        $this->assertSame('Lower priority better discount', $result['label']);
    }

    public function test_category_percentage_requires_a_category(): void
    {
        $owner = \App\Models\User::factory()->create(['role_as' => 1]);

        $this->actingAs($owner)
            ->post(route('admin.promotions.store'), [
                'name' => 'Missing category',
                'type' => PromotionRule::TYPE_CATEGORY_PERCENTAGE,
                'discount_value' => 15,
                'priority' => 0,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('promotion_rules', ['name' => 'Missing category']);
    }

    public function test_switching_promotion_type_clears_irrelevant_configuration(): void
    {
        $owner = \App\Models\User::factory()->create(['role_as' => 1]);
        $category = $this->category('Old Category', 'old-category');

        $promotion = PromotionRule::create([
            'name' => 'Changing rule',
            'type' => PromotionRule::TYPE_BUY_X_GET_Y,
            'discount_value' => 0,
            'category_id' => $category->id,
            'buy_quantity' => 2,
            'get_quantity' => 1,
            'priority' => 4,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->put(route('admin.promotions.update', $promotion), [
                'name' => 'Changing rule',
                'type' => PromotionRule::TYPE_ORDER_FIXED,
                'discount_value' => 30,
                'category_id' => $category->id,
                'buy_quantity' => 9,
                'get_quantity' => 3,
                'priority' => 4,
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $promotion->refresh();

        $this->assertSame(PromotionRule::TYPE_ORDER_FIXED, $promotion->type);
        $this->assertNull($promotion->category_id);
        $this->assertNull($promotion->buy_quantity);
        $this->assertNull($promotion->get_quantity);
        $this->assertSame('30.00', $promotion->discount_value);
    }

    private function category(string $name, string $slug): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
            'meta_title' => $name,
            'meta_keyword' => $slug,
            'meta_description' => $name,
            'status' => false,
        ]);
    }

    private function product(Category $category, string $name, string $slug): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => $slug,
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 10,
            'stock_status' => 'in_stock',
            'status' => 1,
        ]);
    }

    private function item(Product $product, int $quantity, float $unitPrice): object
    {
        return (object) [
            'product' => $product,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ];
    }
}
