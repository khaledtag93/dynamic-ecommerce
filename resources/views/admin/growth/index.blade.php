@extends('admin.growth.layout')

@section('growth-module-content')
@php
    $activeCampaigns = $campaigns->where('is_active', true)->count();
    $pendingDeliveries = $deliveries->where('status', 'pending')->count();
    $activeRuleKeys = $rules->where('is_active', true)->pluck('rule_key')->filter();
    $campaignsMissingRule = $campaigns
        ->where('is_active', true)
        ->filter(fn ($campaign) => ! $activeRuleKeys->contains($campaign->campaign_key))
        ->count();
@endphp

<div class="gm-grid">
    <x-admin.stat-card
        :label="__('Attributed revenue')"
        :value="number_format((float) ($attributionSummary['attributed_revenue'] ?? 0), 2)"
        icon="mdi-cash-multiple"
        tone="success"
        :help="__('Revenue matched back to growth activity inside the attribution window.')" />
    <x-admin.stat-card
        :label="__('30d revenue lift')"
        :value="number_format((float) ($attributionSummary['lift_revenue_30d'] ?? 0), 2)"
        icon="mdi-trending-up"
        :help="__('Recent revenue linked to growth activity over the last 30 days.')" />
    <x-admin.stat-card
        :label="__('Average churn risk')"
        :value="number_format((float) ($predictiveSummary['avg_churn_risk'] ?? 0), 2)"
        icon="mdi-account-alert-outline"
        tone="warning"
        :help="__('Higher values mean win-back journeys may need attention sooner.')" />
    <x-admin.stat-card
        :label="__('Average 90d retention')"
        :value="number_format((float) ($cohortSummary['average_retention_90d'] ?? 0), 2).'%'"
        icon="mdi-account-heart-outline"
        tone="success"
        :help="__('Average 90-day repeat rate across recent customer cohorts.')" />
</div>

<div class="gm-two">
    <section class="gm-panel">
        <div class="gm-section">
            <div>
                <h4>{{ __('Workspace health') }}</h4>
                <div class="gm-mini">{{ __('A quick view of what is configured and waiting for attention.') }}</div>
            </div>
        </div>
        <div class="gm-stack">
            @foreach([
                [__('Active campaigns'), $activeCampaigns, __('Journeys currently enabled and ready to trigger.')],
                [__('Automation rules'), $rules->count(), __('Rules controlling eligibility, timing, priority, and cooldowns.')],
                [__('Templates'), $templates->count(), __('Reusable localized message content.')],
                [__('Pending deliveries'), $pendingDeliveries, __('Messages still waiting to be processed or sent.')],
                [__('Campaigns needing a rule'), $campaignsMissingRule, __('Active campaigns that do not have an active linked automation rule.')],
            ] as [$label, $value, $hint])
                <div class="gm-box d-flex justify-content-between gap-3 align-items-start">
                    <div><strong>{{ $label }}</strong><div class="gm-help">{{ $hint }}</div></div>
                    <div class="gm-value" style="font-size:24px">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        @if($campaignsMissingRule > 0)
            <div class="gm-alert gm-alert--warning mt-3">
                {{ __('Some active campaigns cannot run because they do not have an active linked rule. Open Content & Journeys to fix the linkage.') }}
            </div>
        @endif
    </section>

    <section class="gm-panel">
        <div class="gm-section">
            <div>
                <h4>{{ __('Quick actions') }}</h4>
                <div class="gm-mini">{{ __('Go straight to the task you need without searching through the whole module.') }}</div>
            </div>
        </div>
        <div class="gm-actions">
            <a href="{{ route('admin.growth.campaigns.create') }}" class="btn btn-primary">{{ __('Create campaign') }}</a>
            <a href="{{ route('admin.growth.rules.create') }}" class="btn btn-outline-primary">{{ __('Create rule') }}</a>
            <a href="{{ route('admin.growth.templates.create') }}" class="btn btn-outline-dark">{{ __('Create template') }}</a>
            <a href="{{ route('admin.growth.segments.create') }}" class="btn btn-outline-secondary">{{ __('Create segment') }}</a>
            <a href="{{ route('admin.growth.operations') }}" class="btn btn-outline-warning">{{ __('Open operations') }}</a>
            <a href="{{ route('admin.growth.insights') }}" class="btn btn-outline-success">{{ __('Open insights') }}</a>
        </div>
        <div class="gm-alert mt-3">{{ __('Keep setup, operations, and analytics separate so each page stays easy to scan.') }}</div>
    </section>
</div>

<div class="gm-two">
    <section class="gm-panel">
        <div class="gm-section">
            <div>
                <h4>{{ __('Engine controls') }}</h4>
                <div class="gm-mini">{{ __('Turn major Growth capabilities on or off without changing campaign setup.') }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.growth.settings.update') }}" class="gm-toggle-list" data-growth-async>
            @csrf
            @method('PUT')

            <input type="hidden" name="growth_engine_enabled" value="0">
            <div class="gm-setting-row">
                <div class="gm-setting-copy">
                    <label class="gm-setting-title" for="growth-engine-enabled">{{ __('Enable growth engine') }}</label>
                    <div class="admin-helper-text">{{ __('Runs behavior detection, segmentation, and trigger processing.') }}</div>
                </div>
                <div class="form-check form-switch gm-setting-switch">
                    <input class="form-check-input" type="checkbox" name="growth_engine_enabled" value="1" id="growth-engine-enabled" @checked($engineOn)>
                </div>
            </div>

            <input type="hidden" name="growth_messaging_enabled" value="0">
            <div class="gm-setting-row">
                <div class="gm-setting-copy">
                    <label class="gm-setting-title" for="growth-messaging-enabled">{{ __('Enable campaign messaging') }}</label>
                    <div class="admin-helper-text">{{ __('Keep this off while validating journeys that should not contact customers yet.') }}</div>
                </div>
                <div class="form-check form-switch gm-setting-switch">
                    <input class="form-check-input" type="checkbox" name="growth_messaging_enabled" value="1" id="growth-messaging-enabled" @checked($messagingOn)>
                </div>
            </div>

            <input type="hidden" name="growth_real_email_enabled" value="0">
            <div class="gm-setting-row">
                <div class="gm-setting-copy">
                    <label class="gm-setting-title" for="growth-email-enabled">{{ __('Enable real email sending') }}</label>
                    <div class="admin-helper-text">{{ __('When disabled, email behavior can be tested without sending a real email.') }}</div>
                </div>
                <div class="form-check form-switch gm-setting-switch">
                    <input class="form-check-input" type="checkbox" name="growth_real_email_enabled" value="1" id="growth-email-enabled" @checked($realEmailOn)>
                </div>
            </div>

            <input type="hidden" name="growth_experiments_enabled" value="0">
            <div class="gm-setting-row">
                <div class="gm-setting-copy">
                    <label class="gm-setting-title" for="growth-experiments-enabled">{{ __('Enable offer experiments') }}</label>
                    <div class="admin-helper-text">{{ __('Allows active A/B variants to affect campaign content and offers.') }}</div>
                </div>
                <div class="form-check form-switch gm-setting-switch">
                    <input class="form-check-input" type="checkbox" name="growth_experiments_enabled" value="1" id="growth-experiments-enabled" @checked($experimentsOn)>
                </div>
            </div>

            <div class="pt-1">
                <button class="btn btn-primary" data-loading-text="{{ __('Saving changes...') }}">{{ __('Save growth settings changes') }}</button>
            </div>
        </form>
    </section>

    <section class="gm-panel">
        <div class="gm-section">
            <div>
                <h4>{{ __('Recommended workflow') }}</h4>
                <div class="gm-mini">{{ __('A simple order for setting up Growth safely.') }}</div>
            </div>
        </div>
        <div class="gm-stack">
            <div class="gm-box"><strong>{{ __('1. Check the engine') }}</strong><div class="gm-help">{{ __('Enable the engine first. Leave customer messaging off until the journey is ready.') }}</div></div>
            <div class="gm-box"><strong>{{ __('2. Build the journey') }}</strong><div class="gm-help">{{ __('Create the campaign, then add only the rules, templates, segments, or experiments it needs.') }}</div></div>
            <div class="gm-box"><strong>{{ __('3. Validate safely') }}</strong><div class="gm-help">{{ __('Use Operations to inspect triggers and delivery behavior before enabling real sends.') }}</div></div>
            <div class="gm-box"><strong>{{ __('4. Measure the result') }}</strong><div class="gm-help">{{ __('Use Insights to review revenue, retention, experiments, and customer risk.') }}</div></div>
        </div>
    </section>
</div>
@endsection
