<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="admin-section-heading">
                    <div>
                        <h4 class="admin-section-title">{{ __('Supplier profile') }}</h4>
                        <div class="admin-section-subtitle">{{ __('Keep core contact, business, and sourcing information aligned across purchases and supplier listings.') }}</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">{{ __('Name') }}</label><input type="text" name="name" value="{{ old('name', $supplier->name) }}" class="form-control @error('name') is-invalid @enderror" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">{{ __('Company') }}</label><input type="text" name="company" value="{{ old('company', $supplier->company) }}" class="form-control @error('company') is-invalid @enderror">@error('company')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">{{ __('Contact name') }}</label><input type="text" name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}" class="form-control @error('contact_name') is-invalid @enderror">@error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">{{ __('Phone') }}</label><input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="form-control @error('phone') is-invalid @enderror">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">{{ __('Country') }}</label><input type="text" name="country" value="{{ old('country', $supplier->country) }}" class="form-control @error('country') is-invalid @enderror">@error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label">{{ __('Address') }}</label><textarea name="address" class="form-control @error('address') is-invalid @enderror" rows="3">{{ old('address', $supplier->address) }}</textarea>@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4">{{ old('notes', $supplier->notes) }}</textarea>@error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="admin-section-heading">
                    <div>
                        <h4 class="admin-section-title">{{ __('Availability') }}</h4>
                        <div class="admin-section-subtitle">{{ __('Pause or reactivate the supplier without removing historical records.') }}</div>
                    </div>
                </div>
                <div class="admin-toggle-card">
                    <div>
                        <div class="fw-semibold mb-1">{{ __('Active supplier') }}</div>
                        <small class="text-muted">{{ __('Disable this option to pause the supplier without deleting the record.') }}</small>
                    </div>
                    <div class="form-check form-switch admin-switch-wrap m-0">
                        <input class="form-check-input" type="checkbox" id="supplierIsActive" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true))>
                        <label class="visually-hidden" for="supplierIsActive">{{ __('Active supplier') }}</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="admin-actions-stack justify-content-end">
            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light admin-btn-soft admin-back-btn"><i class="mdi mdi-arrow-left"></i>{{ __('Cancel') }}</a>
            <button class="btn btn-primary">{{ $submitLabel }}</button>
        </div>
    </div>
</div>


@push('styles')
<style>
    .admin-toggle-card {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:1rem;
        padding:1rem 1.1rem;
        border:1px solid color-mix(in srgb, var(--admin-primary) 18%, white);
        border-radius:1rem;
        background:color-mix(in srgb, var(--admin-surface) 76%, white);
    }
    .admin-switch-wrap .form-check-input {
        float:none;
        margin:0;
        width:3rem;
        height:1.55rem;
    }
    .admin-back-btn {
        border-radius:0.95rem;
        padding-inline:1rem;
    }
</style>
@endpush
