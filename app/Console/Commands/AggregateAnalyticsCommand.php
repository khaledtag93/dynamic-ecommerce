<?php

namespace App\Console\Commands;

use App\Jobs\AggregateAnalyticsDayJob;
use App\Services\Analytics\AnalyticsAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateAnalyticsCommand extends Command
{
    protected $signature = 'analytics:aggregate 
                            {--date= : Aggregate a single day (Y-m-d)}
                            {--from= : Aggregate from this day (Y-m-d)}
                            {--to= : Aggregate until this day (Y-m-d)}
                            {--queue : Dispatch queued day jobs instead of running inline}';

    protected $description = 'Aggregate raw analytics events into daily stats tables';

    public function handle(AnalyticsAggregationService $aggregationService): int
    {
        [$from, $to] = $this->resolveDateRange();

        if ($this->option('queue')) {
            for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
                AggregateAnalyticsDayJob::dispatch($cursor->toDateString());
                $this->line('Queued analytics aggregation for ' . $cursor->toDateString());
            }

            $this->info('Analytics aggregation jobs queued successfully.');

            return self::SUCCESS;
        }

        foreach ($aggregationService->aggregateRange($from, $to) as $day) {
            $this->line(sprintf(
                '%s | events=%d | purchases=%d | revenue=%.2f',
                $day['stat_date'],
                $day['events_processed'],
                $day['purchases'],
                $day['revenue_gross']
            ));
        }

        $this->info('Analytics aggregation completed.');

        return self::SUCCESS;
    }

    protected function resolveDateRange(): array
    {
        if ($date = $this->option('date')) {
            $day = Carbon::parse($date)->startOfDay();

            return [$day, $day->copy()];
        }

        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : now()->subDays(6)->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'))->startOfDay()
            : now()->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
