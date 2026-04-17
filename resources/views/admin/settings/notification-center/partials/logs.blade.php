<div id="notification-logs" class="notification-section-title mt-2">
    <div>
        <div class="notification-section-kicker">{{ __('Traceability') }}</div>
        <h3 class="admin-section-title mb-1">{{ __('Logs & recovery') }}</h3>
        <p class="admin-section-subtitle mb-0">{{ __('Review dispatch history, retry recent failures, and track scanner-based recovery from one tidy history view.') }}</p>
    </div>
    <span class="admin-chip">{{ __('History') }}</span>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">{{ __('Dispatch logs') }}</h4>
                        <div class="admin-helper-text">{{ __('Unified history for notification engine dispatch attempts.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('History & filters') }}</span>
                </div>

                <form method="GET" action="{{ route('admin.settings.notifications.logs') }}" class="row g-2 mb-3" data-submit-loading>
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search order / recipient / error') }}">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="channel">
                            <option value="">{{ __('All channels') }}</option>
                            @foreach($channels as $channelKey => $channel)
                                <option value="{{ $channelKey }}" @selected(($filters['channel'] ?? '') === $channelKey)>{{ $channel['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="event">
                            <option value="">{{ __('All events') }}</option>
                            @foreach($events as $eventKey => $event)
                                <option value="{{ $eventKey }}" @selected(($filters['event'] ?? '') === $eventKey)>{{ $event['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach(['sent', 'pending', 'failed', 'skipped'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabels[$status] ?? ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.settings.notifications.logs') }}" class="btn btn-light">{{ __('Reset') }}</a>
                        <button type="submit" class="btn btn-outline-primary" data-loading-text="{{ __('Refreshing logs...') }}" data-bs-toggle="tooltip" title="{{ __('Apply the selected filters and refresh the log view.') }}">{{ __('Apply log filters') }}</button>
                    </div>
                </form>

                <div class="table-responsive admin-table-wrap">
                    <table class="table align-middle admin-table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Order / event') }}</th>
                                <th>{{ __('Channel') }}</th>
                                <th>{{ __('Recipient') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Time') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dispatchLogs as $log)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ optional($log->order)->order_number ?? __('Unknown order') }}</div>
                                        <div class="admin-helper-text">{{ $events[$log->event]['label'] ?? $log->event }}</div>
                                        @if($log->error_message)
                                            <div class="small text-danger mt-1">{{ \Illuminate\Support\Str::limit($log->error_message, 100) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="admin-chip">{{ $channels[$log->channel]['label'] ?? ucfirst($log->channel) }}</span>
                                    </td>
                                    <td>
                                        <div>{{ $log->recipient ?: '—' }}</div>
                                        <div class="admin-helper-text">{{ optional($log->order)->customer_name ?: '—' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $statusColors[$log->status] ?? 'secondary' }}">{{ $statusLabels[$log->status] ?? ucfirst($log->status) }}</span>
                                    </td>
                                    <td>
                                        <div>{{ optional($log->attempted_at)->format('Y-m-d H:i') ?: '—' }}</div>
                                        <div class="admin-helper-text">{{ optional($log->sent_at ?? $log->failed_at)->diffForHumans() ?: '—' }}</div>
                                    </td>
                                    <td class="text-end">
                                        @if(in_array($log->status, ['failed', 'skipped'], true) && $log->order)
                                            <div class="d-flex justify-content-end gap-2">
                                                <form method="POST" action="{{ route('admin.settings.notifications.retry-log', $log) }}" data-submit-loading data-confirm-title="{{ __('Retry notification send') }}" data-confirm-message="{{ __('Retry this notification send now?') }}" data-confirm-subtitle="{{ __('A new controlled retry attempt will be created only if safety guards allow it.') }}" data-confirm-ok="{{ __('Retry send') }}" data-confirm-cancel="{{ __('Keep reviewing') }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" data-loading-text="{{ __('Retrying send...') }}" data-bs-toggle="tooltip" title="{{ __('Retry this notification send immediately.') }}"><i class="mdi mdi-refresh"></i><span>{{ __('Retry send') }}</span></button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.settings.notifications.run-escalation', $log) }}" data-submit-loading data-confirm-title="{{ __('Run escalation now') }}" data-confirm-message="{{ __('Escalate this notification log now?') }}" data-confirm-subtitle="{{ __('The escalation policy will be executed immediately using the current rule configuration.') }}" data-confirm-ok="{{ __('Run escalation now') }}" data-confirm-cancel="{{ __('Keep reviewing') }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1" data-loading-text="{{ __('Running escalation...') }}" data-bs-toggle="tooltip" title="{{ __('Run the escalation path for this log right now.') }}"><i class="mdi mdi-arrow-up-bold-circle-outline"></i><span>{{ __('Escalate now') }}</span></button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="admin-helper-text">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4"><div class="admin-empty-title">{{ __('No dispatch logs yet') }}</div><div class="admin-empty-subtitle">{{ __('Dispatch history will start appearing here as soon as the engine records delivery activity.') }}</div></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($dispatchLogs, 'links'))
                    <div class="mt-3">
                        {{ $dispatchLogs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <details class="notification-collapsible" open>
            <summary>
                <span>{{ __('Retry center and admin timeline') }}</span>
                <span class="meta">{{ __('Recovery workspace') }}</span>
            </summary>
            <div class="notification-collapsible-body">

        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">{{ __('Retry Center') }}</h4>
                        <div class="admin-helper-text">{{ __('Fast recovery for failed notification attempts.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('Safe retry') }}</span>
                </div>

                <h6 class="admin-section-title mb-1">{{ __('Failed notification dispatches') }}</h6>
                <div class="admin-section-subtitle mb-3">{{ __('Review engine-level failures and retry customer-facing notifications from one compact queue.') }}</div>
                <div class="d-grid gap-3 mb-4">
                    @forelse($failedDispatchLogs as $log)
                        <div class="rounded-4 border p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ optional($log->order)->order_number ?? __('Unknown order') }}</div>
                                    <div class="admin-helper-text">{{ $events[$log->event]['label'] ?? $log->event }} • {{ $channels[$log->channel]['label'] ?? $log->channel }}</div>
                                    <div class="small text-danger mt-2">{{ $log->error_message ?: __('Dispatch failed.') }}</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('admin.settings.notifications.retry-log', $log) }}" data-submit-loading data-confirm-title="{{ __('Retry notification send') }}" data-confirm-message="{{ __('Retry this notification send now?') }}" data-confirm-subtitle="{{ __('A new controlled retry attempt will be created only if safety guards allow it.') }}" data-confirm-ok="{{ __('Retry send') }}" data-confirm-cancel="{{ __('Keep reviewing') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" data-loading-text="{{ __('Retrying send...') }}" data-bs-toggle="tooltip" title="{{ __('Retry this notification send immediately.') }}"><i class="mdi mdi-refresh"></i><span>{{ __('Retry send') }}</span></button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.settings.notifications.run-escalation', $log) }}" data-submit-loading data-confirm-title="{{ __('Run escalation now') }}" data-confirm-message="{{ __('Escalate this notification log now?') }}" data-confirm-subtitle="{{ __('The escalation policy will be executed immediately using the current rule configuration.') }}" data-confirm-ok="{{ __('Run escalation now') }}" data-confirm-cancel="{{ __('Keep reviewing') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1" data-loading-text="{{ __('Running escalation...') }}" data-bs-toggle="tooltip" title="{{ __('Run the escalation path for this log right now.') }}"><i class="mdi mdi-arrow-up-bold-circle-outline"></i><span>{{ __('Escalate now') }}</span></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div><div class="admin-empty-title">{{ __('No failed dispatches right now') }}</div><div class="admin-empty-subtitle">{{ __('New failed notification attempts will appear here when recovery is needed.') }}</div></div>
                    @endforelse
                </div>

                <h6 class="admin-section-title mb-1">{{ __('Failed WhatsApp provider logs') }}</h6>
                <div class="admin-section-subtitle mb-3">{{ __('Inspect provider-side failures separately so WhatsApp issues stay easy to isolate.') }}</div>
                <div class="d-grid gap-3">
                    @forelse($failedWhatsAppLogs as $log)
                        <div class="rounded-4 border p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ optional($log->order)->order_number ?? __('Unknown order') }}</div>
                                    <div class="admin-helper-text">{{ $log->message_type }} • {{ $log->normalized_phone ?: $log->phone }}</div>
                                    <div class="small text-danger mt-2">{{ $log->error_message ?: __('WhatsApp provider log failed.') }}</div>
                                </div>
                                <form method="POST" action="{{ route('admin.settings.notifications.retry-whatsapp-log', $log) }}" data-submit-loading data-confirm-title="{{ __('Retry WhatsApp send') }}" data-confirm-message="{{ __('Retry this WhatsApp send now?') }}" data-confirm-subtitle="{{ __('A new WhatsApp attempt will be created only if the safety guard does not detect a duplicate active send.') }}" data-confirm-ok="{{ __('Retry send') }}" data-confirm-cancel="{{ __('Keep reviewing') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success" data-loading-text="{{ __('Retrying WhatsApp send...') }}" data-bs-toggle="tooltip" title="{{ __('Retry the linked WhatsApp notification immediately.') }}"><i class="mdi mdi-refresh"></i><span>{{ __('Retry send') }}</span></button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div><div class="admin-empty-title">{{ __('No failed WhatsApp logs right now') }}</div><div class="admin-empty-subtitle">{{ __('Provider-side WhatsApp failures will appear here when they need review.') }}</div></div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">{{ __('Admin Activity Timeline') }}</h4>
                        <div class="admin-helper-text">{{ __('Recent admin-side actions around orders, retries, and notification settings.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('Timeline') }}</span>
                </div>

                <div class="d-grid gap-3">
                    @forelse($activityTimeline as $activity)
                        <div class="rounded-4 border p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $activity->description }}</div>
                                    <div class="admin-helper-text mt-1">{{ optional($activity->adminUser)->name ?: __('System') }} • {{ ucfirst(str_replace('_', ' ', $activity->action)) }}</div>
                                </div>
                                <div class="text-end small text-muted">
                                    <div>{{ $activity->created_at?->format('Y-m-d H:i') }}</div>
                                    <div>{{ $activity->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div><div class="admin-empty-title">{{ __('No admin activity yet') }}</div><div class="admin-empty-subtitle">{{ __('Activity will start filling automatically after settings changes, retries, and order actions.') }}</div></div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

            </div>
        </details>
    </div>

