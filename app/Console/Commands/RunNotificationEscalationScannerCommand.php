<?php

namespace App\Console\Commands;

use App\Services\Commerce\AdminActivityLogService;
use App\Services\Commerce\NotificationAutomationService;
use Illuminate\Console\Command;

class RunNotificationEscalationScannerCommand extends Command
{
    protected $signature = 'notifications:scan-escalations {--manual : Mark this run as a manual scan} {--limit=100 : Max stale logs to scan}';

    protected $description = 'Scan stale pending notification logs and run escalation recovery rules.';

    public function handle(NotificationAutomationService $notificationAutomationService, AdminActivityLogService $adminActivityLogService): int
    {
        $isManual = (bool) $this->option('manual');

        $result = $notificationAutomationService->scanStalePending(
            $isManual,
            max(1, (int) $this->option('limit'))
        );

        $adminActivityLogService->log(
            'notification_automation',
            $isManual ? 'manual_scanner_run' : 'scheduled_scanner_run',
            $isManual ? __('Manual stale pending scanner run completed.') : __('Scheduled stale pending scanner run completed.'),
            null,
            null,
            $result
        );

        $this->info(__('Scanned: :scanned | Matched: :matched | Recovered: :recovered', $result));

        return self::SUCCESS;
    }
}
