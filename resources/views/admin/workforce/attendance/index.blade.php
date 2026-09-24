@extends('layouts.admin')

@section('title', __('Attendance') . ' | Admin')

@section('content')
<div class="admin-page-shell" data-live-list>
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Attendance')" :description="__('Review clock-in and clock-out sessions without mixing attendance records with POS cash shifts.')">
        <a href="{{ route('admin.workforce.employees.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-account-group-outline"></i><span>{{ __('Employees') }}</span></a>
        <a href="{{ route('admin.workforce.schedule.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-multiselect-outline"></i><span>{{ __('Work schedule') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.clock'))
            <a href="{{ route('admin.workforce.time-clock') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-clock-check-outline"></i><span>{{ __('My time clock') }}</span></a>
        @endif
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Open now'), 'value' => $stats['open_now'], 'copy' => __('Attendance sessions currently running.'), 'icon' => 'mdi-clock-in'],
            ['label' => __('Clock-ins today'), 'value' => $stats['clock_ins_today'], 'copy' => __('Attendance sessions started today.'), 'icon' => 'mdi-calendar-today'],
            ['label' => __('Completed today'), 'value' => $stats['completed_today'], 'copy' => __('Today sessions that already have a clock-out.'), 'icon' => 'mdi-clock-check-outline'],
            ['label' => __('Employee profiles'), 'value' => $stats['employees'], 'copy' => __('Workforce profiles available for attendance.'), 'icon' => 'mdi-account-group-outline'],
        ] as $card)
            <div class="col-md-6 col-xl-3">
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
            <form method="GET" action="{{ route('admin.workforce.attendance.index') }}" class="admin-filter-grid" data-live-filter>
                <div>
                    <label class="form-label fw-semibold">{{ __('Search employee') }}</label>
                    <input type="search" name="search" value="{{ $filters['search'] }}" data-live-search autocomplete="off" class="form-control" placeholder="{{ __('Name, email, employee code, department') }}">
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Session status') }}</label>
                    <select name="status" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All sessions') }}</option>
                        <option value="open" @selected($filters['status'] === 'open')>{{ __('Open') }}</option>
                        <option value="closed" @selected($filters['status'] === 'closed')>{{ __('Closed') }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Work date') }}</label>
                    <input type="date" name="date" value="{{ $filters['date'] }}" class="form-control" data-live-filter-control>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                    <select name="per_page" class="form-select" data-live-filter-control>
                        @foreach([20,40,80] as $size)
                            <option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button class="btn btn-primary btn-text-icon" type="submit"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                    <a href="{{ route('admin.workforce.attendance.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
                </div>
            </form>
            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('admin.workforce.attendance.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>
    </div>

    @include('admin.workforce.attendance._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
