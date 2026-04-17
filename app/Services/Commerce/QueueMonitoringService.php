<?php

namespace App\Services\Commerce;

use App\Models\NotificationAutomationRule;
use App\Models\NotificationDispatchLog;
use App\Models\WhatsAppLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Channels\WhatsApp\Support\WhatsAppConfig;
use Illuminate\Support\Str;

class QueueMonitoringService
{
    public function __construct(
        protected StoreSettingsService $storeSettingsService,
        protected WhatsAppConfig $whatsAppConfig,
    ) {
    }

    public function dashboard(): array
    {
        $queueStats = $this->queueStats();
        $channelHealth = $this->channelHealth();
        $dispatchTrend = $this->dispatchTrend();
        $whatsAppHealth = $this->whatsAppHealth();

        $observability = $this->observability();
        $incidentSummary = $this->incidentSummary($queueStats, $dispatchTrend, $whatsAppHealth, $observability);

        return [
            'queue' => $queueStats,
            'healthChecks' => $this->healthChecks(),
            'channelHealth' => $channelHealth,
            'dispatchTrend' => $dispatchTrend,
            'whatsAppHealth' => $whatsAppHealth,
            'observability' => $observability,
            'incidentSummary' => $incidentSummary,
            'retryGuardBreakdown' => $this->retryGuardBreakdown(),
            'providerFailureBreakdown' => $this->providerFailureBreakdown(),
            'recentIncidents' => $this->recentIncidents(),
            'workerResilience' => $this->workerResilience($queueStats),
            'recoveryPlaybook' => $this->recoveryPlaybook(),
            'providerTuning' => $this->providerTuning(),
            'releaseChecklist' => $this->releaseChecklist($queueStats, $channelHealth, $dispatchTrend, $whatsAppHealth),
            'pendingJobs' => $this->recentPendingJobs(),
            'failedJobs' => $this->recentFailedJobs(),
            'recommendations' => $this->recommendations($queueStats, $channelHealth, $dispatchTrend, $whatsAppHealth),
            'health' => [
                'label' => $incidentSummary['status_label'] ?? __('Needs review'),
                'tone' => $incidentSummary['tone'] ?? 'warning',
                'score' => $incidentSummary['score'] ?? 0,
            ],
            'next_action' => $incidentSummary['next_action'] ?? __('Review diagnostics'),
        ];
    }

    protected function healthChecks(): array
    {
        $settings = $this->storeSettingsService->all();
        $checks = [];

        $queueDriver = (string) config('queue.default', 'sync');
        $mailDriver = (string) config('mail.default', 'log');
        $mailHost = (string) config('mail.mailers.smtp.host', '');
        $mailFrom = (string) config('mail.from.address', '');
        $whatsAppEnabled = ($settings['whatsapp_enabled'] ?? '0') === '1';
        $notificationCenterEnabled = ($settings['notification_center_enabled'] ?? '1') === '1';
        $activeChannels = collect(OrderNotificationService::supportedChannels())
            ->keys()
            ->filter(fn (string $channel) => ($settings['notification_channel_'.$channel.'_enabled'] ?? '0') === '1')
            ->values()
            ->all();

        $checks[] = [
            'key' => 'queue_driver',
            'title' => __('Queue worker readiness'),
            'status' => $queueDriver === 'sync' ? 'warning' : 'healthy',
            'message' => $queueDriver === 'sync'
                ? __('The application is still using the sync queue driver. Production recovery is safer with a real queue worker.')
                : __('Queue driver is :driver and is suitable for monitored background processing.', ['driver' => strtoupper($queueDriver)]),
        ];

        $checks[] = [
            'key' => 'failed_jobs_table',
            'title' => __('Failed jobs recovery'),
            'status' => Schema::hasTable('failed_jobs') ? 'healthy' : 'warning',
            'message' => Schema::hasTable('failed_jobs')
                ? __('The failed jobs table exists, so queue recovery and diagnostics can work safely.')
                : __('The failed jobs table is missing, so queue failure recovery from the admin side is limited.'),
        ];

        $mailHealthy = $mailDriver !== '' && $mailFrom !== '' && ($mailDriver !== 'smtp' || $mailHost !== '');
        $checks[] = [
            'key' => 'mail_config',
            'title' => __('Email channel readiness'),
            'status' => $mailHealthy ? 'healthy' : 'warning',
            'message' => $mailHealthy
                ? __('Mail driver :driver is configured and ready for notification fallback.', ['driver' => $mailDriver])
                : __('Mail configuration looks incomplete. Check mail driver, sender address, and SMTP host before relying on email fallback.'),
        ];

        $whatsAppReady = $whatsAppEnabled
            && trim((string) ($settings['whatsapp_access_token'] ?? '')) !== ''
            && trim((string) ($settings['whatsapp_phone_number_id'] ?? '')) !== ''
            && trim((string) ($settings['whatsapp_business_account_id'] ?? '')) !== '';

        $checks[] = [
            'key' => 'whatsapp_config',
            'title' => __('WhatsApp provider readiness'),
            'status' => ! $whatsAppEnabled ? 'warning' : ($whatsAppReady ? 'healthy' : 'critical'),
            'message' => ! $whatsAppEnabled
                ? __('WhatsApp is disabled in settings, so only fallback channels can carry customer updates right now.')
                : ($whatsAppReady
                    ? __('WhatsApp credentials and key identifiers are present for production sending.')
                    : __('WhatsApp is enabled but one or more required credentials are missing. Review access token, phone number ID, and business account ID.')),
        ];

        $checks[] = [
            'key' => 'notification_channels',
            'title' => __('Notification engine coverage'),
            'status' => $notificationCenterEnabled && ! empty($activeChannels) ? 'healthy' : 'warning',
            'message' => $notificationCenterEnabled && ! empty($activeChannels)
                ? __('Notification center is enabled with active channels: :channels.', ['channels' => implode(', ', $activeChannels)])
                : __('Notification center or channel coverage is limited. Confirm the engine is enabled and at least one customer channel is active.'),
        ];

        $lastScannerRun = null;
        if (Schema::hasTable('notification_dispatch_logs')) {
            $lastScannerRun = NotificationDispatchLog::query()
                ->where('meta->stale_pending_recovered', true)
                ->latest('updated_at')
                ->value('updated_at');
        }

        $scannerHealthy = true;
        if (Schema::hasTable('notification_automation_rules')) {
            $hasPendingStaleRules = NotificationAutomationRule::query()
                ->where('is_active', true)
                ->where('trigger_status', NotificationAutomationRule::TRIGGER_PENDING_STALE)
                ->exists();
            $scannerHealthy = ! $hasPendingStaleRules || $lastScannerRun !== null;
        }

        $checks[] = [
            'key' => 'automation_scanner',
            'title' => __('Automation scanner readiness'),
            'status' => $scannerHealthy ? 'healthy' : 'warning',
            'message' => $scannerHealthy
                ? __('Automation scanner rules are available without obvious readiness gaps.')
                : __('Pending-stale automation rules exist, but no recovery run has been recorded yet. Verify scheduler or run the scanner manually once.'),
        ];

        return $checks;
    }

    protected function queueStats(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $jobsTableExists = Schema::hasTable('jobs');
        $failedJobsTableExists = Schema::hasTable('failed_jobs');

        $pendingTotal = 0;
        $reservedTotal = 0;
        $pendingNotificationTotal = 0;
        $staleTotal = 0;
        $oldestPendingMinutes = null;

        if ($jobsTableExists) {
            $now = now()->timestamp;
            $staleBefore = now()->subMinutes(15)->timestamp;

            $jobsQuery = DB::table('jobs');
            $pendingTotal = (int) (clone $jobsQuery)->count();
            $reservedTotal = (int) (clone $jobsQuery)->whereNotNull('reserved_at')->count();
            $staleTotal = (int) (clone $jobsQuery)
                ->where(function ($query) use ($staleBefore) {
                    $query->where('available_at', '<=', $staleBefore)
                        ->orWhere('reserved_at', '<=', $staleBefore);
                })
                ->count();

            $oldestPendingUnix = (clone $jobsQuery)->min('available_at');
            if ($oldestPendingUnix) {
                $oldestPendingMinutes = now()->diffInMinutes(Carbon::createFromTimestamp((int) $oldestPendingUnix), false) * -1;
            }

            $pendingNotificationTotal = $this->countNotificationPendingJobs();
        }

        $failedTotal = 0;
        $failedNotificationTotal = 0;
        $failedLast24h = 0;

        if ($failedJobsTableExists) {
            $failedQuery = DB::table('failed_jobs');
            $failedTotal = (int) (clone $failedQuery)->count();
            $failedLast24h = (int) (clone $failedQuery)->where('failed_at', '>=', now()->subDay())->count();
            $failedNotificationTotal = $this->countNotificationFailedJobs();
        }

        $score = 100;
        if ($driver === 'sync') {
            $score -= 12;
        }
        if ($pendingNotificationTotal > 10) {
            $score -= 15;
        }
        if ($staleTotal > 0) {
            $score -= min(30, $staleTotal * 5);
        }
        if ($failedNotificationTotal > 0) {
            $score -= min(35, $failedNotificationTotal * 5);
        }
        if ($failedLast24h > 0) {
            $score -= min(15, $failedLast24h * 2);
        }

        $score = max(0, $score);
        $status = $score >= 85 ? 'healthy' : ($score >= 60 ? 'warning' : 'critical');

        return [
            'driver' => $driver,
            'jobs_table_exists' => $jobsTableExists,
            'failed_jobs_table_exists' => $failedJobsTableExists,
            'pending_total' => $pendingTotal,
            'reserved_total' => $reservedTotal,
            'pending_notification_total' => $pendingNotificationTotal,
            'stale_total' => $staleTotal,
            'failed_total' => $failedTotal,
            'failed_notification_total' => $failedNotificationTotal,
            'failed_last_24h' => $failedLast24h,
            'oldest_pending_minutes' => $oldestPendingMinutes,
            'health_score' => $score,
            'health_status' => $status,
        ];
    }

    protected function channelHealth(): array
    {
        $channels = ['database', 'email', 'whatsapp', 'sms'];
        $health = [];

        foreach ($channels as $channel) {
            $health[$channel] = [
                'sent' => 0,
                'failed' => 0,
                'pending' => 0,
                'skipped' => 0,
                'total' => 0,
                'success_rate' => 100,
            ];
        }

        if (! Schema::hasTable('notification_dispatch_logs')) {
            return $health;
        }

        $rows = NotificationDispatchLog::query()
            ->select('channel', 'status', DB::raw('COUNT(*) as aggregate'))
            ->where(function ($query) {
                $query->where('attempted_at', '>=', now()->subDay())
                    ->orWhere('created_at', '>=', now()->subDay());
            })
            ->groupBy('channel', 'status')
            ->get();

        foreach ($rows as $row) {
            $channel = (string) $row->channel;
            if (! isset($health[$channel])) {
                $health[$channel] = [
                    'sent' => 0,
                    'failed' => 0,
                    'pending' => 0,
                    'skipped' => 0,
                    'total' => 0,
                    'success_rate' => 100,
                ];
            }

            $status = (string) $row->status;
            $count = (int) $row->aggregate;
            if (isset($health[$channel][$status])) {
                $health[$channel][$status] += $count;
            }
            $health[$channel]['total'] += $count;
        }

        foreach ($health as $channel => $stats) {
            $denominator = max(1, $stats['sent'] + $stats['failed'] + $stats['pending']);
            $health[$channel]['success_rate'] = (int) round(($stats['sent'] / $denominator) * 100);
        }

        return $health;
    }

    protected function dispatchTrend(): array
    {
        if (! Schema::hasTable('notification_dispatch_logs')) {
            return [
                'last_24h_total' => 0,
                'last_24h_sent' => 0,
                'last_24h_failed' => 0,
                'last_24h_pending' => 0,
                'last_24h_test_sends' => 0,
                'success_rate' => 100,
            ];
        }

        $baseQuery = NotificationDispatchLog::query()->where(function ($query) {
            $query->where('attempted_at', '>=', now()->subDay())
                ->orWhere('created_at', '>=', now()->subDay());
        });

        $total = (int) (clone $baseQuery)->count();
        $sent = (int) (clone $baseQuery)->where('status', NotificationDispatchLog::STATUS_SENT)->count();
        $failed = (int) (clone $baseQuery)->where('status', NotificationDispatchLog::STATUS_FAILED)->count();
        $pending = (int) (clone $baseQuery)->where('status', NotificationDispatchLog::STATUS_PENDING)->count();
        $testSends = (int) (clone $baseQuery)->where('meta->test_send', true)->count();
        $successRate = (int) round(($sent / max(1, $sent + $failed + $pending)) * 100);

        return [
            'last_24h_total' => $total,
            'last_24h_sent' => $sent,
            'last_24h_failed' => $failed,
            'last_24h_pending' => $pending,
            'last_24h_test_sends' => $testSends,
            'success_rate' => $successRate,
        ];
    }

    protected function whatsAppHealth(): array
    {
        if (! Schema::hasTable('whatsapp_logs')) {
            return [
                'last_24h_total' => 0,
                'last_24h_sent' => 0,
                'last_24h_failed' => 0,
                'last_24h_skipped' => 0,
                'duplicate_skipped' => 0,
                'success_rate' => 100,
            ];
        }

        $query = WhatsAppLog::query()->where('created_at', '>=', now()->subDay());
        $total = (int) (clone $query)->count();
        $sent = (int) (clone $query)->where('status', WhatsAppLog::STATUS_SENT)->count();
        $failed = (int) (clone $query)->where('status', WhatsAppLog::STATUS_FAILED)->count();
        $skipped = (int) (clone $query)->where('status', WhatsAppLog::STATUS_SKIPPED)->count();
        $duplicateSkipped = (int) (clone $query)->where('meta->duplicate_guard', true)->count();

        return [
            'last_24h_total' => $total,
            'last_24h_sent' => $sent,
            'last_24h_failed' => $failed,
            'last_24h_skipped' => $skipped,
            'duplicate_skipped' => $duplicateSkipped,
            'success_rate' => (int) round(($sent / max(1, $sent + $failed)) * 100),
        ];
    }

    protected function recentPendingJobs(): Collection
    {
        if (! Schema::hasTable('jobs')) {
            return collect();
        }

        return DB::table('jobs')
            ->select(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn ($job) => $this->mapQueueJob($job));
    }

    protected function recentFailedJobs(): Collection
    {
        if (! Schema::hasTable('failed_jobs')) {
            return collect();
        }

        return DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn ($job) => $this->mapFailedJob($job));
    }

    protected function mapQueueJob(object $job): array
    {
        $payload = $this->decodePayload($job->payload ?? null);
        $name = $this->resolveJobName($payload, $job->payload ?? '');
        $isNotificationRelated = $this->isNotificationRelated($name, $job->payload ?? '');

        return [
            'id' => $job->id,
            'name' => $name,
            'queue' => $job->queue,
            'attempts' => (int) ($job->attempts ?? 0),
            'reserved_at' => $this->fromUnix($job->reserved_at ?? null),
            'available_at' => $this->fromUnix($job->available_at ?? null),
            'created_at' => $this->fromUnix($job->created_at ?? null),
            'is_notification_related' => $isNotificationRelated,
        ];
    }

    protected function mapFailedJob(object $job): array
    {
        $payload = $this->decodePayload($job->payload ?? null);
        $name = $this->resolveJobName($payload, $job->payload ?? '');
        $exception = Str::limit(trim((string) ($job->exception ?? '')), 220);

        return [
            'id' => $job->id,
            'uuid' => $job->uuid ?? null,
            'name' => $name,
            'queue' => $job->queue,
            'connection' => $job->connection,
            'failed_at' => isset($job->failed_at) ? Carbon::parse($job->failed_at) : null,
            'exception' => $exception,
            'is_notification_related' => $this->isNotificationRelated($name, ($job->payload ?? '').' '.($job->exception ?? '')),
        ];
    }

    protected function recommendations(array $queueStats, array $channelHealth, array $dispatchTrend, array $whatsAppHealth): array
    {
        $items = [];

        if ($queueStats['driver'] === 'sync') {
            $items[] = [
                'tone' => 'warning',
                'title' => __('Queue driver is sync'),
                'message' => __('Queued notification monitoring is limited while the application runs on the sync driver. Switch production to a real queue worker for better resilience.'),
            ];
        }

        if ($queueStats['stale_total'] > 0) {
            $items[] = [
                'tone' => 'danger',
                'title' => __('Stale queued jobs detected'),
                'message' => __('Some queued jobs look older than the safe window. Check your queue worker, supervisor, or cron flow before notification delays grow.'),
            ];
        }

        if ($queueStats['failed_notification_total'] > 0) {
            $items[] = [
                'tone' => 'danger',
                'title' => __('Failed notification jobs exist'),
                'message' => __('Recent failed jobs include notification-related work. Review the failed queue records below and compare them with dispatch logs and provider errors.'),
            ];
        }

        if (($channelHealth['email']['success_rate'] ?? 100) < 80) {
            $items[] = [
                'tone' => 'warning',
                'title' => __('Email delivery health dropped'),
                'message' => __('Email success rate in the last 24 hours is below the healthy threshold. Re-check SMTP credentials and recent mail exceptions.'),
            ];
        }

        if ($whatsAppHealth['last_24h_failed'] > 0) {
            $items[] = [
                'tone' => 'warning',
                'title' => __('WhatsApp failures need attention'),
                'message' => __('WhatsApp provider failures were recorded in the last 24 hours. Use the retry center together with provider debug data before customers miss updates.'),
            ];
        }

        if ($dispatchTrend['success_rate'] >= 95 && $queueStats['stale_total'] === 0 && $queueStats['failed_notification_total'] === 0) {
            $items[] = [
                'tone' => 'success',
                'title' => __('Notification health looks strong'),
                'message' => __('Dispatch success is stable and the queue does not show obvious notification backlog risks right now.'),
            ];
        }

        return $items;
    }

    protected function countNotificationPendingJobs(): int
    {
        $jobs = DB::table('jobs')->select(['payload'])->get();

        return $jobs->filter(function ($job) {
            $raw = (string) ($job->payload ?? '');
            $payload = $this->decodePayload($raw);
            $name = $this->resolveJobName($payload, $raw);

            return $this->isNotificationRelated($name, $raw);
        })->count();
    }

    protected function countNotificationFailedJobs(): int
    {
        $jobs = DB::table('failed_jobs')->select(['payload', 'exception'])->get();

        return $jobs->filter(function ($job) {
            $raw = (string) ($job->payload ?? '');
            $payload = $this->decodePayload($raw);
            $name = $this->resolveJobName($payload, $raw);

            return $this->isNotificationRelated($name, $raw.' '.($job->exception ?? ''));
        })->count();
    }

    protected function decodePayload(?string $payload): array
    {
        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function resolveJobName(array $payload, string $rawPayload): string
    {
        $name = (string) ($payload['displayName'] ?? data_get($payload, 'data.commandName', ''));

        if ($name !== '') {
            return class_basename($name);
        }

        if (Str::contains($rawPayload, 'SendWhatsAppMessageJob')) {
            return 'SendWhatsAppMessageJob';
        }

        if (Str::contains($rawPayload, 'Notification')) {
            return 'Notification';
        }

        return __('Unknown job');
    }

    protected function isNotificationRelated(?string $name, string $haystack): bool
    {
        $haystack = Str::lower(($name ?? '').' '.$haystack);

        return Str::contains($haystack, [
            'sendwhatsappmessagejob',
            'notification',
            'whatsapp',
            'orderupdatemail',
            'orderstatuschangednotification',
            'deliverystatusupdatednotification',
        ]);
    }

    protected function observability(): array
    {
        $data = [
            'operator_dispatch_retries_24h' => 0,
            'operator_whatsapp_retries_24h' => 0,
            'duplicate_dispatches_prevented_24h' => 0,
            'duplicate_whatsapp_prevented_24h' => 0,
            'automation_runs_24h' => 0,
            'safety_blocks_24h' => 0,
        ];

        if (Schema::hasTable('notification_dispatch_logs')) {
            $dispatchBase = NotificationDispatchLog::query()->where('created_at', '>=', now()->subDay());
            $data['operator_dispatch_retries_24h'] = (int) (clone $dispatchBase)->where('meta->operator_retry', true)->count();
            $data['duplicate_dispatches_prevented_24h'] = (int) (clone $dispatchBase)->where('meta->duplicate_guard', true)->count();
            $data['automation_runs_24h'] = (int) (clone $dispatchBase)->where('meta->automation_run', true)->count();
        }

        if (Schema::hasTable('whatsapp_logs')) {
            $whatsAppBase = WhatsAppLog::query()->where('created_at', '>=', now()->subDay());
            $data['operator_whatsapp_retries_24h'] = (int) (clone $whatsAppBase)->where('meta->operator_retry', true)->count();
            $data['duplicate_whatsapp_prevented_24h'] = (int) (clone $whatsAppBase)->where('meta->duplicate_guard', true)->count();
        }

        if (Schema::hasTable('admin_activity_logs')) {
            $data['safety_blocks_24h'] = (int) DB::table('admin_activity_logs')
                ->where('created_at', '>=', now()->subDay())
                ->whereIn('action', [
                    'failed_job_retry_blocked',
                    'dispatch_log_retry_blocked',
                    'whatsapp_log_retry_blocked',
                ])
                ->count();
        }

        return $data;
    }


    protected function workerResilience(array $queueStats): array
    {
        $driver = (string) ($queueStats['driver'] ?? 'sync');
        $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after', 90);
        $jobTimeout = $this->whatsAppConfig->queueTimeout();
        $backoff = $this->whatsAppConfig->queueBackoffSchedule();

        $score = 100;
        if ($driver === 'sync') {
            $score -= 40;
        }
        if (($queueStats['stale_total'] ?? 0) > 0) {
            $score -= min(25, ((int) $queueStats['stale_total']) * 5);
        }
        if (($queueStats['failed_notification_total'] ?? 0) > 0) {
            $score -= min(25, ((int) $queueStats['failed_notification_total']) * 5);
        }
        if ($jobTimeout >= $retryAfter && $driver !== 'sync') {
            $score -= 10;
        }

        return [
            'score' => max(0, $score),
            'status' => $score >= 85 ? 'healthy' : ($score >= 60 ? 'warning' : 'critical'),
            'queue_driver' => strtoupper($driver),
            'job_timeout_seconds' => $jobTimeout,
            'retry_after_seconds' => $retryAfter,
            'backoff_schedule' => is_array($backoff) ? $backoff : [$backoff],
            'notes' => [
                __('Use a long-running worker or Supervisor/systemd in production instead of sync mode.'),
                __('Keep queue retry_after above the job timeout so a running WhatsApp job is not picked twice.'),
                __('Restart workers after deploys when queue code changes to avoid stale worker processes.'),
            ],
        ];
    }

    protected function recoveryPlaybook(): array
    {
        $failedJobs = $this->recentFailedJobs();
        $notificationFailed = $failedJobs->where('is_notification_related', true);
        $latest = $notificationFailed->take(5)->values();

        return [
            'failed_notification_jobs' => $notificationFailed->count(),
            'top_failed_jobs' => $latest,
            'steps' => [
                __('Check provider credentials and channel readiness before retrying a burst of failed jobs.'),
                __('Retry one representative failed job first, then confirm the related dispatch or WhatsApp log moves to a healthy state.'),
                __('If failures are caused by validation, missing templates, or missing order data, fix the root cause before bulk retries.'),
                __('Treat older failed jobs as dead-letter style records for diagnosis. Only retry the ones that still make business sense.'),
            ],
        ];
    }

    protected function providerTuning(): array
    {
        $queueBackoff = $this->whatsAppConfig->queueBackoffSchedule();

        return [
            'whatsapp_timeout_seconds' => (int) $this->whatsAppConfig->meta('timeout', 20),
            'whatsapp_connect_timeout_seconds' => $this->whatsAppConfig->metaConnectTimeout(),
            'whatsapp_retry_times' => $this->whatsAppConfig->metaRetryTimes(),
            'whatsapp_retry_sleep_ms' => $this->whatsAppConfig->metaRetrySleepMilliseconds(),
            'queue_backoff_strategy' => $this->whatsAppConfig->queueBackoffStrategy(),
            'queue_backoff_schedule' => is_array($queueBackoff) ? $queueBackoff : [$queueBackoff],
        ];
    }

    protected function releaseChecklist(array $queueStats, array $channelHealth, array $dispatchTrend, array $whatsAppHealth): array
    {
        return [
            [
                'label' => __('Queue worker is running on a non-sync driver'),
                'done' => ($queueStats['driver'] ?? 'sync') !== 'sync',
            ],
            [
                'label' => __('No stale notification jobs are waiting in the queue'),
                'done' => ((int) ($queueStats['stale_total'] ?? 0)) === 0,
            ],
            [
                'label' => __('No failed notification jobs are pending manual recovery'),
                'done' => ((int) ($queueStats['failed_notification_total'] ?? 0)) === 0,
            ],
            [
                'label' => __('Dispatch success rate in the last 24 hours is healthy'),
                'done' => ((int) ($dispatchTrend['success_rate'] ?? 0)) >= 95,
            ],
            [
                'label' => __('WhatsApp provider failures are under control'),
                'done' => ((int) ($whatsAppHealth['last_24h_failed'] ?? 0)) === 0,
            ],
            [
                'label' => __('Email channel maintained a safe success rate'),
                'done' => ((int) data_get($channelHealth, 'email.success_rate', 100)) >= 80,
            ],
        ];
    }


    protected function incidentSummary(array $queueStats, array $dispatchTrend, array $whatsAppHealth, array $observability): array
    {
        $score = 100;
        $openIncidents = 0;
        $attentionReasons = [];

        if (($queueStats['stale_total'] ?? 0) > 0) {
            $openIncidents++;
            $score -= min(25, ((int) $queueStats['stale_total']) * 5);
            $attentionReasons[] = __('Stale queue jobs need worker attention.');
        }

        if (($queueStats['failed_notification_total'] ?? 0) > 0) {
            $openIncidents++;
            $score -= min(20, ((int) $queueStats['failed_notification_total']) * 4);
            $attentionReasons[] = __('Failed notification jobs still need recovery review.');
        }

        if (($dispatchTrend['last_24h_failed'] ?? 0) > 0) {
            $openIncidents++;
            $score -= min(15, ((int) $dispatchTrend['last_24h_failed']) * 2);
            $attentionReasons[] = __('Recent dispatch failures were recorded.');
        }

        if (($whatsAppHealth['last_24h_failed'] ?? 0) > 0) {
            $openIncidents++;
            $score -= min(15, ((int) $whatsAppHealth['last_24h_failed']) * 3);
            $attentionReasons[] = __('WhatsApp provider failures need inspection.');
        }

        if (($observability['safety_blocks_24h'] ?? 0) > 0) {
            $score -= min(10, ((int) $observability['safety_blocks_24h']) * 2);
            $attentionReasons[] = __('Operator safety guards blocked one or more retries recently.');
        }

        $score = max(0, $score);
        $tone = $score >= 90 ? 'success' : ($score >= 70 ? 'warning' : 'danger');
        $statusLabel = $score >= 90 ? __('Stable') : ($score >= 70 ? __('Needs review') : __('At risk'));

        $nextAction = __('Review diagnostics');
        if (($queueStats['stale_total'] ?? 0) > 0) {
            $nextAction = __('Check workers and stale jobs');
        } elseif (($queueStats['failed_notification_total'] ?? 0) > 0) {
            $nextAction = __('Review failed queue jobs');
        } elseif (($whatsAppHealth['last_24h_failed'] ?? 0) > 0) {
            $nextAction = __('Review WhatsApp provider failures');
        } elseif (($dispatchTrend['last_24h_failed'] ?? 0) > 0) {
            $nextAction = __('Inspect failed dispatch logs');
        }

        return [
            'score' => $score,
            'tone' => $tone,
            'status_label' => $statusLabel,
            'open_incidents' => $openIncidents,
            'attention_reasons' => $attentionReasons,
            'next_action' => $nextAction,
        ];
    }

    protected function retryGuardBreakdown(): array
    {
        $default = [
            'total' => 0,
            'blocked_last_24h' => 0,
            'top_reasons' => [],
        ];

        if (! Schema::hasTable('admin_activity_logs')) {
            return $default;
        }

        $logs = DB::table('admin_activity_logs')
            ->select(['meta', 'created_at'])
            ->whereIn('action', [
                'failed_job_retry_blocked',
                'dispatch_log_retry_blocked',
                'whatsapp_log_retry_blocked',
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('id')
            ->get();

        $reasonCounts = [];
        $blockedLast24h = 0;

        foreach ($logs as $row) {
            $meta = is_string($row->meta ?? null) ? json_decode((string) $row->meta, true) : (array) ($row->meta ?? []);
            $reason = (string) ($meta['reason_code'] ?? 'unknown_guard');
            $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;

            if (! empty($row->created_at) && Carbon::parse($row->created_at)->gte(now()->subDay())) {
                $blockedLast24h++;
            }
        }

        arsort($reasonCounts);
        $topReasons = collect($reasonCounts)->take(5)->map(function ($count, $reason) {
            return [
                'reason' => Str::headline(str_replace('_', ' ', (string) $reason)),
                'count' => (int) $count,
            ];
        })->values()->all();

        return [
            'total' => (int) $logs->count(),
            'blocked_last_24h' => $blockedLast24h,
            'top_reasons' => $topReasons,
        ];
    }

    protected function providerFailureBreakdown(): array
    {
        $groups = [];

        if (Schema::hasTable('notification_dispatch_logs')) {
            $dispatchErrors = NotificationDispatchLog::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->where('status', NotificationDispatchLog::STATUS_FAILED)
                ->pluck('error_message');

            foreach ($dispatchErrors as $message) {
                $bucket = $this->classifyFailureMessage((string) $message);
                $groups[$bucket] = ($groups[$bucket] ?? 0) + 1;
            }
        }

        if (Schema::hasTable('whatsapp_logs')) {
            $whatsAppErrors = WhatsAppLog::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->where('status', WhatsAppLog::STATUS_FAILED)
                ->pluck('error_message');

            foreach ($whatsAppErrors as $message) {
                $bucket = $this->classifyFailureMessage((string) $message);
                $groups[$bucket] = ($groups[$bucket] ?? 0) + 1;
            }
        }

        arsort($groups);

        return [
            'total' => array_sum($groups),
            'groups' => collect($groups)->take(6)->map(function ($count, $label) {
                return [
                    'label' => $label,
                    'count' => (int) $count,
                ];
            })->values()->all(),
        ];
    }

    protected function recentIncidents(): array
    {
        $incidents = collect();

        if (Schema::hasTable('notification_dispatch_logs')) {
            $dispatchIncidents = NotificationDispatchLog::query()
                ->with('order:id,order_number')
                ->where('created_at', '>=', now()->subDays(3))
                ->whereIn('status', [NotificationDispatchLog::STATUS_FAILED, NotificationDispatchLog::STATUS_PENDING, NotificationDispatchLog::STATUS_SKIPPED])
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(function (NotificationDispatchLog $log) {
                    $tone = $log->status === NotificationDispatchLog::STATUS_FAILED ? 'danger' : ($log->status === NotificationDispatchLog::STATUS_PENDING ? 'warning' : 'secondary');

                    return [
                        'title' => __('Dispatch incident · :event', ['event' => Str::headline(str_replace('_', ' ', (string) $log->event))]),
                        'meta' => __('Channel: :channel · Order: :order', [
                            'channel' => ucfirst((string) $log->channel),
                            'order' => optional($log->order)->order_number ?: __('Unknown order'),
                        ]),
                        'description' => $log->error_message ?: __('Status: :status', ['status' => Str::headline((string) $log->status)]),
                        'tone' => $tone,
                        'at' => $log->failed_at ?? $log->attempted_at ?? $log->created_at,
                    ];
                });

            $incidents = $incidents->merge($dispatchIncidents);
        }

        if (Schema::hasTable('whatsapp_logs')) {
            $whatsAppIncidents = WhatsAppLog::query()
                ->with('order:id,order_number')
                ->where('created_at', '>=', now()->subDays(3))
                ->whereIn('status', [WhatsAppLog::STATUS_FAILED, WhatsAppLog::STATUS_SKIPPED])
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(function (WhatsAppLog $log) {
                    return [
                        'title' => __('WhatsApp incident · :type', ['type' => Str::headline(str_replace('_', ' ', (string) $log->message_type))]),
                        'meta' => __('Order: :order · Attempts: :attempts', [
                            'order' => optional($log->order)->order_number ?: __('Unknown order'),
                            'attempts' => (int) ($log->attempts ?? 0),
                        ]),
                        'description' => $log->error_message ?: __('Status: :status', ['status' => Str::headline((string) $log->status)]),
                        'tone' => $log->status === WhatsAppLog::STATUS_FAILED ? 'danger' : 'warning',
                        'at' => $log->failed_at ?? $log->created_at,
                    ];
                });

            $incidents = $incidents->merge($whatsAppIncidents);
        }

        if (Schema::hasTable('admin_activity_logs')) {
            $blocked = DB::table('admin_activity_logs')
                ->select(['description', 'meta', 'created_at'])
                ->where('created_at', '>=', now()->subDays(3))
                ->whereIn('action', [
                    'failed_job_retry_blocked',
                    'dispatch_log_retry_blocked',
                    'whatsapp_log_retry_blocked',
                ])
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $meta = is_string($row->meta ?? null) ? json_decode((string) $row->meta, true) : (array) ($row->meta ?? []);
                    return [
                        'title' => __('Safety block'),
                        'meta' => Str::headline(str_replace('_', ' ', (string) ($meta['reason_code'] ?? 'guard_triggered'))),
                        'description' => (string) ($row->description ?? __('An operator retry was blocked by a guard.')),
                        'tone' => 'warning',
                        'at' => ! empty($row->created_at) ? Carbon::parse($row->created_at) : null,
                    ];
                });

            $incidents = $incidents->merge($blocked);
        }

        return $incidents
            ->sortByDesc(fn (array $item) => optional($item['at'] ?? null)?->timestamp ?? 0)
            ->take(10)
            ->values()
            ->all();
    }

    protected function classifyFailureMessage(string $message): string
    {
        $haystack = Str::lower($message);

        if ($haystack === '') {
            return __('Unknown / uncategorized');
        }

        if (Str::contains($haystack, ['401', '403', 'unauthorized', 'forbidden', 'token', 'permission'])) {
            return __('Auth / permissions');
        }

        if (Str::contains($haystack, ['template', 'parameter', 'validation', 'invalid', 'format'])) {
            return __('Template / payload');
        }

        if (Str::contains($haystack, ['recipient', 'phone', 'number', 'allowed list', 'whatsapp'])) {
            return __('Recipient / destination');
        }

        if (Str::contains($haystack, ['timeout', 'timed out', 'connect', 'ssl', 'curl', 'network'])) {
            return __('Transport / network');
        }

        if (Str::contains($haystack, ['duplicate', 'already'])) {
            return __('Duplicate / already processed');
        }

        return __('Other provider / runtime');
    }

    protected function fromUnix($timestamp): ?Carbon
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }
}
