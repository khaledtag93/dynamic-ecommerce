@extends('layouts.admin')

@section('title', __('Coupons') . ' | ' . __('Admin Dashboard'))

@section('content')
<div class="admin-page-header">
    <div>
        <div class="admin-kicker">{{ __('Promotions') }}</div>
        <h1 class="admin-page-title">{{ __('Coupons') }}</h1>
        <p class="admin-page-description">{{ __('Manage discount rules, monitor usage, and keep promotional offers within safe business limits.') }}</p>
    </div>
    <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">{{ __('Create coupon') }}</a>
</div>

<div class="admin-page-shell" data-live-list>
<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total coupons'), 'value' => $stats['total'], 'copy' => __('Every coupon record in the store.'), 'icon' => 'mdi-ticket-percent-outline'],
        ['label' => __('Active'), 'value' => $stats['active'], 'copy' => __('Coupons currently enabled for checkout.'), 'icon' => 'mdi-check-decagram-outline'],
        ['label' => __('Expired'), 'value' => $stats['expired'], 'copy' => __('Offers whose end date has already passed.'), 'icon' => 'mdi-calendar-remove-outline'],
        ['label' => __('Used'), 'value' => $stats['used'], 'copy' => __('Coupons that have at least one redemption.'), 'icon' => 'mdi-chart-timeline-variant'],
        ['label' => __('Limit reached'), 'value' => $stats['limit_reached'], 'copy' => __('Coupons that exhausted their usage allowance.'), 'icon' => 'mdi-ticket-confirmation-outline'],
    ] as $card)
        <div class="col-md-6 col-xl">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                <div class="admin-stat-label">{{ $card['label'] }}</div>
                <div class="admin-stat-value">{{ $card['value'] }}</div>
                <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="{{ route('admin.coupons.index') }}" class="admin-filter-grid admin-filter-grid-coupons" data-live-filter>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="search" name="search" data-live-search autocomplete="off" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Name, code, notes') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Type') }}</label>
                <select name="type" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All types') }}</option>
                    @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Status') }}</label>
                <select name="status" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                    <option value="expired" @selected($filters['status'] === 'expired')>{{ __('Expired') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Redemption activity') }}</label>
                <select name="usage" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All coupons') }}</option>
                    <option value="used" @selected($filters['usage'] === 'used')>{{ __('Used') }}</option>
                    <option value="unused" @selected($filters['usage'] === 'unused')>{{ __('Unused') }}</option>
                    <option value="limit_reached" @selected($filters['usage'] === 'limit_reached')>{{ __('Limit reached') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                <select name="per_page" class="form-select" data-live-filter-control>
                    @foreach([12,24,48] as $size)
                        <option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}" data-live-default="id">
            <input type="hidden" name="direction" value="{{ $filters['direction'] }}" data-live-default="desc">
            <div class="admin-filter-actions admin-filter-actions-wide">
                <button class="btn btn-primary w-100" type="submit">{{ __('Apply filters') }}</button>
                <a href="{{ route('admin.coupons.index') }}" class="btn btn-light border w-100" data-live-reset>{{ __('Reset') }}</a>
            </div>
        </form>
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.coupons.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</div>

@include('admin.coupons._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
