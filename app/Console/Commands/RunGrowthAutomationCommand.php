<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthAttributionService;
use App\Services\Growth\GrowthCampaignService;
use App\Services\Growth\GrowthCohortRetentionService;
use App\Services\Growth\GrowthPredictiveIntelligenceService;
use App\Services\Growth\GrowthAdaptiveLearningService;
use Illuminate\Console\Command;

class RunGrowthAutomationCommand extends Command
{
    protected $signature = 'growth:run {--campaign=}';

    protected $description = 'Run growth automation campaigns and generate trigger/message logs.';

    public function handle(
        GrowthCampaignService $growthCampaignService,
        GrowthAttributionService $growthAttributionService,
        GrowthCohortRetentionService $growthCohortRetentionService,
        GrowthPredictiveIntelligenceService $growthPredictiveIntelligenceService,
        GrowthAdaptiveLearningService $growthAdaptiveLearningService
    ): int
    {
        $campaign = null;

        if ($campaignKey = $this->option('campaign')) {
            $campaign = \App\Models\GrowthCampaign::query()->where('campaign_key', $campaignKey)->first();

            if (! $campaign) {
                $this->error(__('Campaign not found.'));

                return self::FAILURE;
            }
        }

        $result = $growthCampaignService->runNow($campaign);
        $growthAttributionService->syncRecentAttribution();
        $growthCohortRetentionService->refreshSnapshots((int) config('growth.cohort_months', 6));
        $growthPredictiveIntelligenceService->refreshScores();
        $growthAdaptiveLearningService->refreshSnapshots();

        $this->info(__('Processed: :processed | Triggered: :triggered | Messages: :messages | Scheduled: :scheduled | Due deliveries: :due | Skipped: :skipped', $result));

        return self::SUCCESS;
    }
}
