@extends('layouts.admin')

@section('title', __('Payments') . ' | ' . __('Admin'))

@section('content')
<x-admin.page-header :kicker="__('Finance')" :title="__('Payments')" :description="__('Review payment captures, exceptions, refunds, and provider evidence from one finance workspace.')">
    @if(auth()->user()?->hasPermission('payments.settings'))
        <a href="{{ route('admin.settings.payments') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-cog-outline"></i><span>{{ __('Payment settings') }}</span></a>
    @endif
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
@php
    $capturedAmountDisplay = $stats['captured_by_currency']
        ->map(fn (array $row) => $row['currency'].' '.number_format((float) $row['amount'], 2))
        ->implode(' · ');
@endphp
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl"><x-admin.stat-card :label="__('Total records')" :value="number_format($stats['total'])" icon="mdi-cash-multiple" class="h-100" /></div>
    <div class="col-md-6 col-xl"><x-admin.stat-card :label="__('Pending')" :value="number_format($stats['pending'])" icon="mdi-progress-clock" tone="warning" class="h-100" /></div>
    <div class="col-md-6 col-xl"><x-admin.stat-card :label="__('Paid')" :value="number_format($stats['paid'])" icon="mdi-check-decagram-outline" tone="success" class="h-100" /></div>
    <div class="col-md-6 col-xl"><x-admin.stat-card :label="__('Needs attention')" :value="number_format($stats['attention'])" icon="mdi-alert-circle-outline" tone="danger" class="h-100" /></div>
    <div class="col-md-6 col-xl"><x-admin.stat-card :label="__('Captured amount')" :value="$capturedAmountDisplay !== '' ? $capturedAmountDisplay : '—'" icon="mdi-cash-check" tone="success" class="h-100" /></div>
</div>

<div class="alert alert-info border-0 small mb-4">
    <div>{{ __('Captured or Paid status confirms the payment transaction in Flowra; it does not prove that a gateway payout or bank settlement reached the merchant account.') }}</div>
    <div class="mt-1">{{ __('Merchant settlement tracking is not available in Flowra V1. Reconcile provider payouts against provider and bank settlement reports outside Flowra.') }}</div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="admin-filter-grid" data-live-filter>
            <div>
                <label class="form-label fw-semibold" for="paymentSearch">{{ __('Search') }}</label>
                <input id="paymentSearch" type="search" class="form-control" name="search" maxlength="100" data-live-search autocomplete="off" value="{{ $filters['search'] }}" placeholder="{{ __('Reference, order number, provider') }}">
            </div>
            <div>
                <label class="form-label fw-semibold" for="paymentStatus">{{ __('Status') }}</label>
                <select id="paymentStatus" name="status" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold" for="paymentMethod">{{ __('Method') }}</label>
                <select id="paymentMethod" name="method" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All methods') }}</option>
                    @foreach($methodOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold" for="paymentPerPage">{{ __('Per page') }}</label>
                <select id="paymentPerPage" name="per_page" class="form-select" data-live-filter-control>
                    @foreach([20,40,80] as $size)
                        <option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="queue" value="{{ $filters['queue'] }}">
            <div class="admin-filter-actions admin-filter-actions-wide">
                <button type="submit" class="btn btn-primary btn-text-icon"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.payments.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.payments.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</div>

@include('admin.payments._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
