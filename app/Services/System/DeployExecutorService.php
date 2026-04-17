<?php

namespace App\Services\System;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class DeployExecutorService
{
    public function status(?string $selectedLog = null): array
    {
        $logs = $this->listLogs();
        $backups = $this->listBackups();
        $selectedLog = $selectedLog ?: ($logs[0]['name'] ?? null);
        $git = $this->gitSummary();

        return [
            'enabled' => $this->isEnabled(),
            'mode' => 'remote',
            'workspace_path' => $this->getWorkspacePath(),
            'logs_path' => $this->getLogsDirectory(),
            'backups_path' => $this->getBackupsDirectory(),
            'deploy_script_exists' => File::exists($this->getDeployScriptPath()),
            'rollback_script_exists' => File::exists($this->getRollbackScriptPath()),
            'logs' => $logs,
            'backups' => $backups,
            'selected_log' => $selectedLog,
            'selected_log_content' => $selectedLog ? $this->readLog($selectedLog) : null,
            'healthcheck_url' => config('deploy.healthcheck_url', env('HEALTHCHECK_URL', config('app.url'))),
            'server_name' => gethostname() ?: php_uname('n'),
            'generated_at' => now()->toIso8601String(),
            'active_lock' => $this->currentLockData(),
            'git' => $git,
            'readiness' => $this->readinessSummary('deploy', [], $git),
        ];
    }

    public function deploy(array $options = []): array
    {
        return $this->run('deploy', [$this->getDeployScriptPath()], $options);
    }

    public function rollback(string $backupName, array $options = []): array
    {
        $safeBackupName = $this->sanitizeBackupName($backupName);

        if ($safeBackupName === '') {
            return [
                'ok' => false,
                'status' => 'missing-backup',
                'message' => __('Please select a valid backup before running rollback.'),
                'output' => '',
                'exit_code' => null,
                'action_mode' => $options['action_mode'] ?? 'execute',
                'git' => $this->gitSummary(),
                'readiness' => $this->readinessSummary('rollback', $options),
            ];
        }

        if (! $this->backupExists($safeBackupName)) {
            return [
                'ok' => false,
                'status' => 'backup-not-found',
                'message' => __('The selected backup folder could not be found on the server.'),
                'output' => $safeBackupName,
                'exit_code' => null,
                'action_mode' => $options['action_mode'] ?? 'execute',
                'git' => $this->gitSummary(),
                'readiness' => $this->readinessSummary('rollback', ['backup_name' => $safeBackupName]),
            ];
        }

        return $this->run('rollback', [$this->getRollbackScriptPath(), $safeBackupName], array_merge($options, [
            'backup_name' => $safeBackupName,
        ]));
    }

    public function isEnabled(): bool
    {
        return (bool) config('deploy.enabled', true);
    }

    public function getWorkspacePath(): string
    {
        return (string) config('deploy.workspace_path', base_path());
    }

    public function getLogsDirectory(): string
    {
        return (string) config('deploy.logs_path', dirname(base_path()).DIRECTORY_SEPARATOR.'deploy_logs');
    }

    public function getBackupsDirectory(): string
    {
        return (string) config('deploy.backups_path', dirname(base_path()).DIRECTORY_SEPARATOR.'deploy_backups');
    }

    public function getDeployScriptPath(): string
    {
        return (string) config('deploy.deploy_script', base_path('deploy.sh'));
    }

    public function getRollbackScriptPath(): string
    {
        return (string) config('deploy.rollback_script', base_path('rollback.sh'));
    }

    public function listLogs(): array
    {
        $directory = $this->getLogsDirectory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => Str::endsWith($file->getFilename(), '.log'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values()
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => Carbon::createFromTimestamp($file->getMTime())->toIso8601String(),
            ])
            ->all();
    }

    public function listBackups(): array
    {
        $directory = $this->getBackupsDirectory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::directories($directory))
            ->map(function (string $path) {
                return [
                    'name' => basename($path),
                    'path' => $path,
                    'modified_at' => Carbon::createFromTimestamp(File::lastModified($path))->toIso8601String(),
                ];
            })
            ->sortByDesc(fn (array $backup) => strtotime($backup['modified_at']) ?: 0)
            ->values()
            ->all();
    }

    public function readLog(string $fileName): ?string
    {
        $path = $this->resolveLogPath($fileName);

        if (! $path || ! File::exists($path)) {
            return null;
        }

        $content = File::get($path);
        $maxBytes = max(4096, (int) config('deploy.max_log_preview_bytes', 262144));

        if (strlen($content) <= $maxBytes) {
            return $content;
        }

        return "... ".__('Showing the last :kb KB of this log.', ['kb' => number_format((int) ceil($maxBytes / 1024))])."\n\n".substr($content, -1 * $maxBytes);
    }

    protected function run(string $action, array $command, array $options = []): array
    {
        $actionMode = (string) ($options['action_mode'] ?? 'execute');
        $startedAt = microtime(true);
        $startedAtIso = now()->toIso8601String();
        $git = $this->gitSummary();
        $readiness = $this->readinessSummary($action, $options, $git);

        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'status' => 'disabled',
                'message' => __('Deploy Center is disabled by configuration.'),
                'output' => $this->renderReadinessOutput($readiness),
                'exit_code' => null,
                'action_mode' => $actionMode,
                'git' => $git,
                'readiness' => $readiness,
            ];
        }

        $scriptPath = $command[0] ?? null;

        if (! $scriptPath || ! File::exists($scriptPath)) {
            return [
                'ok' => false,
                'status' => 'missing-script',
                'message' => __('The requested deployment script is missing.'),
                'output' => $scriptPath ?: '',
                'exit_code' => null,
                'action_mode' => $actionMode,
                'git' => $git,
                'readiness' => $readiness,
            ];
        }

        if ($busyResult = $this->concurrentExecutionGuardResult()) {
            $busyResult['action_mode'] = $actionMode;
            $busyResult['git'] = $git;
            $busyResult['readiness'] = $readiness;

            return $busyResult;
        }

        if ($actionMode !== 'dry_run' && ! ($readiness['allow_execute'] ?? false)) {
            return [
                'ok' => false,
                'status' => 'blocked',
                'message' => __('Deploy readiness checks found blocker issues. Review the readiness panel and resolve blockers before running a live action.'),
                'output' => $this->renderReadinessOutput($readiness),
                'exit_code' => null,
                'ran_on' => gethostname() ?: php_uname('n'),
                'action_mode' => $actionMode,
                'git' => $git,
                'readiness' => $readiness,
            ];
        }

        if ($actionMode === 'dry_run') {
            return $this->dryRunResult($action, $scriptPath, $options, $git, $readiness, $startedAt, $startedAtIso);
        }

        $command = $this->normalizeCommand($command);
        $this->storeLock($action);

        try {
            $process = new Process($command, $this->getWorkspacePath(), null, null, (int) config('deploy.timeout_seconds', 900));
            $process->mustRun();

            return $this->formatProcessResult(true, 'success', __('Remote command completed successfully.'), $process, [
                'action_mode' => $actionMode,
                'git' => $git,
                'readiness' => $readiness,
                'started_at' => $startedAtIso,
            ], $startedAt);
        } catch (ProcessTimedOutException $exception) {
            return $this->formatProcessResult(false, 'timeout', __('The remote command timed out before finishing.'), $process ?? null, [
                'action_mode' => $actionMode,
                'git' => $git,
                'readiness' => $readiness,
                'started_at' => $startedAtIso,
            ], $startedAt);
        } catch (\Throwable $exception) {
            return $this->formatProcessResult(false, 'failed', $exception->getMessage(), $process ?? null, [
                'action_mode' => $actionMode,
                'git' => $git,
                'readiness' => $readiness,
                'started_at' => $startedAtIso,
            ], $startedAt);
        } finally {
            $this->clearLock();
        }
    }

    protected function dryRunResult(string $action, string $scriptPath, array $options, array $git, array $readiness, float $startedAt, string $startedAtIso): array
    {
        $checks = [
            __('Mode: dry run safety check only'),
            __('Action: :action', ['action' => $action]),
            __('Workspace: :path', ['path' => $this->getWorkspacePath()]),
            __('Script: :path', ['path' => $scriptPath]),
            __('Script exists: :state', ['state' => File::exists($scriptPath) ? __('yes') : __('no')]),
            __('Logs path: :path', ['path' => $this->getLogsDirectory()]),
            __('Backups path: :path', ['path' => $this->getBackupsDirectory()]),
            __('Health check URL: :url', ['url' => (string) config('deploy.healthcheck_url', config('app.url'))]),
            '',
            $this->renderReadinessOutput($readiness),
            '',
        ];

        if ($action === 'rollback') {
            $checks[] = __('Selected backup: :backup', ['backup' => (string) ($options['backup_name'] ?? __('none'))]);
        }

        if ($git['available'] ?? false) {
            $checks[] = __('Git branch: :branch', ['branch' => $git['branch'] ?: __('unknown')]);
            $checks[] = __('Git commit: :commit', ['commit' => trim(($git['short_hash'] ?? '').' '.($git['subject'] ?? ''))]);
            $checks[] = __('Working tree clean: :state', ['state' => ! ($git['dirty'] ?? false) ? __('yes') : __('no')]);
        } else {
            $checks[] = __('Git metadata is not available in the configured workspace.');
        }

        if ($lock = $this->currentLockData()) {
            $checks[] = __('Active lock detected for :action since :time', [
                'action' => $lock['action'] ?? __('unknown'),
                'time' => $lock['created_at'] ?? __('unknown'),
            ]);
        } else {
            $checks[] = __('No active deploy lock was detected.');
        }

        $checks[] = __('No server script was executed in dry-run mode.');

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'ok' => true,
            'status' => 'dry-run',
            'message' => __('Dry-run completed successfully. No server changes were made.'),
            'output' => implode("\n", $checks),
            'exit_code' => 0,
            'ran_on' => gethostname() ?: php_uname('n'),
            'latest_log_name' => $this->listLogs()[0]['name'] ?? null,
            'latest_log_modified_at' => $this->listLogs()[0]['modified_at'] ?? null,
            'action_mode' => 'dry_run',
            'git' => $git,
            'readiness' => $readiness,
            'started_at' => $startedAtIso,
            'completed_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
        ];
    }

    protected function readinessSummary(string $action = 'deploy', array $options = [], ?array $git = null): array
    {
        $git ??= $this->gitSummary();
        $checks = [];
        $workspace = $this->getWorkspacePath();
        $logs = $this->getLogsDirectory();
        $backups = $this->getBackupsDirectory();
        $deployScript = $this->getDeployScriptPath();
        $rollbackScript = $this->getRollbackScriptPath();

        $this->pushReadinessCheck(
            $checks,
            'enabled',
            $this->isEnabled() ? 'ok' : 'blocker',
            __('Deploy Center switch'),
            $this->isEnabled() ? __('Deploy Center is enabled in configuration.') : __('ALLOW_DEPLOY_CENTER is disabled, so live deploy actions are blocked.')
        );

        $workspaceExists = File::isDirectory($workspace);
        $this->pushReadinessCheck(
            $checks,
            'workspace_exists',
            $workspaceExists ? 'ok' : 'blocker',
            __('Workspace path'),
            $workspaceExists ? __('Workspace path exists and can be inspected.') : __('Workspace path does not exist on the server.')
        );

        $this->pushReadinessCheck(
            $checks,
            'workspace_writable',
            $workspaceExists && is_writable($workspace) ? 'ok' : 'warning',
            __('Workspace write access'),
            $workspaceExists && is_writable($workspace)
                ? __('Workspace path is writable.')
                : __('Workspace path is not writable for the current PHP user. The deploy script may still handle permissions itself, so review carefully.')
        );

        $envExists = File::exists($workspace.DIRECTORY_SEPARATOR.'.env');
        $this->pushReadinessCheck(
            $checks,
            'env_file',
            $envExists ? 'ok' : 'blocker',
            __('Environment file'),
            $envExists ? __('A .env file was found in the workspace.') : __('No .env file was found in the workspace path.')
        );

        $this->pushReadinessCheck(
            $checks,
            'deploy_script',
            File::exists($deployScript) ? 'ok' : 'blocker',
            __('Deploy script'),
            File::exists($deployScript) ? __('deploy.sh is present.') : __('deploy.sh is missing from the configured path.')
        );

        $this->pushReadinessCheck(
            $checks,
            'rollback_script',
            File::exists($rollbackScript) ? 'ok' : 'warning',
            __('Rollback script'),
            File::exists($rollbackScript) ? __('rollback.sh is present.') : __('rollback.sh is missing from the configured path. Rollback actions will stay unavailable.')
        );

        $this->pushReadinessCheck($checks, 'logs_directory', $this->directoryHealthLevel($logs), __('Deploy logs directory'), $this->directoryHealthMessage($logs, __('Deploy logs directory')));
        $this->pushReadinessCheck($checks, 'backups_directory', $this->directoryHealthLevel($backups), __('Deploy backups directory'), $this->directoryHealthMessage($backups, __('Deploy backups directory')));

        [$diskLevel, $diskMessage, $diskMeta] = $this->diskReadiness($workspaceExists ? $workspace : base_path());
        $this->pushReadinessCheck($checks, 'disk_space', $diskLevel, __('Free disk space'), $diskMessage, $diskMeta);

        foreach (['cache', 'sessions', 'views'] as $segment) {
            $path = storage_path('framework'.DIRECTORY_SEPARATOR.$segment);
            $pathOk = is_dir($path) && is_writable($path);
            $this->pushReadinessCheck(
                $checks,
                'storage_'.$segment,
                $pathOk ? 'ok' : 'blocker',
                __('Storage path: :name', ['name' => $segment]),
                $pathOk ? __('The :name directory is present and writable.', ['name' => $segment]) : __('The :name directory is missing or not writable.', ['name' => $segment])
            );
        }

        $queueDefault = (string) config('queue.default', '');
        $queueConfig = $queueDefault !== '' ? config('queue.connections.'.$queueDefault) : null;
        $this->pushReadinessCheck(
            $checks,
            'queue_connection',
            $queueConfig ? 'ok' : 'warning',
            __('Queue connection'),
            $queueConfig ? __('Queue driver ":driver" is configured.', ['driver' => $queueDefault]) : __('Queue default connection is missing or not configured.')
        );

        $failedJobsCount = 0;
        if (Schema::hasTable('failed_jobs')) {
            try {
                $failedJobsCount = (int) DB::table('failed_jobs')->count();
            } catch (\Throwable $exception) {
                $failedJobsCount = 0;
            }
        }

        $this->pushReadinessCheck(
            $checks,
            'failed_jobs',
            $failedJobsCount === 0 ? 'ok' : ($failedJobsCount < 10 ? 'warning' : 'blocker'),
            __('Failed jobs backlog'),
            $failedJobsCount === 0 ? __('No failed jobs are currently stored.') : __('There are :count failed jobs waiting for review.', ['count' => number_format($failedJobsCount)]),
            ['count' => $failedJobsCount]
        );

        $healthcheckUrl = (string) config('deploy.healthcheck_url', config('app.url'));
        $this->pushReadinessCheck(
            $checks,
            'healthcheck_url',
            filled($healthcheckUrl) ? 'ok' : 'warning',
            __('Health check target'),
            filled($healthcheckUrl) ? __('Health check URL is configured: :url', ['url' => $healthcheckUrl]) : __('Health check URL is empty; post-deploy verification will be harder.')
        );

        $this->pushReadinessCheck(
            $checks,
            'git_available',
            ($git['available'] ?? false) ? 'ok' : 'warning',
            __('Git metadata'),
            ($git['available'] ?? false) ? __('Git metadata is available from the remote workspace.') : __('Git metadata is not available in the current workspace.')
        );

        if ($git['available'] ?? false) {
            $this->pushReadinessCheck(
                $checks,
                'git_dirty',
                ($git['dirty'] ?? false) ? 'warning' : 'ok',
                __('Working tree state'),
                ($git['dirty'] ?? false) ? __('Uncommitted changes were detected in the remote workspace.') : __('The remote working tree is clean.')
            );
        }

        if ($action === 'rollback') {
            $backupName = (string) ($options['backup_name'] ?? '');
            $hasBackup = $backupName !== '' && $this->backupExists($backupName);
            $this->pushReadinessCheck(
                $checks,
                'rollback_selection',
                $hasBackup ? 'ok' : 'blocker',
                __('Selected backup'),
                $hasBackup ? __('Backup ":backup" is available for rollback.', ['backup' => $backupName]) : __('No valid rollback backup is selected.')
            );
        }

        $blockers = array_values(array_filter($checks, fn (array $check) => $check['level'] === 'blocker'));
        $warnings = array_values(array_filter($checks, fn (array $check) => $check['level'] === 'warning'));
        $okChecks = array_values(array_filter($checks, fn (array $check) => $check['level'] === 'ok'));
        $overall = count($blockers) > 0 ? 'blocked' : (count($warnings) > 0 ? 'warning' : 'ready');

        return [
            'action' => $action,
            'overall' => $overall,
            'summary_label' => $overall === 'blocked' ? __('Blocked') : ($overall === 'warning' ? __('Needs review') : __('Ready')),
            'allow_execute' => count($blockers) === 0,
            'counts' => [
                'total' => count($checks),
                'ok' => count($okChecks),
                'warnings' => count($warnings),
                'blockers' => count($blockers),
            ],
            'checks' => $checks,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'ok_checks' => $okChecks,
        ];
    }

    protected function renderReadinessOutput(array $readiness): string
    {
        $lines = [
            __('Deploy readiness summary'),
            __('Overall status: :status', ['status' => $readiness['summary_label'] ?? __('Unknown')]),
            __('Checks: :ok ok / :warnings warnings / :blockers blockers', [
                'ok' => $readiness['counts']['ok'] ?? 0,
                'warnings' => $readiness['counts']['warnings'] ?? 0,
                'blockers' => $readiness['counts']['blockers'] ?? 0,
            ]),
            '',
        ];

        foreach (($readiness['checks'] ?? []) as $check) {
            $prefix = ($check['level'] ?? 'warning') === 'blocker'
                ? '[BLOCKER]'
                : (($check['level'] ?? 'warning') === 'warning' ? '[WARNING]' : '[OK]');

            $lines[] = $prefix.' '.($check['label'] ?? __('Check')).': '.($check['message'] ?? '');
        }

        return implode("\n", $lines);
    }

    protected function pushReadinessCheck(array &$checks, string $key, string $level, string $label, string $message, array $meta = []): void
    {
        $checks[] = array_merge([
            'key' => $key,
            'level' => in_array($level, ['ok', 'warning', 'blocker'], true) ? $level : 'warning',
            'label' => $label,
            'message' => $message,
        ], $meta);
    }

    protected function directoryHealthLevel(string $path): string
    {
        if (! File::isDirectory($path)) {
            return 'warning';
        }

        return is_writable($path) ? 'ok' : 'warning';
    }

    protected function directoryHealthMessage(string $path, string $label): string
    {
        if (! File::isDirectory($path)) {
            return __(':label does not exist yet. It can still be created later by the deploy flow if permissions allow it.', ['label' => $label]);
        }

        return is_writable($path)
            ? __(':label exists and is writable.', ['label' => $label])
            : __(':label exists but is not writable.', ['label' => $label]);
    }

    protected function diskReadiness(string $path): array
    {
        $freeBytes = @disk_free_space($path);

        if ($freeBytes === false) {
            return ['warning', __('Free disk space could not be measured for the workspace path.'), []];
        }

        $freeMb = (int) floor($freeBytes / 1048576);
        $warningThreshold = max(256, (int) config('deploy.disk_space_warning_mb', 2048));
        $blockerThreshold = max(128, (int) config('deploy.disk_space_blocker_mb', 512));

        if ($freeMb <= $blockerThreshold) {
            return ['blocker', __('Only :mb MB of free disk space remains on the server.', ['mb' => number_format($freeMb)]), ['free_mb' => $freeMb]];
        }

        if ($freeMb <= $warningThreshold) {
            return ['warning', __('Free disk space is getting low (:mb MB remaining).', ['mb' => number_format($freeMb)]), ['free_mb' => $freeMb]];
        }

        return ['ok', __('Free disk space looks healthy (:mb MB remaining).', ['mb' => number_format($freeMb)]), ['free_mb' => $freeMb]];
    }

    protected function normalizeCommand(array $command): array
    {
        $scriptPath = $command[0] ?? '';
        $arguments = array_slice($command, 1);

        if (! $this->isDirectlyExecutable($scriptPath) && File::exists('/bin/bash')) {
            return array_merge(['/bin/bash', $scriptPath], $arguments);
        }

        return $command;
    }

    protected function formatProcessResult(bool $ok, string $status, string $message, ?Process $process, array $extra = [], ?float $startedAt = null): array
    {
        $latestLog = $this->listLogs()[0] ?? null;
        $durationMs = is_null($startedAt) ? null : (int) round((microtime(true) - $startedAt) * 1000);

        return array_merge([
            'ok' => $ok,
            'status' => $status,
            'message' => $message,
            'output' => trim(($process?->getOutput() ?? '')."\n".($process?->getErrorOutput() ?? '')),
            'exit_code' => $process?->getExitCode(),
            'ran_on' => gethostname() ?: php_uname('n'),
            'latest_log_name' => $latestLog['name'] ?? null,
            'latest_log_modified_at' => $latestLog['modified_at'] ?? null,
            'completed_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
        ], $extra);
    }

    protected function resolveLogPath(string $fileName): ?string
    {
        $safeName = basename($fileName);

        if ($safeName === '' || $safeName !== $fileName) {
            return null;
        }

        $path = $this->getLogsDirectory().DIRECTORY_SEPARATOR.$safeName;

        return Str::endsWith($safeName, '.log') ? $path : null;
    }

    protected function sanitizeBackupName(string $backupName): string
    {
        return basename(trim($backupName));
    }

    protected function backupExists(string $backupName): bool
    {
        return File::isDirectory($this->getBackupsDirectory().DIRECTORY_SEPARATOR.$backupName);
    }

    protected function isDirectlyExecutable(string $path): bool
    {
        return $path !== '' && File::exists($path) && is_executable($path);
    }

    protected function concurrentExecutionGuardResult(): ?array
    {
        $lock = $this->currentLockData();

        if (! $lock) {
            return null;
        }

        return [
            'ok' => false,
            'status' => 'busy',
            'message' => __('Another deploy or rollback is already running. Please wait for it to finish before starting a new one.'),
            'output' => __('Running action: :action', ['action' => $lock['action'] ?? __('unknown')]),
            'exit_code' => null,
            'ran_on' => gethostname() ?: php_uname('n'),
        ];
    }

    protected function currentLockData(): ?array
    {
        $path = $this->lockFilePath();

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            File::delete($path);

            return null;
        }

        $createdAt = isset($decoded['created_at']) ? strtotime((string) $decoded['created_at']) : false;
        $ttl = max(60, (int) config('deploy.lock_ttl_seconds', 3600));

        if (! $createdAt || (time() - $createdAt) > $ttl) {
            File::delete($path);

            return null;
        }

        return $decoded;
    }

    protected function storeLock(string $action): void
    {
        $path = $this->lockFilePath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'action' => $action,
            'created_at' => now()->toIso8601String(),
            'server_name' => gethostname() ?: php_uname('n'),
            'workspace_path' => $this->getWorkspacePath(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function clearLock(): void
    {
        File::delete($this->lockFilePath());
    }

    protected function lockFilePath(): string
    {
        return (string) config('deploy.lock_file', storage_path('app/deploy-center/deploy.lock'));
    }

    protected function gitSummary(): array
    {
        if (! File::isDirectory($this->getWorkspacePath().DIRECTORY_SEPARATOR.'.git')) {
            return [
                'available' => false,
                'branch' => '',
                'full_hash' => '',
                'short_hash' => '',
                'subject' => '',
                'author_name' => '',
                'authored_at' => null,
                'dirty' => false,
            ];
        }

        $branch = $this->runGitCommand(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        $fullHash = $this->runGitCommand(['git', 'rev-parse', 'HEAD']);
        $shortHash = $this->runGitCommand(['git', 'rev-parse', '--short', 'HEAD']);
        $subject = $this->runGitCommand(['git', 'log', '-1', '--pretty=%s']);
        $authorName = $this->runGitCommand(['git', 'log', '-1', '--pretty=%an']);
        $authoredAt = $this->runGitCommand(['git', 'log', '-1', '--pretty=%aI']);
        $dirty = trim($this->runGitCommand(['git', 'status', '--porcelain'])) !== '';

        return [
            'available' => $shortHash !== '',
            'branch' => $branch,
            'full_hash' => $fullHash,
            'short_hash' => $shortHash,
            'subject' => $subject,
            'author_name' => $authorName,
            'authored_at' => $authoredAt !== '' ? $authoredAt : null,
            'dirty' => $dirty,
        ];
    }

    protected function runGitCommand(array $command): string
    {
        try {
            $process = new Process($command, $this->getWorkspacePath(), null, null, 15);
            $process->run();

            if (! $process->isSuccessful()) {
                return '';
            }

            return trim($process->getOutput());
        } catch (\Throwable $exception) {
            return '';
        }
    }
}
