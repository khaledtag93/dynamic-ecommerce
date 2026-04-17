<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthTriggerLog;

class GrowthMessageDispatcher
{
    public function __construct(protected GrowthCampaignService $growthCampaignService)
    {
    }

    public function dispatch(int $campaignId, int $triggerLogId): void
    {
        $campaign = GrowthCampaign::query()->findOrFail($campaignId);
        $triggerLog = GrowthTriggerLog::query()->findOrFail($triggerLogId);

        $this->growthCampaignService->dispatchMessage($campaign, $triggerLog);
    }
}
