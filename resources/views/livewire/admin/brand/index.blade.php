<div>
    <div class="admin-page-header">
        <div>
            <span class="admin-eyebrow">{{ __('Catalog / Brands') }}</span>
            <h1 class="admin-page-title">{{ __('Brands') }}</h1>
            <p class="admin-page-description">{{ __('Manage your brands in a cleaner list with safer delete behavior, quick edits, and basic visibility filters.') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Total brands'), 'value' => $stats['total'], 'copy' => __('All brand records available in catalog management.'), 'icon' => 'mdi-tag-outline'],
            ['label' => __('Visible'), 'value' => $stats['visible'], 'copy' => __('Brands currently visible to shoppers.'), 'icon' => 'mdi-eye-outline'],
            ['label' => __('Hidden'), 'value' => $stats['hidden'], 'copy' => __('Brands hidden from customer-facing flows.'), 'icon' => 'mdi-eye-off-outline'],
            ['label' => __('Linked'), 'value' => $stats['linked'], 'copy' => __('Brands already attached to one or more products.'), 'icon' => 'mdi-link-variant'],
        ] as $card)
            <div class="col-md-6 col-xl-3">
                <div class="admin-card admin-stat-card h-100">
                    <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                    <div class="admin-stat-label">{{ $card['label'] }}</div>
                    <div class="admin-stat-value">{{ $card['value'] }}</div>
                    <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if(session('message'))<div class="alert alert-success my-2">{{ session('message') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger my-2">{{ session('error') }}</div>@endif

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-7">
                    <label class="form-label fw-semibold">{{ __('Search brands') }}</label>
                    <input type="text" wire:model.debounce.400ms="search" class="form-control" placeholder="{{ __('Search by brand name or slug') }}">
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">{{ __('Visibility') }}</label>
                    <select wire:model="visibility" class="form-select">
                        <option value="">{{ __('All brands') }}</option>
                        <option value="visible">{{ __('Visible') }}</option>
                        <option value="hidden">{{ __('Hidden') }}</option>
                    </select>
                </div>
                <div class="col-lg-3 text-lg-end">
                    <button type="button" class="btn btn-light border admin-btn-soft me-2" wire:click="resetForm">{{ __('Clear form') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <div>
                    <h4 class="mb-1">{{ $brandIdToEdit ? __('Edit brand') : __('Add brand') }}</h4>
                    <div class="text-muted small">{{ __('Create and update brand records without popups.') }}</div>
                </div>
            </div>
            <form wire:submit.prevent="saveBrand">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Name') }}</label>
                        <input type="text" wire:model.defer="name" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Slug') }}</label>
                        <input type="text" wire:model.defer="slug" class="form-control @error('slug') is-invalid @enderror">
                        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">{{ __('Visibility') }}</label>
                        <div class="form-check form-switch pt-2">
                            <input type="checkbox" wire:model="status" class="form-check-input" id="brandStatusCheck">
                            <label class="form-check-label" for="brandStatusCheck">{{ __('Hide brand') }}</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-light admin-btn-soft" wire:click="resetForm">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ $brandIdToEdit ? __('Update brand') : __('Add brand') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card admin-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover admin-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">{{ __('ID') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th style="width: 120px;">{{ __('Products') }}</th>
                            <th style="width: 100px;">{{ __('Status') }}</th>
                            <th style="width: 180px;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($brands as $brand)
                            <tr>
                                <td>{{ $brand->id }}</td>
                                <td><div class="fw-semibold">{{ $brand->name }}</div><div class="small text-muted">{{ $brand->slug }}</div></td>
                                <td>{{ $brand->products_count }}</td>
                                <td>@if($brand->status)<span class="badge admin-status-badge badge-soft-secondary">{{ __('Hidden') }}</span>@else<span class="badge admin-status-badge badge-soft-success">{{ __('Visible') }}</span>@endif</td>
                                <td><div class="d-flex gap-2 flex-wrap"><button wire:click="edit({{ $brand->id }})" class="btn-table-icon btn-edit" title="{{ __('Edit brand') }}"><i class="mdi mdi-pencil-outline"></i></button><button type="button" class="btn-table-icon btn-delete" title="{{ __('Delete brand') }}" onclick="adminConfirmAction(() => @this.call('delete', {{ $brand->id }}), '{{ __('Are you sure you want to delete this brand?') }}')"><i class="mdi mdi-trash-can-outline"></i></button></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">{{ __('No brands found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($brands->hasPages())<div class="card-footer">{{ $brands->links() }}</div>@endif
    </div>
</div>
