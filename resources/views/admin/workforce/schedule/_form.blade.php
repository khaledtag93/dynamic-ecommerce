@php($editing = $shift->exists)

@if($editing && $shift->isCancelled())
    <div class="alert alert-warning border-0 rounded-4">{{ __('Cancelled shifts are kept for history and cannot be edited.') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <label class="form-label fw-semibold">{{ __('Employee') }}</label>
        <select name="employee_profile_id" class="form-select @error('employee_profile_id') is-invalid @enderror" required @disabled($shift->isCancelled())>
            <option value="">{{ __('Select employee') }}</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" @selected((string)old('employee_profile_id', $shift->employee_profile_id) === (string)$employee->id)>
                    {{ $employee->employee_code }} · {{ $employee->user?->name }}@if($employee->department) · {{ $employee->department }}@endif
                </option>
            @endforeach
        </select>
        @error('employee_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label fw-semibold">{{ __('Starts at') }}</label>
        <input type="datetime-local" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror"
               value="{{ old('starts_at', $shift->starts_at?->format('Y-m-d\TH:i')) }}" required @disabled($shift->isCancelled())>
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label fw-semibold">{{ __('Ends at') }}</label>
        <input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror"
               value="{{ old('ends_at', $shift->ends_at?->format('Y-m-d\TH:i')) }}" required @disabled($shift->isCancelled())>
        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Shift status') }}</label>
        <select name="status" class="form-select @error('status') is-invalid @enderror" required @disabled($shift->isCancelled())>
            <option value="{{ \App\Models\EmployeeWorkShift::STATUS_DRAFT }}" @selected(old('status', $shift->status ?: \App\Models\EmployeeWorkShift::STATUS_DRAFT) === \App\Models\EmployeeWorkShift::STATUS_DRAFT)>{{ __('Draft') }}</option>
            <option value="{{ \App\Models\EmployeeWorkShift::STATUS_PUBLISHED }}" @selected(old('status', $shift->status) === \App\Models\EmployeeWorkShift::STATUS_PUBLISHED)>{{ __('Published') }}</option>
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="section-note">{{ __('Draft shifts stay hidden from My Schedule until published.') }}</div>
    </div>

    <div class="col-md-8">
        <label class="form-label fw-semibold">{{ __('Location') }}</label>
        <input type="text" name="location" class="form-control @error('location') is-invalid @enderror"
               value="{{ old('location', $shift->location) }}" maxlength="160" placeholder="{{ __('Optional branch, counter, or work area') }}" @disabled($shift->isCancelled())>
        @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">{{ __('Shift notes') }}</label>
        <textarea name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" maxlength="2000"
                  placeholder="{{ __('Optional instructions or handover context') }}" @disabled($shift->isCancelled())>{{ old('notes', $shift->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

@error('shift')<div class="alert alert-danger border-0 rounded-4 mt-3">{{ $message }}</div>@enderror
