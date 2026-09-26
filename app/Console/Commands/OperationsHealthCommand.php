<?php

namespace App\Console\Commands;

use App\Services\Operations\HeartbeatService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsHealthCommand extends Command
{
    protected $signature = 'ops:health
                            {--max-age=180 : Maximum heartbeat age in seconds}
                            {--strict : Require an asynchronous queue and zero failed jobs}
                            {--json : Emit machine-readable JSON output}';

    protected $description = 'Check scheduler, queue-worker, pending-job, and failed-job operational health.';

    public function handle(HeartbeatService $heartbeats): int
    {
        $maxAge = max(30, (int) $this->option('max-age'));
        $queueConnection = (string) config('queue.default');
        $queueDriver = (string) config("queue.connections.{$queueConnection}.driver", $queueConnection);

        $scheduler = $this->heartbeatState($heartbeats->get('scheduler'), $maxAge);
        $queue = $this->heartbeatState(
            $heartbeats->get('queue'),
            $maxAge,
            $queueConnection,
        );

        $pendingJobs = null;
        if ($queueDriver === 'database' && Schema::hasTable('jobs')) {
            $pendingJobs = DB::table('jobs')->count();
        }

        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : null;

        $strict = (bool) $this->option('strict');
        $healthy = $scheduler['healthy'] && $queue['healthy'];

        if ($strict) {
            $healthy = $healthy
                && ! in_array($queueDriver, ['sync', 'null'], true)
                && ($failedJobs ?? 0) === 0;
        }

        $result = [
            'healthy' => $healthy,
            'strict' => $strict,
            'max_age_seconds' => $maxAge,
            'queue_connection' => $queueConnection,
            'queue_driver' => $queueDriver,
            'scheduler' => $scheduler,
            'queue' => $queue,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Check', 'Status', 'Details'],
                [
                    ['Scheduler', $scheduler['healthy'] ? 'OK' : 'STALE', $scheduler['detail']],
                    ['Queue worker', $queue['healthy'] ? 'OK' : 'STALE', $queue['detail']],
                    ['Queue driver', $queueDriver, $queueConnection],
                    ['Pending jobs', $pendingJobs ?? 'n/a', 'database driver only'],
                    ['Failed jobs', $failedJobs ?? 'n/a', 'failed_jobs table'],
                ],
            );

            if ($strict && in_array($queueDriver, ['sync', 'null'], true)) {
                $this->warn('Strict mode requires an asynchronous queue driver.');
            }

            if ($strict && ($failedJobs ?? 0) > 0) {
                $this->warn('Strict mode requires failed jobs to be reviewed and cleared or resolved.');
            }
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    private function heartbeatState(
        ?object $heartbeat,
        int $maxAge,
        ?string $expectedQueueConnection = null,
    ): array {
        if (! $heartbeat || ! $heartbeat->last_seen_at) {
            return [
                'healthy' => false,
                'age_seconds' => null,
                'last_seen_at' => null,
                'detail' => 'missing heartbeat',
            ];
        }

        $lastSeen = Carbon::parse($heartbeat->last_seen_at);
        $age = max(0, (int) now()->diffInSeconds($lastSeen, true));
        $context = json_decode((string) ($heartbeat->context ?? ''), true) ?: [];
        $connectionMatches = $expectedQueueConnection === null
            || ($context['connection'] ?? null) === $expectedQueueConnection;
        $healthy = $age <= $maxAge && $connectionMatches;

        $detail = $age <= $maxAge
            ? "last seen {$age}s ago"
            : "stale: last seen {$age}s ago";

        if (! $connectionMatches) {
            $actual = (string) ($context['connection'] ?? 'unknown');
            $detail .= "; connection mismatch: {$actual}";
        }

        return [
            'healthy' => $healthy,
            'age_seconds' => $age,
            'last_seen_at' => $lastSeen->toIso8601String(),
            'connection_matches' => $connectionMatches,
            'detail' => $detail,
        ];
    }
}
