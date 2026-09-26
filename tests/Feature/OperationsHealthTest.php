<?php

namespace Tests\Feature;

use App\Services\Operations\HeartbeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_heartbeat_records_scheduler_and_queue_activity(): void
    {
        config(['queue.default' => 'sync']);

        $exit = Artisan::call('ops:heartbeat');

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('operations_heartbeats', ['name' => 'scheduler']);
        $this->assertDatabaseHas('operations_heartbeats', ['name' => 'queue']);
    }

    public function test_non_strict_health_passes_with_fresh_heartbeats(): void
    {
        config(['queue.default' => 'sync']);

        Artisan::call('ops:heartbeat');

        $this->assertSame(0, Artisan::call('ops:health', ['--max-age' => 180]));
    }

    public function test_strict_health_rejects_sync_queue(): void
    {
        config(['queue.default' => 'sync']);

        Artisan::call('ops:heartbeat');

        $this->assertSame(1, Artisan::call('ops:health', ['--strict' => true]));
        $this->assertStringContainsString(
            'Strict mode requires an asynchronous queue driver.',
            Artisan::output(),
        );
    }

    public function test_health_fails_when_scheduler_heartbeat_is_stale(): void
    {
        $heartbeats = app(HeartbeatService::class);
        $heartbeats->beat('scheduler');
        $heartbeats->beat('queue');

        DB::table('operations_heartbeats')
            ->where('name', 'scheduler')
            ->update(['last_seen_at' => now()->subMinutes(10)]);

        $this->assertSame(1, Artisan::call('ops:health', ['--max-age' => 60]));
    }

    public function test_health_rejects_queue_heartbeat_from_another_connection(): void
    {
        config(['queue.default' => 'database']);

        $heartbeats = app(HeartbeatService::class);
        $heartbeats->beat('scheduler');
        $heartbeats->beat('queue', ['connection' => 'sync']);

        $this->assertSame(1, Artisan::call('ops:health', ['--max-age' => 180]));
        $this->assertStringContainsString('connection mismatch: sync', Artisan::output());
    }

    public function test_strict_database_queue_health_reports_pending_and_failed_jobs(): void
    {
        config(['queue.default' => 'database']);

        $heartbeats = app(HeartbeatService::class);
        $heartbeats->beat('scheduler');
        $heartbeats->beat('queue', ['connection' => 'database']);

        $this->assertSame(0, Artisan::call('ops:health', ['--strict' => true]));
        $this->assertStringContainsString('database', Artisan::output());
    }
}
