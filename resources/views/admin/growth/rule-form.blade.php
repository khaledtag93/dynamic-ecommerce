@extends('layouts.admin')

@section('title', $rule->exists ? __('Edit Rule') : __('Create Rule'))

@section('content')
<style>
.growth-form-page .growth-switch{display:flex;align-items:flex-start;gap:.85rem;flex-wrap:nowrap}
.growth-form-page .growth-switch .form-check-input{margin-top:.25rem;flex:0 0 auto}
.growth-form-page .growth-switch .form-check-label{display:flex;flex-direction:column;gap:.2rem;margin:0}
html[dir="rtl"] .growth-form-page .growth-switch{justify-content:flex-start}
.growth-form-page .row>[class*='col-'],.growth-form-page .admin-card,.growth-form-page .admin-card-body,.growth-form-page .admin-soft-note,.growth-form-page .admin-form-actions{min-width:0}
.growth-form-page .form-control,.growth-form-page .form-select,.growth-form-page textarea{max-width:100%}
.growth-form-page .admin-form-actions-buttons{display:flex;flex-wrap:wrap;gap:.75rem}
.growth-form-page .admin-form-actions-buttons .btn{min-width:0}
@media (max-width:767.98px){.growth-form-page .admin-form-actions-buttons .btn{width:100%}}
</style>

<div class="admin-page-shell growth-form-page">
    <x-admin.page-header
        :kicker="__('Automation rules')"
        :title="$rule->exists ? __('Edit Rule') : __('Create Rule')"
        :description="__('Define campaign eligibility rules with clearer audience, template, and timing controls.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Growth'), 'url' => route('admin.growth.index')],
            ['label' => __('Rules'), 'current' => true],
        ]"
    >
        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ __('Back to growth workspace') }}</a>
    </x-admin.page-header>

    <form method="POST" action="{{ $rule->exists ? route('admin.growth.rules.update', $rule) : route('admin.growth.rules.store') }}" data-submit-loading>
        @csrf
        @if($rule->exists)
            @method('PUT')
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="admin-card mb-4">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Rule definition') }}</h5>
                            <div class="admin-section-subtitle">{{ __('Set the rule identity, trigger behavior, and default targeting that determine when automation can run.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Name') }}</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $rule->name) }}" placeholder="{{ __('Example: Returning customer follow-up') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Rule key') }}</label>
                            <input type="text" name="rule_key" class="form-control" value="{{ old('rule_key', $rule->rule_key) }}" placeholder="returning_customer_followup">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">{{ __('Trigger type') }}</label>
                            <input type="text" name="trigger_type" class="form-control" value="{{ old('trigger_type', $rule->trigger_type) }}" placeholder="{{ __('Example: event') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">{{ __('Channel') }}</label>
                            <select name="channel" class="form-select">
                                <option value="in_app" @selected(old('channel', $rule->channel)==='in_app')>{{ __('In-app') }}</option>
                                <option value="email" @selected(old('channel', $rule->channel)==='email')>{{ __('Email') }}</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">{{ __('Audience type') }}</label>
                            <select name="audience_type" class="form-select">
                                <option value="user" @selected(old('audience_type', $rule->audience_type)==='user')>{{ __('User') }}</option>
                                <option value="session" @selected(old('audience_type', $rule->audience_type)==='session')>{{ __('Session') }}</option>
                                <option value="user_or_session" @selected(old('audience_type', $rule->audience_type)==='user_or_session')>{{ __('User or session') }}</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Audience segment') }}</label>
                            <select name="segment_id" class="form-select">
                                <option value="">{{ __('No segment') }}</option>
                                @foreach($segments as $segment)
                                    <option value="{{ $segment->id }}" @selected((string) old('segment_id', $rule->segment_id)===(string) $segment->id)>{{ $segment->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Template group') }}</label>
                            <select name="default_template_key" class="form-select">
                                <option value="">{{ __('No template') }}</option>
                                @foreach($templateGroups as $key => $locales)
                                    <option value="{{ $key }}" @selected(old('default_template_key', $rule->default_template_key)===$key)>{{ $key }} ({{ $locales }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="admin-card mb-4">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Message fallback') }}</h5>
                            <div class="admin-section-subtitle">{{ __('Use these values when the selected template is missing or when you want a direct rule-level message.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Subject') }}</label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject', $rule->subject) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Coupon code') }}</label>
                            <input type="text" name="coupon_code" class="form-control" value="{{ old('coupon_code', $rule->coupon_code) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">{{ __('Rule message') }}</label>
                            <textarea name="message" class="form-control" rows="4">{{ old('message', $rule->message) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Eligibility thresholds') }}</h5>
                            <div class="admin-section-subtitle">{{ __('Tune the delay, cooldown, and scoring thresholds that decide whether the rule can fire.') }}</div>
                        </div>

                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Priority') }}</label><input type="number" name="priority" class="form-control" value="{{ old('priority', $rule->priority ?? 100) }}"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Delay minutes') }}</label><input type="number" name="delay_minutes" class="form-control" value="{{ old('delay_minutes', data_get($rule->config, 'delay_minutes')) }}"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Cooldown hours') }}</label><input type="number" name="cooldown_hours" class="form-control" value="{{ old('cooldown_hours', data_get($rule->config, 'cooldown_hours')) }}"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('View threshold') }}</label><input type="number" name="view_threshold" class="form-control" value="{{ old('view_threshold', data_get($rule->config, 'view_threshold')) }}"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Lookback days') }}</label><input type="number" name="lookback_days" class="form-control" value="{{ old('lookback_days', data_get($rule->config, 'lookback_days')) }}"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Lookback hours') }}</label><input type="number" name="lookback_hours" class="form-control" value="{{ old('lookback_hours', data_get($rule->config, 'lookback_hours')) }}"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Minimum LTV score') }}</label><input type="number" step="0.01" name="minimum_ltv_score" class="form-control" value="{{ old('minimum_ltv_score', data_get($rule->config, 'minimum_ltv_score')) }}"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Minimum churn risk') }}</label><input type="number" step="0.01" name="minimum_churn_risk" class="form-control" value="{{ old('minimum_churn_risk', data_get($rule->config, 'minimum_churn_risk')) }}"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Minimum days since order') }}</label><input type="number" name="minimum_days_since_order" class="form-control" value="{{ old('minimum_days_since_order', data_get($rule->config, 'minimum_days_since_order')) }}"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Maximum days since order') }}</label><input type="number" name="maximum_days_since_order" class="form-control" value="{{ old('maximum_days_since_order', data_get($rule->config, 'maximum_days_since_order')) }}"></div>

                        <div class="col-12">
                            <div class="form-check form-switch growth-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="rule_active" @checked(old('is_active', $rule->is_active))>
                                <label class="form-check-label" for="rule_active">
                                    <span class="admin-inline-title">{{ __('Rule active') }}</span>
                                    <span class="admin-helper-text">{{ __('Allow this rule to be considered during live automation checks.') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="admin-card mb-4">
                    <div class="admin-card-body">
                        <h5 class="admin-section-title mb-3">{{ __('Rule design notes') }}</h5>
                        <div class="d-grid gap-3">
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Reuse segments where possible') }}</div>
                                <div class="admin-helper-text">{{ __('Shared segments reduce duplication and keep targeting logic easier to maintain across multiple campaigns.') }}</div>
                            </div>
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Use direct copy carefully') }}</div>
                                <div class="admin-helper-text">{{ __('If a template group is available, keep it as the primary source and use the fallback text for safe recovery only.') }}</div>
                            </div>
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Watch threshold conflicts') }}</div>
                                <div class="admin-helper-text">{{ __('Strict timing plus high scoring requirements can make the rule appear inactive even when the trigger is correct.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <div class="admin-form-actions-copy">
                        <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                        <div class="admin-form-actions-subtitle">{{ __('Review the targeting and timing details, then save this rule to the growth workspace when you are ready.') }}</div>
                    </div>
                    <div class="admin-form-actions-buttons">
                        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ $rule->exists ? __('Discard changes') : __('Cancel setup') }}</a>
                        <button class="btn btn-primary" data-loading-text="{{ $rule->exists ? __('Saving rule changes...') : __('Creating rule...') }}">{{ $rule->exists ? __('Save changes') : __('Create rule') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
