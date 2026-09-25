@extends('layouts.admin')

@section('title', __('Customer Support') . ' | Admin')

@section('content')
<x-admin.page-header
    :kicker="__('Customer service')"
    :title="__('Customer Support')"
    :description="__('Track customer cases, ownership, urgency, order context, and conversation progress from one workspace.')"
>
    @if(auth()->user()?->hasPermission('support.manage'))
        <a href="{{ route('admin.support.settings') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-tune-variant"></i><span>{{ __('Support settings') }}</span></a>
        <a href="{{ route('admin.support.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus"></i><span>{{ __('New support case') }}</span></a>
    @endif
</x-admin.page-header>

<div class="admin-page-shell" data-live-list>
    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Open cases'), 'value' => $stats['open'], 'icon' => 'mdi-headset', 'tone' => 'primary'],
            ['label' => __('Urgent'), 'value' => $stats['urgent'], 'icon' => 'mdi-alert-circle-outline', 'tone' => 'danger'],
            ['label' => __('Unassigned'), 'value' => $stats['unassigned'], 'icon' => 'mdi-account-question-outline', 'tone' => 'warning'],
            ['label' => __('Resolved'), 'value' => $stats['resolved'], 'icon' => 'mdi-check-circle-outline', 'tone' => 'success'],
            ['label' => __('SLA breached'), 'value' => $stats['sla_breached'], 'icon' => 'mdi-timer-alert-outline', 'tone' => 'danger'],
        ] as $card)
            <div class="col-sm-6 col-xl">
                <x-admin.stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :tone="$card['tone']" class="h-100" />
            </div>
        @endforeach
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <form method="GET" action="{{ route('admin.support.index') }}" class="admin-filter-grid" data-live-filter>
                <div>
                    <label class="form-label fw-semibold">{{ __('Search cases') }}</label>
                    <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Case, subject, customer, email, or order') }}" autocomplete="off" data-live-search>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Status') }}</label>
                    <select name="status" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach(\App\Models\SupportCase::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Priority') }}</label>
                    <select name="priority" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All priorities') }}</option>
                        @foreach(\App\Models\SupportCase::priorityOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">{{ __('Owner') }}</label>
                    <select name="assigned_to_user_id" class="form-select" data-live-filter-control>
                        <option value="">{{ __('All owners') }}</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" @selected((int)$filters['assigned_to_user_id'] === (int)$member->id)>{{ $member->name }}</option>
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
                    <button class="btn btn-primary btn-text-icon"><i class="mdi mdi-filter-outline"></i><span>{{ __('Apply') }}</span></button>
                    <a href="{{ route('admin.support.index') }}" class="btn btn-light border btn-text-icon" data-live-reset><i class="mdi mdi-refresh"></i><span>{{ __('Reset') }}</span></a>
                </div>
            </form>
            <div class="small mt-2" role="status" aria-live="polite" data-live-status
                 data-loading="{{ __('Updating results...') }}"
                 data-updated="{{ __('Results updated.') }}"
                 data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
            <a href="{{ route('admin.support.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
        </div>
    </div>

    @include('admin.support._results')
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
