<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class QueueRecoveryService
{
    public function retryFailedJob(string $identifier): array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new RuntimeException('Missing failed job identifier.');
        }

        if (! Schema::hasTable('failed_jobs')) {
            throw new RuntimeException('The failed jobs table is not available.');
        }

        $exitCode = Artisan::call('queue:retry', [
            'id' => [$identifier],
        ]);

        $output = trim((string) Artisan::output());
        $ok = $exitCode === 0;

        Log::info('Queue failed job retry requested.', [
            'failed_job_identifier' => $identifier,
            'exit_code' => $exitCode,
            'output' => $output,
            'ok' => $ok,
        ]);

        return [
            'ok' => $ok,
            'identifier' => $identifier,
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }
}
