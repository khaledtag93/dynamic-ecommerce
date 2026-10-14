<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold" for="leaveTypeCode">{{ __('Code') }}</label>
        <input id="leaveTypeCode" type="text" name="code" aria-required="true" class="form-control @error('code') is-invalid @enderror" maxlength="40" value="{{ old('code', $leaveType->code) }}" required placeholder="ANNUAL">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold" for="leaveTypeName">{{ __('Name') }}</label>
        <input id="leaveTypeName" type="text" name="name" aria-required="true" class="form-control @error('name') is-invalid @enderror" maxlength="120" value="{{ old('name', $leaveType->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold" for="leaveTypeNameAr">{{ __('Arabic name') }}</label>
        <input id="leaveTypeNameAr" type="text" name="name_ar" dir="rtl" class="form-control @error('name_ar') is-invalid @enderror" maxlength="120" value="{{ old('name_ar', $leaveType->name_ar) }}">
        @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold" for="leaveTypeEntitlement">{{ __('Annual entitlement days') }}</label>
        <input id="leaveTypeEntitlement" type="number" name="default_annual_entitlement_days" aria-required="true" step="0.25" min="0" max="365" class="form-control @error('default_annual_entitlement_days') is-invalid @enderror" value="{{ old('default_annual_entitlement_days', $leaveType->default_annual_entitlement_days ?? 0) }}" required>
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

    <div class="col-12"><hr><h5 class="mb-1">{{ __('Leave request policy') }}</h5>
        <p class="text-muted small mb-2">{{ __('Legacy keeps existing calendar-day behavior. New duration and workday rules are opt-in and apply only to new requests.') }}</p>
        @if($leaveType->exists)
            <p class="text-muted small mb-2">{{ __('Policy edits affect future requests. Existing request dates, charged days, and recorded policy snapshots remain unchanged. Effective-date scheduling is not enabled.') }}</p>
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold" for="leaveDurationMode">{{ __('Duration mode') }}</label>
        <select id="leaveDurationMode" name="duration_mode" class="form-select @error('duration_mode') is-invalid @enderror">
            @foreach(['legacy' => __('Legacy (unchanged)'), 'fixed' => __('Fixed duration'), 'flexible' => __('Flexible duration')] as $mode => $label)
                <option value="{{ $mode }}" @selected(old('duration_mode', $leaveType->duration_mode ?? 'legacy') === $mode)>{{ $label }}</option>
            @endforeach
        </select>
        @error('duration_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold" for="leaveFixedDays">{{ __('Fixed request days') }}</label>
        <input type="number" id="leaveFixedDays" name="fixed_duration_days" min="1" max="365" step="1" class="form-control @error('fixed_duration_days') is-invalid @enderror" value="{{ old('fixed_duration_days', $leaveType->fixed_duration_days) }}">
        @error('fixed_duration_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label fw-semibold" for="leaveMinDays">{{ __('Minimum request days') }}</label>
        <input type="number" id="leaveMinDays" name="min_request_days" min="1" max="365" step="1" class="form-control @error('min_request_days') is-invalid @enderror" value="{{ old('min_request_days', $leaveType->min_request_days) }}">
        @error('min_request_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label fw-semibold" for="leaveMaxDays">{{ __('Maximum request days') }}</label>
        <input type="number" id="leaveMaxDays" name="max_request_days" min="1" max="365" step="1" class="form-control @error('max_request_days') is-invalid @enderror" value="{{ old('max_request_days', $leaveType->max_request_days) }}">
        @error('max_request_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold" for="leaveCountingMode">{{ __('Count requested days by') }}</label>
        <select id="leaveCountingMode" name="counting_mode" class="form-select @error('counting_mode') is-invalid @enderror">
            <option value="calendar" @selected(old('counting_mode', $leaveType->counting_mode ?? 'calendar') === 'calendar')>{{ __('Calendar days') }}</option>
            <option value="workdays" @selected(old('counting_mode', $leaveType->counting_mode ?? 'calendar') === 'workdays')>{{ __('Configured working days') }}</option>
        </select>
        @error('counting_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label class="form-label fw-semibold">{{ __('Working weekdays (select explicitly when using workdays)') }}</label>
        <div class="d-flex gap-3 flex-wrap">
            @foreach([1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')] as $day => $label)
                <label class="form-check-label"><input type="checkbox" name="working_weekdays[]" value="{{ $day }}" class="form-check-input me-1" @checked(in_array($day, array_map('intval', old('working_weekdays', $leaveType->working_weekdays ?? []))))> {{ $label }}</label>
            @endforeach
        </div>
        @error('working_weekdays')<div class="text-danger small">{{ $message }}</div>@enderror
        @error('working_weekdays.*')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold" for="leaveHolidayDates">{{ __('Holiday dates (one YYYY-MM-DD per line)') }}</label>
        <textarea id="leaveHolidayDates" name="holiday_dates_input" rows="3" class="form-control @error('holiday_dates') is-invalid @enderror" placeholder="2026-12-25">{{ old('holiday_dates_input', implode("\n", $leaveType->holiday_dates ?? [])) }}</textarea>
        <div class="form-text">{{ __('These dates are excluded only when workday counting is selected. No public holidays are assumed.') }}</div>
        @error('holiday_dates')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('holiday_dates.*')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold" for="leaveTypeNotes">{{ __('Notes') }}</label>
        <textarea id="leaveTypeNotes" name="notes" rows="4" maxlength="2000" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $leaveType->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
