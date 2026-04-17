@extends('layouts.admin')

@section('title', $experiment->exists ? __('Edit Experiment') : __('Create Experiment'))

@php
    $variants = collect(old('variant_keys') ? collect(old('variant_keys'))->map(function ($key, $index) {
        return [
            'key' => old('variant_keys.'.$index),
            'name' => old('variant_names.'.$index),
            'weight' => old('variant_weights.'.$index),
            'coupon_code' => old('variant_coupon_codes.'.$index),
            'subject_translations' => ['ar' => old('variant_subject_ar.'.$index), 'en' => old('variant_subject_en.'.$index)],
            'message_translations' => ['ar' => old('variant_message_ar.'.$index), 'en' => old('variant_message_en.'.$index)],
        ];
    }) : ($experiment->variants ?? [
        ['key' => 'A', 'name' => 'Variant A', 'weight' => 50, 'coupon_code' => null, 'subject_translations' => ['ar' => null, 'en' => null], 'message_translations' => ['ar' => null, 'en' => null]],
        ['key' => 'B', 'name' => 'Variant B', 'weight' => 50, 'coupon_code' => null, 'subject_translations' => ['ar' => null, 'en' => null], 'message_translations' => ['ar' => null, 'en' => null]],
    ]))->values();
@endphp

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
        :kicker="__('Experiments')"
        :title="$experiment->exists ? __('Edit Experiment') : __('Create Experiment')"
        :description="__('Create localized A/B style offer experiments with clearer setup, balanced traffic weights, and cleaner variant editing.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Growth'), 'url' => route('admin.growth.index')],
            ['label' => __('Experiments'), 'current' => true],
        ]"
    >
        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ __('Back to growth workspace') }}</a>
    </x-admin.page-header>

    <form method="POST" action="{{ $experiment->exists ? route('admin.growth.experiments.update', $experiment) : route('admin.growth.experiments.store') }}" data-submit-loading>
        @csrf
        @if($experiment->exists)
            @method('PUT')
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="admin-card mb-4">
                    <div class="admin-card-body row g-3">
                        <div class="col-12">
                            <h5 class="admin-section-title">{{ __('Experiment setup') }}</h5>
                            <div class="admin-section-subtitle">{{ __('Link this experiment to a campaign, document its purpose, and control whether it is live.') }}</div>
                        </div>

                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Name') }}</label><input type="text" name="name" class="form-control" value="{{ old('name', $experiment->name) }}" placeholder="{{ __('Example: Repeat buyer offer test') }}" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Experiment key') }}</label><input type="text" name="experiment_key" class="form-control" value="{{ old('experiment_key', $experiment->experiment_key) }}" placeholder="repeat_buyer_offer_test"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Campaign') }}</label><select name="campaign_id" class="form-select"><option value="">{{ __('Select campaign') }}</option>@foreach($campaigns as $campaign)<option value="{{ $campaign->id }}" @selected((string) old('campaign_id', $experiment->campaign_id)===(string) $campaign->id)>{{ $campaign->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Priority') }}</label><input type="number" name="priority" class="form-control" value="{{ old('priority', $experiment->priority ?? 100) }}"></div>
                        <div class="col-md-3">
                            <div class="form-check form-switch growth-switch mt-md-4">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="experiment_active" @checked(old('is_active', $experiment->is_active))>
                                <label class="form-check-label" for="experiment_active">
                                    <span class="admin-inline-title">{{ __('Experiment active') }}</span>
                                    <span class="admin-helper-text">{{ __('Allow this experiment to split live traffic between variants.') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label fw-semibold">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="3">{{ old('description', $experiment->description) }}</textarea></div>
                    </div>
                </div>

                @foreach($variants as $index => $variant)
                    <div class="admin-card mb-4">
                        <div class="admin-card-body row g-3">
                            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h5 class="admin-section-title">{{ __('Variant') }} {{ $index + 1 }}</h5>
                                    <div class="admin-section-subtitle">{{ __('Define the offer, copy, and traffic allocation for this experiment branch.') }}</div>
                                </div>
                                <span class="badge rounded-pill text-bg-light border">{{ __('Traffic weight') }}: {{ $variant['weight'] ?? 50 }}%</span>
                            </div>

                            <div class="col-md-2"><label class="form-label fw-semibold">{{ __('Key') }}</label><input type="text" name="variant_keys[]" class="form-control" value="{{ $variant['key'] ?? '' }}" required></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">{{ __('Name') }}</label><input type="text" name="variant_names[]" class="form-control" value="{{ $variant['name'] ?? '' }}" required></div>
                            <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Weight') }}</label><input type="number" name="variant_weights[]" class="form-control" value="{{ $variant['weight'] ?? 50 }}" min="1"></div>
                            <div class="col-md-3"><label class="form-label fw-semibold">{{ __('Coupon code') }}</label><input type="text" name="variant_coupon_codes[]" class="form-control" value="{{ $variant['coupon_code'] ?? '' }}"></div>

                            <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Arabic subject') }}</label><input type="text" name="variant_subject_ar[]" class="form-control" value="{{ data_get($variant, 'subject_translations.ar') }}"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">{{ __('English subject') }}</label><input type="text" name="variant_subject_en[]" class="form-control" value="{{ data_get($variant, 'subject_translations.en') }}"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">{{ __('Arabic message') }}</label><textarea name="variant_message_ar[]" class="form-control" rows="4">{{ data_get($variant, 'message_translations.ar') }}</textarea></div>
                            <div class="col-md-6"><label class="form-label fw-semibold">{{ __('English message') }}</label><textarea name="variant_message_en[]" class="form-control" rows="4">{{ data_get($variant, 'message_translations.en') }}</textarea></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="col-xl-4">
                <div class="admin-card mb-4">
                    <div class="admin-card-body">
                        <h5 class="admin-section-title mb-3">{{ __('Experiment checklist') }}</h5>
                        <div class="d-grid gap-3">
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Balance variant weights') }}</div>
                                <div class="admin-helper-text">{{ __('Make sure the weight values reflect the traffic split you expect to test in production.') }}</div>
                            </div>
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Localize both branches') }}</div>
                                <div class="admin-helper-text">{{ __('Arabic and English should communicate the same offer details and placeholders for valid comparison.') }}</div>
                            </div>
                            <div class="admin-soft-note">
                                <div class="admin-inline-title mb-1">{{ __('Link to the right campaign') }}</div>
                                <div class="admin-helper-text">{{ __('Experiments are easier to review later when they are attached to the exact campaign they are meant to optimize.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <div class="admin-form-actions-copy">
                        <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                        <div class="admin-form-actions-subtitle">{{ __('Review the experiment setup and variant weights, then save it to the growth workspace when you are ready.') }}</div>
                    </div>
                    <div class="admin-form-actions-buttons">
                        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ $experiment->exists ? __('Discard changes') : __('Cancel setup') }}</a>
                        <button class="btn btn-primary" data-loading-text="{{ $experiment->exists ? __('Saving experiment changes...') : __('Creating experiment...') }}">{{ $experiment->exists ? __('Save changes') : __('Create experiment') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
