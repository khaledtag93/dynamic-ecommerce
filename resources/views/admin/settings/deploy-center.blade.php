@extends('layouts.admin')

@section('title', __('Deploy Center') . ' | Admin')

@section('content')
<x-admin.page-header
    :kicker="__('Phase 6.8')"
    :title="__('Deploy Monitoring Dashboard')"
    :description="__('Remote-only deploy dashboard with readiness intelligence, live monitoring KPIs, deploy history, rollback visibility, and log review.')"
    :breadcrumbs="[
        ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('Channels & setup')],
        ['label' => __('Deploy Center'), 'current' => true],
    ]"
>
    <div class="d-flex flex-wrap gap-2">
        <a href="#deploy-logs" class="btn btn-outline-dark btn-text-icon">
            <i class="mdi mdi-text-box-search-outline"></i><span>{{ __('Logs') }}</span>
        </a>
        <a href="{{ route('admin.notifications.index') }}" class="btn btn-light border btn-text-icon">
            <i class="mdi mdi-bell-outline"></i><span>{{ __('Inbox') }}</span>
        </a>
        <a href="{{ route('admin.settings.deploy-center', array_filter(['log' => $selected_log])) }}" class="btn btn-light border btn-text-icon">
            <i class="mdi mdi-refresh"></i><span>{{ __('Refresh') }}</span>
        </a>
    </div>
</x-admin.page-header>

@php
    $result = session('deploy_center_result');
    $gitAvailable = (bool) ($git['available'] ?? false);
    $gitDirty = (bool) ($git['dirty'] ?? false);
    $actionMode = (string) ($result['action_mode'] ?? 'execute');
    $readiness = is_array($readiness ?? null) ? $readiness : [];
    $readinessOverall = (string) ($readiness['overall'] ?? 'warning');
    $readinessLabel = (string) ($readiness['summary_label'] ?? __('Needs review'));
    $readinessCounts = [
        'ok' => (int) ($readiness['counts']['ok'] ?? 0),
        'warnings' => (int) ($readiness['counts']['warnings'] ?? 0),
        'blockers' => (int) ($readiness['counts']['blockers'] ?? 0),
        'total' => (int) ($readiness['counts']['total'] ?? 0),
    ];
    $readinessAllowExecute = (bool) ($readiness['allow_execute'] ?? false);
    $readinessTone = $readinessOverall === 'blocked' ? 'danger' : ($readinessOverall === 'warning' ? 'warning' : 'success');
    $resultReadiness = is_array($result['readiness'] ?? null) ? $result['readiness'] : $readiness;
    $deployButtonDisabled = ! $enabled || ! $remote_available || ! $deploy_script_exists;
    $rollbackButtonDisabled = ! $enabled || ! $remote_available || ! $rollback_script_exists || count($backups) === 0;
    $monitoring = is_array($monitoring ?? null) ? $monitoring : [];
    $monitoringTotals = is_array($monitoring['totals'] ?? null) ? $monitoring['totals'] : [];
    $monitoringReadiness = is_array($monitoring['readiness'] ?? null) ? $monitoring['readiness'] : [];
    $recentMonitoringActions = $monitoring['recent_actions'] ?? [];
    $healthScore = (int) ($monitoringReadiness['score'] ?? 0);
    $successRate = $monitoring['success_rate'] ?? null;
    $avgDurationHuman = (string) ($monitoring['avg_duration_human'] ?? __('n/a'));
    $lastLiveDeploy = $monitoring['last_live_deploy'] ?? null;
    $lastRollback = $monitoring['last_rollback'] ?? null;
    $lastSuccess = $monitoring['last_success'] ?? null;
    $lastFailure = $monitoring['last_failure'] ?? null;
    $statusCards = [
        [
            'label' => __('Readiness'),
            'value' => $readinessLabel,
            'icon' => 'mdi-shield-check-outline',
            'tone' => $readinessTone,
        ],
        [
            'label' => __('Deploy script'),
            'value' => $deploy_script_exists ? __('Ready') : __('Missing'),
            'icon' => 'mdi-rocket-launch-outline',
            'tone' => $deploy_script_exists ? 'success' : 'danger',
        ],
        [
            'label' => __('Rollback script'),
            'value' => $rollback_script_exists ? __('Ready') : __('Missing'),
            'icon' => 'mdi-backup-restore',
            'tone' => $rollback_script_exists ? 'success' : 'danger',
        ],
        [
            'label' => __('Git head'),
            'value' => $gitAvailable ? (($git['short_hash'] ?? '') ?: __('Available')) : __('Unavailable'),
            'icon' => 'mdi-source-commit',
            'tone' => $gitAvailable ? ($gitDirty ? 'warning' : 'success') : 'danger',
        ],
        [
            'label' => __('Remote executor'),
            'value' => $remote_available ? __('Reachable') : __('Unavailable'),
            'icon' => 'mdi-cloud-lock-outline',
            'tone' => $remote_available ? 'success' : 'danger',
        ],
        [
            'label' => __('Logs'),
            'value' => number_format(count($logs)),
            'icon' => 'mdi-text-box-search-outline',
            'tone' => count($logs) > 0 ? 'info' : 'warning',
        ],
        [
            'label' => __('Backups'),
            'value' => number_format(count($backups)),
            'icon' => 'mdi-folder-multiple-outline',
            'tone' => count($backups) > 0 ? 'info' : 'warning',
        ],
    ];

    $toneClasses = [
        'success' => 'badge-soft-success',
        'danger' => 'badge-soft-danger',
        'warning' => 'badge-soft-warning',
        'info' => 'badge-soft-primary',
    ];
@endphp

<div class="admin-page-shell settings-page deploy-center-page">
    <div class="admin-card mb-4">
        <div class="admin-card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h4 class="mb-1">{{ __('Deploy workspace') }}</h4>
                <div class="text-muted small">{{ __('Monitor readiness, review the current git head, then launch live actions only when blockers are cleared.') }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="admin-chip">{{ __('Readiness') }}: {{ $readinessLabel }}</span>
                <span class="admin-chip">{{ __('Logs') }}: {{ number_format(count($logs)) }}</span>
                <span class="admin-chip">{{ __('Backups') }}: {{ number_format(count($backups)) }}</span>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="admin-kpi-card h-100 deploy-monitor-card">
                <div class="admin-kpi-icon"><i class="mdi mdi-heart-pulse"></i></div>
                <div class="admin-kpi-label">{{ __('Deploy health score') }}</div>
                <div class="admin-kpi-value admin-stat-value-sm">{{ number_format($healthScore) }}/100</div>
                <span class="badge {{ $toneClasses[$readinessTone] ?? 'badge-soft-primary' }} mt-2">{{ $monitoringReadiness['label'] ?? $readinessLabel }}</span>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="admin-kpi-card h-100 deploy-monitor-card">
                <div class="admin-kpi-icon"><i class="mdi mdi-chart-line"></i></div>
                <div class="admin-kpi-label">{{ __('Success rate') }}</div>
                <div class="admin-kpi-value admin-stat-value-sm">{{ is_null($successRate) ? __('n/a') : number_format((float) $successRate, 1).'%' }}</div>
                <span class="badge badge-soft-primary mt-2">{{ number_format((int) ($monitoringTotals['actions'] ?? 0)) }} {{ __('tracked actions') }}</span>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="admin-kpi-card h-100 deploy-monitor-card">
                <div class="admin-kpi-icon"><i class="mdi mdi-timer-outline"></i></div>
                <div class="admin-kpi-label">{{ __('Average duration') }}</div>
                <div class="admin-kpi-value admin-stat-value-sm">{{ $avgDurationHuman }}</div>
                <span class="badge badge-soft-primary mt-2">{{ number_format((int) ($monitoringTotals['live_actions'] ?? 0)) }} {{ __('live actions') }}</span>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="admin-kpi-card h-100 deploy-monitor-card">
                <div class="admin-kpi-icon"><i class="mdi mdi-shield-alert-outline"></i></div>
                <div class="admin-kpi-label">{{ __('Open readiness risks') }}</div>
                <div class="admin-kpi-value admin-stat-value-sm">{{ number_format((int) ($monitoringReadiness['blockers'] ?? 0)) }} / {{ number_format((int) ($monitoringReadiness['warnings'] ?? 0)) }}</div>
                <span class="badge badge-soft-warning mt-2">{{ __('Blockers / warnings') }}</span>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h3 class="h5 mb-1">{{ __('Deploy monitoring overview') }}</h3>
                            <p class="text-muted mb-0">{{ __('Follow the latest live deploys, rollbacks, dry runs, and operating signals from one dashboard.') }}</p>
                        </div>
                        <span class="badge badge-soft-primary">{{ count($recentMonitoringActions) }} {{ __('recent actions') }}</span>
                    </div>

                    <div class="deploy-monitor-grid mb-4">
                        <div class="deploy-monitor-item">
                            <span class="text-muted small">{{ __('Total actions') }}</span>
                            <strong>{{ number_format((int) ($monitoringTotals['actions'] ?? 0)) }}</strong>
                        </div>
                        <div class="deploy-monitor-item">
                            <span class="text-muted small">{{ __('Successful actions') }}</span>
                            <strong>{{ number_format((int) ($monitoringTotals['successes'] ?? 0)) }}</strong>
                        </div>
                        <div class="deploy-monitor-item">
                            <span class="text-muted small">{{ __('Needs review') }}</span>
                            <strong>{{ number_format((int) ($monitoringTotals['failures'] ?? 0)) }}</strong>
                        </div>
                        <div class="deploy-monitor-item">
                            <span class="text-muted small">{{ __('Deploy / rollback') }}</span>
                            <strong>{{ number_format((int) ($monitoringTotals['deploys'] ?? 0)) }} / {{ number_format((int) ($monitoringTotals['rollbacks'] ?? 0)) }}</strong>
                        </div>
                    </div>

                    @if(count($recentMonitoringActions) > 0)
                        <div class="deploy-monitor-timeline">
                            @foreach($recentMonitoringActions as $activity)
                                <div class="deploy-monitor-row">
                                    <div class="deploy-monitor-row__main">
                                        <div class="fw-semibold">{{ $activity['description'] }}</div>
                                        <div class="text-muted small">
                                            {{ ucfirst($activity['action']) }} · {{ $activity['admin_name'] }} · {{ $activity['created_at']->diffForHumans() }}
                                            @if(!empty($activity['meta']['action_mode']))
                                                · {{ $activity['meta']['action_mode'] === 'dry_run' ? __('Dry run') : __('Live action') }}
                                            @endif
                                            @if(!empty($activity['duration_human']) && $activity['duration_human'] !== __('n/a'))
                                                · {{ __('Duration') }}: {{ $activity['duration_human'] }}
                                            @endif
                                            @if(!empty($activity['meta']['git_branch']) || !empty($activity['meta']['latest_commit']))
                                                · {{ $activity['meta']['git_branch'] ?? __('branch unknown') }} / {{ $activity['meta']['latest_commit'] ?? __('commit unknown') }}
                                            @endif
                                        </div>
                                    </div>
                                    <span class="badge {{ !empty($activity['is_success']) ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ !empty($activity['is_success']) ? __('Success') : __('Needs review') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-4 border border-dashed p-4 text-center text-muted">{{ __('No tracked deploy actions were found yet in the admin activity log.') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h3 class="h5 mb-1">{{ __('Operational signals') }}</h3>
                            <p class="text-muted mb-0">{{ __('Quick visibility into the most important moments before you press deploy.') }}</p>
                        </div>
                        <span class="badge {{ $toneClasses[$readinessTone] ?? 'badge-soft-primary' }}">{{ $monitoringReadiness['label'] ?? $readinessLabel }}</span>
                    </div>

                    <div class="deploy-signal-list">
                        <div class="deploy-signal-card">
                            <span class="text-muted small">{{ __('Last live deploy') }}</span>
                            <strong>{{ $lastLiveDeploy && !empty($lastLiveDeploy['created_at']) ? $lastLiveDeploy['created_at']->diffForHumans() : __('No live deploy yet') }}</strong>
                            <small>{{ $lastLiveDeploy['duration_human'] ?? __('No duration yet') }}</small>
                        </div>
                        <div class="deploy-signal-card">
                            <span class="text-muted small">{{ __('Last rollback') }}</span>
                            <strong>{{ $lastRollback && !empty($lastRollback['created_at']) ? $lastRollback['created_at']->diffForHumans() : __('No rollback yet') }}</strong>
                            <small>{{ $lastRollback['action_mode_label'] ?? __('Waiting') }}</small>
                        </div>
                        <div class="deploy-signal-card">
                            <span class="text-muted small">{{ __('Last successful action') }}</span>
                            <strong>{{ $lastSuccess && !empty($lastSuccess['created_at']) ? $lastSuccess['created_at']->diffForHumans() : __('No success yet') }}</strong>
                            <small>{{ $lastSuccess['description'] ?? __('No successful deploy activity is recorded yet.') }}</small>
                        </div>
                        <div class="deploy-signal-card {{ !empty($monitoring['active_lock']) ? 'is-warning' : '' }}">
                            <span class="text-muted small">{{ __('Current lock state') }}</span>
                            <strong>{{ !empty($monitoring['active_lock']) ? __('Busy') : __('Idle') }}</strong>
                            <small>{{ !empty($monitoring['active_lock']) ? __('A deploy lock is active right now.') : __('No running deploy lock was detected.') }}</small>
                        </div>
                        <div class="deploy-signal-card {{ $lastFailure ? 'is-warning' : '' }}">
                            <span class="text-muted small">{{ __('Last action needing review') }}</span>
                            <strong>{{ $lastFailure && !empty($lastFailure['created_at']) ? $lastFailure['created_at']->diffForHumans() : __('None') }}</strong>
                            <small>{{ $lastFailure['description'] ?? __('No recent warning or failed action was recorded.') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        @foreach($statusCards as $card)
            <div class="col-12 col-md-6 col-xl-4">
                <div class="admin-kpi-card h-100">
                    <div class="admin-kpi-icon"><i class="mdi {{ $card['icon'] }}"></i></div>
                    <div class="admin-kpi-label">{{ $card['label'] }}</div>
                    <div class="admin-kpi-value admin-stat-value-sm">{{ $card['value'] }}</div>
                    <span class="badge {{ $toneClasses[$card['tone']] ?? 'badge-soft-primary' }} mt-2">{{ __('Safety status') }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h3 class="h5 mb-1">{{ __('Remote deploy controls') }}</h3>
                            <p class="text-muted mb-0">{{ __('All actions below are sent as protected remote requests to the server executor. Typed confirmation is required before a live deploy or rollback can continue.') }}</p>
                        </div>
                        <span class="badge {{ $toneClasses[$readinessTone] ?? 'badge-soft-primary' }}">{{ $readinessLabel }}</span>
                    </div>

                    @php
                        $remoteNeedsConfig = blank($remote_base_url);
                    @endphp

                    @if($remoteNeedsConfig)
                        <div class="alert alert-warning border-0 rounded-4 mb-4">
                            <div class="fw-semibold mb-2">{{ __('Configure a real remote executor URL to enable deploy actions.') }}</div>
                            <div class="small text-muted mb-2">{{ __('Set DEPLOY_REMOTE_BASE_URL to your production domain or executor host, then refresh this page.') }}</div>
                            <div class="small"><strong>{{ __('Example') }}:</strong> <code>https://tag-marketplace.com</code></div>
                        </div>
                    @endif

                    @if(is_array($active_lock ?? null) && !empty($active_lock))
                        <div class="alert alert-info border-0 rounded-4 mb-4">
                            <div class="fw-semibold mb-1">{{ __('Another deploy action is currently marked as running.') }}</div>
                            <div class="small text-muted">{{ __('Action') }}: <strong>{{ ucfirst($active_lock['action'] ?? __('unknown')) }}</strong> · {{ __('Started at') }}: {{ !empty($active_lock['created_at']) ? \Illuminate\Support\Carbon::parse($active_lock['created_at'])->format('Y-m-d H:i:s') : __('Unknown') }}</div>
                        </div>
                    @endif

                    @if($readinessOverall === 'blocked')
                        <div class="alert alert-danger border-0 rounded-4 mb-4 deploy-readiness-alert">
                            <div class="fw-semibold mb-1">{{ __('Live deploy is currently blocked.') }}</div>
                            <div class="small mb-0">{{ __('Resolve the blocker items below first, or run dry-run mode to review the full readiness report safely.') }}</div>
                        </div>
                    @elseif($readinessOverall === 'warning')
                        <div class="alert alert-warning border-0 rounded-4 mb-4 deploy-readiness-alert">
                            <div class="fw-semibold mb-1">{{ __('Deploy is allowed, but it needs review.') }}</div>
                            <div class="small mb-0">{{ __('Warnings were found in the readiness checks. Dry-run is recommended before any live action.') }}</div>
                        </div>
                    @endif

                    <div class="deploy-readiness-panel mb-4">
                        <div class="deploy-git-head mb-3">
                            <div>
                                <div class="deploy-section-title">{{ __('Deploy readiness summary') }}</div>
                                <p class="text-muted mb-0">{{ __('This panel checks paths, environment, storage, queue basics, failed jobs, disk space, and git health before a live action can continue.') }}</p>
                            </div>
                            <span class="badge {{ $toneClasses[$readinessTone] ?? 'badge-soft-primary' }}">{{ $readinessLabel }}</span>
                        </div>

                        <div class="deploy-readiness-stats">
                            <div class="deploy-readiness-stat">
                                <span class="text-muted small">{{ __('OK') }}</span>
                                <strong>{{ number_format($readinessCounts['ok']) }}</strong>
                            </div>
                            <div class="deploy-readiness-stat is-warning">
                                <span class="text-muted small">{{ __('Warnings') }}</span>
                                <strong>{{ number_format($readinessCounts['warnings']) }}</strong>
                            </div>
                            <div class="deploy-readiness-stat is-danger">
                                <span class="text-muted small">{{ __('Blockers') }}</span>
                                <strong>{{ number_format($readinessCounts['blockers']) }}</strong>
                            </div>
                            <div class="deploy-readiness-stat">
                                <span class="text-muted small">{{ __('Checks') }}</span>
                                <strong>{{ number_format($readinessCounts['total']) }}</strong>
                            </div>
                        </div>

                        @if($readinessCounts['blockers'] > 0 || $readinessCounts['warnings'] > 0)
                            <div class="row g-3 mt-1">
                                @if($readinessCounts['blockers'] > 0)
                                    <div class="col-12 col-lg-6">
                                        <div class="deploy-readiness-list is-danger">
                                            <div class="deploy-readiness-list__title">{{ __('Blockers') }}</div>
                                            @foreach(($readiness['blockers'] ?? []) as $check)
                                                <div class="deploy-readiness-item is-danger">
                                                    <strong>{{ $check['label'] }}</strong>
                                                    <small>{{ $check['message'] }}</small>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @if($readinessCounts['warnings'] > 0)
                                    <div class="col-12 col-lg-6">
                                        <div class="deploy-readiness-list is-warning">
                                            <div class="deploy-readiness-list__title">{{ __('Warnings') }}</div>
                                            @foreach(($readiness['warnings'] ?? []) as $check)
                                                <div class="deploy-readiness-item is-warning">
                                                    <strong>{{ $check['label'] }}</strong>
                                                    <small>{{ $check['message'] }}</small>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="deploy-readiness-list mt-3">
                                <div class="deploy-readiness-item is-success">
                                    <strong>{{ __('All readiness checks passed.') }}</strong>
                                    <small>{{ __('The server looks ready for a live deploy, while dry-run remains available for a final preview.') }}</small>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="rounded-4 border p-3 bg-light-subtle">
                                <div class="small text-muted mb-1">{{ __('Remote endpoint') }}</div>
                                <code>{{ $remote_base_url }}</code>
                                @if($server_name)
                                    <div class="small text-muted mt-2">{{ __('Remote server') }}: <strong>{{ $server_name }}</strong></div>
                                @endif
                                @if($generated_at)
                                    <div class="small text-muted mt-1">{{ __('Last sync') }}: {{ $generated_at->format('Y-m-d H:i:s') }}</div>
                                @endif
                                @if($remote_message)
                                    <div class="small mt-2 {{ $remote_available ? 'text-success' : 'text-danger' }}">{{ $remote_message }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="rounded-4 border p-3 bg-light-subtle">
                                <div class="small text-muted mb-1">{{ __('Workspace path') }}</div>
                                <code>{{ $workspace_path }}</code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="rounded-4 border p-3 bg-light-subtle h-100">
                                <div class="small text-muted mb-1">{{ __('Logs path') }}</div>
                                <code>{{ $logs_path }}</code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="rounded-4 border p-3 bg-light-subtle h-100">
                                <div class="small text-muted mb-1">{{ __('Backups path') }}</div>
                                <code>{{ $backups_path }}</code>
                            </div>
                        </div>
                    </div>

                    <div class="deploy-git-panel mb-4">
                        <div class="deploy-git-head">
                            <div>
                                <div class="deploy-section-title">{{ __('Current git commit before deploy') }}</div>
                                <p class="text-muted mb-0">{{ __('Review the exact branch and commit shown by the remote workspace before running a live action.') }}</p>
                            </div>
                            <span class="badge {{ $gitAvailable ? ($gitDirty ? 'badge-soft-warning' : 'badge-soft-success') : 'badge-soft-danger' }}">{{ $gitAvailable ? ($gitDirty ? __('Uncommitted changes') : __('Clean working tree')) : __('Git unavailable') }}</span>
                        </div>

                        @if($gitAvailable)
                            <div class="deploy-git-grid">
                                <div class="deploy-git-item">
                                    <span class="text-muted small">{{ __('Branch') }}</span>
                                    <strong>{{ $git['branch'] ?: __('Unknown') }}</strong>
                                </div>
                                <div class="deploy-git-item">
                                    <span class="text-muted small">{{ __('Commit') }}</span>
                                    <code>{{ $git['short_hash'] ?: __('Unknown') }}</code>
                                </div>
                                <div class="deploy-git-item deploy-git-item-wide">
                                    <span class="text-muted small">{{ __('Message') }}</span>
                                    <strong>{{ $git['subject'] ?: __('No commit message available.') }}</strong>
                                </div>
                                <div class="deploy-git-item">
                                    <span class="text-muted small">{{ __('Author') }}</span>
                                    <strong>{{ $git['author_name'] ?: __('Unknown') }}</strong>
                                </div>
                                <div class="deploy-git-item">
                                    <span class="text-muted small">{{ __('Authored at') }}</span>
                                    <strong>{{ $git['authored_at'] ? $git['authored_at']->format('Y-m-d H:i:s') : __('Unknown') }}</strong>
                                </div>
                            </div>
                        @else
                            <div class="rounded-4 border border-dashed p-4 text-center text-muted">{{ __('Git metadata was not available from the remote workspace.') }}</div>
                        @endif
                    </div>

                    <div class="deploy-action-grid">
                        <form method="POST" action="{{ route('admin.settings.deploy-center.deploy') }}" class="deploy-action-card" data-confirm-title="{{ __('Run deploy now?') }}" data-confirm-message="{{ __('This will execute the server deploy script immediately.') }}" data-confirm-subtitle="{{ __('Type DEPLOY to unlock the live action, or keep dry-run enabled for a safe preview only.') }}" data-confirm-ok="{{ __('Continue') }}" data-confirm-cancel="{{ __('Keep reviewing') }}" data-confirm-input-label="{{ __('Type DEPLOY to continue') }}" data-confirm-input-placeholder="DEPLOY" data-confirm-input-expected="DEPLOY" data-confirm-input-target="input[name='confirm_phrase']">
                            @csrf
                            <input type="hidden" name="confirm_phrase" value="">
                            <div class="deploy-action-copy">
                                <span class="deploy-action-icon"><i class="mdi mdi-rocket-launch-outline"></i></span>
                                <div>
                                    <h4>{{ __('Deploy latest version') }}</h4>
                                    <p>{{ __('Runs deploy.sh from the configured workspace. Enable dry-run to preview safety checks without changing the server.') }}</p>
                                </div>
                            </div>
                            <div class="d-grid gap-3 w-100">
                                <label class="deploy-toggle-row">
                                    <input type="checkbox" name="action_mode" value="dry_run" checked>
                                    <span>
                                        <strong>{{ __('Dry-run mode') }}</strong>
                                        <small class="d-block text-muted">{{ __('Recommended first: validate paths, lock status, git head, and executor readiness without running deploy.sh.') }}</small>
                                    </span>
                                </label>
                                <button type="submit" class="btn btn-primary" @disabled($deployButtonDisabled)>{{ __('Deploy / dry-run') }}</button>
                                @if($readinessCounts['blockers'] > 0)
                                    <div class="small text-danger">{{ __('Live deploy will stop automatically until the blocker checks are resolved.') }}</div>
                                @endif
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.settings.deploy-center.rollback') }}" class="deploy-action-card" data-confirm-title="{{ __('Run rollback now?') }}" data-confirm-message="{{ __('This will restore the selected backup on the server.') }}" data-confirm-subtitle="{{ __('Type ROLLBACK to unlock the live action, or keep dry-run enabled to validate the selected backup safely first.') }}" data-confirm-ok="{{ __('Continue') }}" data-confirm-cancel="{{ __('Keep reviewing') }}" data-confirm-input-label="{{ __('Type ROLLBACK to continue') }}" data-confirm-input-placeholder="ROLLBACK" data-confirm-input-expected="ROLLBACK" data-confirm-input-target="input[name='confirm_phrase']">
                            @csrf
                            <input type="hidden" name="confirm_phrase" value="">
                            <div class="deploy-action-copy">
                                <span class="deploy-action-icon"><i class="mdi mdi-backup-restore"></i></span>
                                <div>
                                    <h4>{{ __('Rollback to backup') }}</h4>
                                    <p>{{ __('Choose a backup folder created by the deploy flow, then run rollback.sh safely from this page.') }}</p>
                                </div>
                            </div>
                            <div class="d-grid gap-3 w-100">
                                <select name="backup_name" class="form-select" @disabled(! $enabled || ! $remote_available || ! $rollback_script_exists || count($backups) === 0)>
                                    <option value="">{{ __('Select backup') }}</option>
                                    @foreach($backups as $backup)
                                        <option value="{{ $backup['name'] }}">{{ $backup['name'] }} — {{ $backup['modified_at']->diffForHumans() }}</option>
                                    @endforeach
                                </select>
                                <label class="deploy-toggle-row">
                                    <input type="checkbox" name="action_mode" value="dry_run" checked>
                                    <span>
                                        <strong>{{ __('Dry-run mode') }}</strong>
                                        <small class="d-block text-muted">{{ __('Validate the selected rollback point without executing rollback.sh.') }}</small>
                                    </span>
                                </label>
                                <button type="submit" class="btn btn-outline-warning" @disabled($rollbackButtonDisabled)>{{ __('Rollback / dry-run') }}</button>
                            </div>
                        </form>
                    </div>

                    <div class="rounded-4 border p-3 bg-light-subtle mt-4">
                        <div class="small text-muted mb-1">{{ __('Health check URL') }}</div>
                        <code>{{ $healthcheck_url }}</code>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h3 class="h5 mb-1">{{ __('Recent backups') }}</h3>
                            <p class="text-muted mb-0">{{ __('Use the latest successful backup when you need a quick restore point.') }}</p>
                        </div>
                        <span class="badge badge-soft-primary">{{ count($backups) }} {{ __('items') }}</span>
                    </div>

                    @if(count($backups) > 0)
                        <div class="deploy-list">
                            @foreach($backups as $backup)
                                <div class="admin-list-row align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ $backup['name'] }}</div>
                                        <div class="text-muted small">{{ $backup['modified_at']->format('Y-m-d H:i') }} · {{ $backup['modified_at']->diffForHumans() }}</div>
                                    </div>
                                    <span class="badge badge-soft-warning">{{ __('Rollback point') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-4 border border-dashed p-4 text-center text-muted">{{ __('No backup folders were found yet in the configured backups path.') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($result)
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">{{ __('Latest command output') }}</h3>
                        <p class="text-muted mb-0">{{ __('The newest deploy, rollback, or dry-run response is kept here after each action.') }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge {{ ($result['ok'] ?? false) ? 'badge-soft-success' : 'badge-soft-danger' }}">{{ ucfirst($result['status'] ?? 'unknown') }}</span>
                        <span class="badge badge-soft-primary">{{ $actionMode === 'dry_run' ? __('Dry run') : __('Live action') }}</span>
                        @if(is_array($resultReadiness ?? null) && !empty($resultReadiness))
                            @php($resultReadinessTone = ($resultReadiness['overall'] ?? 'warning') === 'blocked' ? 'badge-soft-danger' : (($resultReadiness['overall'] ?? 'warning') === 'warning' ? 'badge-soft-warning' : 'badge-soft-success'))
                            <span class="badge {{ $resultReadinessTone }}">{{ $resultReadiness['summary_label'] ?? __('Needs review') }}</span>
                        @endif
                        @if(!empty($result['duration_ms']))
                            <span class="badge badge-soft-primary">{{ __('Duration') }}: {{ $result['duration_ms'] < 1000 ? number_format($result['duration_ms']).' ms' : number_format($result['duration_ms'] / 1000, 1).' s' }}</span>
                        @endif
                        @if(!is_null($result['exit_code'] ?? null))
                            <span class="badge badge-soft-primary">{{ __('Exit code') }}: {{ $result['exit_code'] }}</span>
                        @endif
                    </div>
                </div>

                <pre class="deploy-console mb-0">{{ trim(($result['message'] ?? '') . "\n\n" . ($result['output'] ?? '')) }}</pre>
            </div>
        </div>
    @endif

    @if(count($recent_activity ?? []) > 0)
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">{{ __('Deploy activity') }}</h3>
                        <p class="text-muted mb-0">{{ __('Track recent deploy, rollback, and dry-run actions performed from the admin area.') }}</p>
                    </div>
                    <span class="badge badge-soft-primary">{{ count($recent_activity) }} {{ __('entries') }}</span>
                </div>

                <div class="deploy-list">
                    @foreach($recent_activity as $activity)
                        <div class="admin-list-row align-items-start">
                            <div>
                                <div class="fw-semibold">{{ $activity['description'] }}</div>
                                <div class="text-muted small">
                                    {{ ucfirst($activity['action']) }} · {{ $activity['admin_name'] }} · {{ $activity['created_at']->diffForHumans() }}
                                    @if(!empty($activity['meta']['action_mode']))
                                        · {{ $activity['meta']['action_mode'] === 'dry_run' ? __('Dry run') : __('Live action') }}
                                    @endif
                                    @if(!empty($activity['meta']['git_branch']) || !empty($activity['meta']['latest_commit']))
                                        · {{ $activity['meta']['git_branch'] ?? __('branch unknown') }} / {{ $activity['meta']['latest_commit'] ?? __('commit unknown') }}
                                    @endif
                                    @if(!empty($activity['duration_human']) && $activity['duration_human'] !== __('n/a'))
                                        · {{ __('Duration') }}: {{ $activity['duration_human'] }}
                                    @endif
                                    @if(!empty($activity['meta']['latest_log_name']))
                                        · {{ $activity['meta']['latest_log_name'] }}
                                    @endif
                                </div>
                            </div>
                            <span class="badge {{ !empty($activity['meta']['ok']) ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ !empty($activity['meta']['ok']) ? __('Success') : __('Needs review') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="admin-card" id="deploy-logs">
        <div class="admin-card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h3 class="h5 mb-1">{{ __('Deployment logs') }}</h3>
                    <p class="text-muted mb-0">{{ __('Browse the saved deploy logs and inspect the selected file inside the dashboard.') }}</p>
                </div>
                <span class="badge badge-soft-primary">{{ count($logs) }} {{ __('logs') }}</span>
            </div>

            <div class="row g-4">
                <div class="col-12 col-xl-4">
                    @if(count($logs) > 0)
                        <div class="deploy-log-list">
                            @foreach($logs as $log)
                                <a href="{{ route('admin.settings.deploy-center', ['log' => $log['name']]) }}" class="admin-search-item deploy-log-link {{ $selected_log === $log['name'] ? 'is-active' : '' }}">
                                    <span>
                                        <span class="d-block fw-semibold">{{ $log['name'] }}</span>
                                        <small class="text-muted">{{ $log['modified_at']->format('Y-m-d H:i') }} · {{ number_format(max(1, (int) ceil($log['size'] / 1024))) }} KB</small>
                                    </span>
                                    <i class="mdi mdi-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-4 border border-dashed p-4 text-center text-muted">{{ __('No deploy logs were found yet in the configured logs path.') }}</div>
                    @endif
                </div>
                <div class="col-12 col-xl-8">
                    <div class="deploy-console-wrap">
                        @if($selected_log && $selected_log_content)
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <div class="fw-semibold">{{ $selected_log }}</div>
                                    <div class="text-muted small">{{ __('Showing the selected log file content.') }}</div>
                                </div>
                            </div>
                            <pre class="deploy-console mb-0">{{ $selected_log_content }}</pre>
                        @else
                            <div class="rounded-4 border border-dashed p-4 text-center text-muted">{{ __('Pick a log from the list to preview it here.') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.deploy-center-page .admin-kpi-card {
    border: 1px solid var(--admin-border);
    border-radius: 1.15rem;
    background: linear-gradient(180deg, var(--admin-surface), color-mix(in srgb, var(--admin-surface-alt) 22%, white));
    box-shadow: 0 16px 35px color-mix(in srgb, var(--admin-text) 4%, transparent);
}
.deploy-monitor-card {
    min-height: 100%;
}
.deploy-monitor-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .85rem;
}
.deploy-monitor-item {
    display: grid;
    gap: .3rem;
    padding: .9rem 1rem;
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 96%, white), color-mix(in srgb, var(--admin-surface-alt) 15%, white));
}
.deploy-monitor-item strong {
    font-size: 1.05rem;
}
.deploy-monitor-timeline,
.deploy-signal-list {
    display: grid;
    gap: .8rem;
}
.deploy-monitor-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    padding: .95rem 1rem;
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    background: color-mix(in srgb, var(--admin-surface) 95%, white);
}
.deploy-monitor-row__main {
    min-width: 0;
}
.deploy-signal-card {
    display: grid;
    gap: .3rem;
    padding: .95rem 1rem;
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 96%, white), color-mix(in srgb, var(--admin-surface-alt) 14%, white));
}
.deploy-signal-card strong {
    font-size: 1rem;
}
.deploy-signal-card small {
    color: var(--admin-muted);
    line-height: 1.55;
}
.deploy-signal-card.is-warning {
    border-color: color-mix(in srgb, #f59e0b 30%, white);
}

.deploy-action-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
.deploy-action-card {
    display: grid;
    gap: 1rem;
    align-content: space-between;
    min-height: 100%;
    padding: 1rem;
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 96%, white), color-mix(in srgb, var(--admin-surface-alt) 18%, white));
}
.deploy-action-copy {
    display: flex;
    align-items: flex-start;
    gap: .9rem;
}
.deploy-action-copy h4 {
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: .35rem;
}
.deploy-action-copy p {
    margin: 0;
    color: var(--admin-muted);
    line-height: 1.6;
}
.deploy-action-icon {
    width: 3rem;
    height: 3rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    background: var(--admin-primary-soft);
    color: var(--admin-primary-dark);
    font-size: 1.35rem;
    flex: 0 0 auto;
}
.deploy-toggle-row {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .9rem 1rem;
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    background: color-mix(in srgb, var(--admin-surface-alt) 18%, white);
}
.deploy-toggle-row input {
    margin-top: .2rem;
}

.deploy-readiness-alert {
    box-shadow: 0 12px 28px color-mix(in srgb, var(--admin-text) 6%, transparent);
}
.deploy-readiness-panel {
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    padding: 1rem;
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 96%, white), color-mix(in srgb, var(--admin-surface-alt) 18%, white));
}
.deploy-readiness-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .85rem;
}
.deploy-readiness-stat {
    display: grid;
    gap: .25rem;
    padding: .85rem .95rem;
    border-radius: .9rem;
    border: 1px solid var(--admin-border);
    background: rgba(255,255,255,.72);
}
.deploy-readiness-stat strong {
    font-size: 1.05rem;
}
.deploy-readiness-stat.is-warning {
    border-color: color-mix(in srgb, #f59e0b 35%, white);
}
.deploy-readiness-stat.is-danger {
    border-color: color-mix(in srgb, #ef4444 35%, white);
}
.deploy-readiness-list {
    display: grid;
    gap: .75rem;
    padding: .9rem;
    border-radius: 1rem;
    border: 1px solid var(--admin-border);
    background: rgba(255,255,255,.68);
}
.deploy-readiness-list.is-warning {
    border-color: color-mix(in srgb, #f59e0b 30%, white);
}
.deploy-readiness-list.is-danger {
    border-color: color-mix(in srgb, #ef4444 30%, white);
}
.deploy-readiness-list__title {
    font-size: .95rem;
    font-weight: 800;
}
.deploy-readiness-item {
    display: grid;
    gap: .25rem;
    padding: .85rem .95rem;
    border-radius: .9rem;
    border: 1px solid var(--admin-border);
    background: color-mix(in srgb, var(--admin-surface) 95%, white);
}
.deploy-readiness-item small {
    color: var(--admin-muted);
    line-height: 1.55;
}
.deploy-readiness-item.is-danger {
    border-color: color-mix(in srgb, #ef4444 28%, white);
}
.deploy-readiness-item.is-warning {
    border-color: color-mix(in srgb, #f59e0b 28%, white);
}
.deploy-readiness-item.is-success {
    border-color: color-mix(in srgb, #22c55e 28%, white);
}

.deploy-git-panel {
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    padding: 1rem;
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface) 95%, white), color-mix(in srgb, var(--admin-surface-alt) 12%, white));
}
.deploy-git-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}
.deploy-section-title {
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: .35rem;
}
.deploy-git-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .9rem;
}
.deploy-git-item {
    display: grid;
    gap: .35rem;
    padding: .9rem 1rem;
    border: 1px solid var(--admin-border);
    border-radius: .95rem;
    background: rgba(255,255,255,.72);
}
.deploy-git-item-wide {
    grid-column: 1 / -1;
}
.deploy-list,
.deploy-log-list {
    display: grid;
    gap: .75rem;
}
.deploy-log-link {
    padding: .9rem 1rem;
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    background: linear-gradient(180deg, var(--admin-surface), color-mix(in srgb, var(--admin-surface-alt) 14%, white));
}
.deploy-log-link.is-active {
    border-color: color-mix(in srgb, var(--admin-primary) 34%, white);
    box-shadow: 0 12px 28px color-mix(in srgb, var(--admin-primary) 10%, transparent);
}
.deploy-console-wrap {
    border: 1px solid var(--admin-border);
    border-radius: 1rem;
    background: #111827;
    padding: 1rem;
}
.deploy-console {
    margin: 0;
    max-height: 34rem;
    overflow: auto;
    padding: 1rem;
    border-radius: .85rem;
    background: #0b1220;
    color: #dbe7ff;
    font-size: .83rem;
    line-height: 1.65;
    white-space: pre-wrap;
    word-break: break-word;
}
.border-dashed {
    border-style: dashed !important;
}
@media (max-width: 991.98px) {
    .deploy-action-grid,
    .deploy-git-grid,
    .deploy-monitor-grid {
        grid-template-columns: 1fr;
    }
    .deploy-monitor-row {
        flex-direction: column;
    }
    .deploy-git-head {
        flex-direction: column;
    }
}
</style>
@endpush
@endsection
