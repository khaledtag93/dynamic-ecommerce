@extends('layouts.admin')

@section('title', $campaign->exists ? __('Edit Campaign') : __('Create Campaign'))

@section('content')
<style>
.growth-form-page .growth-switch{display:flex;align-items:flex-start;gap:.85rem;flex-wrap:nowrap}
.growth-form-page .growth-switch .form-check-input{margin-top:.25rem;flex:0 0 auto}
.growth-form-page .growth-switch .form-check-label{display:flex;flex-direction:column;gap:.2rem;margin:0}
.growth-form-page .growth-hint-list{display:grid;gap:.75rem}
.growth-form-page .growth-hint-item{padding:.85rem 1rem;border:1px solid var(--admin-border);border-radius:1rem;background:color-mix(in srgb,var(--admin-surface) 94%,white)}
.growth-form-page .growth-hint-item strong{display:block;margin-bottom:.2rem}
html[dir="rtl"] .growth-form-page .growth-switch{justify-content:flex-start}
.growth-form-page .row>[class*='col-'],.growth-form-page .admin-card,.growth-form-page .admin-card-body,.growth-form-page .admin-soft-note,.growth-form-page .admin-form-actions{min-width:0}
.growth-form-page .form-control,.growth-form-page .form-select,.growth-form-page textarea{max-width:100%}
.growth-form-page .admin-form-actions-buttons{display:flex;flex-wrap:wrap;gap:.75rem}
.growth-form-page .admin-form-actions-buttons .btn{min-width:0}
@media (max-width:767.98px){.growth-form-page .admin-form-actions-buttons .btn{width:100%}}
</style>

<div class="admin-page-shell growth-form-page">
    <x-admin.page-header
        :kicker="__('Growth campaigns')"
        :title="$campaign->exists ? __('Edit Campaign') : __('Create Campaign')"
        :description="__('Configure campaign targeting, fallback copy, and timing rules in one cleaner workflow without affecting the live engine unexpectedly.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Growth'), 'url' => route('admin.growth.index')],
            ['label' => __('Campaigns'), 'current' => true],
        ]"
    >
        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ __('Back to growth workspace') }}</a>
    </x-admin.page-header>

    <form method="POST" action="{{ $campaign->exists ? route('admin.growth.campaigns.update', $campaign) : route('admin.growth.campaigns.store') }}" data-submit-loading>
        @csrf
        @if($campaign->exists)
            @method('PUT')
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="admin-card mb-4">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Core campaign setup') }}</h5>
                            <div class="admin-section-subtitle">{{ __('Define the internal identity, trigger, delivery channel, and default audience for this campaign.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Name') }}</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $campaign->name) }}" placeholder="{{ __('Example: Cart recovery reminder') }}" required>
                            <div class="form-text">{{ __('Use a clear internal name so the team can identify this campaign quickly.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Campaign key') }}</label>
                            <input type="text" name="campaign_key" class="form-control" value="{{ old('campaign_key', $campaign->campaign_key) }}" placeholder="cart_recovery">
                            <div class="form-text">{{ __('Keep this key stable because automation logic may depend on it later.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Campaign type') }}</label>
                            <input type="text" name="campaign_type" class="form-control" value="{{ old('campaign_type', $campaign->campaign_type) }}" placeholder="{{ __('Example: retention') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Trigger event') }}</label>
                            <input type="text" name="trigger_event" class="form-control" value="{{ old('trigger_event', $campaign->trigger_event) }}" placeholder="add_to_cart">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">{{ __('Channel') }}</label>
                            <select name="channel" class="form-select">
                                <option value="in_app" @selected(old('channel', $campaign->channel)==='in_app')>{{ __('In-app') }}</option>
                                <option value="email" @selected(old('channel', $campaign->channel)==='email')>{{ __('Email') }}</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">{{ __('Audience type') }}</label>
                            <select name="audience_type" class="form-select">
                                <option value="user" @selected(old('audience_type', $campaign->audience_type)==='user')>{{ __('User') }}</option>
                                <option value="session" @selected(old('audience_type', $campaign->audience_type)==='session')>{{ __('Session') }}</option>
                                <option value="user_or_session" @selected(old('audience_type', $campaign->audience_type)==='user_or_session')>{{ __('User or session') }}</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">{{ __('Priority') }}</label>
                            <input type="number" name="priority" class="form-control" value="{{ old('priority', $campaign->priority ?? 100) }}" min="1">
                            <div class="form-text">{{ __('Lower values can be treated as higher priority when multiple campaigns qualify.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Audience segment') }}</label>
                            <select name="segment_id" class="form-select">
                                <option value="">{{ __('No segment') }}</option>
                                @foreach($segments as $segment)
                                    <option value="{{ $segment->id }}" @selected((string) old('segment_id', $campaign->segment_id)===(string) $segment->id)>{{ $segment->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Attach a reusable segment when this campaign should target a shared audience definition.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Default template group') }}</label>
                            <select name="default_template_key" class="form-select">
                                <option value="">{{ __('No template') }}</option>
                                @foreach($templateGroups as $key => $locales)
                                    <option value="{{ $key }}" @selected(old('default_template_key', $campaign->default_template_key)===$key)>{{ $key }} ({{ $locales }})</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Use a template group for the preferred live copy and keep fallback text below as a safe backup.') }}</div>
                        </div>
                    </div>
                </div>

                <div class="admin-card mb-4">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Fallback copy') }}</h5>
                            <div class="admin-section-subtitle">{{ __('These values are used when no template is selected or when the campaign needs safe default messaging.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Fallback subject') }}</label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject', $campaign->subject) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Coupon code') }}</label>
                            <input type="text" name="coupon_code" class="form-control" value="{{ old('coupon_code', $campaign->coupon_code) }}" placeholder="{{ __('Optional promotional code') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">{{ __('Fallback message') }}</label>
                            <textarea name="message" class="form-control" rows="4">{{ old('message', $campaign->message) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Localized copy') }}</h5>
                            <div class="admin-section-subtitle">{{ __('Write Arabic and English fallback content so campaign delivery stays polished across locales.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Arabic subject') }}</label>
                            <input type="text" name="subject_ar" class="form-control" value="{{ old('subject_ar', data_get($campaign->subject_translations, 'ar')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('English subject') }}</label>
                            <input type="text" name="subject_en" class="form-control" value="{{ old('subject_en', data_get($campaign->subject_translations, 'en')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Arabic message') }}</label>
                            <textarea name="message_ar" class="form-control" rows="5">{{ old('message_ar', data_get($campaign->message_translations, 'ar')) }}</textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('English message') }}</label>
                            <textarea name="message_en" class="form-control" rows="5">{{ old('message_en', data_get($campaign->message_translations, 'en')) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="admin-card mb-4">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="mb-1">{{ __('Timing and delivery logic') }}</h5>
                            <div class="admin-helper-text">{{ __('Control when this campaign becomes eligible and how aggressively it can be delivered.') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Delay minutes') }}</label>
                            <input type="number" name="delay_minutes" class="form-control" value="{{ old('delay_minutes', data_get($campaign->config, 'delay_minutes')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Cooldown hours') }}</label>
                            <input type="number" name="cooldown_hours" class="form-control" value="{{ old('cooldown_hours', data_get($campaign->config, 'cooldown_hours')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Lookback days') }}</label>
                            <input type="number" name="lookback_days" class="form-control" value="{{ old('lookback_days', data_get($campaign->config, 'lookback_days')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Lookback hours') }}</label>
                            <input type="number" name="lookback_hours" class="form-control" value="{{ old('lookback_hours', data_get($campaign->config, 'lookback_hours')) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">{{ __('View threshold') }}</label>
                            <input type="number" name="view_threshold" class="form-control" value="{{ old('view_threshold', data_get($campaign->config, 'view_threshold')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Minimum LTV score') }}</label>
                            <input type="number" step="0.01" name="minimum_ltv_score" class="form-control" value="{{ old('minimum_ltv_score', data_get($campaign->config, 'minimum_ltv_score')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Minimum churn risk') }}</label>
                            <input type="number" step="0.01" name="minimum_churn_risk" class="form-control" value="{{ old('minimum_churn_risk', data_get($campaign->config, 'minimum_churn_risk')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Minimum days since order') }}</label>
                            <input type="number" name="minimum_days_since_order" class="form-control" value="{{ old('minimum_days_since_order', data_get($campaign->config, 'minimum_days_since_order')) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('Maximum days since order') }}</label>
                            <input type="number" name="maximum_days_since_order" class="form-control" value="{{ old('maximum_days_since_order', data_get($campaign->config, 'maximum_days_since_order')) }}">
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch growth-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="campaign_active" @checked(old('is_active', $campaign->is_active))>
                                <label class="form-check-label" for="campaign_active">
                                    <span class="admin-inline-title">{{ __('Campaign active') }}</span>
                                    <span class="admin-helper-text">{{ __('Enable this campaign for live evaluation by the engine.') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch growth-switch">
                                <input class="form-check-input" type="checkbox" name="is_messaging_enabled" value="1" id="campaign_messaging_enabled" @checked(old('is_messaging_enabled', $campaign->is_messaging_enabled))>
                                <label class="form-check-label" for="campaign_messaging_enabled">
                                    <span class="fw-semibold">{{ __('Campaign messaging enabled') }}</span>
                                    <span class="admin-helper-text">{{ __('Allow this campaign to send customer-facing messages when it qualifies.') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-card mb-4">
                    <div class="admin-card-body">
                        <h5 class="admin-section-title mb-3">{{ __('Publishing notes') }}</h5>
                        <div class="growth-hint-list">
                            <div class="growth-hint-item">
                                <strong>{{ __('Template first, fallback second') }}</strong>
                                <span class="admin-helper-text">{{ __('Use a template group for the preferred live copy, then keep fallback text completed so the flow remains safe if a template is missing.') }}</span>
                            </div>
                            <div class="growth-hint-item">
                                <strong>{{ __('Keep conditions realistic') }}</strong>
                                <span class="admin-helper-text">{{ __('High thresholds and long cooldowns can make a campaign appear inactive even when the trigger event happens.') }}</span>
                            </div>
                            <div class="growth-hint-item">
                                <strong>{{ __('Review Arabic and English together') }}</strong>
                                <span class="admin-helper-text">{{ __('Make sure placeholders, offer details, and coupon references stay aligned in both locales.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <div class="admin-form-actions-copy">
                        <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                        <div class="admin-form-actions-subtitle">{{ __('Review the campaign setup, then save it to the growth workspace when you are ready.') }}</div>
                    </div>
                    <div class="admin-form-actions-buttons">
                        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ $campaign->exists ? __('Discard changes') : __('Cancel setup') }}</a>
                        <button class="btn btn-primary" data-loading-text="{{ $campaign->exists ? __('Saving campaign changes...') : __('Creating campaign...') }}">{{ $campaign->exists ? __('Save changes') : __('Create campaign') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
