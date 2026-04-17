@extends('layouts.admin')

@section('title', __('Import Jobs') . ' | Admin')

@section('content')
<div class="admin-page-shell">
<div class="admin-page-header">
    <div>
        <div class="admin-kicker">{{ __('Data tools') }}</div>
        <h1 class="admin-page-title">{{ __('Import Jobs') }}</h1>
        <p class="admin-page-description">{{ __('Prepare CSV import drafts with safer validation feedback and cleaner empty states.') }}</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-database-import-outline"></i></span>
            <div class="admin-stat-label">{{ __('Total jobs') }}</div>
            <div class="admin-stat-value">{{ $jobs->total() }}</div>
            <div class="text-muted small mt-2">{{ __('Import drafts created in the system.') }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-file-document-edit-outline"></i></span>
            <div class="admin-stat-label">{{ __('Draft jobs') }}</div>
            <div class="admin-stat-value">{{ $jobs->where('status', 'draft')->count() }}</div>
            <div class="text-muted small mt-2">{{ __('Drafts waiting for future processing logic.') }}</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="admin-card admin-stat-card h-100">
            <span class="admin-stat-icon"><i class="mdi mdi-table-search"></i></span>
            <div class="admin-stat-label">{{ __('This page') }}</div>
            <div class="admin-stat-value">{{ $jobs->count() }}</div>
            <div class="text-muted small mt-2">{{ __('Import records visible right now.') }}</div>
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
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('File name') }}</label>
                        <input type="text" name="file_name" class="form-control @error('file_name') is-invalid @enderror" value="{{ old('file_name') }}" placeholder="products.csv">
                        @error('file_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="section-note">{{ __('Optional now. Useful for tracking drafts later.') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Column mapping JSON') }}</label>
                        <textarea name="column_mapping" class="form-control @error('column_mapping') is-invalid @enderror" rows="6" placeholder='{"name":"Product Name","base_price":"Price"}'>{{ old('column_mapping') }}</textarea>
                        @error('column_mapping')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="admin-form-actions-compact">
                        <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Creating...') }}">
                            <i class="mdi mdi-plus-circle-outline"></i>
                            <span>{{ __('Create job') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="admin-table-toolbar">
                    <div>
                        <h4 class="mb-1">{{ __('Import drafts') }}</h4>
                        <div class="text-muted small">{{ __('Showing :count draft(s) on this page.', ['count' => $jobs->count()]) }}</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('File') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Rows') }}</th>
                                <th>{{ __('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jobs as $job)
                                <tr>
                                    <td>{{ ucfirst((string) $job->type) }}</td>
                                    <td>{{ $job->file_name ?: '—' }}</td>
                                    <td><span class="badge admin-status-badge badge-soft-secondary">{{ ucfirst((string) ($job->status ?: 'draft')) }}</span></td>
                                    <td>{{ (int) $job->rows_processed }}/{{ (int) $job->rows_total }}</td>
                                    <td>{{ optional($job->created_at)->format('M d, Y H:i') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-5">
                                        <div class="admin-empty-state py-4">
                                            <div class="empty-icon"><i class="mdi mdi-database-off-outline"></i></div>
                                            <h5 class="mb-2">{{ __('No import jobs yet') }}</h5>
                                            <p class="text-muted mb-0">{{ __('Create a draft on the left to prepare future product, customer, or order imports.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4">{{ $jobs->links() }}</div>
    </div>
</div>
</div>
@endsection
