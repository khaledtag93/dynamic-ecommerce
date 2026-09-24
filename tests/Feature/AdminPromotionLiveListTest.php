<?php

namespace Tests\Feature;

use App\Models\PromotionRule;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPromotionLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_live_list_preserves_filters_permissions_sorting_and_escaped_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = User::factory()->create(['role_as' => 1]);
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        PromotionRule::create([
            'name' => '<script>Live Promotion</script>',
            'type' => PromotionRule::TYPE_ORDER_PERCENTAGE,
            'discount_value' => 10,
            'priority' => 50,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        PromotionRule::create([
            'name' => 'Other Promotion',
            'type' => PromotionRule::TYPE_ORDER_FIXED,
            'discount_value' => 5,
            'priority' => 10,
            'is_active' => false,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(3),
        ]);

        $url = route('admin.promotions.index', [
            'search' => 'Live Promotion',
            'type' => PromotionRule::TYPE_ORDER_PERCENTAGE,
            'status' => 'active',
            'schedule' => 'running',
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
            ->assertSee('&lt;script&gt;Live Promotion&lt;/script&gt;', false)
            ->assertDontSee('Other Promotion');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('&lt;script&gt;Live Promotion&lt;/script&gt;', false)
            ->assertDontSee('Other Promotion')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }
}
