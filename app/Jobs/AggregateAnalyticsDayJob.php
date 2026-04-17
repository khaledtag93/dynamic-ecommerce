<?php

namespace App\Jobs;

use App\Services\Analytics\AnalyticsAggregationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateAnalyticsDayJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $date)
    {
    }

    public function handle(AnalyticsAggregationService $aggregationService): void
    {
        $aggregationService->aggregateDay($this->date);
    }
}
