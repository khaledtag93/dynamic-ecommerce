<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSupplierLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_live_list_preserves_filters_permissions_sorting_and_escaped_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = $this->createSuperAdmin();
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        Supplier::create([
            'name' => '<script>Live Supplier</script>',
            'email' => 'live-supplier@example.test',
            'company' => 'Live Source',
            'is_active' => false,
        ]);
        Supplier::create([
            'name' => 'Other Supplier',
            'email' => 'other-supplier@example.test',
            'company' => 'Other Source',
            'is_active' => true,
        ]);

        $url = route('admin.suppliers.index', [
            'search' => 'live-supplier',
            'status' => 'inactive',
            'usage' => 'unused',
            'sort' => 'name',
            'direction' => 'asc',
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-search', false)
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('data-live-reset', false)
            ->assertSee('live-supplier@example.test')
            ->assertSee('&lt;script&gt;Live Supplier&lt;/script&gt;', false)
            ->assertDontSee('other-supplier@example.test');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('live-supplier@example.test')
            ->assertSee('&lt;script&gt;Live Supplier&lt;/script&gt;', false)
            ->assertDontSee('other-supplier@example.test')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }

    public function test_supplier_live_list_pagination_keeps_server_filtered_results(): void
    {
        $owner = $this->createSuperAdmin();

        foreach (range(1, 13) as $number) {
            Supplier::create([
                'name' => 'Supplier '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'email' => 'supplier'.$number.'@example.test',
                'is_active' => true,
            ]);
        }

        $url = route('admin.suppliers.index', [
            'status' => 'active',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 12,
            'page' => 2,
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('Supplier 13')
            ->assertDontSee('Supplier 01');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('Supplier 13')
            ->assertDontSee('Supplier 01')
            ->assertSee('data-live-results', false);
    }
}
