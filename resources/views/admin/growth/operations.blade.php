@extends('admin.growth.layout')

@section('growth-module-content')
<div class="gm-grid gm-grid--three">
    <x-admin.stat-card
        :label="__('Pending deliveries')"
        :value="(int) ($operationsSummary['pending_deliveries'] ?? 0)"
        icon="mdi-clock-outline"
        tone="warning"
        :help="__('Messages still waiting for delivery processing.')" />
    <x-admin.stat-card
        :label="__('Failed deliveries')"
        :value="(int) ($operationsSummary['failed_deliveries'] ?? 0)"
        icon="mdi-alert-circle-outline"
        tone="danger"
        :help="__('Failed sends that may need investigation or retry.')" />
    <x-admin.stat-card
        :label="__('Trigger records')"
        :value="(int) ($operationsSummary['trigger_records'] ?? 0)"
        icon="mdi-lightning-bolt-outline"
        :help="__('Tracked automation trigger records available for review.')" />
</div>

@if(app()->environment('local', 'testing', 'staging'))
<details class="gm-panel gm-module">
    <summary>
        <div class="gm-module-summary">
            <span class="gm-module-icon"><i class="mdi mdi-test-tube"></i></span>
            <div><div class="gm-module-title">{{ __('Test-data tools') }}</div><div class="gm-mini">{{ __('Available only outside Production for safe Growth validation.') }}</div></div>
        </div>
        <span class="gm-count">{{ __('Test only') }}</span>
    </summary>
    <div class="gm-module-body">
        <div class="gm-two mt-3">
            <div class="gm-box"><strong>{{ __('What gets generated') }}</strong><div class="gm-help mt-2">{{ __('Creates clearly tagged test customers, orders, and behavior events for cart recovery, repeat-buyer, high-intent, at-risk, and VIP scenarios.') }}</div></div>
            <div class="gm-box"><strong>{{ __('Safe workflow') }}</strong><div class="gm-help mt-2">{{ __('Keep customer messaging off, create the test data, run the engine, then review scores, triggers, and deliveries before enabling real sends.') }}</div></div>
        </div>
        <div class="gm-actions mt-3">
            <form method="POST" action="{{ route('admin.growth.validation-demo.seed') }}" data-submit-loading>@csrf <button class="btn btn-primary" data-loading-text="{{ __('Creating test data...') }}">{{ __('Create test data') }}</button></form>
            <form method="POST" action="{{ route('admin.growth.run-now') }}" data-submit-loading>@csrf <button class="btn btn-outline-primary" data-loading-text="{{ __('Running engine...') }}">{{ __('Run engine now') }}</button></form>
            <form method="POST" action="{{ route('admin.growth.validation-demo.clear') }}"
                  data-submit-loading
                  data-confirm-title="{{ __('Remove test data') }}"
                  data-confirm-message="{{ __('Remove all Growth validation test data?') }}"
                  data-confirm-subtitle="{{ __('This removes the tagged test customers, orders, and behavior events created for Growth validation. Production customer data is not part of this test-data cleanup.') }}"
                  data-confirm-ok="{{ __('Remove test data') }}"
                  data-confirm-cancel="{{ __('Keep test data') }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger" data-loading-text="{{ __('Removing test data...') }}">{{ __('Remove test data') }}</button>
            </form>
        </div>
    </div>
</details>
@endif

<div class="gm-section-heading">
    <div>
        <h3>{{ __('Delivery activity') }}</h3>
        <p>{{ __('Review delivery status first, then investigate trigger and message history only when you need more detail.') }}</p>
    </div>
</div>

<section class="gm-panel">
    <div class="gm-section"><div><h4>{{ __('Recent deliveries') }}</h4><div class="gm-mini">{{ __('Latest queue and send activity.') }}</div></div></div>
    @if($deliveries->isEmpty())
        <div class="gm-empty">{{ __('No deliveries yet.') }}</div>
    @else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
        @foreach($deliveries as $delivery)
            <tr>
                <td><strong>{{ $delivery->campaign?->name ?? '—' }}</strong></td>
                <td>{{ $delivery->user?->name ?? __('Guest') }}</td>
                <td><span data-growth-state>{{ $deliveryStatusLabels[$delivery->status] ?? ucfirst((string) $delivery->status) }}</span></td>
                <td>{{ optional($delivery->created_at)->format('Y-m-d H:i') }}</td>
                <td>@if($delivery->status === 'failed')<form method="POST" action="{{ route('admin.growth.deliveries.retry', $delivery) }}" data-growth-async data-growth-retry>@csrf @method('PATCH')<button class="btn btn-sm btn-outline-warning" data-growth-action>{{ __('Retry') }}</button></form>@else<span class="gm-mini">—</span>@endif</td>
            </tr>
        @endforeach
        </tbody></table></div>
        @if(method_exists($deliveries, 'hasPages') && $deliveries->hasPages())
            <div class="mt-3">{{ $deliveries->fragment('growth-deliveries')->links() }}</div>
        @endif
    @endif
</section>

<div class="gm-two">
    <section class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Recent trigger log') }}</h4><div class="gm-mini">{{ __('Why and when automation rules fired.') }}</div></div></div>
        @if($triggerLogs->isEmpty())
            <div class="gm-empty">{{ __('No trigger logs yet.') }}</div>
        @else
            <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Event') }}</th><th>{{ __('Triggered at') }}</th></tr></thead><tbody>
            @foreach($triggerLogs as $log)
                <tr><td>{{ $log->campaign?->name ?? '—' }}</td><td>{{ $log->user?->name ?? __('Guest') }}</td><td>{{ $log->trigger_event ?? '—' }}</td><td>{{ optional($log->triggered_at)->format('Y-m-d H:i') }}</td></tr>
            @endforeach
            </tbody></table></div>
            @if(method_exists($triggerLogs, 'hasPages') && $triggerLogs->hasPages())
                <div class="mt-3">{{ $triggerLogs->fragment('growth-trigger-log')->links() }}</div>
            @endif
        @endif
    </section>

    <section class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Recent message log') }}</h4><div class="gm-mini">{{ __('Customer-facing message history and experiment participation.') }}</div></div></div>
        @if($messageLogs->isEmpty())
            <div class="gm-empty">{{ __('No message logs yet.') }}</div>
        @else
            <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Experiment') }}</th><th>{{ __('Sent at') }}</th></tr></thead><tbody>
            @foreach($messageLogs as $log)
                <tr><td>{{ $log->campaign?->name ?? '—' }}</td><td>{{ $log->user?->name ?? __('Guest') }}</td><td>{{ strtoupper((string) ($log->channel ?? '—')) }}</td><td>{{ $log->experiment?->name ?? '—' }}</td><td>{{ optional($log->sent_at)->format('Y-m-d H:i') }}</td></tr>
            @endforeach
            </tbody></table></div>
            @if(method_exists($messageLogs, 'hasPages') && $messageLogs->hasPages())
                <div class="mt-3">{{ $messageLogs->fragment('growth-message-log')->links() }}</div>
            @endif
        @endif
    </section>
</div>
@endsection
