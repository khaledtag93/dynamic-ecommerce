@extends('admin.growth.layout')

@section('growth-module-content')
@php
    $countCards = [
        ['label' => __('Active campaigns'), 'value' => $campaigns->where('is_active', true)->count(), 'hint' => __('Live journeys currently ready to trigger and deliver.')],
        ['label' => __('Automation rules'), 'value' => $rules->count(), 'hint' => __('Eligibility, delays, cooldowns, and trigger logic in one place.')],
        ['label' => __('Templates'), 'value' => $templates->count(), 'hint' => __('Reusable localized copy across channels and campaigns.')],
        ['label' => __('Pending deliveries'), 'value' => $deliveries->where('status', 'pending')->count(), 'hint' => __('Queued or waiting sends that still need delivery execution.')],
    ];
@endphp
<div class="gm-grid">
    <div class="gm-card"><div class="gm-mini">{{ __('Attributed revenue') }}</div><div class="gm-value">{{ number_format((float) ($attributionSummary['attributed_revenue'] ?? 0), 2) }}</div><div class="gm-help">{{ __('Revenue matched back to growth deliveries inside the attribution window.') }}</div></div>
    <div class="gm-card"><div class="gm-mini">{{ __('30d revenue lift') }}</div><div class="gm-value">{{ number_format((float) ($attributionSummary['lift_revenue_30d'] ?? 0), 2) }}</div><div class="gm-help">{{ __('Recent revenue tied to growth sends over the last 30 days.') }}</div></div>
    <div class="gm-card"><div class="gm-mini">{{ __('Avg churn risk') }}</div><div class="gm-value">{{ number_format((float) ($predictiveSummary['avg_churn_risk'] ?? 0), 2) }}</div><div class="gm-help">{{ __('Higher values mean stronger win-back urgency.') }}</div></div>
    <div class="gm-card"><div class="gm-mini">{{ __('Avg 90d retention') }}</div><div class="gm-value">{{ number_format((float) ($cohortSummary['average_retention_90d'] ?? 0), 2) }}%</div><div class="gm-help">{{ __('Average 90-day repeat rate across the latest cohorts.') }}</div></div>
</div>

<div class="gm-two">
    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Workspace summary') }}</h4><div class="gm-mini">{{ __('The fastest summary of what is live right now.') }}</div></div></div>
        <div class="gm-list">
            @foreach($countCards as $item)
                <div class="gm-item">
                    <div><strong>{{ $item['label'] }}</strong><div class="gm-help">{{ $item['hint'] }}</div></div>
                    <div class="gm-value" style="font-size:24px">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Quick actions') }}</h4><div class="gm-mini">{{ __('Move straight into the most common growth tasks.') }}</div></div></div>
        <div class="gm-actions">
            <a href="{{ route('admin.growth.campaigns.create') }}" class="btn btn-primary">{{ __('Create campaign') }}</a>
            <a href="{{ route('admin.growth.rules.create') }}" class="btn btn-outline-primary">{{ __('Create rule') }}</a>
            <a href="{{ route('admin.growth.templates.create') }}" class="btn btn-outline-dark">{{ __('Create template') }}</a>
            <a href="{{ route('admin.growth.segments.create') }}" class="btn btn-outline-secondary">{{ __('Create segment') }}</a>
            <a href="{{ route('admin.growth.operations') }}" class="btn btn-outline-warning">{{ __('Open operations') }}</a>
            <a href="{{ route('admin.growth.insights') }}" class="btn btn-outline-success">{{ __('Open insights') }}</a>
        </div>
        <div class="gm-alert mt-3">{{ __('Overview is now intentionally lighter: controls stay here, while journeys, operations, and analytics each have their own page.') }}</div>
    </div>
</div>

<div class="gm-two">
    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Engine controls') }}</h4><div class="gm-mini">{{ __('Master toggles for automation, messaging, experiments, and email behavior.') }}</div></div></div>
        <form method="POST" action="{{ route('admin.growth.settings.update') }}" class="gm-stack" data-submit-loading>
            @csrf
            @method('PUT')
            <label class="gm-box d-flex justify-content-between align-items-center gap-3"><span><strong>{{ __('Enable growth engine') }}</strong><div class="gm-help">{{ __('Behavior detection, segmentation, and trigger generation stay active.') }}</div></span><input type="checkbox" name="growth_engine_enabled" value="1" @checked($engineOn)></label>
            <label class="gm-box d-flex justify-content-between align-items-center gap-3"><span><strong>{{ __('Enable campaign messaging') }}</strong><div class="gm-help">{{ __('Keep OFF to observe logs only.') }}</div></span><input type="checkbox" name="growth_messaging_enabled" value="1" @checked($messagingOn)></label>
            <label class="gm-box d-flex justify-content-between align-items-center gap-3"><span><strong>{{ __('Enable real email sending') }}</strong><div class="gm-help">{{ __('When OFF, email deliveries are simulated only.') }}</div></span><input type="checkbox" name="growth_real_email_enabled" value="1" @checked($realEmailOn)></label>
            <label class="gm-box d-flex justify-content-between align-items-center gap-3"><span><strong>{{ __('Enable offer experiments') }}</strong><div class="gm-help">{{ __('Allow active A/B variants to affect campaign copy and offers.') }}</div></span><input type="checkbox" name="growth_experiments_enabled" value="1" @checked($experimentsOn)></label>
            <div><button class="btn btn-primary">{{ __('Save growth settings changes') }}</button></div>
        </form>
    </div>

    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Activation checklist') }}</h4><div class="gm-mini">{{ __('A calmer checklist instead of keeping all heavy details on one screen.') }}</div></div></div>
        <div class="gm-stack">
            <div class="gm-box"><strong>{{ __('1. Confirm the engine status') }}</strong><div class="gm-help">{{ __('Enable the engine first, then decide whether live messaging should stay off or on.') }}</div></div>
            <div class="gm-box"><strong>{{ __('2. Build the journeys') }}</strong><div class="gm-help">{{ __('Use the content page for campaigns, rules, templates, segments, and experiments.') }}</div></div>
            <div class="gm-box"><strong>{{ __('3. Validate safely') }}</strong><div class="gm-help">{{ __('Seed demo data and run the engine from Operations before exposing customer-facing sends.') }}</div></div>
            <div class="gm-box"><strong>{{ __('4. Review impact') }}</strong><div class="gm-help">{{ __('Open Insights to read revenue attribution, retention, churn risk, and learning performance.') }}</div></div>
        </div>
    </div>
</div>
@endsection
