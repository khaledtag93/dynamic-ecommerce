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
