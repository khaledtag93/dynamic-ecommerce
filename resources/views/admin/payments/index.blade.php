@extends('layouts.admin')

@section('title', __('Payments') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Finance')" :title="__('Payments')" :description="__('Payment foundation is now ready for COD, transfer, and future gateway integrations.')">
    <a href="{{ route('admin.settings.payments') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-cog-outline"></i><span>{{ __('Payment settings') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-cash-multiple"></i></span><div class="admin-stat-label">{{ __('Total records') }}</div><div class="admin-stat-value">{{ number_format($stats['total']) }}</div></div></div>
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-progress-clock"></i></span><div class="admin-stat-label">{{ __('Pending') }}</div><div class="admin-stat-value">{{ number_format($stats['pending']) }}</div></div></div>
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-check-decagram-outline"></i></span><div class="admin-stat-label">{{ __('Paid') }}</div><div class="admin-stat-value">{{ number_format($stats['paid']) }}</div></div></div>
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-alert-circle-outline"></i></span><div class="admin-stat-label">{{ __('Needs attention') }}</div><div class="admin-stat-value">{{ number_format($stats['attention']) }}</div></div></div>
    <div class="col-md-6 col-xl"><div class="admin-card admin-stat-card h-100"><span class="admin-stat-icon"><i class="mdi mdi-cash-check"></i></span><div class="admin-stat-label">{{ __('Paid amount') }}</div><div class="admin-stat-value">EGP {{ number_format($stats['paid_amount'], 2) }}</div></div></div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="admin-filter-grid" data-live-filter>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="search" data-live-search autocomplete="off" value="{{ $filters['search'] }}" placeholder="{{ __('Reference, order number, provider') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Status') }}</label>
                <select name="status" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Method') }}</label>
                <select name="method" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All methods') }}</option>
                    @foreach($methodOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                <select name="per_page" class="form-select" data-live-filter-control>
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
