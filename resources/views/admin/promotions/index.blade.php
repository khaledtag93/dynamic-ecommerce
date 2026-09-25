@extends('layouts.admin')

@section('title', __('Promotions') . ' | Admin')

@section('content')
<x-admin.page-header
    :kicker="__('Sales')"
    :title="__('Promotions')"
    :description="__('Automatic discount rules for orders, categories, and buy X get Y.')"
>
    <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Add promotion') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
<div class="row g-3 mb-4">
    @foreach([
        ['label' => __('Total promotions'), 'value' => $stats['total'], 'copy' => __('All promotion rules in the system.'), 'icon' => 'mdi-sale'],
        ['label' => __('Active'), 'value' => $stats['active'], 'copy' => __('Promotion rules currently applied.'), 'icon' => 'mdi-check-circle-outline'],
        ['label' => __('Inactive'), 'value' => $stats['inactive'], 'copy' => __('Rules saved but not active right now.'), 'icon' => 'mdi-pause-circle-outline'],
        ['label' => __('Running now'), 'value' => $stats['running'], 'copy' => __('Active promotions currently inside their schedule.'), 'icon' => 'mdi-play-circle-outline'],
        ['label' => __('Upcoming'), 'value' => $stats['upcoming'], 'copy' => __('Scheduled promotions that have not started yet.'), 'icon' => 'mdi-calendar-clock-outline'],
        ['label' => __('Expired'), 'value' => $stats['expired'], 'copy' => __('Promotion schedules that have already ended.'), 'icon' => 'mdi-calendar-remove-outline'],
    ] as $card)
        <div class="col-md-6 col-xl">
            <x-admin.stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :help="$card['copy']" class="h-100" />
        </div>
    @endforeach
</div>

<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="{{ route('admin.promotions.index') }}" class="admin-filter-grid" data-live-filter>
            <div>
                <label class="form-label fw-semibold">{{ __('Search') }}</label>
                <input type="search" name="search" data-live-search autocomplete="off" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Promotion name') }}">
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Type') }}</label>
                <select name="type" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All types') }}</option>
                    @foreach([
                        'order_percentage' => __('Order percentage'),
                        'order_fixed' => __('Order fixed'),
                        'category_percentage' => __('Category percentage'),
                        'buy_x_get_y' => __('Buy X Get Y'),
                    ] as $value => $label)
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
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Schedule') }}</label>
                <select name="schedule" class="form-select" data-live-filter-control>
                    <option value="">{{ __('All schedules') }}</option>
                    <option value="running" @selected($filters['schedule']==='running')>{{ __('Running now') }}</option>
                    <option value="upcoming" @selected($filters['schedule']==='upcoming')>{{ __('Upcoming') }}</option>
                    <option value="expired" @selected($filters['schedule']==='expired')>{{ __('Expired') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                <select name="per_page" class="form-select" data-live-filter-control>
                    @foreach([20,40,80] as $size)
                        <option value="{{ $size }}" @selected((int)$filters['per_page']===$size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}" data-live-default="priority">
            <input type="hidden" name="direction" value="{{ $filters['direction'] }}" data-live-default="desc">
            <div class="admin-filter-actions admin-filter-actions-wide">
                <button type="submit" class="btn btn-primary btn-text-icon"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                <a href="{{ route('admin.promotions.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
            </div>
        </form>
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.promotions.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</div>

@include('admin.promotions._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
