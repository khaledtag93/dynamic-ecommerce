@extends('layouts.admin')

@section('title', __('My leave') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('My leave')" :description="__('Review your leave balances, submit requests, and follow manager decisions.')">
        <a href="{{ route('admin.workforce.my-schedule') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-account-outline"></i><span>{{ __('My schedule') }}</span></a>
        <a href="{{ route('admin.workforce.time-clock') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-clock-check-outline"></i><span>{{ __('My time clock') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.view'))
            <a href="{{ route('admin.workforce.leave.index') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-calendar-check-outline"></i><span>{{ __('Leave review') }}</span></a>
        @endif
    </x-admin.page-header>

    @if(!$employee)
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="admin-empty-state py-5">
                    <div class="empty-icon"><i class="mdi mdi-account-alert-outline"></i></div>
                    <h4 class="mb-2">{{ __('Employee profile not configured') }}</h4>
                    <p class="text-muted mb-0">{{ __('Your account must be linked to an employee profile before leave can be requested.') }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-1">{{ __('Leave balances for :year', ['year' => $year]) }}</h4>
                <div class="text-muted small">{{ __('Available balance excludes approved leave; projected balance also subtracts pending requests.') }}</div>
            </div>
            <form method="GET" action="{{ route('admin.workforce.my-leave') }}" class="d-flex gap-2">
                <input type="number" name="year" class="form-control" min="2000" max="2100" value="{{ $year }}" style="max-width: 120px">
                <button class="btn btn-light border">{{ __('View year') }}</button>
            </form>
        </div>

        <div class="row g-3 mb-4">
            @forelse($balances as $row)
                @php($balance = $row['balance'])
                <div class="col-md-6 col-xl-4">
                    <div class="admin-card h-100">
                        <div class="admin-card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="admin-inline-label">{{ $row['type']->code }}</div>
                                    <h4 class="mb-1">{{ $row['type']->displayName() }}</h4>
                                </div>
                                <span class="badge admin-status-badge {{ $row['type']->is_paid ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $row['type']->is_paid ? __('Paid') : __('Unpaid') }}</span>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-6"><div class="text-muted small">{{ __('Available') }}</div><div class="h4 mb-0">{{ number_format($balance['available'], 2) }}</div></div>
                                <div class="col-6"><div class="text-muted small">{{ __('Projected') }}</div><div class="h4 mb-0">{{ number_format($balance['projected_available'], 2) }}</div></div>
                                <div class="col-4"><div class="text-muted small">{{ __('Entitlement') }}</div><div class="fw-semibold">{{ number_format($balance['entitlement'], 2) }}</div></div>
                                <div class="col-4"><div class="text-muted small">{{ __('Used') }}</div><div class="fw-semibold">{{ number_format($balance['used'], 2) }}</div></div>
                                <div class="col-4"><div class="text-muted small">{{ __('Pending') }}</div><div class="fw-semibold">{{ number_format($balance['pending'], 2) }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="admin-card"><div class="admin-card-body"><div class="admin-empty-state py-4">
                        <div class="empty-icon"><i class="mdi mdi-calendar-question-outline"></i></div>
                        <h5>{{ __('No active leave types') }}</h5>
                        <p class="text-muted mb-0">{{ __('A manager must configure leave types before requests can be submitted.') }}</p>
                    </div></div></div>
                </div>
            @endforelse
        </div>

        <div class="row g-4">
            <div class="col-xl-5">
                <div class="admin-card">
                    <div class="admin-card-body">
                        <h4 class="mb-1">{{ __('Request leave') }}</h4>
                        <p class="text-muted small">{{ __('V1 counts inclusive calendar days. Requests cannot cross calendar years.') }}</p>

                        @error('leave')<div class="alert alert-danger border-0 rounded-4">{{ $message }}</div>@enderror

                        @if($leaveTypes->isEmpty())
                            <div class="alert alert-warning border-0 rounded-4 mb-0">{{ __('No active leave type is available for new requests.') }}</div>
                        @else
                            <form method="POST" action="{{ route('admin.workforce.leave.request') }}" data-submit-loading>
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">{{ __('Leave type') }}</label>
                                    <select name="employee_leave_type_id" class="form-select @error('employee_leave_type_id') is-invalid @enderror" required>
                                        <option value="">{{ __('Select leave type') }}</option>
                                        @foreach($leaveTypes as $type)
                                            <option value="{{ $type->id }}" @selected((string)old('employee_leave_type_id') === (string)$type->id)>{{ $type->displayName() }} · {{ $type->code }}</option>
                                        @endforeach
                                    </select>
                                    @error('employee_leave_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">{{ __('Starts on') }}</label>
                                        <input type="date" name="starts_on" class="form-control @error('starts_on') is-invalid @enderror" value="{{ old('starts_on') }}" required>
                                        @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">{{ __('Ends on') }}</label>
                                        <input type="date" name="ends_on" class="form-control @error('ends_on') is-invalid @enderror" value="{{ old('ends_on') }}" required>
                                        @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label fw-semibold">{{ __('Reason') }}</label>
                                    <textarea name="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" maxlength="2000" placeholder="{{ __('Optional leave request context') }}">{{ old('reason') }}</textarea>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button class="btn btn-primary w-100 btn-text-icon mt-3" data-loading-text="{{ __('Submitting...') }}"><i class="mdi mdi-calendar-plus"></i><span>{{ __('Submit leave request') }}</span></button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="admin-card">
                    <div class="admin-card-body">
                        <div class="admin-table-toolbar">
                            <div>
                                <h4 class="mb-1">{{ __('My leave requests') }}</h4>
                                <div class="text-muted small">{{ __('Pending requests can be cancelled until a manager reviews them.') }}</div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Days') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                                <tbody>
                                    @if($requests->isEmpty())
                                        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No leave requests yet.') }}</td></tr>
                                    @else
                                        @foreach($requests as $leaveRequest)
                                            @php
                                                $statusClass = match($leaveRequest->status) {
                                                    \App\Models\EmployeeLeaveRequest::STATUS_APPROVED => 'badge-soft-success',
                                                    \App\Models\EmployeeLeaveRequest::STATUS_REJECTED => 'badge-soft-danger',
                                                    \App\Models\EmployeeLeaveRequest::STATUS_CANCELLED => 'badge-soft-secondary',
                                                    default => 'badge-soft-warning',
                                                };
                                            @endphp
                                            <tr>
                                                <td><div class="fw-semibold">{{ $leaveRequest->leaveType?->displayName() }}</div><div class="text-muted small">{{ $leaveRequest->reason ?: '—' }}</div></td>
                                                <td>{{ $leaveRequest->starts_on->format('d M Y') }} → {{ $leaveRequest->ends_on->format('d M Y') }}</td>
                                                <td>{{ number_format((float)$leaveRequest->requested_days, 2) }}</td>
                                                <td>
                                                    <span class="badge admin-status-badge {{ $statusClass }}">{{ \App\Models\EmployeeLeaveRequest::statusOptions()[$leaveRequest->status] ?? \Illuminate\Support\Str::headline($leaveRequest->status) }}</span>
                                                    @if($leaveRequest->review_notes)<div class="text-muted small mt-1">{{ $leaveRequest->review_notes }}</div>@endif
                                                </td>
                                                <td class="text-end">
                                                    @if($leaveRequest->isPending())
                                                        <form method="POST" action="{{ route('admin.workforce.leave.cancel', $leaveRequest) }}" data-confirm-message="{{ __('Cancel this pending leave request?') }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button class="btn btn-sm btn-outline-danger">{{ __('Cancel request') }}</button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted small">{{ __('Review complete') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
