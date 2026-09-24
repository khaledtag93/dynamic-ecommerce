<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPurchaseLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_live_list_preserves_filters_permissions_and_escaped_supplier_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = User::factory()->create(['role_as' => 1]);
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        $supplier = Supplier::create([
            'name' => '<script>Live Vendor</script>',
            'company' => 'Live Procurement',
            'email' => 'live-vendor@example.test',
            'is_active' => true,
        ]);
        $otherSupplier = Supplier::create([
            'name' => 'Other Vendor',
            'company' => 'Other Procurement',
            'email' => 'other-vendor@example.test',
            'is_active' => true,
        ]);

        Purchase::create([
            'supplier_id' => $supplier->id,
            'reference' => 'PO-LIVE-MATCH',
            'status' => Purchase::STATUS_ORDERED,
            'purchase_date' => now()->toDateString(),
            'grand_total' => 125,
        ]);
        Purchase::create([
            'supplier_id' => $otherSupplier->id,
            'reference' => 'PO-LIVE-OTHER',
            'status' => Purchase::STATUS_RECEIVED,
            'purchase_date' => now()->toDateString(),
            'grand_total' => 50,
        ]);

        $url = route('admin.purchases.index', [
            'search' => 'PO-LIVE-MATCH',
            'status' => Purchase::STATUS_ORDERED,
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-search', false)
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('data-live-reset', false)
            ->assertSee('PO-LIVE-MATCH')
            ->assertSee('&lt;script&gt;Live Vendor&lt;/script&gt;', false)
            ->assertDontSee('PO-LIVE-OTHER');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('PO-LIVE-MATCH')
            ->assertSee('&lt;script&gt;Live Vendor&lt;/script&gt;', false)
            ->assertDontSee('PO-LIVE-OTHER')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }

    public function test_purchase_live_list_pagination_keeps_server_filtered_results(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
        $supplier = Supplier::create([
            'name' => 'Paging Vendor',
            'is_active' => true,
        ]);

        foreach (range(1, 16) as $number) {
            Purchase::create([
                'supplier_id' => $supplier->id,
                'reference' => 'PO-PAGE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'status' => Purchase::STATUS_ORDERED,
                'purchase_date' => now()->toDateString(),
                'grand_total' => $number,
            ]);
        }

        $url = route('admin.purchases.index', [
            'status' => Purchase::STATUS_ORDERED,
            'supplier_id' => $supplier->id,
            'per_page' => 15,
            'page' => 2,
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('PO-PAGE-01')
            ->assertDontSee('PO-PAGE-16');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('PO-PAGE-01')
            ->assertDontSee('PO-PAGE-16')
            ->assertSee('data-live-results', false);
    }
}
