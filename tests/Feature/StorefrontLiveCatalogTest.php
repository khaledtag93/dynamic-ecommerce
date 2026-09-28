<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Services\Commerce\AIRecommendationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontLiveCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_same_filtered_products_for_full_and_live_results(): void
    {
        $category = $this->category('Search Category');
        $match = $this->product($category, '<script>Live Search Product</script>', 8, 20, 15);
        $other = $this->product($category, 'Other Product', 0, 30, null);

        $url = route('frontend.search', [
            'q' => 'Live Search',
            'availability' => 'in_stock',
            'offer' => 'on_sale',
            'sort' => 'name_az',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Live Search Product&lt;/script&gt;', false)
            ->assertDontSee('Other Product');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Live Search Product&lt;/script&gt;', false)
            ->assertDontSee('Other Product')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);
    }

    public function test_category_live_results_keep_category_scope_and_filters(): void
    {
        $category = $this->category('Live Category');
        $otherCategory = $this->category('Other Category');
        $this->product($category, '<script>Category Match</script>', 5, 20, 10);
        $this->product($category, 'Out Of Stock Match', 0, 20, 10);
        $this->product($otherCategory, 'Wrong Category Product', 5, 20, 10);

        $url = route('category.products', $category->id).'?'.http_build_query([
            'q' => 'Match',
            'availability' => 'in_stock',
            'offer' => 'on_sale',
            'sort' => 'latest',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Category Match&lt;/script&gt;', false)
            ->assertDontSee('Out Of Stock Match')
            ->assertDontSee('Wrong Category Product');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Category Match&lt;/script&gt;', false)
            ->assertDontSee('Out Of Stock Match')
            ->assertDontSee('Wrong Category Product')
            ->assertDontSee('<html', false);
    }

    public function test_in_stock_filter_and_product_card_exclude_expired_tracked_stock(): void
    {
        $category = $this->category('Tracked Availability');
        $expired = $this->product($category, 'Expired Aggregate Stock', 5, 20, null);

        InventoryLot::query()->create([
            'product_id' => $expired->id,
            'lot_code' => 'CAT-EXP-'.Str::upper(Str::random(6)),
            'source_type' => 'test_seed',
            'initial_quantity' => 5,
            'quantity_on_hand' => 5,
            'unit_cost' => 10,
            'expiration_date' => today()->subDay(),
            'received_at' => now()->subDays(2),
        ]);

        $this->get(route('frontend.search', [
            'q' => 'Expired Aggregate',
            'availability' => 'in_stock',
        ]))
            ->assertOk()
            ->assertDontSee('Expired Aggregate Stock');

        $this->get(route('category.products', $category->id))
            ->assertOk()
            ->assertViewHas('categoryStats', fn (array $stats) => (int) $stats['in_stock'] === 0)
            ->assertSee('Expired Aggregate Stock')
            ->assertSee('data-product-in-stock="0"', false);

        $this->get(route('frontend.products.show', $expired))
            ->assertOk()
            ->assertSee(__('Currently unavailable'))
            ->assertSee(__('Out of stock'));
    }

    public function test_ai_recommendation_stock_signal_uses_sellable_lot_quantity(): void
    {
        $category = $this->category('AI Availability');
        $expired = $this->product($category, 'AI Expired Stock', 5, 20, null);
        $sellable = $this->product($category, 'AI Sellable Stock', 1, 20, null);

        InventoryLot::query()->create([
            'product_id' => $expired->id,
            'lot_code' => 'AI-EXP-'.Str::upper(Str::random(6)),
            'source_type' => 'test_seed',
            'initial_quantity' => 5,
            'quantity_on_hand' => 5,
            'unit_cost' => 10,
            'expiration_date' => today()->subDay(),
            'received_at' => now()->subDays(2),
        ]);

        InventoryLot::query()->create([
            'product_id' => $sellable->id,
            'lot_code' => 'AI-OK-'.Str::upper(Str::random(6)),
            'source_type' => 'test_seed',
            'initial_quantity' => 1,
            'quantity_on_hand' => 1,
            'unit_cost' => 10,
            'expiration_date' => today()->addDays(30),
            'received_at' => now(),
        ]);

        $products = app(AIRecommendationEngine::class)->forHome(8)['products']->keyBy('id');

        $this->assertFalse($products->get($expired->id)->in_stock);
        $this->assertTrue($products->get($sellable->id)->in_stock);
        $this->assertGreaterThan(
            (int) $products->get($expired->id)->ai_score,
            (int) $products->get($sellable->id)->ai_score
        );
    }

    public function test_storefront_search_treats_like_wildcards_as_literals_and_bounds_query_length(): void
    {
        $category = $this->category('Wildcard Search Category');
        $percent = $this->product($category, 'Save 100% Cotton', 5, 20, null);
        $underscore = $this->product($category, 'Model_A', 5, 20, null);
        $this->product($category, 'ModelXA', 5, 20, null);
        $this->product($category, 'Save 100 Cotton', 5, 20, null);

        $this->get(route('frontend.search', ['q' => '100%']))
            ->assertOk()
            ->assertSee($percent->name)
            ->assertDontSee('Save 100 Cotton');

        $this->get(route('frontend.search', ['q' => 'Model_A']))
            ->assertOk()
            ->assertSee($underscore->name)
            ->assertDontSee('ModelXA');

        $longQuery = str_repeat('a', 140);

        $this->get(route('frontend.search', ['q' => $longQuery]))
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters) => mb_strlen($filters['q']) === 100);

        $this->get(route('category.products', ['id' => $category->id, 'q' => $longQuery]))
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters) => mb_strlen($filters['q']) === 100);
    }

    public function test_storefront_catalog_filters_have_explicit_labels_and_search_limits(): void
    {
        $search = file_get_contents(resource_path('views/frontend/products/search.blade.php'));
        $category = file_get_contents(resource_path('views/frontend/products/by_category.blade.php'));

        foreach ([
            'catalogSearch',
            'catalogAvailability',
            'catalogOffer',
            'catalogSort',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $search);
            $this->assertStringContainsString('id="' . $controlId . '"', $search);
        }

        foreach ([
            'categoryCatalogSearch',
            'categoryAvailability',
            'categoryOffer',
            'categorySort',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $category);
            $this->assertStringContainsString('id="' . $controlId . '"', $category);
        }

        $this->assertStringContainsString('maxlength="100"', $search);
        $this->assertStringContainsString('maxlength="100"', $category);

        $controller = file_get_contents(app_path('Http/Controllers/Frontend/FrontendController.php'));

        $this->assertSame(
            2,
            substr_count($controller, 'mb_substr(trim((string) $request->string(\'q\')), 0, 100)')
        );
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_category_quick_view_is_accessible_and_respects_product_stock_state(): void
    {
        $category = $this->category('Quick View Category');
        $this->product($category, 'Quick View In Stock', 5, 20, null);
        $this->product($category, 'Quick View Unavailable', 0, 20, null);

        $this->get(route('category.products', $category->id))
            ->assertOk()
            ->assertSee('aria-labelledby="quickViewName"', false)
            ->assertSee('aria-label="' . __('Quick view') . ': Quick View In Stock"', false)
            ->assertSee('aria-label="' . __('Quick view') . ': Quick View Unavailable"', false)
            ->assertSee('data-product-in-stock="1"', false)
            ->assertSee('data-product-in-stock="0"', false)
            ->assertSee("elements.image.alt = productName", false)
            ->assertSee("cartButton.disabled = !inStock", false);
    }

    private function category(string $name): Category
    {
        $token = Str::lower(Str::random(8));

        return Category::create([
            'name' => $name,
            'slug' => 'category-'.$token,
            'description' => 'Visible category',
            'meta_title' => $name,
            'meta_keyword' => Str::lower($name),
            'meta_description' => 'Visible category test fixture',
            'status' => 0,
        ]);
    }

    private function product(Category $category, string $name, int $quantity, float $basePrice, ?float $salePrice): Product
    {
        $token = Str::lower(Str::random(8));

        return Product::create([
            'name' => $name,
            'slug' => 'product-'.$token,
            'category_id' => $category->id,
            'base_price' => $basePrice,
            'sale_price' => $salePrice,
            'quantity' => $quantity,
            'has_variants' => false,
            'status' => true,
        ]);
    }
}
