<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCouponLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_live_list_preserves_filters_permissions_sorting_and_escaped_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = $this->createSuperAdmin();
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        Coupon::create([
            'name' => '<script>Live Coupon</script>',
            'code' => 'LIVE10',
            'type' => Coupon::TYPE_FIXED,
            'value' => 10,
            'used_count' => 5,
            'usage_limit' => 5,
            'ends_at' => now()->subDay(),
            'is_active' => true,
            'notes' => '<script>Coupon note</script>',
        ]);
        Coupon::create([
            'name' => 'Other Coupon',
            'code' => 'OTHER10',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $url = route('admin.coupons.index', [
            'search' => 'LIVE10',
            'type' => Coupon::TYPE_FIXED,
            'status' => 'expired',
            'usage' => 'limit_reached',
            'sort' => 'type',
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
            ->assertSee('LIVE10')
            ->assertSee('&lt;script&gt;Live Coupon&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;Coupon note&lt;/script&gt;', false)
            ->assertDontSee('OTHER10');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('LIVE10')
            ->assertSee('&lt;script&gt;Live Coupon&lt;/script&gt;', false)
            ->assertDontSee('OTHER10')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }
}
