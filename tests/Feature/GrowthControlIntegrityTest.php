<?php

namespace Tests\Feature;

use App\Models\GrowthAudienceSegment;
use App\Models\GrowthAutomationRule;
use App\Models\GrowthCampaign;
use App\Models\GrowthExperiment;
use App\Models\GrowthMessageTemplate;
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
        $this->assertNotEmpty($content['campaigns']);
        $this->assertNotEmpty($content['rules']);
        $this->assertNotEmpty($content['templates']);
        $this->assertEmpty($content['deliveries']);
        $this->assertEmpty($content['trigger_logs']);
        $this->assertSame([], $content['attribution_summary']);
        $this->assertSame([], $content['predictive_summary']);
        $this->assertSame([], $content['performance']);

        $operations = $service->dashboardSnapshot('operations');
        $this->assertEmpty($operations['campaigns']);
        $this->assertEmpty($operations['rules']);
        $this->assertEmpty($operations['templates']);
        $this->assertEmpty($operations['segments']);
        $this->assertSame([], $operations['attribution_summary']);
        $this->assertSame([], $operations['cohort_summary']);
        $this->assertSame([], $operations['predictive_summary']);
        $this->assertSame([], $operations['performance']);

        $insights = $service->dashboardSnapshot('insights');
        $this->assertEmpty($insights['campaigns']);
        $this->assertEmpty($insights['rules']);
        $this->assertEmpty($insights['templates']);
        $this->assertEmpty($insights['deliveries']);
        $this->assertSame([], $insights['performance']);
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
