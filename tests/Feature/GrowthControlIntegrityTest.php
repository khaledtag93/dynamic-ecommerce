<?php

namespace Tests\Feature;

use App\Models\GrowthAudienceSegment;
use App\Models\GrowthAutomationRule;
use App\Models\GrowthCampaign;
use App\Models\GrowthExperiment;
use App\Models\GrowthMessageTemplate;
use App\Models\GrowthDelivery;
use App\Models\Order;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Growth\GrowthCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthControlIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_growth_settings_update_preserves_unsubmitted_advanced_controls(): void
    {
        WebsiteSetting::setValue('growth_ai_selection_enabled', '1', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_smart_timing_enabled', '1', 'growth', 'boolean');
        WebsiteSetting::setValue('growth_predictive_enabled', '1', 'growth', 'boolean');

        app(GrowthCampaignService::class)->updateSettings([
            'growth_engine_enabled' => 0,
            'growth_messaging_enabled' => 1,
        ]);

        $this->assertSame('0', (string) WebsiteSetting::getValue('growth_engine_enabled'));
        $this->assertSame('1', (string) WebsiteSetting::getValue('growth_messaging_enabled'));
        $this->assertSame('1', (string) WebsiteSetting::getValue('growth_ai_selection_enabled'));
        $this->assertSame('1', (string) WebsiteSetting::getValue('growth_smart_timing_enabled'));
        $this->assertSame('1', (string) WebsiteSetting::getValue('growth_predictive_enabled'));
    }

    public function test_ensure_defaults_does_not_overwrite_admin_growth_customizations(): void
    {
        $service = app(GrowthCampaignService::class);
        $service->ensureDefaults();

        $campaign = GrowthCampaign::query()->where('campaign_key', 'cart_recovery')->firstOrFail();
        $rule = GrowthAutomationRule::query()->where('rule_key', 'cart_recovery')->firstOrFail();
        $segment = GrowthAudienceSegment::query()->where('segment_key', 'cart_abandoners')->firstOrFail();
        $template = GrowthMessageTemplate::query()->where('template_key', 'cart_recovery')->where('locale', 'en')->firstOrFail();
        $experiment = GrowthExperiment::query()->where('experiment_key', 'cart_recovery_offer_test')->firstOrFail();

        $campaign->update(['name' => 'Custom campaign name', 'is_active' => false]);
        $rule->update(['name' => 'Custom rule name', 'is_active' => false]);
        $segment->update(['description' => 'Custom segment description', 'is_active' => false]);
        $template->update(['body' => 'Custom template body', 'is_active' => false]);
        $experiment->update(['name' => 'Custom experiment name', 'is_active' => false]);

        $service->ensureDefaults();

        $this->assertSame('Custom campaign name', $campaign->fresh()->name);
        $this->assertFalse((bool) $campaign->fresh()->is_active);
        $this->assertSame('Custom rule name', $rule->fresh()->name);
        $this->assertFalse((bool) $rule->fresh()->is_active);
        $this->assertSame('Custom segment description', $segment->fresh()->description);
        $this->assertFalse((bool) $segment->fresh()->is_active);
        $this->assertSame('Custom template body', $template->fresh()->body);
        $this->assertFalse((bool) $template->fresh()->is_active);
        $this->assertSame('Custom experiment name', $experiment->fresh()->name);
        $this->assertFalse((bool) $experiment->fresh()->is_active);
    }

    public function test_growth_dashboard_snapshot_does_not_recompute_heavy_analytics_on_get(): void
    {
        $serviceSource = file_get_contents(app_path('Services/Growth/GrowthCampaignService.php'));
        $commandSource = file_get_contents(app_path('Console/Commands/RunGrowthAutomationCommand.php'));

        $snapshotStart = strpos($serviceSource, 'public function dashboardSnapshot(): array');
        $snapshotEnd = strpos($serviceSource, 'public function engineEnabled(): bool', $snapshotStart);
        $snapshotSource = substr($serviceSource, $snapshotStart, $snapshotEnd - $snapshotStart);

        $this->assertStringNotContainsString('syncRecentAttribution()', $snapshotSource);
        $this->assertStringNotContainsString('refreshSnapshots((int) config(\'growth.cohort_months\'', $snapshotSource);
        $this->assertStringNotContainsString('refreshScores()', $snapshotSource);
        $this->assertStringNotContainsString('GrowthAdaptiveLearningService::class)->refreshSnapshots()', $snapshotSource);

        $controllerSource = file_get_contents(app_path('Http/Controllers/Admin/GrowthController.php'));

        $this->assertStringContainsString('dashboardSnapshot($page)', $controllerSource);
        $this->assertStringContainsString("public function dashboardSnapshot(string $page = 'full'): array", $serviceSource);
        $this->assertStringContainsString('growth:run', file_get_contents(app_path('Console/Kernel.php')));
        $this->assertStringContainsString('syncRecentAttribution()', $commandSource);
        $this->assertStringContainsString('GrowthCohortRetentionService', $commandSource);
        $this->assertStringNotContainsString('GrowthPredictiveIntelligenceService', $commandSource);
        $this->assertStringNotContainsString('GrowthAdaptiveLearningService', $commandSource);
    }

    public function test_growth_workspace_snapshots_only_load_the_data_needed_by_that_page(): void
    {
        $service = app(GrowthCampaignService::class);

        $content = $service->dashboardSnapshot('content');
        $this->assertTrue($content['campaigns']->isNotEmpty());
        $this->assertTrue($content['rules']->isNotEmpty());
        $this->assertTrue($content['templates']->isNotEmpty());
        $this->assertTrue($content['deliveries']->isEmpty());
        $this->assertTrue($content['trigger_logs']->isEmpty());
        $this->assertSame([], $content['attribution_summary']);
        $this->assertSame([], $content['predictive_summary']);
        $this->assertSame([], $content['performance']);

        $operations = $service->dashboardSnapshot('operations');
        $this->assertTrue($operations['campaigns']->isEmpty());
        $this->assertTrue($operations['rules']->isEmpty());
        $this->assertTrue($operations['templates']->isEmpty());
        $this->assertTrue($operations['segments']->isEmpty());
        $this->assertSame([], $operations['attribution_summary']);
        $this->assertSame([], $operations['cohort_summary']);
        $this->assertSame([], $operations['predictive_summary']);
        $this->assertSame([], $operations['performance']);

        $insights = $service->dashboardSnapshot('insights');
        $this->assertTrue($insights['campaigns']->isEmpty());
        $this->assertTrue($insights['rules']->isEmpty());
        $this->assertTrue($insights['templates']->isEmpty());
        $this->assertTrue($insights['deliveries']->isEmpty());
        $this->assertSame([], $insights['performance']);
    }

    public function test_growth_experiment_performance_batches_order_windows_without_changing_results(): void
    {
        $user = User::factory()->create();

        $experiment = GrowthExperiment::query()->create([
            'name' => 'Performance test',
            'experiment_key' => 'performance_test',
            'variants' => [
                ['key' => 'a', 'name' => 'A', 'weight' => 1],
                ['key' => 'b', 'name' => 'B', 'weight' => 1],
            ],
            'priority' => 10,
            'is_active' => true,
        ]);

        $reference = now()->startOfMinute();

        foreach ([
            ['variant' => 'a', 'sent_at' => $reference->copy()->subMinutes(120)],
            ['variant' => 'a', 'sent_at' => $reference->copy()->subMinutes(60)],
            ['variant' => 'b', 'sent_at' => $reference->copy()->subMinutes(180)],
        ] as $delivery) {
            GrowthDelivery::query()->create([
                'experiment_id' => $experiment->id,
                'user_id' => $user->id,
                'channel' => 'in_app',
                'status' => 'sent',
                'experiment_variant' => $delivery['variant'],
                'sent_at' => $delivery['sent_at'],
            ]);
        }

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'GROWTH-PERF-001',
            'grand_total' => 120,
            'customer_name' => 'Growth Test',
            'customer_email' => 'growth-performance@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test address',
            'shipping_city' => 'Cairo',
        ]);
        $order->forceFill([
            'created_at' => $reference->copy()->subMinutes(90),
            'updated_at' => $reference->copy()->subMinutes(90),
        ])->save();

        $result = collect(app(GrowthCampaignService::class)->variantPerformance($experiment))->keyBy('key');

        $this->assertSame(2, $result['a']['deliveries']);
        $this->assertSame(1, $result['a']['converted']);
        $this->assertSame(50.0, $result['a']['conversion_rate']);
        $this->assertSame(120.0, $result['a']['revenue']);

        $this->assertSame(1, $result['b']['deliveries']);
        $this->assertSame(1, $result['b']['converted']);
        $this->assertSame(100.0, $result['b']['conversion_rate']);
        $this->assertSame(120.0, $result['b']['revenue']);

        $serviceSource = file_get_contents(app_path('Services/Growth/GrowthCampaignService.php'));
        $methodStart = strpos($serviceSource, 'public function variantPerformance(GrowthExperiment $experiment): array');
        $methodEnd = strpos($serviceSource, 'protected function defaultCampaigns(): array', $methodStart);
        $methodSource = substr($serviceSource, $methodStart, $methodEnd - $methodStart);

        $this->assertSame(1, substr_count($methodSource, 'GrowthDelivery::query()'));
        $this->assertSame(1, substr_count($methodSource, 'Order::query()'));
        $this->assertStringNotContainsString('->exists()', $methodSource);
    }

    public function test_disabled_growth_run_returns_a_complete_result_shape(): void
    {
        WebsiteSetting::setValue('growth_engine_enabled', '0', 'growth', 'boolean');

        $result = app(GrowthCampaignService::class)->runNow();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['triggered']);
        $this->assertSame(0, $result['messages']);
        $this->assertSame(0, $result['scheduled']);
        $this->assertSame(0, $result['due']);
        $this->assertSame(0, $result['skipped']);
        $this->assertNotEmpty($result['note']);
    }
}
