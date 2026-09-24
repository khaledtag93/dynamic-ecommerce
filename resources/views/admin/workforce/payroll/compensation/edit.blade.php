@extends('layouts.admin')

@section('title', __('Edit compensation') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header
        :kicker="__('Payroll')"
        :title="__('Edit compensation')"
        :description="__('Changes affect future payroll snapshots only. Existing payroll entries keep their historical rate and currency snapshots.')"
    >
        <a href="{{ route('admin.workforce.payroll.compensation.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to compensation') }}</span></a>
    </x-admin.page-header>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="admin-inline-label">{{ __('Employee') }}</div>
            <h3 class="mb-1">{{ $employee->user?->name }}</h3>
            <div class="text-muted">{{ $employee->employee_code }}@if($employee->department) · {{ $employee->department }}@endif @if($employee->job_title) · {{ $employee->job_title }}@endif</div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.workforce.payroll.compensation.update', $employee) }}" data-submit-loading>
                @csrf
                @method('PUT')

                <div class="alert alert-info border-0 rounded-4">
                    {{ __('For Salary, Base rate is the fixed base amount for one payroll period. For Hourly, Base rate is the amount per effective net attendance hour. Paid leave, unpaid leave, overtime, tax, and statutory deductions are not automatically monetized in V1.') }}
                </div>

                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fw-semibold">{{ __('Pay basis') }}</label>
                        <select name="pay_basis" class="form-select @error('pay_basis') is-invalid @enderror" required>
                            @foreach(\App\Models\EmployeeCompensation::payBasisOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('pay_basis', $compensation->pay_basis) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('pay_basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fw-semibold">{{ __('Base rate') }}</label>
                        <input type="number" name="base_rate" step="0.01" min="0.01" class="form-control @error('base_rate') is-invalid @enderror" value="{{ old('base_rate', $compensation->base_rate) }}" required>
                        @error('base_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <label class="form-label fw-semibold">{{ __('Currency') }}</label>
                        <input type="text" name="currency" maxlength="3" class="form-control text-uppercase @error('currency') is-invalid @enderror" value="{{ old('currency', $compensation->currency) }}" required placeholder="ISO">
                        @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <label class="form-label fw-semibold">{{ __('Effective from') }}</label>
                        <input type="date" name="effective_from" class="form-control @error('effective_from') is-invalid @enderror" value="{{ old('effective_from', $compensation->effective_from?->format('Y-m-d')) }}" required>
                        @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <label class="form-label fw-semibold">{{ __('Effective to') }}</label>
                        <input type="date" name="effective_to" class="form-control @error('effective_to') is-invalid @enderror" value="{{ old('effective_to', $compensation->effective_to?->format('Y-m-d')) }}">
                        @error('effective_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold d-block">{{ __('Overtime policy') }}</label>
                        <input type="hidden" name="overtime_eligible" value="0">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="overtime_eligible" value="1" id="overtimeEligible" @checked(old('overtime_eligible', $compensation->overtime_eligible))>
                            <label class="form-check-label" for="overtimeEligible">{{ __('Eligible for explicit overtime payroll components') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('Reference overtime multiplier') }}</label>
                        <input type="number" name="overtime_rate_multiplier" step="0.001" min="0.001" max="10" class="form-control @error('overtime_rate_multiplier') is-invalid @enderror" value="{{ old('overtime_rate_multiplier', $compensation->overtime_rate_multiplier) }}">
                        <div class="section-note">{{ __('Stored as policy context only. V1 does not auto-create overtime pay.') }}</div>
                        @error('overtime_rate_multiplier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Notes') }}</label>
                        <textarea name="notes" rows="4" maxlength="2000" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $compensation->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="admin-form-actions mt-4">
                    <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Save compensation') }}</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
