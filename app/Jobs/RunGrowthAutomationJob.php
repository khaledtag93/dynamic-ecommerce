<?php

namespace App\Jobs;

use App\Models\GrowthCampaign;
use App\Services\Growth\GrowthCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunGrowthAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected ?int $campaignId = null)
    {
    }

    public function handle(GrowthCampaignService $growthCampaignService): void
    {
        $campaign = $this->campaignId ? GrowthCampaign::query()->find($this->campaignId) : null;

        $growthCampaignService->runNow($campaign);
    }
}
