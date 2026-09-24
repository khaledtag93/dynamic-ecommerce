@extends('layouts.admin')

@section('title', __('Employees') . ' | Admin')

@section('content')
<div class="admin-page-shell" data-live-list>
    <x-admin.page-header
        :kicker="__('Workforce')"
        :title="__('Employees')"
        :description="__('Manage staff records, employment status, and operational identity separately from account permissions.')"
    >
        @if(auth()->user()?->hasPermission('workforce.clock'))
            <a href="{{ route('admin.workforce.time-clock') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-clock-check-outline"></i><span>{{ __('My time clock') }}</span></a>
        @endif
        <a href="{{ route('admin.workforce.schedule.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-multiselect-outline"></i><span>{{ __('Work schedule') }}</span></a>
        <a href="{{ route('admin.workforce.attendance.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-clock-outline"></i><span>{{ __('Attendance') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.manage'))
            <a href="{{ route('admin.workforce.employees.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-account-plus-outline"></i><span>{{ __('Add employee') }}</span></a>
        @endif
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Employees'), 'value' => $stats['total'], 'copy' => __('Staff profiles linked to authenticated accounts.'), 'icon' => 'mdi-account-group-outline'],
            ['label' => __('Active'), 'value' => $stats['active'], 'copy' => __('Employees currently available for work.'), 'icon' => 'mdi-account-check-outline'],
            ['label' => __('On leave'), 'value' => $stats['on_leave'], 'copy' => __('Employees currently marked as on leave.'), 'icon' => 'mdi-account-clock-outline'],
            ['label' => __('Clocked in now'), 'value' => $stats['clocked_in'], 'copy' => __('Employees with an open attendance session.'), 'icon' => 'mdi-clock-in'],
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
            <form method="GET" action="{{ route('admin.workforce.employees.index') }}" class="admin-filter-grid" data-live-filter>
                <div>
                    <label class="form-label fw-semibold">{{ __('Search') }}</label>
                    <input type="search" name="search" value="{{ $filters['search'] }}" data-live-search autocomplete="off" class="form-control" placeholder="{{ __('Name, email, employee code, title, department') }}">
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Status') }}</label>
                    <select name="status" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach(\App\Models\EmployeeProfile::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Employment type') }}</label>
                    <select name="employment_type" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All employment types') }}</option>
                        @foreach(\App\Models\EmployeeProfile::employmentTypeOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters['employment_type'] === $value)>{{ $label }}</option>
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
                    <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                    <select name="per_page" class="form-select" data-live-filter-control>
                        @foreach([15,30,60] as $size)
                            <option value="{{ $size }}" @selected((int)$filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button class="btn btn-primary btn-text-icon" type="submit"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                    <a href="{{ route('admin.workforce.employees.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
                </div>
            </form>
            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('admin.workforce.employees.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>
    </div>

    @include('admin.workforce.employees._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
