@extends('layouts.admin')

@section('title', __('Adjust stock') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Inventory')" :title="__('Adjust stock')" :description="__('Record a counted quantity with a reason. The change will appear in inventory movements.')">
    <a href="{{ route('admin.inventory.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to inventory') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <h4 class="mb-1">{{ __('Find an item') }}</h4>
                    <p class="text-muted small mb-3">{{ __('Search by product name, SKU, or barcode, then choose the exact stock item.') }}</p>
                    <form method="GET" action="{{ route('admin.inventory.adjust') }}" class="d-flex gap-2 mb-3" data-submit-loading>
                        <input name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Name, SKU, or barcode') }}" aria-label="{{ __('Search products') }}">
                        <button class="btn btn-primary" data-loading-text="{{ __('Searching...') }}">{{ __('Search') }}</button>
                    </form>

                    <div class="list-group">
                        @forelse($products as $candidate)
                            <a href="{{ route('admin.inventory.adjust', ['product_id' => $candidate->id, 'search' => $search]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 {{ $product?->id === $candidate->id ? 'active' : '' }}">
                                <span><span class="d-block fw-semibold">{{ $candidate->name }}</span><span class="small">{{ $candidate->sku ?: ($candidate->barcode ?: __('No SKU')) }}</span></span>
                                <i class="mdi mdi-chevron-right"></i>
                            </a>
                        @empty
                            <div class="admin-empty-state py-4"><p class="text-muted mb-0">{{ __('No products match this search.') }}</p></div>
                        @endforelse
                    </div>
                    @if($products->count() === 30)
                        <p class="text-muted small mt-3 mb-0">{{ __('Showing the first 30 matches. Narrow your search to find another item.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if($product)
                <div class="admin-card mb-4">
                    <div class="admin-card-body">
                        <h4 class="mb-1">{{ $product->name }}</h4>
                        <p class="text-muted small mb-3">{{ $product->has_variants ? __('Choose the variant whose stock you counted.') : __('This product keeps stock on its main record.') }}</p>
                        @if($product->has_variants)
                            <div class="row g-2">
                                @forelse($product->variants as $choice)
                                    <div class="col-sm-6">
                                        <a href="{{ route('admin.inventory.adjust', ['product_id' => $product->id, 'variant_id' => $choice->id, 'search' => $search]) }}" class="d-block border rounded-3 p-3 text-decoration-none {{ $variant?->id === $choice->id ? 'border-primary' : '' }}">
                                            <span class="d-block fw-semibold">{{ $choice->sku ?: ('#' . $choice->id) }}</span>
                                            <span class="text-muted small">{{ __('Current stock') }}: {{ $choice->stock }}</span>
                                        </a>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">{{ __('No variants are available for this product. Add a variant before adjusting stock.') }}</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>

                @if(! $product->has_variants || $variant)
                    @php($currentStock = (int) ($variant?->stock ?? $product->quantity))
                    <div class="admin-card">
                        <div class="admin-card-body">
                            <h4 class="mb-1">{{ __('Record stock count') }}</h4>
                            <p class="text-muted small mb-4">{{ __('Enter the quantity you counted, not the difference. A changed stock count creates one movement with your reason.') }}</p>
                            <div class="admin-section-card mb-4">
                                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                    <div><div class="text-muted small">{{ __('Selected item') }}</div><div class="fw-semibold">{{ $product->name }}{{ $variant ? ' · ' . ($variant->sku ?: ('#' . $variant->id)) : '' }}</div></div>
                                    <div><div class="text-muted small">{{ __('Current stock') }}</div><div class="fw-bold fs-4">{{ $currentStock }}</div></div>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('admin.inventory.adjust.store') }}" data-submit-loading data-confirm-title="{{ __('Confirm stock adjustment') }}" data-confirm-message="{{ __('Apply this counted stock quantity to the selected item?') }}" data-confirm-subtitle="{{ __('The current stock will be checked again before saving, and the change will be recorded.') }}" data-confirm-ok="{{ __('Confirm adjustment') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                @if($variant)<input type="hidden" name="variant_id" value="{{ $variant->id }}">@endif
                                <input type="hidden" name="expected_stock" value="{{ old('expected_stock', $currentStock) }}">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label for="new_stock" class="form-label fw-semibold">{{ __('Counted quantity') }}</label>
                                        <input id="new_stock" name="new_stock" type="number" min="0" max="999999999" step="1" required value="{{ old('new_stock', max(0, $currentStock)) }}" class="form-control @error('new_stock') is-invalid @enderror">
                                        @error('new_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-7">
                                        <label for="reason" class="form-label fw-semibold">{{ __('Reason') }}</label>
                                        <input id="reason" name="reason" type="text" minlength="5" maxlength="255" required value="{{ old('reason') }}" class="form-control @error('reason') is-invalid @enderror" placeholder="{{ __('For example: physical count correction') }}">
                                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="admin-actions-stack mt-4">
                                    <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Save stock adjustment') }}</span></button>
                                    <a href="{{ route('admin.inventory.index') }}" class="btn btn-light border">{{ __('Cancel') }}</a>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @else
                <div class="admin-card h-100"><div class="admin-card-body admin-empty-state py-5"><div class="admin-empty-icon"><i class="mdi mdi-package-variant"></i></div><h5>{{ __('Select a product to adjust stock') }}</h5><p class="text-muted mb-0">{{ __('A variant product needs an exact variant before you can save a stock count.') }}</p></div></div>
            @endif
        </div>
    </div>
</div>
@endsection
