@extends('admin.growth.layout')

@section('growth-module-content')
<div class="gm-panel">
    <div class="gm-section"><div><h4>{{ __('Live validation mode') }}</h4><div class="gm-mini">{{ __('Generate safe demo customers, orders, and behavior events for realistic validation.') }}</div></div></div>
    <div class="gm-two">
        <div class="gm-box"><strong>{{ __('What gets generated') }}</strong><div class="gm-help mt-2">{{ __('Cart recovery, abandoned checkout, repeat buyer, high-intent, at-risk, and VIP validation profiles are inserted with tagged demo records only.') }}</div></div>
        <div class="gm-box"><strong>{{ __('Safe workflow') }}</strong><div class="gm-help mt-2">{{ __('Keep messaging OFF, seed the demo dataset, run the engine, then inspect predictive scores, trigger logs, and deliveries before enabling customer-facing sends.') }}</div></div>
    </div>
    <div class="gm-actions mt-3">
        <form method="POST" action="{{ route('admin.growth.validation-demo.seed') }}" data-submit-loading>@csrf <button class="btn btn-primary">{{ __('Seed validation demo data') }}</button></form>
        <form method="POST" action="{{ route('admin.growth.run-now') }}" data-submit-loading>@csrf <button class="btn btn-outline-primary">{{ __('Run engine after seeding') }}</button></form>
        <form method="POST" action="{{ route('admin.growth.validation-demo.clear') }}" data-submit-loading>@csrf @method('DELETE') <button class="btn btn-outline-danger">{{ __('Remove demo data') }}</button></form>
    </div>
    <div class="gm-alert mt-3">{{ __('CLI alternative: php artisan growth:seed-demo ثم php artisan growth:run. ولو عايز تمسح الداتا التجريبية استخدم php artisan growth:seed-demo --clear') }}</div>
</div>

<div class="gm-two">
    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Recent deliveries') }}</h4><div class="gm-mini">{{ __('Queue and send activity.') }}</div></div></div>
        @if($deliveries->isEmpty())<div class="gm-empty">{{ __('No deliveries yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th></th></tr></thead><tbody>
        @foreach($deliveries->take(15) as $delivery)
        <tr><td><strong>{{ $delivery->campaign?->name ?? '—' }}</strong></td><td>{{ $delivery->user?->name ?? __('Guest') }}</td><td>{{ $deliveryStatusLabels[$delivery->status] ?? ucfirst((string) $delivery->status) }}</td><td>{{ optional($delivery->created_at)->format('Y-m-d H:i') }}</td><td>@if($delivery->status === 'failed')<form method="POST" action="{{ route('admin.growth.deliveries.retry', $delivery) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-warning">{{ __('Retry') }}</button></form>@endif</td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>

    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Recent trigger log') }}</h4><div class="gm-mini">{{ __('Latest automation triggers.') }}</div></div></div>
        @if($triggerLogs->isEmpty())<div class="gm-empty">{{ __('No trigger logs yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Event') }}</th><th>{{ __('Triggered at') }}</th></tr></thead><tbody>
        @foreach($triggerLogs->take(12) as $log)
        <tr><td>{{ $log->campaign?->name ?? '—' }}</td><td>{{ $log->user?->name ?? __('Guest') }}</td><td>{{ $log->trigger_event ?? '—' }}</td><td>{{ optional($log->triggered_at)->format('Y-m-d H:i') }}</td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>
</div>

<div class="gm-panel">
    <div class="gm-section"><div><h4>{{ __('Recent message log') }}</h4><div class="gm-mini">{{ __('Customer-facing messages and experiment participation.') }}</div></div></div>
    @if($messageLogs->isEmpty())<div class="gm-empty">{{ __('No message logs yet.') }}</div>@else
    <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Experiment') }}</th><th>{{ __('Sent at') }}</th></tr></thead><tbody>
    @foreach($messageLogs->take(15) as $log)
    <tr><td>{{ $log->campaign?->name ?? '—' }}</td><td>{{ $log->user?->name ?? __('Guest') }}</td><td>{{ strtoupper((string) ($log->channel ?? '—')) }}</td><td>{{ $log->experiment?->name ?? '—' }}</td><td>{{ optional($log->sent_at)->format('Y-m-d H:i') }}</td></tr>
    @endforeach
    </tbody></table></div>@endif
</div>
@endsection
