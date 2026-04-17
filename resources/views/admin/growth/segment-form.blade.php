@extends('layouts.admin')

@section('title', $segment->exists ? __('Edit Segment') : __('Create Segment'))

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
        :kicker="__('Audience segments')"
        :title="$segment->exists ? __('Edit Segment') : __('Create Segment')"
        :description="__('Build reusable audience logic so campaigns and automation rules can share the same targeting safely.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Growth'), 'url' => route('admin.growth.index')],
            ['label' => __('Segments'), 'current' => true],
        ]"
    >
        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ __('Back to growth workspace') }}</a>
    </x-admin.page-header>

    <form method="POST" action="{{ $segment->exists ? route('admin.growth.segments.update', $segment) : route('admin.growth.segments.store') }}" data-submit-loading>
        @csrf
        @if($segment->exists)
            @method('PUT')
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="admin-card mb-4">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Segment identity') }}</h5>
                            <div class="admin-section-subtitle">{{ __('Give this audience a clear name, stable key, and shared description for the team.') }}</div>
                        </div>

                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Name') }}</label><input type="text" name="name" class="form-control" value="{{ old('name', $segment->name) }}" placeholder="{{ __('Example: Returning customers') }}" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Segment key') }}</label><input type="text" name="segment_key" class="form-control" value="{{ old('segment_key', $segment->segment_key) }}" placeholder="returning_customers"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Audience type') }}</label><select name="audience_type" class="form-select"><option value="user" @selected(old('audience_type', $segment->audience_type)==='user')>{{ __('User') }}</option><option value="session" @selected(old('audience_type', $segment->audience_type)==='session')>{{ __('Session') }}</option><option value="user_or_session" @selected(old('audience_type', $segment->audience_type)==='user_or_session')>{{ __('User or session') }}</option></select></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Priority') }}</label><input type="number" name="priority" class="form-control" value="{{ old('priority', $segment->priority ?? 100) }}"><div class="form-text">{{ __('Use priority when multiple segments could match and you need a preferred order.') }}</div></div>
                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="4">{{ old('description', $segment->description) }}</textarea></div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Audience thresholds') }}</h5>
                            <div class="admin-section-subtitle">{{ __('Combine activity, value, and identity checks to describe who belongs inside this segment.') }}</div>
                        </div>

                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Minimum orders') }}</label><input type="number" name="minimum_orders" class="form-control" value="{{ old('minimum_orders', data_get($segment->filters, 'minimum_orders')) }}"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Minimum view count') }}</label><input type="number" name="minimum_view_count" class="form-control" value="{{ old('minimum_view_count', data_get($segment->filters, 'minimum_view_count')) }}"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Minimum cart count') }}</label><input type="number" name="minimum_cart_count" class="form-control" value="{{ old('minimum_cart_count', data_get($segment->filters, 'minimum_cart_count')) }}"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Minimum LTV score') }}</label><input type="number" step="0.01" name="minimum_ltv_score" class="form-control" value="{{ old('minimum_ltv_score', data_get($segment->filters, 'minimum_ltv_score')) }}"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Minimum churn risk') }}</label><input type="number" step="0.01" name="minimum_churn_risk" class="form-control" value="{{ old('minimum_churn_risk', data_get($segment->filters, 'minimum_churn_risk')) }}"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Minimum days since order') }}</label><input type="number" name="minimum_days_since_order" class="form-control" value="{{ old('minimum_days_since_order', data_get($segment->filters, 'minimum_days_since_order')) }}"></div>

                        <div class="col-md-6">
                            <div class="form-check form-switch growth-switch mt-md-4">
                                <input class="form-check-input" type="checkbox" name="requires_user" value="1" id="requires_user" @checked(old('requires_user', data_get($segment->filters, 'requires_user')))> 
                                <label class="form-check-label" for="requires_user">
                                    <span class="admin-inline-title">{{ __('Requires logged-in user') }}</span>
                                    <span class="admin-helper-text">{{ __('Only match customers with an authenticated account profile.') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch growth-switch mt-md-4">
                                <input class="form-check-input" type="checkbox" name="requires_session" value="1" id="requires_session" @checked(old('requires_session', data_get($segment->filters, 'requires_session')))> 
                                <label class="form-check-label" for="requires_session">
                                    <span class="admin-inline-title">{{ __('Requires session id') }}</span>
                                    <span class="admin-helper-text">{{ __('Use this when anonymous or session-based activity must exist before the segment can match.') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch growth-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="segment_active" @checked(old('is_active', $segment->is_active))>
                                <label class="form-check-label" for="segment_active">
                                    <span class="admin-inline-title">{{ __('Segment active') }}</span>
                                    <span class="admin-helper-text">{{ __('Allow campaigns and rules to use this segment in live matching.') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="admin-card mb-4">
                    <div class="admin-card-body">
                        <h5 class="admin-section-title mb-3">{{ __('Segment design notes') }}</h5>
                        <div class="d-grid gap-3">
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Start broad, then tighten') }}</div>
                                <div class="admin-helper-text">{{ __('Begin with only the most meaningful filters, then add more thresholds when the audience becomes too wide.') }}</div>
                            </div>
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Avoid conflicting requirements') }}</div>
                                <div class="admin-helper-text">{{ __('For example, very high minimum activity plus a required session can exclude nearly everyone unexpectedly.') }}</div>
                            </div>
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Reuse across campaigns') }}</div>
                                <div class="admin-helper-text">{{ __('A clean reusable segment reduces duplication and keeps targeting changes easier to maintain later.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <div class="admin-form-actions-copy">
                        <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                        <div class="admin-form-actions-subtitle">{{ __('Review the audience rules, then save this segment to the growth workspace when you are ready.') }}</div>
                    </div>
                    <div class="admin-form-actions-buttons">
                        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ $segment->exists ? __('Discard changes') : __('Cancel setup') }}</a>
                        <button class="btn btn-primary" data-loading-text="{{ $segment->exists ? __('Saving segment changes...') : __('Creating segment...') }}">{{ $segment->exists ? __('Save changes') : __('Create segment') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
