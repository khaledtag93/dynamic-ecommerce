<?php

namespace App\Services\System;

use App\Models\AdminActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeployCenterService
{
    public function __construct(
        protected DeployRemoteRequest $remoteRequest,
    ) {
    }

    public function dashboard(?string $selectedLog = null): array
    {
        $payload = array_filter([
            'selected_log' => $selectedLog,
        ], fn ($value) => ! is_null($value) && $value !== '');

        $response = $this->send('GET', $this->statusUrl(), $payload);
        $data = (array) ($response['data'] ?? []);

        $logs = collect($data['logs'] ?? [])->map(function (array $log) {
            $log['modified_at'] = isset($log['modified_at']) ? Carbon::parse($log['modified_at']) : now();

            return $log;
        })->all();

        $backups = collect($data['backups'] ?? [])->map(function (array $backup) {
            $backup['modified_at'] = isset($backup['modified_at']) ? Carbon::parse($backup['modified_at']) : now();

            return $backup;
        })->all();

        $selectedLog = $data['selected_log'] ?? ($selectedLog ?: ($logs[0]['name'] ?? null));
        $activityFeed = $this->activityFeed();
        $activity = $activityFeed['recent'] ?? [];
        $monitoring = $this->buildMonitoringSnapshot($activityFeed, $logs, $backups, (array) ($data['active_lock'] ?? null), (array) ($data['readiness'] ?? []));
        $git = (array) ($data['git'] ?? []);

        return [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'mode' => 'remote',
            'remote_available' => (bool) ($response['ok'] ?? false),
            'remote_message' => (string) ($response['message'] ?? ''),
            'remote_base_url' => $this->remoteBaseUrl(),
            'workspace_path' => (string) ($data['workspace_path'] ?? ''),
            'logs_path' => (string) ($data['logs_path'] ?? ''),
            'backups_path' => (string) ($data['backups_path'] ?? ''),
            'deploy_script_exists' => (bool) ($data['deploy_script_exists'] ?? false),
            'rollback_script_exists' => (bool) ($data['rollback_script_exists'] ?? false),
            'logs' => $logs,
            'backups' => $backups,
            'selected_log' => $selectedLog,
            'selected_log_content' => $data['selected_log_content'] ?? null,
            'healthcheck_url' => (string) ($data['healthcheck_url'] ?? config('app.url')),
            'server_name' => (string) ($data['server_name'] ?? ''),
            'generated_at' => ! empty($data['generated_at']) ? Carbon::parse($data['generated_at']) : null,
            'active_lock' => $data['active_lock'] ?? null,
            'recent_activity' => $activity,
            'monitoring' => $monitoring,
            'git' => [
                'available' => (bool) ($git['available'] ?? false),
                'branch' => (string) ($git['branch'] ?? ''),
                'full_hash' => (string) ($git['full_hash'] ?? ''),
                'short_hash' => (string) ($git['short_hash'] ?? ''),
                'subject' => (string) ($git['subject'] ?? ''),
                'author_name' => (string) ($git['author_name'] ?? ''),
                'authored_at' => ! empty($git['authored_at']) ? Carbon::parse($git['authored_at']) : null,
                'dirty' => (bool) ($git['dirty'] ?? false),
            ],
            'readiness' => $this->mapReadiness((array) ($data['readiness'] ?? [])),
        ];
    }


    protected function mapReadiness(array $readiness): array
    {
        $checks = collect($readiness['checks'] ?? [])->map(function ($check) {
            return [
                'key' => (string) ($check['key'] ?? ''),
                'level' => (string) ($check['level'] ?? 'warning'),
                'label' => (string) ($check['label'] ?? __('Check')),
                'message' => (string) ($check['message'] ?? ''),
                'count' => $check['count'] ?? null,
                'free_mb' => $check['free_mb'] ?? null,
            ];
        })->all();

        return [
            'action' => (string) ($readiness['action'] ?? 'deploy'),
            'overall' => (string) ($readiness['overall'] ?? 'warning'),
            'summary_label' => (string) ($readiness['summary_label'] ?? __('Needs review')),
            'allow_execute' => (bool) ($readiness['allow_execute'] ?? false),
            'counts' => [
                'total' => (int) (($readiness['counts']['total'] ?? 0)),
                'ok' => (int) (($readiness['counts']['ok'] ?? 0)),
                'warnings' => (int) (($readiness['counts']['warnings'] ?? 0)),
                'blockers' => (int) (($readiness['counts']['blockers'] ?? 0)),
            ],
            'checks' => $checks,
            'blockers' => array_values(array_filter($checks, fn ($check) => ($check['level'] ?? '') === 'blocker')),
            'warnings' => array_values(array_filter($checks, fn ($check) => ($check['level'] ?? '') === 'warning')),
            'ok_checks' => array_values(array_filter($checks, fn ($check) => ($check['level'] ?? '') === 'ok')),
        ];
    }

    protected function activityFeed(): array
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return [
                'recent' => [],
                'all' => [],
            ];
        }

        $rows = AdminActivityLog::query()
            ->with('adminUser:id,name')
            ->where('type', 'deploy_center')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(function (AdminActivityLog $row) {
                $meta = is_array($row->meta) ? $row->meta : [];
                $durationMs = isset($meta['duration_ms']) && is_numeric($meta['duration_ms']) ? (int) $meta['duration_ms'] : null;

                return [
                    'action' => (string) $row->action,
                    'description' => (string) ($row->description ?: __('Deploy Center activity')),
                    'admin_name' => (string) ($row->adminUser?->name ?: __('System')),
                    'created_at' => $row->created_at ?: now(),
                    'meta' => $meta,
                    'duration_ms' => $durationMs,
                    'duration_human' => $this->humanDuration($durationMs),
                    'is_success' => ! empty($meta['ok']),
                    'is_dry_run' => ($meta['action_mode'] ?? 'execute') === 'dry_run',
                ];
            })
            ->values();

        return [
            'recent' => $rows->take(8)->all(),
            'all' => $rows->all(),
        ];
    }

    protected function buildMonitoringSnapshot(array $activityFeed, array $logs, array $backups, array $activeLock = [], array $remoteReadiness = []): array
    {
        $all = collect($activityFeed['all'] ?? []);
        $live = $all->filter(fn (array $item) => ! ($item['is_dry_run'] ?? false));
        $successful = $all->filter(fn (array $item) => (bool) ($item['is_success'] ?? false));
        $failed = $all->filter(fn (array $item) => ! ($item['is_success'] ?? false));
        $durations = $all->pluck('duration_ms')->filter(fn ($value) => is_numeric($value) && (int) $value >= 0)->map(fn ($value) => (int) $value);
        $recentActions = $all->take(10)->values()->all();
        $lastLiveDeploy = $all->first(fn (array $item) => ($item['action'] ?? '') === 'deploy' && ! ($item['is_dry_run'] ?? false));
        $lastRollback = $all->first(fn (array $item) => ($item['action'] ?? '') === 'rollback' && ! ($item['is_dry_run'] ?? false));
        $lastSuccess = $all->first(fn (array $item) => (bool) ($item['is_success'] ?? false));
        $lastFailure = $all->first(fn (array $item) => ! ($item['is_success'] ?? false));
        $deployCount = $all->filter(fn (array $item) => ($item['action'] ?? '') === 'deploy')->count();
        $rollbackCount = $all->filter(fn (array $item) => ($item['action'] ?? '') === 'rollback')->count();
        $successRate = $all->count() > 0 ? round(($successful->count() / max(1, $all->count())) * 100, 1) : null;
        $avgDurationMs = $durations->count() > 0 ? (int) round($durations->avg()) : null;
        $readiness = $this->mapReadiness($remoteReadiness);
        $blockers = (int) ($readiness['counts']['blockers'] ?? 0);
        $warnings = (int) ($readiness['counts']['warnings'] ?? 0);
        $overall = (string) ($readiness['overall'] ?? 'warning');

        return [
            'totals' => [
                'actions' => $all->count(),
                'live_actions' => $live->count(),
                'successes' => $successful->count(),
                'failures' => $failed->count(),
                'deploys' => $deployCount,
                'rollbacks' => $rollbackCount,
                'logs' => count($logs),
                'backups' => count($backups),
            ],
            'success_rate' => $successRate,
            'avg_duration_ms' => $avgDurationMs,
            'avg_duration_human' => $this->humanDuration($avgDurationMs),
            'readiness' => [
                'overall' => $overall,
                'label' => (string) ($readiness['summary_label'] ?? __('Needs review')),
                'blockers' => $blockers,
                'warnings' => $warnings,
                'score' => $this->readinessScore($overall, $warnings, $blockers),
            ],
            'last_live_deploy' => $this->compactActivitySummary($lastLiveDeploy),
            'last_rollback' => $this->compactActivitySummary($lastRollback),
            'last_success' => $this->compactActivitySummary($lastSuccess),
            'last_failure' => $this->compactActivitySummary($lastFailure),
            'recent_actions' => $recentActions,
            'active_lock' => ! empty($activeLock),
        ];
    }

    protected function compactActivitySummary(?array $activity): ?array
    {
        if (! is_array($activity) || empty($activity)) {
            return null;
        }

        return [
            'action' => (string) ($activity['action'] ?? ''),
            'description' => (string) ($activity['description'] ?? ''),
            'admin_name' => (string) ($activity['admin_name'] ?? __('System')),
            'created_at' => $activity['created_at'] ?? null,
            'duration_human' => (string) ($activity['duration_human'] ?? __('n/a')),
            'duration_ms' => $activity['duration_ms'] ?? null,
            'status_label' => ! empty($activity['is_success']) ? __('Success') : __('Needs review'),
            'status_tone' => ! empty($activity['is_success']) ? 'success' : 'warning',
            'action_mode_label' => ($activity['meta']['action_mode'] ?? 'execute') === 'dry_run' ? __('Dry run') : __('Live action'),
            'git_branch' => (string) (($activity['meta']['git_branch'] ?? '')),
            'latest_commit' => (string) (($activity['meta']['latest_commit'] ?? '')),
        ];
    }

    protected function humanDuration(?int $durationMs): string
    {
        if (is_null($durationMs) || $durationMs < 0) {
            return __('n/a');
        }

        if ($durationMs < 1000) {
            return __(':value ms', ['value' => number_format($durationMs)]);
        }

        $seconds = $durationMs / 1000;

        if ($seconds < 60) {
            return __(':value s', ['value' => number_format($seconds, 1)]);
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = (int) round($seconds - ($minutes * 60));

        return __(':minutes m :seconds s', [
            'minutes' => number_format((int) $minutes),
            'seconds' => number_format($remainingSeconds),
        ]);
    }

    protected function readinessScore(string $overall, int $warnings, int $blockers): int
    {
        $score = 100 - ($warnings * 8) - ($blockers * 30);

        if ($overall === 'blocked') {
            $score = min($score, 39);
        } elseif ($overall === 'warning') {
            $score = min($score, 79);
        }

        return max(0, min(100, $score));
    }


    public function deploy(array $options = []): array
    {
        return $this->execute('deploy', $options);
    }

    public function rollback(string $backupName, array $options = []): array
    {
        return $this->execute('rollback', array_merge($options, [
            'backup_name' => basename(trim($backupName)),
        ]));
    }

    protected function execute(string $action, array $payload = []): array
    {
        return $this->send('POST', $this->executeUrl(), array_merge($payload, [
            'action' => $action,
        ]));
    }

    protected function send(string $method, string $url, array $payload = []): array
    {
        if ($guard = $this->preflightGuardResult()) {
            return $guard;
        }

        try {
            $encodedBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $body = $method === 'GET' ? '' : ($encodedBody !== false ? $encodedBody : '{}');

            $request = Http::timeout((int) config('deploy.remote.timeout_seconds', 900))
                ->connectTimeout((int) config('deploy.remote.connect_timeout_seconds', 10))
                ->withHeaders($this->remoteRequest->headers($body));

            $response = $method === 'GET'
                ? $request->get($url, $payload)
                : $request->withBody($body, 'application/json')->post($url);

            $json = $response->json();

            if ($response->successful() && is_array($json)) {
                return [
                    'ok' => (bool) ($json['ok'] ?? true),
                    'status' => (string) ($json['status'] ?? 'success'),
                    'message' => (string) ($json['message'] ?? __('Remote request completed successfully.')),
                    'output' => (string) ($json['output'] ?? ''),
                    'exit_code' => $json['exit_code'] ?? null,
                    'data' => (array) ($json['data'] ?? []),
                    'ran_on' => $json['ran_on'] ?? null,
                    'latest_log_name' => $json['latest_log_name'] ?? null,
                    'latest_log_modified_at' => $json['latest_log_modified_at'] ?? null,
                    'action_mode' => $json['action_mode'] ?? ($payload['action_mode'] ?? 'execute'),
                    'duration_ms' => isset($json['duration_ms']) && is_numeric($json['duration_ms']) ? (int) $json['duration_ms'] : null,
                    'started_at' => $json['started_at'] ?? null,
                    'completed_at' => $json['completed_at'] ?? null,
                    'git' => (array) ($json['git'] ?? []),
                    'readiness' => $this->mapReadiness((array) ($json['readiness'] ?? [])),
                ];
            }

            return [
                'ok' => false,
                'status' => 'http-error',
                'message' => is_array($json) ? (string) ($json['message'] ?? __('Remote request failed.')) : __('Remote request failed.'),
                'output' => $response->body(),
                'exit_code' => $response->status(),
                'data' => [],
                'action_mode' => $payload['action_mode'] ?? 'execute',
                'git' => [],
                'readiness' => $this->mapReadiness([]),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => 'connection-failed',
                'message' => __('Could not reach the remote deploy executor.'),
                'output' => $exception->getMessage(),
                'exit_code' => null,
                'data' => [],
                'action_mode' => $payload['action_mode'] ?? 'execute',
                'git' => [],
                'readiness' => $this->mapReadiness([]),
            ];
        }
    }

    protected function preflightGuardResult(): ?array
    {
        if ($guard = $this->missingRemoteUrlGuardResult()) {
            return $guard;
        }

        if ($guard = $this->selfRemoteGuardResult()) {
            return $guard;
        }

        return null;
    }

    protected function missingRemoteUrlGuardResult(): ?array
    {
        if ($this->remoteBaseUrl() !== '') {
            return null;
        }

        return [
            'ok' => false,
            'status' => 'remote-url-missing',
            'message' => __('Remote executor URL is not configured.'),
            'output' => '',
            'exit_code' => null,
            'data' => [],
            'action_mode' => 'execute',
            'git' => [],
        ];
    }

    protected function selfRemoteGuardResult(): ?array
    {
        if (! $this->shouldBlockLocalSelfRemoteCall()) {
            return null;
        }

        return [
            'ok' => false,
            'status' => 'self-remote-blocked',
            'message' => __('Deploy Center remote mode must target the production executor URL, not this same local app instance.'),
            'output' => '',
            'exit_code' => null,
            'data' => [],
            'action_mode' => 'execute',
            'git' => [],
        ];
    }

    protected function shouldBlockLocalSelfRemoteCall(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        $remote = $this->normalizedHost($this->remoteBaseUrl());
        $app = $this->normalizedHost(config('app.url'));

        if ($remote === '' || $app === '') {
            return false;
        }

        return $remote === $app;
    }

    protected function normalizedHost(?string $url): string
    {
        if (! is_string($url) || trim($url) === '') {
            return '';
        }

        $host = parse_url(trim($url), PHP_URL_HOST);

        return is_string($host) ? Str::lower($host) : '';
    }

    protected function remoteBaseUrl(): string
    {
        return rtrim((string) config('deploy.remote.base_url', config('app.url')), '/');
    }

    protected function statusUrl(): string
    {
        return $this->remoteBaseUrl() === '' ? '' : $this->remoteBaseUrl().'/api/internal/deploy-center/status';
    }

    protected function executeUrl(): string
    {
        return $this->remoteBaseUrl() === '' ? '' : $this->remoteBaseUrl().'/api/internal/deploy-center/execute';
    }
}
