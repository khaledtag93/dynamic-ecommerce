<?php

namespace App\Jobs;

use App\Services\Operations\HeartbeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordQueueHeartbeat implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $dispatchedAt)
    {
    }

    public function handle(HeartbeatService $heartbeats): void
    {
        $heartbeats->beat('queue', [
            'connection' => config('queue.default'),
            'dispatched_at' => $this->dispatchedAt,
            'handled_at' => now()->toIso8601String(),
            'host' => gethostname() ?: null,
            'pid' => getmypid() ?: null,
        ]);
    }
}
