<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueHeartbeat;
use App\Services\Operations\HeartbeatService;
use Illuminate\Console\Command;

class RecordOperationsHeartbeatCommand extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Record scheduler activity and dispatch a queue-worker heartbeat job.';

    public function handle(HeartbeatService $heartbeats): int
    {
        $heartbeats->beat('scheduler', [
            'queue_connection' => config('queue.default'),
            'host' => gethostname() ?: null,
            'pid' => getmypid() ?: null,
        ]);

        RecordQueueHeartbeat::dispatch(now()->toIso8601String());

        $this->info('Operations heartbeat recorded and queue heartbeat dispatched.');

        return self::SUCCESS;
    }
}
