<?php

namespace App\Jobs;

use App\Services\Growth\GrowthMessageDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchGrowthMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $campaignId, protected int $triggerLogId)
    {
    }

    public function handle(GrowthMessageDispatcher $dispatcher): void
    {
        $dispatcher->dispatch($this->campaignId, $this->triggerLogId);
    }
}
