<?php

namespace Tests\Feature;

use App\Models\AnalyticsDailyStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminAnalyticsExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_overview_uses_compact_decision_first_hierarchy(): void
    {
        $owner = $this->createSuperAdmin();

        AnalyticsDailyStat::create([
            'stat_date' => now()->toDateString(),
            'product_views' => 40,
            'cart_views' => 12,
            'add_to_cart_count' => 10,
            'remove_from_cart_count' => 1,
            'checkout_starts' => 6,
            'purchases' => 3,
            'orders_count' => 3,
            'sessions_count' => 20,
            'users_count' => 15,
            'revenue_gross' => 450,
            'discount_total' => 25,
            'shipping_total' => 0,
            'average_order_value' => 150,
            'cart_abandonment_rate' => 0.25,
            'checkout_completion_rate' => 0.5,
            'view_to_cart_rate' => 0.25,
            'view_to_purchase_rate' => 0.075,
            'aggregated_at' => now(),
        ]);
        Cache::flush();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.index', ['range' => '7d']));

        $response
            ->assertOk()
            ->assertSee('Decision read')
            ->assertSee('Performance trends')
            ->assertSee('Funnel health')
            ->assertSee('Commercial drilldowns')
            ->assertSee('More diagnostics')
            ->assertDontSee('Comparison storytelling')
            ->assertDontSee('Operator reading mode');

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'id="analytics-panel-performance"'));
        $this->assertSame(1, substr_count($html, 'id="analytics-panel-decision"'));
        $this->assertSame(1, substr_count($html, 'id="analytics-panel-trends"'));
        $this->assertSame(1, substr_count($html, 'id="analytics-panel-funnel"'));
        $this->assertSame(1, substr_count($html, 'id="analytics-panel-drilldowns"'));
    }

    public function test_growth_analytics_does_not_repeat_the_same_signal_layer(): void
    {
        $owner = $this->createSuperAdmin();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.growth', ['range' => '7d']));

        $response
            ->assertOk()
            ->assertSee('Growth focus')
            ->assertDontSee('Growth storytelling')
            ->assertDontSee('Executive focus');
    }

    public function test_growth_insights_reuses_shared_admin_metric_cards(): void
    {
        $owner = $this->createSuperAdmin();

        $response = $this->actingAs($owner)
            ->get(route('admin.growth.insights'));

        $response
            ->assertOk()
            ->assertSee('Attributed revenue')
            ->assertSee('Average churn risk');

        $source = file_get_contents(resource_path('views/admin/growth/insights.blade.php'));
        $this->assertSame(4, substr_count($source, '<x-admin.stat-card'));
    }

    public function test_offers_analytics_uses_one_summary_layer_before_kpis(): void
    {
        $owner = $this->createSuperAdmin();

        $response = $this->actingAs($owner)
            ->get(route('admin.analytics.offers', ['range' => '7d']));

        $response
            ->assertOk()
            ->assertSee('Offer focus')
            ->assertDontSee('Offer storytelling')
            ->assertDontSee('Offer performance summary');

        $this->assertStringNotContainsString('id="offers-operator-summary"', $response->getContent());
    }
}
