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

    <div class="card admin-card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Add or Edit Value') }}</h5>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="save" class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label">{{ __('Value') }}</label>
                    <input type="text" wire:model="value" class="form-control" placeholder="{{ __('Enter value (e.g. Red)') }}">
                    @error('value') <span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary">{{ __('Save') }}</button>
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
                            <th style="width: 160px;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attribute->values as $val)
                            <tr>
                                <td>{{ $val->value }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button wire:click="edit({{ $val->id }})" class="btn btn-sm btn-outline-primary btn-action" title="{{ __('Edit value') }}">
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </button>
                                        <button type="button" wire:click="delete({{ $val->id }})" class="btn btn-sm btn-outline-danger btn-action" title="{{ __('Delete value') }}">
                                            <i class="mdi mdi-trash-can-outline"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted">{{ __('No values found yet') }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
