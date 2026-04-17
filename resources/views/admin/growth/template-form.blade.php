@extends('layouts.admin')

@section('title', $template->exists ? __('Edit message template') : __('Create message template'))

@section('content')
<style>
.template-form-page .template-switch{display:flex;align-items:flex-start;gap:.75rem;flex-wrap:nowrap}
.template-form-page .template-switch .form-check-input{margin-top:.2rem;flex:0 0 auto}
.template-form-page .template-switch .form-check-label{display:flex;flex-direction:column;gap:.2rem;margin:0}
html[dir="rtl"] .template-form-page .template-switch{justify-content:flex-start}
.template-form-page .row>[class*='col-'],.template-form-page .admin-card,.template-form-page .admin-card-body,.template-form-page .admin-soft-note,.template-form-page .admin-form-actions{min-width:0}
.template-form-page .form-control,.template-form-page .form-select,.template-form-page textarea{max-width:100%}
.template-form-page .admin-form-actions-buttons{display:flex;flex-wrap:wrap;gap:.75rem}
.template-form-page .admin-form-actions-buttons .btn{min-width:0}
@media (max-width:767.98px){.template-form-page .admin-form-actions-buttons .btn{width:100%}}
</style>

<div class="admin-page-shell template-form-page">
    <x-admin.page-header
        :kicker="__('Templates')"
        :title="$template->exists ? __('Edit message template') : __('Create message template')"
        :description="__('Build reusable Arabic and English copy blocks for your automation and notification flows.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Growth'), 'url' => route('admin.growth.index')],
            ['label' => __('Templates'), 'current' => true],
        ]"
    >
        <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ __('Back to growth workspace') }}</a>
    </x-admin.page-header>

    <form method="POST" action="{{ $template->exists ? route('admin.growth.templates.update', $template) : route('admin.growth.templates.store') }}" data-submit-loading>
        @csrf
        @if($template->exists)
            @method('PUT')
        @endif

        <div class="admin-card mb-4">
            <div class="admin-card-body row g-3">
                <div class="col-12">
                    <h5 class="admin-section-title">{{ __('Template setup') }}</h5>
                    <div class="admin-section-subtitle">{{ __('Name the template, choose its channel and locale, and keep tokens consistent for cleaner reuse.') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('Template name') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" placeholder="{{ __('Example: Order recovery reminder') }}" required>
                    <div class="form-text">{{ __('Use a clear internal name so the team can find this template quickly.') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('Template key') }}</label>
                    <input type="text" name="template_key" class="form-control" value="{{ old('template_key', $template->template_key) }}" placeholder="order_recovery_reminder" required>
                    <div class="form-text">{{ __('Keep this key stable because automations and flows can depend on it.') }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ __('Channel') }}</label>
                    <select name="channel" class="form-select">
                        <option value="in_app" @selected(old('channel', $template->channel)==='in_app')>{{ __('In-app') }}</option>
                        <option value="email" @selected(old('channel', $template->channel)==='email')>{{ __('Email') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ __('Locale') }}</label>
                    <select name="locale" class="form-select">
                        <option value="ar" @selected(old('locale', $template->locale)==='ar')>{{ __('AR') }}</option>
                        <option value="en" @selected(old('locale', $template->locale)==='en')>{{ __('EN') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ __('Priority') }}</label>
                    <input type="number" name="priority" class="form-control" value="{{ old('priority', $template->priority ?? 100) }}">
                    <div class="form-text">{{ __('Lower numbers can be treated as higher priority when your workflow sorts templates.') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">{{ __('Subject') }}</label>
                    <input type="text" name="subject" class="form-control" value="{{ old('subject', $template->subject) }}" placeholder="{{ __('Optional for in-app messages, recommended for email templates') }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">{{ __('Message body') }}</label>
                    <textarea name="body" class="form-control" rows="6" required>{{ old('body', $template->body) }}</textarea>
                    <div class="form-text">{{ __('Write production-ready copy and keep placeholders consistent between Arabic and English versions.') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">{{ __('Tokens (comma separated)') }}</label>
                    <input type="text" name="tokens_text" class="form-control" value="{{ old('tokens_text', implode(', ', $template->tokens ?? [])) }}" placeholder=":customer_name, :coupon, :store_name">
                    <div class="form-text">{{ __('List every placeholder used inside the message body so edits stay predictable.') }}</div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch template-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="template_active" @checked(old('is_active', $template->is_active))>
                        <label class="form-check-label" for="template_active">
                            <span class="admin-inline-title">{{ __('Template is active') }}</span>
                            <span class="admin-helper-text">{{ __('Only active templates should be available for live automation and notification flows.') }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-form-actions">
            <div class="admin-form-actions-copy">
                <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                <div class="admin-form-actions-subtitle">{{ __('Review the localized copy and tokens, then save this template to the growth workspace when you are ready.') }}</div>
            </div>
            <div class="admin-form-actions-buttons">
                <a href="{{ route('admin.growth.index') }}" class="btn btn-light border">{{ $template->exists ? __('Discard changes') : __('Cancel setup') }}</a>
                <button class="btn btn-primary" data-loading-text="{{ $template->exists ? __('Saving template changes...') : __('Creating template...') }}">{{ $template->exists ? __('Save changes') : __('Create template') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
