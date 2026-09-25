<div>
    <x-admin.page-header
        :kicker="__('Catalog management')"
        :title="__('Product Attributes')"
        :description="__('Create and manage reusable product attributes and their values with cleaner search and coverage stats.')"
    />

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Attributes'), 'value' => $stats['total'], 'copy' => __('Reusable attribute groups.'), 'icon' => 'mdi-tune-variant'],
            ['label' => __('With values'), 'value' => $stats['with_values'], 'copy' => __('Attributes already filled with values.'), 'icon' => 'mdi-format-list-bulleted-square'],
            ['label' => __('Values total'), 'value' => $stats['values_total'], 'copy' => __('All attribute values stored in the catalog.'), 'icon' => 'mdi-alpha-v-box'],
            ['label' => __('Empty'), 'value' => $stats['empty'], 'copy' => __('Attributes that still need reusable values.'), 'icon' => 'mdi-format-list-bulleted'],
            ['label' => __('In use'), 'value' => $stats['in_use'], 'copy' => __('Attributes already referenced by product variants.'), 'icon' => 'mdi-link-variant'],
        ] as $card)
            <div class="col-md-6 col-xl">
                <x-admin.stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :help="$card['copy']" class="h-100" />
            </div>
        @endforeach
    </div>

    <div class="card admin-card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
                <div>
                    <h4 class="mb-1">{{ __('Attribute operations') }}</h4>
                    <div class="text-muted small">{{ __('Review incomplete attributes and protect variant structures already in use.') }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn {{ $coverage === 'empty' ? 'btn-primary' : 'btn-light border' }} btn-sm" wire:click="$set('coverage', 'empty')">{{ __('Needs values') }} · {{ $stats['empty'] }}</button>
                    <button type="button" class="btn {{ $coverage === 'in_use' ? 'btn-primary' : 'btn-light border' }} btn-sm" wire:click="$set('coverage', 'in_use')">{{ __('In use') }} · {{ $stats['in_use'] }}</button>
                    <button type="button" class="btn btn-light border btn-sm" wire:click="resetFilters">{{ __('Reset filters') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card admin-card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">{{ __('Add or Edit Attribute') }}</h5>
                    <small class="text-muted">{{ __('Keep labels reusable for product variants or specification sections.') }}</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="admin-search-inline">
                        <input type="text" wire:model.debounce.400ms="search" class="form-control" placeholder="{{ __('Search attributes') }}">
                    </div>
                    <select wire:model="coverage" class="form-select" style="width:auto">
                        <option value="">{{ __('All attributes') }}</option>
                        <option value="with_values">{{ __('With values') }}</option>
                        <option value="empty">{{ __('Needs values') }}</option>
                        <option value="in_use">{{ __('In use by variants') }}</option>
                    </select>
                    <select wire:model="perPage" class="form-select" style="width:auto">
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form wire:submit.prevent="save" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="name" class="form-label">{{ __('Attribute Name') }}</label>
                    <input type="text" wire:model="name" id="name" class="form-control @error('name') is-invalid @enderror">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">{{ $editingId ? __('Update Attribute') : __('Add Attribute') }}</button>
                    @if($editingId)
                        <button type="button" class="btn btn-light admin-btn-soft" wire:click="resetForm">{{ __('Cancel') }}</button>
                    @endif
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
                            <th style="width: 120px;">{{ __('Values') }}</th>
                            <th style="width: 220px;" class="rtl-text-start">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attributes as $attr)
                            <tr>
                                <td>{{ $attr->id }}</td>
                                <td>{{ $attr->name }}</td>
                                <td><div class="fw-semibold">{{ $attr->values_count }}</div><small class="text-muted">{{ $attr->variant_attributes_count }} {{ __('variant links') }}</small></td>
                                <td class="rtl-text-start">
                                    <div class="d-flex gap-2 flex-wrap rtl-justify-start">
                                        <button wire:click="edit({{ $attr->id }})" class="btn-table-icon btn-edit" title="{{ __('Edit attribute') }}">
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </button>
                                        <button type="button" class="btn-table-icon btn-delete" title="{{ __('Delete attribute') }}" wire:click="requestDelete({{ $attr->id }})" @disabled($attr->variant_attributes_count > 0)>
                                            <i class="mdi mdi-trash-can-outline"></i>
                                        </button>
                                        <a href="{{ route('admin.attributes.values', $attr->id) }}" class="btn-table-icon btn-values" title="{{ __('Manage values') }}">
                                            <i class="mdi mdi-format-list-bulleted"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">{{ __('No attributes found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $attributes->links() }}</div>
    </div>

    <div class="modal fade" id="attributeDeleteConfirmationModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div><span class="badge badge-soft-danger mb-2">{{ __('Destructive action') }}</span><h5 class="modal-title">{{ __('Delete this attribute?') }}</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">{{ __('Deleting an unused attribute also removes its reusable values. Attributes referenced by product variants are protected.') }}</p>
                    <p class="text-muted small mb-0">{{ __('This action cannot be undone.') }}</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal" wire:click="cancelDelete">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmDelete" wire:loading.attr="disabled" wire:target="confirmDelete">{{ __('Delete attribute') }}</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        window.addEventListener('open-attribute-delete-confirmation', () => {
            const element = document.getElementById('attributeDeleteConfirmationModal');
            if (element && window.bootstrap) bootstrap.Modal.getOrCreateInstance(element).show();
        });
        document.addEventListener('livewire:load', () => {
            Livewire.hook('message.processed', () => {
                if (@this.get('pendingDeleteId')) return;
                const element = document.getElementById('attributeDeleteConfirmationModal');
                if (!element || !window.bootstrap) return;
                const modal = bootstrap.Modal.getInstance(element);
                if (modal) modal.hide();
            });
        });
    </script>
    @endpush
</div>
