<?php

namespace App\Console\Commands;

use App\Models\AnalyticsDailyStat;
use App\Services\Analytics\AnalyticsAggregationService;
use Illuminate\Console\Command;

class RestateDirtyAnalyticsCommand extends Command
{
    protected $signature = 'analytics:restate-dirty
                            {--limit=30 : Maximum dirty days to restate per run}';

    protected $description = 'Rebuild analytics days invalidated by later commerce changes';

    public function handle(AnalyticsAggregationService $aggregationService): int
    {
        $limit = max(1, min(365, (int) $this->option('limit')));

        $dirtyStats = AnalyticsDailyStat::query()
            ->whereNotNull('meta->restatement_requested_at')
            ->orderBy('stat_date')
            ->limit($limit)
            ->get(['id', 'stat_date']);

        if ($dirtyStats->isEmpty()) {
            $this->info('No dirty analytics days require restatement.');

            return self::SUCCESS;
        }
        foreach ($dirtyStats as $stat) {
            $date = $stat->stat_date->toDateString();
            $result = $aggregationService->aggregateDay($date);

            $this->line(sprintf(
                '%s | purchases=%d | revenue=%.2f',
                $date,
                $result['purchases'],
                $result['revenue_gross']
            ));
        }

        $this->info(sprintf(
            'Restated %d dirty analytics day(s).',
            $dirtyStats->count()
        ));

        return self::SUCCESS;
    }
}
