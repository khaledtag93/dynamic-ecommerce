@php
    $scanner = $automation['scanner'] ?? [];
@endphp

<div id="notification-automation" class="notification-section-title mt-2">
    <div>
        <div class="notification-section-kicker">{{ __('Automation') }}</div>
        <h3 class="admin-section-title mb-1">{{ __('Automation & scanner') }}</h3>
        <p class="admin-section-subtitle mb-0">{{ __('Create escalation rules, monitor stale-pending recovery, and review scanner activity with cleaner structure and clearer guidance.') }}</p>
    </div>
    <span class="admin-chip">{{ __('Phase 4.7') }}</span>
</div>

<div class="notification-workspace-strip">
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Total rules') }}</div>
        <div class="value">{{ number_format($automation['total_rules'] ?? 0) }}</div>
        <div class="caption">{{ __('All saved automation policies across retry, fallback, and escalation behavior.') }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Active rules') }}</div>
        <div class="value">{{ number_format($automation['active_rules'] ?? 0) }}</div>
        <div class="caption">{{ __('Currently participating in automated incident handling.') }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Triggered in 24h') }}</div>
        <div class="value">{{ number_format($automation['triggered_last_24h'] ?? 0) }}</div>
        <div class="caption">{{ __('Recent policy activity recorded by the automation workflow.') }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Recovered stale logs') }}</div>
        <div class="value">{{ number_format($scanner['recovered_last_24h'] ?? 0) }}</div>
        <div class="caption">{{ __('Items recovered by the stale pending scanner during the last 24 hours.') }}</div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-7">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="notification-subsection-head">
                    <div>
                        <h4 class="mb-1">{{ __('Policy workspace') }}</h4>
                        <div class="admin-helper-text">{{ __('Define safe reactions when a notification fails, gets skipped, or remains stale long enough to need escalation handling.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('Policy builder') }}</span>
                </div>

                <div class="notification-grid-2 mb-4">
                    <div class="notification-subsection-card">
                        <div class="fw-semibold mb-2">{{ __('Stale pending scanner') }}</div>
                        <div class="admin-helper-text mb-3">{{ __('Keep stale items visible, recover them early, and only escalate when recovery is no longer safe.') }}</div>
                        <div class="d-grid gap-2 small text-muted mb-3">
                            <div>{{ __('Stale logs right now: :count', ['count' => number_format($scanner['stale_pending_total'] ?? 0)]) }}</div>
                            <div>{{ __('Recovered in 24h: :count', ['count' => number_format($scanner['recovered_last_24h'] ?? 0)]) }}</div>
                            <div>{{ __('Last scan: :time', ['time' => ($scanner['last_scan_at'] ?? null) ? \Carbon\Carbon::parse($scanner['last_scan_at'])->format('Y-m-d H:i') : '—']) }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.settings.notifications.run-scanner') }}" data-submit-loading data-confirm-title="{{ __('Run escalation scanner now') }}" data-confirm-message="{{ __('Run the scheduled escalation scanner now?') }}" data-confirm-subtitle="{{ __('This checks stale pending items immediately and may trigger recovery or escalation actions.') }}" data-confirm-ok="{{ __('Run scanner now') }}" data-confirm-cancel="{{ __('Keep scheduled timing') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-dark" data-loading-text="{{ __('Running scanner...') }}">{{ __('Run scanner now') }}</button>
                        </form>
                    </div>
                    <div class="notification-subsection-card">
                        <div class="fw-semibold mb-2">{{ __('Recommended policy flow') }}</div>
                        <ol class="small text-muted mb-0 ps-3 d-grid gap-2">
                            <li>{{ __('Retry transient failures once before moving to a fallback channel.') }}</li>
                            <li>{{ __('Use fallback only for meaningful customer-facing updates, not every low-signal event.') }}</li>
                            <li>{{ __('Recover stale pending logs before sending additional customer traffic.') }}</li>
                            <li>{{ __('Escalate repeated failures to operators with a clear cooldown and rate window.') }}</li>
                        </ol>
                    </div>
                </div>

                <div class="notification-form-shell mb-4">
                    <div class="notification-subsection-head mb-3">
                        <div>
                            <h5 class="mb-1">{{ __('Create automation rule') }}</h5>
                            <div class="admin-helper-text">{{ __('Use this form to define retry, fallback, admin alerting, or activity-log behavior.') }}</div>
                        </div>
                        <span class="badge rounded-pill text-bg-light border">{{ __('Rule builder') }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.notifications.automation-rules.save') }}" class="row g-3" data-submit-loading>
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Rule name') }}</label>
                            <input type="text" class="form-control" name="name" required placeholder="{{ __('Example: WhatsApp fallback to email') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Trigger status') }}</label>
                            <select class="form-select" name="trigger_status" required>
                                <option value="failed">{{ __('Failed') }}</option>
                                <option value="skipped">{{ __('Skipped') }}</option>
                                <option value="pending_stale">{{ __('Pending stale') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Escalation level') }}</label>
                            <select class="form-select" name="escalation_level" required>
                                @for($level = 1; $level <= 5; $level++)
                                    <option value="{{ $level }}">{{ __('Level') }} {{ $level }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Event scope') }}</label>
                            <select class="form-select" name="event">
                                <option value="">{{ __('All events') }}</option>
                                @foreach($events as $eventKey => $event)
                                    <option value="{{ $eventKey }}">{{ $event['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Source channel') }}</label>
                            <select class="form-select" name="source_channel">
                                <option value="">{{ __('Any channel') }}</option>
                                @foreach($channels as $channelKey => $channel)
                                    <option value="{{ $channelKey }}">{{ $channel['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Action') }}</label>
                            <select class="form-select" name="action_type" required>
                                <option value="retry_same_channel">{{ __('Retry same channel') }}</option>
                                <option value="fallback_channel">{{ __('Fallback channel') }}</option>
                                <option value="notify_admin_email">{{ __('Notify admin email') }}</option>
                                <option value="create_activity">{{ __('Create admin activity') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Fallback target') }}</label>
                            <select class="form-select" name="target_channel">
                                <option value="">{{ __('Not required') }}</option>
                                @foreach(['database', 'email', 'whatsapp'] as $channelKey)
                                    <option value="{{ $channelKey }}">{{ $channels[$channelKey]['label'] ?? ucfirst($channelKey) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Max attempts') }}</label>
                            <input type="number" class="form-control" name="max_attempts" min="1" max="10" value="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Delay (minutes)') }}</label>
                            <input type="number" class="form-control" name="delay_minutes" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Sort order') }}</label>
                            <input type="number" class="form-control" name="sort_order" min="0" value="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Admin email (optional)') }}</label>
                            <input type="email" class="form-control" name="admin_email" placeholder="{{ __('Example: ops@example.com') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <input type="text" class="form-control" name="notes" placeholder="{{ __('Example: Escalate critical failures to operations') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Cooldown (minutes)') }}</label>
                            <input type="number" class="form-control" name="cooldown_minutes" min="0" max="1440" value="15">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Rate window (minutes)') }}</label>
                            <input type="number" class="form-control" name="rate_limit_window_minutes" min="1" max="1440" value="60">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Max runs in window') }}</label>
                            <input type="number" class="form-control" name="rate_limit_max_runs" min="1" max="100" value="3">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch notification-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="automation_rule_active" checked>
                                <label class="form-check-label small" for="automation_rule_active">{{ __('Active immediately') }}</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="admin-form-actions">
                                <div class="admin-form-actions-copy">
                                    <div class="admin-form-actions-title">{{ __('Ready to save this rule?') }}</div>
                                    <div class="admin-form-actions-subtitle">{{ __('Keep the policy narrow, define cooldowns clearly, and avoid broad escalation rules unless the event is genuinely critical.') }}</div>
                                </div>
                                <div class="admin-form-actions-buttons">
                                    <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Saving automation rule...') }}">{{ __('Save automation rule') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <details class="notification-collapsible">
                    <summary>
                        <span>{{ __('Existing automation rules') }}</span>
                        <span class="meta">{{ __('Review, disable, or clean up the policies already in production') }}</span>
                    </summary>
                    <div class="notification-collapsible-body">
                        <div class="d-grid gap-3">
                            @forelse(($automation['rules'] ?? collect()) as $rule)
                                <div class="notification-list-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                        <div>
                                            <div class="fw-semibold">{{ $rule->name ?: __('Automation rule') }}</div>
                                            <div class="admin-helper-text">{{ $events[$rule->event]['label'] ?? ($rule->event ?: __('All events')) }} • {{ $channels[$rule->source_channel]['label'] ?? ($rule->source_channel ?: __('Any channel')) }}</div>
                                        </div>
                                        <div class="notification-pill-row">
                                            <span class="badge rounded-pill text-bg-light border">{{ __('Level') }} {{ $rule->escalation_level }}</span>
                                            <span class="badge {{ !empty($rule->is_active) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }}">{{ !empty($rule->is_active) ? __('Active') : __('Inactive') }}</span>
                                        </div>
                                    </div>
                                    <div class="row g-3 mt-1 small">
                                        <div class="col-md-3"><div class="notification-mini-label">{{ __('Trigger') }}</div><div class="fw-semibold">{{ $rule->trigger_status }}</div></div>
                                        <div class="col-md-3"><div class="notification-mini-label">{{ __('Action') }}</div><div class="fw-semibold">{{ $rule->action_type }}</div></div>
                                        <div class="col-md-3"><div class="notification-mini-label">{{ __('Cooldown') }}</div><div class="fw-semibold">{{ $rule->cooldown_minutes }} {{ __('min') }}</div></div>
                                        <div class="col-md-3"><div class="notification-mini-label">{{ __('Rate window') }}</div><div class="fw-semibold">{{ $rule->rate_limit_window_minutes }} / {{ $rule->rate_limit_max_runs }}</div></div>
                                    </div>
                                </div>
                            @empty
                                <div class="admin-helper-text">{{ __('No automation rules exist yet.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="notification-subsection-head">
                    <div>
                        <h4 class="mb-1">{{ __('Escalation insight') }}</h4>
                        <div class="admin-helper-text">{{ __('A lighter operator view for level distribution, recent scanner runs, and recovered stale logs.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('Operations view') }}</span>
                </div>

                <div class="notification-subsection-card mb-3">
                    <div class="small text-muted mb-2">{{ __('Escalation levels summary') }}</div>
                    <div class="notification-pill-row">
                        @forelse(($automation['escalation_levels'] ?? []) as $level => $count)
                            <span class="badge rounded-pill text-bg-light border">{{ __('Level') }} {{ $level }}: {{ $count }}</span>
                        @empty
                            <span class="admin-helper-text">{{ __('No levels configured yet.') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="notification-soft-card mb-3">
                    <div class="notification-subsection-head mb-3">
                        <div>
                            <h5 class="mb-1">{{ __('Scanner history') }}</h5>
                            <div class="admin-helper-text">{{ __('Latest manual and scheduled scanner runs with recovery counts.') }}</div>
                        </div>
                        <span class="badge rounded-pill text-bg-light border">{{ __('Latest runs') }}</span>
                    </div>
                    <div class="notification-list">
                        @forelse($scannerHistory as $scanItem)
                            @php($scanMeta = (array) ($scanItem->meta ?? []))
                            <div class="notification-list-item">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <div class="fw-semibold">{{ $scanItem->action === 'manual_scanner_run' ? __('Manual run') : __('Scheduled run') }}</div>
                                        <div class="admin-helper-text">{{ optional($scanItem->created_at)->format('Y-m-d H:i') ?: '—' }}</div>
                                    </div>
                                    <span class="badge bg-secondary">{{ __('Scanned: :count', ['count' => $scanMeta['scanned'] ?? 0]) }}</span>
                                </div>
                                <div class="row g-2 mt-1 small">
                                    <div class="col-4"><div class="notification-mini-label">{{ __('Matched') }}</div><div class="fw-semibold">{{ $scanMeta['matched'] ?? 0 }}</div></div>
                                    <div class="col-4"><div class="notification-mini-label">{{ __('Recovered') }}</div><div class="fw-semibold">{{ $scanMeta['recovered'] ?? 0 }}</div></div>
                                    <div class="col-4"><div class="notification-mini-label">{{ __('By') }}</div><div class="fw-semibold">{{ optional($scanItem->adminUser)->name ?: __('System') }}</div></div>
                                </div>
                            </div>
                        @empty
                            <div class="admin-helper-text">{{ __('No scanner history yet.') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="notification-soft-card mb-3">
                    <div class="notification-subsection-head mb-3">
                        <div>
                            <h5 class="mb-1">{{ __('Recovered stale logs') }}</h5>
                            <div class="admin-helper-text">{{ __('Recent pending notifications that were recovered by the stale scanner rules.') }}</div>
                        </div>
                        <span class="badge rounded-pill text-bg-light border">{{ __('Recovery log') }}</span>
                    </div>
                    <div class="notification-list">
                        @forelse($staleRecoveredLogs as $recoveredLog)
                            @php($recoveredMeta = (array) ($recoveredLog->meta ?? []))
                            <div class="notification-list-item">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <div class="fw-semibold">{{ optional($recoveredLog->order)->order_number ?? __('Unknown order') }}</div>
                                        <div class="admin-helper-text">{{ $channels[$recoveredLog->channel]['label'] ?? ucfirst($recoveredLog->channel) }} • {{ $events[$recoveredLog->event]['label'] ?? $recoveredLog->event }}</div>
                                    </div>
                                    <span class="badge bg-{{ $statusColors[$recoveredLog->status] ?? 'secondary' }}">{{ $statusLabels[$recoveredLog->status] ?? ucfirst($recoveredLog->status) }}</span>
                                </div>
                                <div class="admin-helper-text mt-2">{{ __('Recovered at: :time', ['time' => optional($recoveredLog->updated_at)->format('Y-m-d H:i') ?: '—']) }}</div>
                                <div class="small mt-2">{{ __('Rules applied: :rules', ['rules' => collect($recoveredMeta['stale_pending_rules'] ?? [])->pluck('name')->filter()->implode(', ') ?: __('No rule names recorded')]) }}</div>
                            </div>
                        @empty
                            <div class="admin-helper-text">{{ __('No recovered stale logs yet.') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="notification-soft-card">
                    <div class="notification-subsection-head mb-3">
                        <div>
                            <h5 class="mb-1">{{ __('Recent escalations') }}</h5>
                            <div class="admin-helper-text">{{ __('Recent automation runs that escalated beyond simple retry or recovery.') }}</div>
                        </div>
                        <span class="badge rounded-pill text-bg-light border">{{ __('Escalations') }}</span>
                    </div>
                    <div class="d-grid gap-3">
                        @forelse(($automation['recent_escalations'] ?? collect()) as $escalationLog)
                            <div class="rounded-4 border p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ optional($escalationLog->order)->order_number ?? __('Unknown order') }}</div>
                                        <div class="admin-helper-text">{{ $escalationLog->channel }} • {{ $escalationLog->event }}</div>
                                        <div class="small mt-2">{{ __('Automation run recorded at') }} {{ optional($escalationLog->created_at)->format('Y-m-d H:i') ?: '—' }}</div>
                                    </div>
                                    <span class="badge bg-info">{{ __('Escalated') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="admin-helper-text">{{ __('No recent escalation runs yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
