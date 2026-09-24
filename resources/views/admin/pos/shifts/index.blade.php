@extends('layouts.admin')

@section('title', __('Cash Shift Review') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Point of Sale')" :title="__('Cash Shift Review')" :description="__('Review cashier drawer sessions, expected cash, counted cash, and reconciliation variances.')">
    <a href="{{ route('admin.pos.index') }}" class="btn btn-light border"><i class="mdi mdi-cash-register me-1"></i>{{ __('Open POS') }}</a>
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
    <div class="row g-3">
        <div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Open shifts') }}</div><div class="h3 mb-0">{{ number_format($shiftMetrics['open']) }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Closed shifts') }}</div><div class="h3 mb-0">{{ number_format($shiftMetrics['closed']) }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Shifts with variance') }}</div><div class="h3 mb-0">{{ number_format($shiftMetrics['with_variance']) }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Total shortage') }}</div><div class="h3 mb-0 text-danger">EGP {{ number_format($shiftMetrics['short_total'], 2) }}</div></div></div></div>
        <div class="col-sm-6 col-xl"><div class="admin-card h-100"><div class="admin-card-body"><div class="text-muted small">{{ __('Total overage') }}</div><div class="h3 mb-0 text-primary">EGP {{ number_format($shiftMetrics['over_total'], 2) }}</div></div></div></div>
    </div>

    <div class="admin-card mb-4"><div class="admin-card-body">
        <form method="GET" action="{{ route('admin.pos.shifts.index') }}" class="row g-3 align-items-end" data-live-filter>
            <div class="col-lg-5">
                <label for="shiftCashierSearch" class="form-label fw-semibold">{{ __('Cashier') }}</label>
                <input id="shiftCashierSearch" type="search" name="cashier" value="{{ $cashierSearch }}" class="form-control" placeholder="{{ __('Search cashier by name or email') }}" autocomplete="off" data-live-search>
            </div>
            <div class="col-lg-4">
                <label for="shiftStatus" class="form-label fw-semibold">{{ __('Shift status') }}</label>
                <select id="shiftStatus" name="status" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All shifts') }}</option>
                    <option value="open" @selected($status === 'open')>{{ __('Open') }}</option>
                    <option value="closed" @selected($status === 'closed')>{{ __('Closed') }}</option>
                    <option value="variance" @selected($status === 'variance')>{{ __('With variance') }}</option>
                </select>
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Apply filters') }}</button>
                <a href="{{ route('admin.pos.shifts.index') }}" class="btn btn-light border" data-live-reset>{{ __('Reset') }}</a>
            </div>
        </form>
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.pos.shifts.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div></div>

    @include('admin.pos.shifts._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
