<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationEscalationScannerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_prints_scalar_summary_when_result_contains_log_details(): void
    {
        $exitCode = Artisan::call('notifications:scan-escalations');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'Scanned: 0 | Matched: 0 | Recovered: 0',
            Artisan::output()
        );
    }
}
