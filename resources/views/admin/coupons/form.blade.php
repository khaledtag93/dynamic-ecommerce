@extends('layouts.admin')

@section('title', $title . ' | Admin')

@section('content')
<x-admin.page-header
    :kicker="__('Promotions')"
    :title="$title"
    :description="__('Define safe discount rules with clear scheduling, usage limits, and minimum order validation.')"
>
    <a href="{{ route('admin.coupons.index') }}" class="btn btn-light border admin-back-btn">{{ __('Back to coupons') }}</a>
</x-admin.page-header>

<div class="admin-card mb-4"><div class="admin-card-body"><div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3"><div><span class="badge badge-soft-info mb-2">{{ __('Promotion builder') }}</span><h4 class="mb-1">{{ __('Build a controlled offer') }}</h4><p class="text-muted small mb-0">{{ __('Define the discount, eligibility, schedule, and usage guardrails before making the coupon available.') }}</p></div><div class="d-flex gap-2 flex-wrap"><span class="admin-chip"><i class="mdi mdi-ticket-percent-outline"></i> {{ __('Discount') }}</span><span class="admin-chip"><i class="mdi mdi-shield-check-outline"></i> {{ __('Limits') }}</span><span class="admin-chip"><i class="mdi mdi-calendar-clock-outline"></i> {{ __('Schedule') }}</span></div></div></div></div>

@if($errors->any())<div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><div class="fw-bold mb-2">{{ __('Please review the coupon details:') }}</div><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card">
            <div class="admin-card-body">
                <form method="POST" action="{{ $action }}" data-submit-loading data-admin-section-tabs="coupon-editor">
                    @csrf
                    @if($method !== 'POST') @method($method) @endif

                    <x-admin.section-tabs id="coupon-editor" :sections="[
                        'offer' => __('Offer'),
                        'limits' => __('Limits & eligibility'),
                        'schedule' => __('Schedule & notes'),
                    ]" />

                    <div id="coupon-editor-panel-offer" role="tabpanel" aria-labelledby="coupon-editor-tab-offer" data-admin-section-panel="offer">
                        <div class="row g-4">
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">{{ __('Name') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $coupon->name) }}" placeholder="{{ __('Optional internal label') }}">
                        <div class="form-text">{{ __('Useful when your team wants a readable internal title for the promotion.') }}</div>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">{{ __('Code') }} <span class="required-star">*</span></label>
                        <input type="text" name="code" class="form-control text-uppercase" autocomplete="off" value="{{ old('code', $coupon->code) }}" placeholder="{{ __('SAVE10') }}">
                        <div class="form-text">{{ __('Customers will enter this code during cart or checkout.') }}</div>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">{{ __('Type') }}</label>
                        <select name="type" id="couponType" class="form-select">
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $coupon->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">{{ __('Value') }}</label>
                        <input type="number" step="0.01" min="0.01" name="value" id="couponValue" class="form-control" value="{{ old('value', $coupon->value) }}">
                    </div>
                        </div>
                    </div>

                    <div id="coupon-editor-panel-limits" role="tabpanel" aria-labelledby="coupon-editor-tab-limits" data-admin-section-panel="limits">
                        <div class="row g-4">
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">{{ __('Usage limit') }}</label>
                        <input type="number" min="1" name="usage_limit" class="form-control" value="{{ old('usage_limit', $coupon->usage_limit) }}" placeholder="{{ __('Optional') }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">{{ __('Min order amount') }}</label>
                        <input type="number" step="0.01" min="0" name="min_order_amount" class="form-control" value="{{ old('min_order_amount', $coupon->min_order_amount) }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">{{ __('Max discount') }}</label>
                        <input type="number" step="0.01" min="0" name="max_discount_amount" class="form-control" value="{{ old('max_discount_amount', $coupon->max_discount_amount) }}">
                    </div>
                    <div class="col-lg-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" value="1" @checked(old('is_active', $coupon->is_active))>
                            <label class="form-check-label" for="isActive">{{ __('Coupon is active') }}</label>
                        </div>
                    </div>
                        </div>
                    </div>

                    <div id="coupon-editor-panel-schedule" role="tabpanel" aria-labelledby="coupon-editor-tab-schedule" data-admin-section-panel="schedule">
                        <div class="row g-4">
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">{{ __('Starts at') }}</label>
                        <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">{{ __('Ends at') }}</label>
                        <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', optional($coupon->ends_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Notes') }}</label>
                        <textarea name="notes" rows="4" class="form-control" placeholder="{{ __('Optional notes') }}">{{ old('notes', $coupon->notes) }}</textarea>
                    </div>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <div class="admin-form-actions">
                            <div class="admin-form-actions-copy">
                                <div class="admin-form-actions-title">{{ __('Ready to save?') }}</div>
                                <div class="admin-form-actions-subtitle">{{ __('Review the coupon rule, limits, schedule, and notes before saving it to the promotions workspace.') }}</div>
                            </div>
                            <div class="admin-form-actions-buttons">
                                <a href="{{ route('admin.coupons.index') }}" class="btn btn-light border">{{ __('Cancel') }}</a>
                                <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Saving...') }}">{{ __('Save coupon') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="admin-card admin-card-sticky">
            <div class="admin-card-body">
                <div class="admin-inline-label mb-2">{{ __('Coupon preview') }}</div>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><span class="text-muted small">{{ __('Current offer') }}</span><span class="badge badge-soft-success" id="couponLiveValue">—</span></div>
                <div class="admin-preview-box">
                    <div class="admin-coupon-code mb-3">{{ old('code', $coupon->code ?: 'CODE') }}</div>
                    <div class="fw-bold mb-2">{{ __('How this coupon will behave') }}</div>
                    <ul class="admin-bullet-list mb-0">
                        <li>{{ __('Apply either a fixed amount or a percentage-based discount.') }}</li>
                        <li>{{ __('Set a minimum cart value to protect margins.') }}</li>
                        <li>{{ __('Optional max discount keeps percentage offers under control.') }}</li>
                        <li>{{ __('Usage limits and schedule prevent unintended overuse.') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const type = document.getElementById('couponType');
    const value = document.getElementById('couponValue');
    const output = document.getElementById('couponLiveValue');
    const code = document.querySelector('input[name="code"]');
    const codePreview = document.querySelector('.admin-coupon-code');

    function refreshCouponPreview() {
        const amount = parseFloat(value?.value || 0);
        output.textContent = type?.value === 'percent'
            ? (amount ? amount.toLocaleString(undefined, {maximumFractionDigits: 2}) + '%' : '—')
            : (amount ? 'EGP ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '—');
        if (codePreview) codePreview.textContent = (code?.value || 'CODE').toUpperCase();
    }
    type?.addEventListener('change', refreshCouponPreview);
    value?.addEventListener('input', refreshCouponPreview);
    code?.addEventListener('input', refreshCouponPreview);
    refreshCouponPreview();
});
</script>
@endpush
@endsection
