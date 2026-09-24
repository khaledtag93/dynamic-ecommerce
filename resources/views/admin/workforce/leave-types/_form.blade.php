<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Code') }}</label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" maxlength="40" value="{{ old('code', $leaveType->code) }}" required placeholder="ANNUAL">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Name') }}</label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" maxlength="120" value="{{ old('name', $leaveType->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Arabic name') }}</label>
        <input type="text" name="name_ar" dir="rtl" class="form-control @error('name_ar') is-invalid @enderror" maxlength="120" value="{{ old('name_ar', $leaveType->name_ar) }}">
        @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">{{ __('Annual entitlement days') }}</label>
        <input type="number" name="default_annual_entitlement_days" step="0.25" min="0" max="365" class="form-control @error('default_annual_entitlement_days') is-invalid @enderror" value="{{ old('default_annual_entitlement_days', $leaveType->default_annual_entitlement_days ?? 0) }}" required>
        @error('default_annual_entitlement_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold d-block">{{ __('Payment') }}</label>
        <input type="hidden" name="is_paid" value="0">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="is_paid" value="1" id="leaveTypePaid" @checked(old('is_paid', $leaveType->exists ? $leaveType->is_paid : true))>
            <label class="form-check-label" for="leaveTypePaid">{{ __('Paid leave') }}</label>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold d-block">{{ __('Availability') }}</label>
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="leaveTypeActive" @checked(old('is_active', $leaveType->exists ? $leaveType->is_active : true))>
            <label class="form-check-label" for="leaveTypeActive">{{ __('Active for new requests') }}</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">{{ __('Notes') }}</label>
        <textarea name="notes" rows="4" maxlength="2000" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $leaveType->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
