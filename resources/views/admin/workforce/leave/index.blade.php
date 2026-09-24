@extends('layouts.admin')

@section('title', __('Leave review') . ' | Admin')

@section('content')
<div class="admin-page-shell" data-live-list>
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Leave review')" :description="__('Review employee leave requests while keeping balances and work schedules consistent.')">
        <a href="{{ route('admin.workforce.leave-types.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-format-list-bulleted-type"></i><span>{{ __('Leave types') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.manage'))
            <a href="{{ route('admin.workforce.leave.adjustment') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-scale-balance"></i><span>{{ __('Balance adjustment') }}</span></a>
        @endif
        <a href="{{ route('admin.workforce.schedule.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-multiselect-outline"></i><span>{{ __('Work schedule') }}</span></a>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Pending'), 'value' => $stats['pending'], 'copy' => __('Requests waiting for manager review.'), 'icon' => 'mdi-clock-alert-outline'],
            ['label' => __('Currently on leave'), 'value' => $stats['current'], 'copy' => __('Approved requests covering today.'), 'icon' => 'mdi-account-clock-outline'],
            ['label' => __('Approved'), 'value' => $stats['approved'], 'copy' => __('Approved leave request history.'), 'icon' => 'mdi-check-decagram-outline'],
            ['label' => __('Rejected'), 'value' => $stats['rejected'], 'copy' => __('Rejected leave request history.'), 'icon' => 'mdi-close-circle-outline'],
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
            <form method="GET" action="{{ route('admin.workforce.leave.index') }}" class="admin-filter-grid" data-live-filter>
                <div>
                    <label class="form-label fw-semibold">{{ __('Search') }}</label>
                    <input type="search" name="search" value="{{ $filters['search'] }}" data-live-search autocomplete="off" class="form-control" placeholder="{{ __('Employee, code, department, or reason') }}">
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Status') }}</label>
                    <select name="status" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach(\App\Models\EmployeeLeaveRequest::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Leave type') }}</label>
                    <select name="type" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All leave types') }}</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected((int)$filters['type'] === (int)$type->id)>{{ $type->displayName() }}</option>
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
                <div class="admin-filter-actions">
                    <button class="btn btn-primary btn-text-icon" type="submit"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                    <a href="{{ route('admin.workforce.leave.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
                </div>
            </form>
            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('admin.workforce.leave.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>
    </div>

    @include('admin.workforce.leave._results')
</div>
@endsection

@push('scripts')
<script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
