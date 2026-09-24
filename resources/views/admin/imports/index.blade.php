@extends('layouts.admin')

@section('title', __('Import Jobs') . ' | Admin')

@section('content')
<div class="admin-page-shell" data-live-list>
<div class="admin-page-header">
    <div>
        <div class="admin-kicker">{{ __('Data tools') }}</div>
        <h1 class="admin-page-title">{{ __('Import Jobs') }}</h1>
        <p class="admin-page-description">{{ __('Prepare CSV import drafts with safer validation feedback and cleaner empty states.') }}</p>
    </div>
</div>

<form method="GET" action="{{ route('admin.imports.index') }}" data-live-filter class="d-none"></form>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-database-import-outline"></i></span>
            <div class="admin-stat-label">{{ __('Total jobs') }}</div>
            <div class="admin-stat-value">{{ $stats['total'] }}</div>
            <div class="text-muted small mt-2">{{ __('Import drafts created in the system.') }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-file-document-edit-outline"></i></span>
            <div class="admin-stat-label">{{ __('Draft jobs') }}</div>
            <div class="admin-stat-value">{{ $stats['draft'] }}</div>
            <div class="text-muted small mt-2">{{ __('Drafts waiting for future processing logic.') }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-file-check-outline"></i></span>
            <div class="admin-stat-label">{{ __('Named files') }}</div>
            <div class="admin-stat-value">{{ $stats['named_files'] }}</div>
            <div class="text-muted small mt-2">{{ __('Drafts with a source file name recorded for later processing.') }}</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Create import draft') }}</h4>
                <p class="text-muted small mb-4">{{ __('This creates a safe draft only. It does not import any data yet.') }}</p>

                <form method="POST" action="{{ route('admin.imports.store') }}" data-submit-loading>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('Type') }}</label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror">
                            <option value="products" @selected(old('type') === 'products')>{{ __('Products') }}</option>
                            <option value="customers" @selected(old('type') === 'customers')>{{ __('Customers') }}</option>
                            <option value="orders" @selected(old('type') === 'orders')>{{ __('Orders') }}</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('File name') }}</label>
                        <input type="text" name="file_name" class="form-control @error('file_name') is-invalid @enderror" value="{{ old('file_name') }}" placeholder="products.csv">
                        @error('file_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="section-note">{{ __('Optional now. Useful for tracking drafts later.') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Column mapping JSON') }}</label>
                        <textarea name="column_mapping" class="form-control @error('column_mapping') is-invalid @enderror" rows="6" placeholder='{"name":"Product Name","base_price":"Price"}'>{{ old('column_mapping') }}</textarea>
                        @error('column_mapping')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="admin-form-actions-compact">
                        <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Creating...') }}"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Create job') }}</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        @include('admin.imports._results')
        <div class="small mt-2" role="status" aria-live="polite" data-live-status
             data-loading="{{ __('Updating results...') }}"
             data-updated="{{ __('Results updated.') }}"
             data-error="{{ __('Could not update results. Open the full page to retry.') }}"></div>
        <a href="{{ route('admin.imports.index') }}" class="small" data-live-fallback hidden>{{ __('Open full page') }}</a>
    </div>
</div>
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
