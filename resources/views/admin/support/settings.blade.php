@extends('layouts.admin')

@section('title', __('Support settings') . ' | Admin')

@section('content')
<x-admin.page-header
    :kicker="__('Customer Support')"
    :title="__('Support settings')"
    :description="__('Configure response targets and reusable reply templates without changing the support case history.')"
>
    <a href="{{ route('admin.support.index') }}" class="btn btn-light border">{{ __('Back to support') }}</a>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start mb-4">
                <div>
                    <h3 class="mb-1">{{ __('SLA targets') }}</h3>
                    <p class="text-muted mb-0">{{ __('Targets start from case creation. V2 does not pause the clock while waiting for the customer; pause calendars and business hours remain future policy work.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.support.settings.sla') }}" data-submit-loading>
                @csrf @method('PUT')
                <div class="table-responsive admin-table-wrap">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Priority') }}</th>
                                <th>{{ __('First response target (hours)') }}</th>
                                <th>{{ __('Resolution target (hours)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(\App\Models\SupportCase::priorityOptions() as $priority => $label)
                                <tr>
                                    <td><span class="fw-semibold">{{ $label }}</span></td>
                                    <td>
                                        <label class="visually-hidden" for="supportSlaFirst-{{ $priority }}">{{ $label }} · {{ __('First response target (hours)') }}</label>
                                        <input id="supportSlaFirst-{{ $priority }}" type="number" min="1" max="720" name="sla[{{ $priority }}][first_response_hours]" aria-required="true" value="{{ old("sla.$priority.first_response_hours", $sla[$priority]['first_response_hours']) }}" class="form-control" required>
                                    </td>
                                    <td>
                                        <label class="visually-hidden" for="supportSlaResolution-{{ $priority }}">{{ $label }} · {{ __('Resolution target (hours)') }}</label>
                                        <input id="supportSlaResolution-{{ $priority }}" type="number" min="1" max="720" name="sla[{{ $priority }}][resolution_hours]" aria-required="true" value="{{ old("sla.$priority.resolution_hours", $sla[$priority]['resolution_hours']) }}" class="form-control" required>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <button class="btn btn-primary" data-loading-text="{{ __('Saving SLA targets...') }}">{{ __('Save SLA targets') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <h3 class="mb-1">{{ __('New reply template') }}</h3>
            <p class="text-muted mb-4">{{ __('Templates only prefill the reply composer. Staff can always edit the text before sending or saving an internal note.') }}</p>

            <form method="POST" action="{{ route('admin.support.templates.store') }}" data-submit-loading>
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="supportTemplateNewNameEn">{{ __('Name (English)') }}</label>
                        <input id="supportTemplateNewNameEn" name="name" aria-required="true" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="160" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="supportTemplateNewNameAr">{{ __('Name (Arabic)') }}</label>
                        <input id="supportTemplateNewNameAr" name="name_ar" value="{{ old('name_ar') }}" class="form-control @error('name_ar') is-invalid @enderror" maxlength="160" dir="rtl">
                        @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="supportTemplateNewBodyEn">{{ __('Body (English)') }}</label>
                        <textarea id="supportTemplateNewBodyEn" name="body" aria-required="true" rows="5" class="form-control @error('body') is-invalid @enderror" maxlength="5000" required>{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="supportTemplateNewBodyAr">{{ __('Body (Arabic)') }}</label>
                        <textarea id="supportTemplateNewBodyAr" name="body_ar" rows="5" class="form-control @error('body_ar') is-invalid @enderror" maxlength="5000" dir="rtl">{{ old('body_ar') }}</textarea>
                        @error('body_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="supportTemplateNewVisibility">{{ __('Default visibility') }}</label>
                        <select id="supportTemplateNewVisibility" name="visibility" class="form-select">
                            <option value="{{ \App\Models\SupportCaseMessage::VISIBILITY_CUSTOMER }}">{{ __('Customer-visible reply') }}</option>
                            <option value="{{ \App\Models\SupportCaseMessage::VISIBILITY_INTERNAL }}">{{ __('Internal note') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="supportTemplateNewSort">{{ __('Sort order') }}</label>
                        <input id="supportTemplateNewSort" type="number" min="0" max="10000" name="sort_order" aria-required="true" value="{{ old('sort_order', 100) }}" class="form-control" required>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" data-loading-text="{{ __('Creating template...') }}">{{ __('Create reply template') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="d-grid gap-3">
        @forelse($templates as $template)
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start mb-3">
                        <div>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <h4 class="mb-0">{{ $template->displayName() }}</h4>
                                <span class="badge admin-status-badge {{ $template->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $template->is_active ? __('Active') : __('Inactive') }}</span>
                                <span class="badge badge-soft-info">{{ $template->visibility === \App\Models\SupportCaseMessage::VISIBILITY_INTERNAL ? __('Internal note') : __('Customer-visible reply') }}</span>
                            </div>
                            <div class="text-muted small mt-1">{{ __('Sort order') }}: {{ $template->sort_order }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.support.templates.toggle', $template) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-light border">{{ $template->is_active ? __('Disable') : __('Enable') }}</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.support.templates.update', $template) }}" data-submit-loading>
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="supportTemplateNameEn-{{ $template->id }}">{{ __('Name (English)') }}</label>
                                <input id="supportTemplateNameEn-{{ $template->id }}" name="name" aria-required="true" value="{{ old('name_'.$template->id, $template->name) }}" class="form-control" maxlength="160" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="supportTemplateNameAr-{{ $template->id }}">{{ __('Name (Arabic)') }}</label>
                                <input id="supportTemplateNameAr-{{ $template->id }}" name="name_ar" value="{{ old('name_ar_'.$template->id, $template->name_ar) }}" class="form-control" maxlength="160" dir="rtl">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="supportTemplateBodyEn-{{ $template->id }}">{{ __('Body (English)') }}</label>
                                <textarea id="supportTemplateBodyEn-{{ $template->id }}" name="body" aria-required="true" rows="4" class="form-control" maxlength="5000" required>{{ old('body_'.$template->id, $template->body) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="supportTemplateBodyAr-{{ $template->id }}">{{ __('Body (Arabic)') }}</label>
                                <textarea id="supportTemplateBodyAr-{{ $template->id }}" name="body_ar" rows="4" class="form-control" maxlength="5000" dir="rtl">{{ old('body_ar_'.$template->id, $template->body_ar) }}</textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="supportTemplateVisibility-{{ $template->id }}">{{ __('Default visibility') }}</label>
                                <select id="supportTemplateVisibility-{{ $template->id }}" name="visibility" class="form-select">
                                    <option value="{{ \App\Models\SupportCaseMessage::VISIBILITY_CUSTOMER }}" @selected($template->visibility === \App\Models\SupportCaseMessage::VISIBILITY_CUSTOMER)>{{ __('Customer-visible reply') }}</option>
                                    <option value="{{ \App\Models\SupportCaseMessage::VISIBILITY_INTERNAL }}" @selected($template->visibility === \App\Models\SupportCaseMessage::VISIBILITY_INTERNAL)>{{ __('Internal note') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="supportTemplateSort-{{ $template->id }}">{{ __('Sort order') }}</label>
                                <input id="supportTemplateSort-{{ $template->id }}" type="number" min="0" max="10000" name="sort_order" aria-required="true" value="{{ $template->sort_order }}" class="form-control" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end justify-content-end">
                                <button class="btn btn-outline-primary" data-loading-text="{{ __('Saving template...') }}">{{ __('Save template') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="admin-card"><div class="admin-card-body text-center text-muted py-5">{{ __('No reply templates yet.') }}</div></div>
        @endforelse
    </div>
</div>
@endsection
