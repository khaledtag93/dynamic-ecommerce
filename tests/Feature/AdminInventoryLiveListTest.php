<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInventoryLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_live_list_preserves_filters_permissions_and_escaped_movement_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = $this->createSuperAdmin();
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        $product = $this->product('Live Stock Product');
        $otherProduct = $this->product('Other Stock Product');

        InventoryMovement::create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'reason' => '<script>Live movement</script>',
            'quantity_change' => 3,
            'balance_after' => 8,
            'meta' => ['source' => 'manual_adjustment'],
        ]);
        InventoryMovement::create([
            'product_id' => $otherProduct->id,
            'type' => InventoryMovement::TYPE_PURCHASE_IN,
            'reason' => 'Other movement',
            'quantity_change' => 4,
            'balance_after' => 9,
        ]);

        $url = route('admin.inventory.index', [
            'search' => 'Live Stock',
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'reference' => 'manual',
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-search', false)
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-reset', false)
            ->assertSee('Live Stock Product')
            ->assertSee('&lt;script&gt;Live movement&lt;/script&gt;', false)
            ->assertDontSee('Other movement');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('Live Stock Product')
            ->assertSee('&lt;script&gt;Live movement&lt;/script&gt;', false)
            ->assertDontSee('Other movement')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }

    public function test_inventory_live_list_pagination_keeps_server_filtered_results(): void
    {
        $owner = $this->createSuperAdmin();
        $product = $this->product('Paging Stock Product');

        foreach (range(1, 21) as $number) {
            InventoryMovement::create([
                'product_id' => $product->id,
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'reason' => 'PAGE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'quantity_change' => 1,
                'balance_after' => $number,
                'meta' => ['source' => 'manual_adjustment'],
            ]);
        }

        $url = route('admin.inventory.index', [
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'reference' => 'manual',
            'per_page' => 20,
            'page' => 2,
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('PAGE-01')
            ->assertDontSee('PAGE-21');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('PAGE-01')
            ->assertDontSee('PAGE-21')
            ->assertSee('data-live-results', false);
    }

    private function product(string $name): Product
    {
        $token = Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'Inventory Category '.$token,
            'slug' => 'inventory-category-'.$token,
            'description' => 'Inventory live list category',
            'meta_title' => 'Inventory test',
            'meta_keyword' => 'inventory',
            'meta_description' => 'Inventory live list category',
            'status' => false,
        ]);

        return Product::create([
            'name' => $name,
            'slug' => 'inventory-product-'.$token,
            'category_id' => $category->id,
            'base_price' => 20,
            'quantity' => 5,
            'low_stock_threshold' => 2,
            'has_variants' => false,
            'status' => true,
        ]);
    }
}
