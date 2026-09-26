<div>
    <x-admin.page-header
        :kicker="__('Catalog management')"
        :title="__('Brands')"
        :description="__('Manage your brands in a cleaner list with safer delete behavior, quick edits, and basic visibility filters.')"
    />

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Total brands'), 'value' => $stats['total'], 'copy' => __('All brand records available in catalog management.'), 'icon' => 'mdi-tag-outline'],
            ['label' => __('Visible'), 'value' => $stats['visible'], 'copy' => __('Brands currently visible to shoppers.'), 'icon' => 'mdi-eye-outline'],
            ['label' => __('Hidden'), 'value' => $stats['hidden'], 'copy' => __('Brands hidden from customer-facing flows.'), 'icon' => 'mdi-eye-off-outline'],
            ['label' => __('Linked'), 'value' => $stats['linked'], 'copy' => __('Brands already attached to one or more products.'), 'icon' => 'mdi-link-variant'],
            ['label' => __('Empty'), 'value' => $stats['empty'], 'copy' => __('Brands with no linked products and ready for cleanup review.'), 'icon' => 'mdi-link-variant-off'],
        ] as $card)
            <div class="col-md-6 col-xl-3">
                <x-admin.stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :help="$card['copy']" class="h-100" />
            </div>
        @endforeach
    </div>

    @if(session('message'))<div class="alert alert-success my-2">{{ session('message') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger my-2">{{ session('error') }}</div>@endif

    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('Brand operations') }}</h4>
                    <div class="text-muted small">{{ __('Find unused brands, review visibility, and keep catalog naming clean.') }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn {{ $usage === 'empty' ? 'btn-primary' : 'btn-light border' }} btn-sm" wire:click="$set('usage', 'empty')">
                        <i class="mdi mdi-link-variant-off me-1"></i>{{ __('Empty') }} · {{ $stats['empty'] }}
                    </button>
                    <button type="button" class="btn {{ $visibility === 'hidden' ? 'btn-primary' : 'btn-light border' }} btn-sm" wire:click="$set('visibility', 'hidden')">
                        <i class="mdi mdi-eye-off-outline me-1"></i>{{ __('Hidden') }} · {{ $stats['hidden'] }}
                    </button>
                </div>
            </div>
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
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">{{ __('Product usage') }}</label>
                    <select wire:model="usage" class="form-select">
                        <option value="">{{ __('All brands') }}</option>
                        <option value="linked">{{ __('With products') }}</option>
                        <option value="empty">{{ __('Empty brands') }}</option>
                    </select>
                </div>
                <div class="col-lg-1">
                    <label class="form-label fw-semibold">{{ __('Per page') }}</label>
                    <select wire:model="perPage" class="form-select">
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option>
                    </select>
                </div>
                <div class="col-lg-3 text-lg-end">
                    <button type="button" class="btn btn-light border admin-btn-soft me-2" wire:click="resetFilters">{{ __('Reset filters') }}</button>
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
                            <th style="width: 180px;" class="rtl-text-start">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($brands as $brand)
                            <tr>
                                <td>{{ $brand->id }}</td>
                                <td><div class="fw-semibold">{{ $brand->name }}</div><div class="small text-muted">{{ $brand->slug }}</div></td>
                                <td>{{ $brand->products_count }}</td>
                                <td>@if($brand->status)<span class="badge admin-status-badge badge-soft-secondary">{{ __('Hidden') }}</span>@else<span class="badge admin-status-badge badge-soft-success">{{ __('Visible') }}</span>@endif</td>
                                <td class="rtl-text-start"><div class="d-flex gap-2 flex-wrap rtl-justify-start"><button wire:click="edit({{ $brand->id }})" class="btn-table-icon btn-edit" title="{{ __('Edit brand') }}"><i class="mdi mdi-pencil-outline"></i></button><button type="button" class="btn-table-icon btn-delete" title="{{ __('Delete brand') }}" wire:click="requestDelete({{ $brand->id }})" @disabled($brand->products_count > 0)><i class="mdi mdi-trash-can-outline"></i></button></div></td>
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

    <div class="modal fade" id="brandDeleteConfirmationModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="badge badge-soft-danger mb-2">{{ __('Destructive action') }}</span>
                        <h5 class="modal-title">{{ __('Delete this brand?') }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">{{ __('This permanently removes the brand record. Brands linked to products remain protected and cannot be deleted.') }}</p>
                    <p class="text-muted small mb-0">{{ __('This action cannot be undone.') }}</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal" wire:click="cancelDelete">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmDelete" wire:loading.attr="disabled" wire:target="confirmDelete">
                        <span wire:loading.remove wire:target="confirmDelete">{{ __('Delete brand') }}</span>
                        <span wire:loading wire:target="confirmDelete">{{ __('Deleting...') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        window.addEventListener('open-brand-delete-confirmation', () => {
            const element = document.getElementById('brandDeleteConfirmationModal');
            if (element && window.bootstrap) bootstrap.Modal.getOrCreateInstance(element).show();
        });

        document.addEventListener('livewire:init', () => {
            Livewire.hook('message.processed', () => {
                if (@this.get('pendingDeleteId')) return;
                const element = document.getElementById('brandDeleteConfirmationModal');
                if (!element || !window.bootstrap) return;
                const modal = bootstrap.Modal.getInstance(element);
                if (modal) modal.hide();
            });
        });
    </script>
    @endpush
</div>
