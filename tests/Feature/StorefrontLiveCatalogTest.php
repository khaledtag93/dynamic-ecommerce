<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
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

    private function category(string $name): Category
    {
        $token = Str::lower(Str::random(8));

        return Category::create([
            'name' => $name,
            'slug' => 'category-'.$token,
            'description' => 'Visible category',
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
