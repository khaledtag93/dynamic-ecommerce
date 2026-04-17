    <div id="notification-overview" class="notification-section-title">
        <div>
            <div class="notification-section-kicker">{{ __('Summary') }}</div>
            <h3 class="admin-section-title mb-1">{{ __('Notification overview') }}</h3>
            <p class="admin-section-subtitle mb-0">{{ __('Start with the big picture: total traffic, channel health, pending workload, and the key items that may need admin attention.') }}</p>
        </div>
        <span class="admin-chip">{{ __('Read first') }}</span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-bell-ring-outline"></i></span>
                <div class="admin-stat-label">{{ __('Database notifications') }}</div>
                <div class="admin-stat-value">{{ number_format($summary['database_total'] ?? 0) }}</div>
                <div class="text-muted small mt-2">{{ __('Unread: :count', ['count' => number_format($summary['database_unread'] ?? 0)]) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-send-check-outline"></i></span>
                <div class="admin-stat-label">{{ __('Dispatch logs') }}</div>
                <div class="admin-stat-value">{{ number_format($summary['dispatch_total'] ?? 0) }}</div>
                <div class="text-muted small mt-2">{{ __('Sent: :sent • Failed: :failed', ['sent' => number_format($summary['dispatch_sent'] ?? 0), 'failed' => number_format($summary['dispatch_failed'] ?? 0)]) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-whatsapp"></i></span>
                <div class="admin-stat-label">{{ __('WhatsApp logs') }}</div>
                <div class="admin-stat-value">{{ number_format($summary['whatsapp_total'] ?? 0) }}</div>
                <div class="text-muted small mt-2">{{ __('Sent: :count', ['count' => number_format($summary['whatsapp_sent'] ?? 0)]) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-tune-vertical-variant"></i></span>
                <div class="admin-stat-label">{{ __('Engine status') }}</div>
                <div class="admin-stat-value">{{ (old('notification_center_enabled', $settings['notification_center_enabled'] ?? '1') === '1') ? __('Enabled') : __('Disabled') }}</div>
                <div class="text-muted small mt-2">{{ __('Pending dispatches: :count', ['count' => number_format($summary['dispatch_pending'] ?? 0)]) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-heart-pulse"></i></span>
                <div class="admin-stat-label">{{ __('Queue health') }}</div>
                <div class="admin-stat-value">{{ $monitoring['queue']['health_score'] ?? 0 }}%</div>
                <div class="text-muted small mt-2">{{ $healthLabels[$monitoring['queue']['health_status'] ?? 'healthy'] ?? ucfirst($monitoring['queue']['health_status'] ?? 'healthy') }} • {{ __('Driver: :driver', ['driver' => strtoupper($monitoring['queue']['driver'] ?? 'sync')]) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-timer-sand"></i></span>
                <div class="admin-stat-label">{{ __('Queued notifications') }}</div>
                <div class="admin-stat-value">{{ number_format($monitoring['queue']['pending_notification_total'] ?? 0) }}</div>
                <div class="text-muted small mt-2">{{ __('Stale jobs: :count', ['count' => number_format($monitoring['queue']['stale_total'] ?? 0)]) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-alert-circle-outline"></i></span>
                <div class="admin-stat-label">{{ __('Failed queue jobs') }}</div>
                <div class="admin-stat-value">{{ number_format($monitoring['queue']['failed_notification_total'] ?? 0) }}</div>
                <div class="text-muted small mt-2">{{ __('Last 24h: :count', ['count' => number_format($monitoring['queue']['failed_last_24h'] ?? 0)]) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-chart-line"></i></span>
                <div class="admin-stat-label">{{ __('24h dispatch success') }}</div>
                <div class="admin-stat-value">{{ $monitoring['dispatchTrend']['success_rate'] ?? 100 }}%</div>
                <div class="text-muted small mt-2">{{ __('Test sends: :count', ['count' => number_format($monitoring['dispatchTrend']['last_24h_test_sends'] ?? 0)]) }}</div>
            </div>
        </div>
    </div>

