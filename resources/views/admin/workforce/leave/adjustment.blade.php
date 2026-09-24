@extends('layouts.admin')

@section('title', __('Leave balance adjustment') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('Leave balance adjustment')" :description="__('Record an append-only opening balance, carryover, credit, or debit without rewriting leave history.')">
        <a href="{{ route('admin.workforce.leave.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to leave review') }}</span></a>
    </x-admin.page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="alert alert-info border-0 rounded-4">{{ __('Adjustments are permanent audit records. Use a negative number only for a documented debit.') }}</div>

            <form method="POST" action="{{ route('admin.workforce.leave.adjust') }}" data-submit-loading>
                @csrf
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">{{ __('Employee') }}</label>
                        <select name="employee_profile_id" class="form-select @error('employee_profile_id') is-invalid @enderror" required>
                            <option value="">{{ __('Select employee') }}</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string)old('employee_profile_id') === (string)$employee->id)>{{ $employee->employee_code }} · {{ $employee->user?->name }}</option>
                            @endforeach
                        </select>
                        @error('employee_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">{{ __('Leave type') }}</label>
                        <select name="employee_leave_type_id" class="form-select @error('employee_leave_type_id') is-invalid @enderror" required>
                            <option value="">{{ __('Select leave type') }}</option>
                            @foreach($leaveTypes as $type)
                                <option value="{{ $type->id }}" @selected((string)old('employee_leave_type_id') === (string)$type->id)>{{ $type->displayName() }} · {{ $type->code }}</option>
                            @endforeach
                        </select>
                        @error('employee_leave_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ __('Year') }}</label>
                        <input type="number" name="year" min="2000" max="2100" value="{{ old('year', $year) }}" class="form-control @error('year') is-invalid @enderror" required>
                        @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ __('Adjustment type') }}</label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach(AppModelsEmployeeLeaveAdjustment::typeOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', AppModelsEmployeeLeaveAdjustment::TYPE_ADJUSTMENT) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ __('Days') }}</label>
                        <input type="number" name="days" step="0.25" min="-365" max="365" value="{{ old('days') }}" class="form-control @error('days') is-invalid @enderror" required>
                        @error('days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Reason') }}</label>
                        <textarea name="reason" rows="4" maxlength="2000" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="admin-form-actions mt-4">
                    <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Record adjustment') }}</span></button>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card mt-4">
        <div class="admin-card-body">
            <div class="admin-table-toolbar">
                <div>
                    <h4 class="mb-1">{{ __('Recent balance adjustments') }}</h4>
                    <div class="text-muted small">{{ __('Append-only history for opening balances, carryovers, credits, and debits.') }}</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Leave type') }}</th><th>{{ __('Year') }}</th><th>{{ __('Type') }}</th><th>{{ __('Days') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Actor') }}</th></tr></thead>
                    <tbody>
                        @forelse($adjustments as $adjustment)
                            <tr>
                                <td><div class="fw-semibold">{{ $adjustment->employee?->user?->name }}</div><div class="text-muted small">{{ $adjustment->employee?->employee_code }}</div></td>
                                <td>{{ $adjustment->leaveType?->displayName() }}</td>
                                <td>{{ $adjustment->year }}</td>
                                <td>{{ AppModelsEmployeeLeaveAdjustment::typeOptions()[$adjustment->type] ?? IlluminateSupportStr::headline($adjustment->type) }}</td>
                                <td class="fw-semibold {{ (float)$adjustment->days < 0 ? 'text-danger' : 'text-success' }}">{{ (float)$adjustment->days > 0 ? '+' : '' }}{{ number_format((float)$adjustment->days, 2) }}</td>
                                <td>{{ $adjustment->reason }}</td>
                                <td><div>{{ $adjustment->createdBy?->name ?: '—' }}</div><div class="text-muted small">{{ $adjustment->created_at?->format('d M Y H:i') }}</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No balance adjustments yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
