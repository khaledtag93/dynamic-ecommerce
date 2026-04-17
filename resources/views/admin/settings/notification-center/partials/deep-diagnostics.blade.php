@php
    $observability = $monitoring['observability'] ?? [];
    $workerResilience = $monitoring['workerResilience'] ?? [];
    $providerTuning = $monitoring['providerTuning'] ?? [];
    $recoveryPlaybook = $monitoring['recoveryPlaybook'] ?? [];
    $releaseChecklist = $monitoring['releaseChecklist'] ?? [];
    $incidentSummary = $monitoring['incidentSummary'] ?? [];
    $providerFailureBreakdown = $monitoring['providerFailureBreakdown'] ?? [];
    $retryGuardBreakdown = $monitoring['retryGuardBreakdown'] ?? [];
    $recentIncidents = $monitoring['recentIncidents'] ?? [];
    $qaDoneCount = collect($releaseChecklist)->where('done', true)->count();
@endphp

<details class="notification-collapsible" open>
    <summary>
        <span>{{ __('Deep diagnostics, resilience tuning, and release checks') }}</span>
        <span class="meta">{{ __('Open when you need worker health, provider tuning, or release-readiness details') }}</span>
    </summary>
    <div class="notification-collapsible-body">
        <div class="notification-kpi-grid mb-4">
            <div class="notification-kpi-box"><div class="value">{{ number_format($observability['operator_dispatch_retries_24h'] ?? 0) }}</div><div class="label">{{ __('Dispatch retries') }}</div></div>
            <div class="notification-kpi-box"><div class="value">{{ number_format($observability['operator_whatsapp_retries_24h'] ?? 0) }}</div><div class="label">{{ __('WhatsApp retries') }}</div></div>
            <div class="notification-kpi-box"><div class="value">{{ number_format($observability['duplicate_dispatches_prevented_24h'] ?? 0) }}</div><div class="label">{{ __('Dispatch duplicates blocked') }}</div></div>
            <div class="notification-kpi-box"><div class="value">{{ number_format($observability['duplicate_whatsapp_prevented_24h'] ?? 0) }}</div><div class="label">{{ __('WhatsApp duplicates blocked') }}</div></div>
            <div class="notification-kpi-box"><div class="value">{{ number_format($observability['automation_runs_24h'] ?? 0) }}</div><div class="label">{{ __('Automation runs') }}</div></div>
            <div class="notification-kpi-box"><div class="value">{{ number_format($observability['safety_blocks_24h'] ?? 0) }}</div><div class="label">{{ __('Safety blocks') }}</div></div>
        </div>


        <div class="notification-grid-2 mb-4">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="notification-subsection-head">
                        <div>
                            <h4 class="mb-1">{{ __('Incident-driven reliability summary') }}</h4>
                            <div class="admin-helper-text">{{ __('A compact reading of whether the notification system is stable, needs review, or is under active pressure.') }}</div>
                        </div>
                        <span class="badge bg-{{ $incidentSummary['tone'] ?? 'warning' }}">{{ $incidentSummary['status_label'] ?? __('Needs review') }}</span>
                    </div>
                    <div class="notification-grid-3 mb-3">
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($incidentSummary['score'] ?? 0) }}%</div><div class="admin-helper-text mt-1">{{ __('Reliability score') }}</div></div>
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($incidentSummary['open_incidents'] ?? 0) }}</div><div class="admin-helper-text mt-1">{{ __('Open incidents') }}</div></div>
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ $monitoring['next_action'] ?? __('Review diagnostics') }}</div><div class="admin-helper-text mt-1">{{ __('Recommended next action') }}</div></div>
                    </div>
                    <div class="d-grid gap-2">
                        @forelse(($incidentSummary['attention_reasons'] ?? []) as $reason)
                            <div class="rounded-4 border p-3 bg-white">{{ $reason }}</div>
                        @empty
                            <div class="rounded-4 border p-3 bg-white">{{ __('No active pressure signals were detected right now.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="notification-subsection-head">
                        <div>
                            <h4 class="mb-1">{{ __('Provider and safety patterns') }}</h4>
                            <div class="admin-helper-text">{{ __('See what kinds of failures repeat most often and which retry guards are blocking operators most often.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('7-day pattern') }}</span>
                    </div>
                    <div class="notification-grid-2 mb-3">
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($providerFailureBreakdown['total'] ?? 0) }}</div><div class="admin-helper-text mt-1">{{ __('Categorized failures') }}</div></div>
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($retryGuardBreakdown['blocked_last_24h'] ?? 0) }}</div><div class="admin-helper-text mt-1">{{ __('Blocked retries (24h)') }}</div></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">{{ __('Top provider failure buckets') }}</div>
                            <div class="d-grid gap-2">
                                @forelse(($providerFailureBreakdown['groups'] ?? []) as $bucket)
                                    <div class="rounded-4 border p-3 bg-white d-flex justify-content-between align-items-center gap-2">
                                        <span>{{ $bucket['label'] ?? __('Unknown') }}</span>
                                        <span class="badge text-bg-light border">{{ number_format($bucket['count'] ?? 0) }}</span>
                                    </div>
                                @empty
                                    <div class="rounded-4 border p-3 bg-white">{{ __('No recent provider failure categories were detected.') }}</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-semibold mb-2">{{ __('Top retry guard reasons') }}</div>
                            <div class="d-grid gap-2">
                                @forelse(($retryGuardBreakdown['top_reasons'] ?? []) as $reason)
                                    <div class="rounded-4 border p-3 bg-white d-flex justify-content-between align-items-center gap-2">
                                        <span>{{ $reason['reason'] ?? __('Unknown guard') }}</span>
                                        <span class="badge text-bg-light border">{{ number_format($reason['count'] ?? 0) }}</span>
                                    </div>
                                @empty
                                    <div class="rounded-4 border p-3 bg-white">{{ __('No retry guard blocks were recorded recently.') }}</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="notification-subsection-head">
                    <div>
                        <h4 class="mb-1">{{ __('Recent incidents feed') }}</h4>
                        <div class="admin-helper-text">{{ __('One mixed timeline for failed dispatches, WhatsApp incidents, and safety-blocked operator actions.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('Latest 10') }}</span>
                </div>
                <div class="d-grid gap-3">
                    @forelse($recentIncidents as $incident)
                        <div class="rounded-4 border p-3 bg-white d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold">{{ $incident['title'] ?? __('Incident') }}</div>
                                <div class="admin-helper-text mt-1">{{ $incident['meta'] ?? '—' }}</div>
                                <div class="small mt-2">{{ $incident['description'] ?? '—' }}</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-{{ $incident['tone'] ?? 'secondary' }} mb-2">{{ ucfirst($incident['tone'] ?? 'info') }}</span>
                                <div class="admin-helper-text">{{ optional($incident['at'] ?? null)?->diffForHumans() ?? '—' }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-4 border p-3 bg-white">{{ __('No recent incidents were detected in the mixed observability feed.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="notification-grid-2 mb-4">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="notification-subsection-head">
                        <div>
                            <h4 class="mb-1">{{ __('Queue worker resilience') }}</h4>
                            <div class="admin-helper-text">{{ __('Operational guidance for worker uptime, retry timing, and duplicate-safe queue execution.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Score: :score%', ['score' => $workerResilience['score'] ?? 0]) }}</span>
                    </div>
                    <div class="notification-grid-3 mb-3">
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ $workerResilience['queue_driver'] ?? 'SYNC' }}</div><div class="admin-helper-text mt-1">{{ __('Queue driver') }}</div></div>
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($workerResilience['job_timeout_seconds'] ?? 0) }}s</div><div class="admin-helper-text mt-1">{{ __('Job timeout') }}</div></div>
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($workerResilience['retry_after_seconds'] ?? 0) }}s</div><div class="admin-helper-text mt-1">{{ __('Retry after') }}</div></div>
                    </div>
                    <div class="admin-helper-text mb-2">{{ __('Backoff schedule') }}: {{ implode(', ', $workerResilience['backoff_schedule'] ?? []) }}s</div>
                    <ul class="mb-0 ps-3 d-grid gap-2">
                        @foreach(($workerResilience['notes'] ?? []) as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="notification-subsection-head">
                        <div>
                            <h4 class="mb-1">{{ __('Provider timeout & backoff tuning') }}</h4>
                            <div class="admin-helper-text">{{ __('Live config snapshot for safer Meta provider retries and queue pacing.') }}</div>
                        </div>
                        <span class="admin-chip">{{ strtoupper($providerTuning['queue_backoff_strategy'] ?? 'fixed') }}</span>
                    </div>
                    <div class="notification-grid-2">
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($providerTuning['whatsapp_timeout_seconds'] ?? 0) }}s</div><div class="admin-helper-text mt-1">{{ __('WhatsApp timeout') }}</div></div>
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($providerTuning['whatsapp_connect_timeout_seconds'] ?? 0) }}s</div><div class="admin-helper-text mt-1">{{ __('Connect timeout') }}</div></div>
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($providerTuning['whatsapp_retry_times'] ?? 0) }}</div><div class="admin-helper-text mt-1">{{ __('HTTP retries') }}</div></div>
                        <div class="notification-subsection-card"><div class="fw-semibold">{{ number_format($providerTuning['whatsapp_retry_sleep_ms'] ?? 0) }}ms</div><div class="admin-helper-text mt-1">{{ __('Retry sleep') }}</div></div>
                    </div>
                    <div class="admin-helper-text mt-3">{{ __('Queue backoff schedule') }}: {{ implode(', ', $providerTuning['queue_backoff_schedule'] ?? []) }}s</div>
                </div>
            </div>
        </div>

        <div class="notification-grid-2">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="notification-subsection-head">
                        <div>
                            <h4 class="mb-1">{{ __('Dead-letter style recovery playbook') }}</h4>
                            <div class="admin-helper-text">{{ __('Use failed jobs as diagnostic records first, then retry only the items that still make operational sense.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Failed notification jobs: :count', ['count' => number_format($recoveryPlaybook['failed_notification_jobs'] ?? 0)]) }}</span>
                    </div>
                    <ol class="ps-3 mb-3 d-grid gap-2">
                        @foreach(($recoveryPlaybook['steps'] ?? []) as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                    @if(collect($recoveryPlaybook['top_failed_jobs'] ?? [])->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Job') }}</th>
                                        <th>{{ __('Queue') }}</th>
                                        <th>{{ __('Failed at') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($recoveryPlaybook['top_failed_jobs'] ?? []) as $job)
                                        <tr>
                                            <td>{{ $job['name'] ?? __('Unknown job') }}</td>
                                            <td>{{ $job['queue'] ?? '—' }}</td>
                                            <td>{{ optional($job['failed_at'] ?? null)?->format('Y-m-d H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="notification-subsection-head">
                        <div>
                            <h4 class="mb-1">{{ __('Final release QA checklist') }}</h4>
                            <div class="admin-helper-text">{{ __('A quick production-readiness checklist before the next stable release or deploy.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __(':done / :total passed', ['done' => $qaDoneCount, 'total' => collect($releaseChecklist)->count()]) }}</span>
                    </div>
                    <div class="d-grid gap-2">
                        @foreach($releaseChecklist as $item)
                            <div class="rounded-4 border p-3 bg-white d-flex justify-content-between align-items-center gap-3">
                                <div>{{ $item['label'] ?? '' }}</div>
                                <span class="badge {{ !empty($item['done']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle' }}">
                                    {{ !empty($item['done']) ? __('Passed') : __('Needs review') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</details>
