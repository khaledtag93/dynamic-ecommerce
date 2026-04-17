<div>
    <div class="admin-page-header">
        <div>
            <span class="admin-eyebrow">{{ __('Catalog / Attributes') }}</span>
            <h1 class="admin-page-title">{{ __('Product Attributes') }}</h1>
            <p class="admin-page-description">{{ __('Create and manage reusable product attributes and their values with cleaner search and coverage stats.') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => __('Attributes'), 'value' => $stats['total'], 'copy' => __('Reusable attribute groups.'), 'icon' => 'mdi-tune-variant'],
            ['label' => __('With values'), 'value' => $stats['with_values'], 'copy' => __('Attributes already filled with values.'), 'icon' => 'mdi-format-list-bulleted-square'],
            ['label' => __('Values total'), 'value' => $stats['values_total'], 'copy' => __('All attribute values stored in the catalog.'), 'icon' => 'mdi-alpha-v-box'],
        ] as $card)
            <div class="col-md-6 col-xl-4">
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
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">{{ __('Add or Edit Attribute') }}</h5>
                    <small class="text-muted">{{ __('Keep labels reusable for product variants or specification sections.') }}</small>
                </div>
                <div class="admin-search-inline">
                    <input type="text" wire:model.debounce.400ms="search" class="form-control" placeholder="{{ __('Search attributes') }}">
                </div>
            </div>
        </div>
        <div class="card-body">
            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
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
                            <th style="width: 220px;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attributes as $attr)
                            <tr>
                                <td>{{ $attr->id }}</td>
                                <td>{{ $attr->name }}</td>
                                <td>{{ $attr->values_count }}</td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button wire:click="edit({{ $attr->id }})" class="btn-table-icon btn-edit" title="{{ __('Edit attribute') }}">
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </button>
                                        <button type="button" class="btn-table-icon btn-delete" title="{{ __('Delete attribute') }}" onclick="adminConfirmAction(() => @this.call('delete', {{ $attr->id }}), __('Are you sure you want to delete this attribute?'))">
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
</div>
