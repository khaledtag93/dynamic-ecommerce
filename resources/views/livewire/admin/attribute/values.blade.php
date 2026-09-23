<div>
    <div class="admin-page-header">
        <div>
            <span class="admin-eyebrow">{{ __('Catalog / Attributes / Values') }}</span>
            <h1 class="admin-page-title">{{ $attribute->name }} {{ __('Values') }}</h1>
            <p class="admin-page-description">{{ __('Add and edit the reusable values for this attribute.') }}</p>
        </div>
        <div class="admin-page-actions">
            <a href="{{ route('admin.attributes.index') }}" class="btn btn-light admin-btn-soft">
                <i class="mdi mdi-arrow-left"></i>
                {{ __('Back to Attributes') }}
            </a>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Values'), 'value' => $stats['total'], 'copy' => __('Reusable values in this attribute.'), 'icon' => 'mdi-format-list-bulleted'],
            ['label' => __('In use'), 'value' => $stats['in_use'], 'copy' => __('Values currently referenced by product variants.'), 'icon' => 'mdi-link-variant'],
            ['label' => __('Unused'), 'value' => $stats['unused'], 'copy' => __('Values available for cleanup or future variants.'), 'icon' => 'mdi-link-variant-off'],
        ] as $card)
            <div class="col-md-4">
                <div class="admin-card admin-stat-card h-100">
                    <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                    <div class="admin-stat-label">{{ $card['label'] }}</div>
                    <div class="admin-stat-value">{{ $card['value'] }}</div>
                    <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card admin-card mb-4">
        <div class="card-header">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h5 class="mb-1">{{ __('Add or Edit Value') }}</h5>
                    <small class="text-muted">{{ __('Values used by variants are protected from rename and deletion to keep catalog data consistent.') }}</small>
                </div>
                <input type="text" wire:model.debounce.300ms="search" class="form-control" style="max-width:280px" placeholder="{{ __('Search values') }}">
            </div>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="save" class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label">{{ __('Value') }}</label>
                    <input type="text" wire:model="value" class="form-control" placeholder="{{ __('Enter value (e.g. Red)') }}" wire:loading.attr="disabled" wire:target="save">
                    @error('value') <span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                <div class="col-md-2 d-grid gap-2">
                    <button class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $valueId ? __('Update') : __('Add value') }}</span>
                        <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span>{{ __('Saving...') }}</span>
                    </button>
                    @if($valueId)<button type="button" class="btn btn-light border" wire:click="resetForm">{{ __('Cancel') }}</button>@endif
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
                            <th>{{ __('Value') }}</th>
                            <th style="width: 180px;">{{ __('Variant usage') }}</th>
                            <th style="width: 160px;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($values as $val)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $val->value }}</div>
                                    @if($val->variant_usage_count > 0)
                                        <div class="text-muted small mt-1"><i class="mdi mdi-lock-outline me-1"></i>{{ __('Rename and delete are locked while this value is in use.') }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($val->variant_usage_count > 0)
                                        <span class="badge badge-soft-success">{{ $val->variant_usage_count }} {{ __('variant(s)') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ __('Unused') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button wire:click="edit({{ $val->id }})" class="btn btn-sm btn-outline-primary btn-action" title="{{ $val->variant_usage_count > 0 ? __('Used values cannot be renamed') : __('Edit value') }}" @disabled($val->variant_usage_count > 0)>
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </button>
                                        <button type="button" wire:click="requestDelete({{ $val->id }})" class="btn btn-sm btn-outline-danger btn-action" title="{{ __('Delete value') }}" @disabled($val->variant_usage_count > 0)>
                                            <i class="mdi mdi-trash-can-outline"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">{{ $search !== '' ? __('No values match your search.') : __('No values found yet') }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attributeValueDeleteConfirmationModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div><span class="badge badge-soft-danger mb-2">{{ __('Destructive action') }}</span><h5 class="modal-title">{{ __('Delete this value?') }}</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">{{ __('The value will be permanently removed from this attribute. Values used by product variants cannot be deleted.') }}</p>
                    <p class="text-muted small mb-0">{{ __('This action cannot be undone.') }}</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal" wire:click="cancelDelete">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmDelete" wire:loading.attr="disabled" wire:target="confirmDelete">{{ __('Delete value') }}</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        window.addEventListener('open-attribute-value-delete-confirmation', () => {
            const element = document.getElementById('attributeValueDeleteConfirmationModal');
            if (element && window.bootstrap) bootstrap.Modal.getOrCreateInstance(element).show();
        });
        document.addEventListener('livewire:load', () => {
            Livewire.hook('message.processed', () => {
                if (@this.get('pendingDeleteId')) return;
                const element = document.getElementById('attributeValueDeleteConfirmationModal');
                if (!element || !window.bootstrap) return;
                const modal = bootstrap.Modal.getInstance(element);
                if (modal) modal.hide();
            });
        });
    </script>
    @endpush
</div>
