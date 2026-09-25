@extends('layouts.admin')

@section('title', __('Shipping methods') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Commerce setup')" :title="__('Shipping methods')" :description="__('Control delivery methods, storefront names, availability, and ETA without changing their stable system codes.')">
    </x-admin.page-header>

    @include('admin.settings.shipping._nav', ['shippingSection' => 'methods'])

    <div class="row g-4">
        @foreach($methods as $method)
            <div class="col-xl-4">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <div class="d-flex justify-content-between gap-3 align-items-start mb-3">
                            <div>
                                <div class="admin-inline-label">{{ $method->code }}</div>
                                <h4 class="mb-1">{{ $method->displayName() }}</h4>
                            </div>
                            <span class="badge admin-status-badge {{ $method->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $method->is_active ? __('Active') : __('Inactive') }}</span>
                        </div>

                        <form method="POST" action="{{ route('admin.settings.shipping.methods.update', $method) }}" data-submit-loading>
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('English name') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $method->name) }}" required maxlength="120">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('Arabic name') }}</label>
                                <input type="text" name="name_ar" dir="rtl" class="form-control" value="{{ old('name_ar', $method->name_ar) }}" maxlength="120">
                            </div>
                            <div class="row g-3">
                                <div class="col-4">
                                    <label class="form-label fw-semibold">{{ __('Sort') }}</label>
                                    <input type="number" name="sort_order" min="1" max="999" class="form-control" value="{{ old('sort_order', $method->sort_order) }}" required>
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold">{{ __('ETA min') }}</label>
                                    <input type="number" name="eta_min_days" min="0" max="365" class="form-control" value="{{ old('eta_min_days', $method->eta_min_days) }}">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold">{{ __('ETA max') }}</label>
                                    <input type="number" name="eta_max_days" min="0" max="365" class="form-control" value="{{ old('eta_max_days', $method->eta_max_days) }}">
                                </div>
                            </div>
                            <div class="mt-3">
                                <input type="hidden" name="is_active" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="method-active-{{ $method->id }}" @checked($method->is_active)>
                                    <label class="form-check-label" for="method-active-{{ $method->id }}">{{ __('Available at checkout') }}</label>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label fw-semibold">{{ __('Notes') }}</label>
                                <textarea name="notes" rows="3" maxlength="2000" class="form-control">{{ old('notes', $method->notes) }}</textarea>
                            </div>
                            <button class="btn btn-primary w-100 mt-3" data-loading-text="{{ __('Saving...') }}">{{ __('Save method') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
