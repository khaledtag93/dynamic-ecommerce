@php
    $editing = $employee->exists;
@endphp

@if(!$editing)
<div class="mb-4">
    <label class="form-label fw-semibold">{{ __('Staff account') }}</label>
    <select name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
        <option value="">{{ __('Select a staff account') }}</option>
        @foreach($candidateUsers as $candidate)
            <option value="{{ $candidate->id }}" @selected((string)old('user_id') === (string)$candidate->id)>{{ $candidate->name }} · {{ $candidate->email }}</option>
        @endforeach
    </select>
    @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="section-note">{{ __('Employee records are linked to existing authenticated staff accounts. Roles and permissions remain managed separately.') }}</div>
</div>
@else
<div class="admin-card mb-4">
    <div class="admin-card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <div class="admin-inline-label">{{ __('Linked staff account') }}</div>
            <div class="fw-bold">{{ $employee->user?->name }}</div>
            <div class="text-muted small">{{ $employee->user?->email }} · {{ $employee->user?->primaryRoleName() }}</div>
        </div>
        @if($employee->openAttendanceSession)
            <span class="badge admin-status-badge badge-soft-success">{{ __('Clocked in') }}</span>
        @else
            <span class="badge admin-status-badge badge-soft-secondary">{{ __('Clocked out') }}</span>
        @endif
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Employee code') }}</label>
        <input type="text" name="employee_code" class="form-control @error('employee_code') is-invalid @enderror" value="{{ old('employee_code', $employee->employee_code) }}" maxlength="40" required placeholder="EMP-001">
        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Job title') }}</label>
        <input type="text" name="job_title" class="form-control @error('job_title') is-invalid @enderror" value="{{ old('job_title', $employee->job_title) }}" maxlength="120">
        @error('job_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Department') }}</label>
        <input type="text" name="department" class="form-control @error('department') is-invalid @enderror" value="{{ old('department', $employee->department) }}" maxlength="120">
        @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Employment type') }}</label>
        <select name="employment_type" class="form-select @error('employment_type') is-invalid @enderror" required>
            @foreach(AppModelsEmployeeProfile::employmentTypeOptions() as $value => $label)
                <option value="{{ $value }}" @selected(old('employment_type', $employee->employment_type ?: AppModelsEmployeeProfile::TYPE_FULL_TIME) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('employment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Employment status') }}</label>
        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach(AppModelsEmployeeProfile::statusOptions() as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $employee->status ?: AppModelsEmployeeProfile::STATUS_ACTIVE) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Phone') }}</label>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $employee->phone) }}" maxlength="50">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">{{ __('Hire date') }}</label>
        <input type="date" name="hire_date" class="form-control @error('hire_date') is-invalid @enderror" value="{{ old('hire_date', optional($employee->hire_date)->format('Y-m-d')) }}">
        @error('hire_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">{{ __('Termination date') }}</label>
        <input type="date" name="termination_date" class="form-control @error('termination_date') is-invalid @enderror" value="{{ old('termination_date', optional($employee->termination_date)->format('Y-m-d')) }}">
        @error('termination_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="section-note">{{ __('Used only when the employee status is Terminated; otherwise it is cleared.') }}</div>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">{{ __('Notes') }}</label>
        <textarea name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" maxlength="2000">{{ old('notes', $employee->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
