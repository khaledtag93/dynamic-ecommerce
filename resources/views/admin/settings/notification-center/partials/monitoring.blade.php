@php
    $queue = $monitoring['queue'] ?? [];
    $dispatchTrend = $monitoring['dispatchTrend'] ?? [];
    $whatsAppHealth = $monitoring['whatsAppHealth'] ?? [];
    $healthLabel = data_get($monitoring, 'health.label', __('Unknown'));
    $nextAction = data_get($monitoring, 'next_action', __('Review diagnostics'));
    $incidentSummary = $monitoring['incidentSummary'] ?? [];
@endphp

<div id="notification-monitoring" class="notification-section-title mt-2">
    <div>
        <div class="notification-section-kicker">{{ __('Reliability') }}</div>
        <h3 class="admin-section-title mb-1">{{ __('Queue & monitoring') }}</h3>
        <p class="admin-section-subtitle mb-0">{{ __('Monitor queue stability, stale jobs, failures, and recent delivery behavior from one cleaner operational view.') }}</p>
    </div>
    <span class="admin-chip">{{ __('Monitoring') }}</span>
</div>

<div class="notification-workspace-strip">
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Queue driver') }}</div>
        <div class="value">{{ strtoupper($queue['driver'] ?? 'sync') }}</div>
        <div class="caption">{{ __('Current queue driver used by the notification pipeline.') }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Pending jobs') }}</div>
        <div class="value">{{ number_format($queue['pending_total'] ?? 0) }}</div>
        <div class="caption">{{ __('Jobs waiting in the queue before the worker picks them up.') }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Health status') }}</div>
        <div class="value">{{ $healthLabel }}</div>
        <div class="caption">{{ __('Recommended next action: :action', ['action' => $nextAction]) }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Reliability score') }}</div>
        <div class="value">{{ number_format($incidentSummary['score'] ?? 0) }}%</div>
        <div class="caption">{{ __('Open incidents: :count', ['count' => number_format($incidentSummary['open_incidents'] ?? 0)]) }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('WhatsApp success rate') }}</div>
        <div class="value">{{ $whatsAppHealth['success_rate'] ?? 100 }}%</div>
        <div class="caption">{{ __('Last 24h delivery success for the WhatsApp provider path.') }}</div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="notification-subsection-head">
                    <div>
                        <h4 class="mb-1">{{ __('Queue monitoring dashboard') }}</h4>
                        <div class="admin-helper-text">{{ __('Live queue visibility for notification-related workload, stale jobs, and recent failures.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('Health dashboard') }}</span>
                </div>

                <div class="notification-grid-2 mb-4">
                    <div class="notification-subsection-card">
                        <div class="fw-semibold mb-2">{{ __('Queue snapshot') }}</div>
                        <div class="admin-helper-text d-grid gap-2">
                            <div>{{ __('Driver: :driver', ['driver' => strtoupper($queue['driver'] ?? 'sync')]) }}</div>
                            <div>{{ __('Pending jobs: :count', ['count' => number_format($queue['pending_total'] ?? 0)]) }}</div>
                            <div>{{ __('Reserved jobs: :count', ['count' => number_format($queue['reserved_total'] ?? 0)]) }}</div>
                            <div>{{ __('Oldest pending age: :value', ['value' => isset($queue['oldest_pending_minutes']) ? number_format($queue['oldest_pending_minutes']).' '.__('min') : '—']) }}</div>
                        </div>
                    </div>
                    <div class="notification-subsection-card">
                        <div class="fw-semibold mb-2">{{ __('24h health pulse') }}</div>
                        <div class="admin-helper-text d-grid gap-2">
                            <div>{{ __('Total dispatches: :count', ['count' => number_format($dispatchTrend['last_24h_total'] ?? 0)]) }}</div>
                            <div>{{ __('Failed dispatches: :count', ['count' => number_format($dispatchTrend['last_24h_failed'] ?? 0)]) }}</div>
                            <div>{{ __('WhatsApp success rate: :rate%', ['rate' => $whatsAppHealth['success_rate'] ?? 100]) }}</div>
                            <div>{{ __('Duplicate-guard skips: :count', ['count' => number_format($whatsAppHealth['duplicate_skipped'] ?? 0)]) }}</div>
                        </div>
                    </div>
                </div>

                <div class="notification-subsection-card mb-4">
                    <div class="notification-subsection-head mb-3">
                        <div>
                            <h5 class="mb-1">{{ __('Channel health table') }}</h5>
                            <div class="admin-helper-text">{{ __('Compare sent, failed, pending, and skipped counts per channel from one place.') }}</div>
                        </div>
                        <span class="badge rounded-pill text-bg-light border">{{ __('Channel health') }}</span>
                    </div>
                    <div class="table-responsive admin-table-wrap mb-0">
                        <table class="table align-middle admin-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Channel') }}</th>
                                    <th>{{ __('Sent') }}</th>
                                    <th>{{ __('Failed') }}</th>
                                    <th>{{ __('Pending') }}</th>
                                    <th>{{ __('Skipped') }}</th>
                                    <th>{{ __('Success rate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(($monitoring['channelHealth'] ?? []) as $channelKey => $health)
                                    <tr>
                                        <td><span class="admin-chip">{{ $channels[$channelKey]['label'] ?? ucfirst($channelKey) }}</span></td>
                                        <td>{{ number_format($health['sent'] ?? 0) }}</td>
                                        <td>{{ number_format($health['failed'] ?? 0) }}</td>
                                        <td>{{ number_format($health['pending'] ?? 0) }}</td>
                                        <td>{{ number_format($health['skipped'] ?? 0) }}</td>
                                        <td>
                                            @php($rate = (int) ($health['success_rate'] ?? 100))
                                            <span class="badge bg-{{ $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger') }}">{{ $rate }}%</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <details class="notification-collapsible">
                    <summary>
                        <span>{{ __('Recent jobs and failed jobs') }}</span>
                        <span class="meta">{{ __('Open for queue-level troubleshooting and careful retries') }}</span>
                    </summary>
                    <div class="notification-collapsible-body">
                        <div class="notification-grid-2">
                            <div class="notification-subsection-card">
                                <div class="fw-semibold mb-3">{{ __('Recent queued jobs') }}</div>
                                <div class="d-grid gap-3">
                                    @forelse(($monitoring['pendingJobs'] ?? []) as $job)
                                        <div class="border rounded-4 p-3 bg-white">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div>
                                                    <div class="fw-semibold">{{ $job['name'] }}</div>
                                                    <div class="admin-helper-text">{{ __('Queue: :queue • Attempts: :attempts', ['queue' => $job['queue'] ?: 'default', 'attempts' => $job['attempts']]) }}</div>
                                                    <div class="admin-helper-text mt-1">{{ __('Available: :time', ['time' => $job['available_at']?->format('Y-m-d H:i') ?: '—']) }}</div>
                                                </div>
                                                @if($job['is_notification_related'])
                                                    <span class="badge bg-info">{{ __('Notification') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div><div class="admin-empty-title">{{ __('No queued jobs right now') }}</div><div class="admin-empty-subtitle">{{ __('Pending notification jobs will appear here when queue activity is active.') }}</div></div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="notification-subsection-card">
                                <div class="fw-semibold mb-3">{{ __('Recent failed queue jobs') }}</div>
                                <div class="d-grid gap-3">
                                    @forelse(($monitoring['failedJobs'] ?? []) as $job)
                                        <div class="border rounded-4 p-3 bg-white">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div>
                                                    <div class="fw-semibold">{{ $job['name'] }}</div>
                                                    <div class="admin-helper-text">{{ __('Queue: :queue • Connection: :connection', ['queue' => $job['queue'] ?: 'default', 'connection' => $job['connection'] ?: '—']) }}</div>
                                                    <div class="small text-danger mt-2">{{ $job['exception'] ?: __('No exception body stored.') }}</div>
                                                </div>
                                                <div class="text-end">
                                                    @if($job['is_notification_related'])
                                                        <span class="badge bg-danger mb-2">{{ __('Notification') }}</span>
                                                    @endif
                                                    <div class="admin-helper-text mb-2">{{ $job['failed_at']?->format('Y-m-d H:i') ?: '—' }}</div>
                                                    @if(!empty($job['uuid']))
                                                        <form method="POST" action="{{ route('admin.settings.notifications.retry-failed-job') }}" data-submit-loading data-confirm-title="{{ __('Retry failed queue job') }}" data-confirm-message="{{ __('Retry this failed queue job now?') }}" data-confirm-subtitle="{{ __('Use this only after reviewing the failure details so duplicate worker activity stays controlled.') }}" data-confirm-ok="{{ __('Retry queue job') }}" data-confirm-cancel="{{ __('Keep reviewing') }}">
                                                            @csrf
                                                            <input type="hidden" name="failed_job_id" value="{{ $job['uuid'] }}">
                                                            <button type="submit" class="btn btn-sm btn-outline-dark" data-loading-text="{{ __('Retrying queue job...') }}">{{ __('Retry queue job') }}</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div><div class="admin-empty-title">{{ __('No failed queue jobs found') }}</div><div class="admin-empty-subtitle">{{ __('Failed queue jobs will appear here whenever the worker records an exception.') }}</div></div>
                                    @endforelse
                                </div>
                            </div>
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
                        <h4 class="mb-1">{{ __('Health recommendations') }}</h4>
                        <div class="admin-helper-text">{{ __('Auto-read guidance based on queue state, dispatch results, and channel reliability.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('Smart checks') }}</span>
                </div>

                <div class="d-grid gap-3 mb-4">
                    @forelse(($monitoring['recommendations'] ?? []) as $item)
                        <div class="rounded-4 border p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $item['title'] }}</div>
                                    <div class="admin-helper-text mt-1">{{ $item['message'] }}</div>
                                </div>
                                <span class="badge bg-{{ $item['tone'] }}">{{ ucfirst($item['tone']) }}</span>
                            </div>
                        </div>
                    @empty
                        <div><div class="admin-empty-title">{{ __('No recommendations right now') }}</div><div class="admin-empty-subtitle">{{ __('Operational guidance will appear here when queue health or channel activity needs attention.') }}</div></div>
                    @endforelse
                </div>

                <div class="notification-note-box">
                    <div class="fw-semibold mb-3">{{ __('Operator checklist') }}</div>
                    <ul class="mb-0 admin-helper-text d-grid gap-2 ps-3">
                        <li>{{ __('Keep a live queue worker active in production before enabling heavy WhatsApp traffic.') }}</li>
                        <li>{{ __('Review failed queue jobs together with dispatch logs to separate provider errors from worker issues.') }}</li>
                        <li>{{ __('Use test send after template edits so dashboard health reflects real channel readiness.') }}</li>
                        <li>{{ __('If stale jobs rise, check supervisor, cron, or connection settings before retrying customer sends.') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
