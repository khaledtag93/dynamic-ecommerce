@extends('layouts.admin')

@section('title', __('Work schedule') . ' | Admin')

@section('content')
<div class="admin-page-shell" data-live-list>
    <x-admin.page-header
        :kicker="__('Workforce')"
        :title="__('Work schedule')"
        :description="__('Plan employee work shifts, prevent overlaps, and compare published schedules with actual attendance.')"
    >
        <a href="{{ route('admin.workforce.employees.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-account-group-outline"></i><span>{{ __('Employees') }}</span></a>
        <a href="{{ route('admin.workforce.attendance.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-clock-outline"></i><span>{{ __('Attendance') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.manage'))
            <a href="{{ route('admin.workforce.schedule.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-calendar-plus"></i><span>{{ __('Add work shift') }}</span></a>
        @endif
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Published today'), 'value' => $stats['today'], 'copy' => __('Published work shifts starting today.'), 'icon' => 'mdi-calendar-today'],
            ['label' => __('Next 7 days'), 'value' => $stats['upcoming_7d'], 'copy' => __('Published shifts starting in the next seven days.'), 'icon' => 'mdi-calendar-week'],
            ['label' => __('Draft shifts'), 'value' => $stats['draft'], 'copy' => __('Prepared shifts not visible in employee schedules yet.'), 'icon' => 'mdi-file-document-edit-outline'],
            ['label' => __('Published shifts'), 'value' => $stats['published'], 'copy' => __('Published shifts across the schedule history.'), 'icon' => 'mdi-calendar-check-outline'],
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
            <form method="GET" action="{{ route('admin.workforce.schedule.index') }}" class="admin-filter-grid" data-live-filter>
                <div>
                    <label class="form-label fw-semibold">{{ __('Search') }}</label>
                    <input type="search" name="search" value="{{ $filters['search'] }}" data-live-search autocomplete="off" class="form-control" placeholder="{{ __('Employee, code, department, role, or location') }}">
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Shift status') }}</label>
                    <select name="status" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All shifts') }}</option>
                        @foreach(AppModelsEmployeeWorkShift::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Department') }}</label>
                    <select name="department" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All departments') }}</option>
                        @foreach($departments as $department)
                            <option value="{{ $department }}" @selected($filters['department'] === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('From date') }}</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control" data-live-filter-control>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('To date') }}</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control" data-live-filter-control>
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
                    <a href="{{ route('admin.workforce.schedule.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
                </div>
            </form>

            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('admin.workforce.schedule.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>
    </div>

    @include('admin.workforce.schedule._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
