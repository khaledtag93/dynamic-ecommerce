@extends('layouts.admin')

@section('title', __('Scan barcode') . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Inventory')" :title="__('Scan barcode')" :description="__('Find the exact catalog item before opening a stock action. Scanner lookup never guesses between duplicate barcodes.')">
    <a href="{{ route('admin.inventory.adjust') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-clipboard-edit-outline"></i><span>{{ __('Adjust stock') }}</span></a>
    <a href="{{ route('admin.inventory.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to inventory') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="row g-4">
        <div class="col-xl-5">
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="admin-stat-icon"><i class="mdi mdi-barcode-scan"></i></span>
                        <div>
                            <h4 class="mb-1">{{ __('Scanner input') }}</h4>
                            <p class="text-muted small mb-0">{{ __('Most USB and Bluetooth barcode scanners work like a keyboard. Keep the field focused, scan the barcode, and the scanner Enter key will submit it.') }}</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('admin.inventory.scan') }}" data-submit-loading>
                        <label for="barcodeScanInput" class="form-label fw-semibold">{{ __('Barcode') }}</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="mdi mdi-barcode"></i></span>
                            <input
                                id="barcodeScanInput"
                                name="barcode"
                                type="text"
                                value="{{ $barcode }}"
                                class="form-control @error('barcode') is-invalid @enderror"
                                placeholder="{{ __('Scan or enter an exact barcode') }}"
                                maxlength="255"
                                autocomplete="off"
                                autocapitalize="off"
                                spellcheck="false"
                                enterkeyhint="search"
                                autofocus
                            >
                            <button class="btn btn-primary" data-loading-text="{{ __('Looking up...') }}">{{ __('Find item') }}</button>
                        </div>
                        @error('barcode')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

                        <div class="d-flex align-items-center gap-2 flex-wrap mt-3">
                            <span class="admin-chip"><i class="mdi mdi-keyboard-outline me-1"></i>{{ __('HID keyboard scanners supported') }}</span>
                            <span class="text-muted small">{{ __('Camera scanning is intentionally not part of this first version.') }}</span>
                        </div>
                    </form>
                </div>
            </div>

            @if($barcode !== '')
                <div class="admin-card mt-4">
                    <div class="admin-card-body">
                        <div class="text-muted small">{{ __('Last scanned barcode') }}</div>
                        <div class="font-monospace fw-semibold text-break">{{ $barcode }}</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-7">
            @if($lookupError)
                <div class="admin-card">
                    <div class="admin-card-body">
                        <div class="alert alert-danger mb-0">
                            <div class="fw-semibold mb-1">{{ __('Barcode needs attention') }}</div>
                            <div class="small">{{ $lookupError }}</div>
                        </div>
                    </div>
                </div>
            @elseif($barcode !== '' && ! $match)
                <div class="admin-card">
                    <div class="admin-card-body admin-empty-state py-5">
                        <div class="admin-empty-icon"><i class="mdi mdi-barcode-off"></i></div>
                        <h5>{{ __('No exact barcode match') }}</h5>
                        <p class="text-muted mb-3">{{ __('No product or variant uses this barcode. Check the label or add the barcode from the product editor.') }}</p>
                        <a href="{{ route('admin.products.index', ['search' => $barcode]) }}" class="btn btn-light border">{{ __('Search catalog') }}</a>
                    </div>
                </div>
            @elseif($match)
                @php
                    $matchedProduct = $match['product'];
                    $matchedVariant = $match['variant'];
                    $needsVariant = (bool) $match['requires_variant_selection'];
                    $currentStock = $matchedVariant ? (int) $matchedVariant->stock : (int) $matchedProduct->quantity;
                @endphp

                <div class="admin-card mb-4">
                    <div class="admin-card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <h4 class="mb-0">{{ $matchedProduct->name }}</h4>
                                    <span class="badge admin-status-badge badge-soft-success">{{ __('Exact barcode match') }}</span>
                                </div>
                                <div class="text-muted small">
                                    {{ $matchedVariant ? __('Variant barcode matched') : __('Product barcode matched') }}
                                </div>
                            </div>

                            <a href="{{ route('admin.products.edit', $matchedProduct) }}" class="btn btn-light border btn-sm">
                                <i class="mdi mdi-open-in-new me-1"></i>{{ __('Open product') }}
                            </a>
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="admin-section-card h-100">
                                    <div class="text-muted small mb-1">{{ __('Product SKU') }}</div>
                                    <div class="fw-semibold font-monospace text-break">{{ $matchedProduct->sku ?: '—' }}</div>
                                    <div class="text-muted small mt-3 mb-1">{{ __('Product barcode') }}</div>
                                    <div class="fw-semibold font-monospace text-break">{{ $matchedProduct->barcode ?: '—' }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="admin-section-card h-100">
                                    @if($matchedVariant)
                                        <div class="text-muted small mb-1">{{ __('Variant SKU') }}</div>
                                        <div class="fw-semibold font-monospace text-break">{{ $matchedVariant->sku ?: '—' }}</div>
                                        <div class="text-muted small mt-3 mb-1">{{ __('Current stock') }}</div>
                                        <div class="fw-bold fs-3">{{ $currentStock }}</div>
                                    @elseif(! $needsVariant)
                                        <div class="text-muted small mb-1">{{ __('Current stock') }}</div>
                                        <div class="fw-bold fs-3">{{ $currentStock }}</div>
                                        <div class="text-muted small mt-2">{{ __('Stock is stored on the product record.') }}</div>
                                    @else
                                        <div class="text-muted small mb-1">{{ __('Variant selection required') }}</div>
                                        <div class="fw-semibold">{{ __('This barcode identifies the parent product, not one exact stock variant.') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($matchedVariant)
                            <div class="admin-actions-stack mt-4">
                                <a href="{{ route('admin.inventory.adjust', ['product_id' => $matchedProduct->id, 'variant_id' => $matchedVariant->id, 'search' => $barcode]) }}" class="btn btn-primary btn-text-icon">
                                    <i class="mdi mdi-clipboard-edit-outline"></i><span>{{ __('Adjust this stock') }}</span>
                                </a>
                            </div>
                        @elseif(! $needsVariant)
                            <div class="admin-actions-stack mt-4">
                                <a href="{{ route('admin.inventory.adjust', ['product_id' => $matchedProduct->id, 'search' => $barcode]) }}" class="btn btn-primary btn-text-icon">
                                    <i class="mdi mdi-clipboard-edit-outline"></i><span>{{ __('Adjust this stock') }}</span>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                @if($needsVariant)
                    <div class="admin-card">
                        <div class="admin-card-body">
                            <h4 class="mb-1">{{ __('Choose the exact variant') }}</h4>
                            <p class="text-muted small mb-3">{{ __('The scanned barcode belongs to a product with variants. Choose the physical item before changing stock.') }}</p>

                            <div class="row g-3">
                                @forelse($matchedProduct->variants as $choice)
                                    <div class="col-md-6">
                                        <div class="border rounded-4 p-3 h-100">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                                <div>
                                                    <div class="fw-semibold">{{ $choice->sku ?: ('#' . $choice->id) }}</div>
                                                    <div class="text-muted small font-monospace">{{ $choice->barcode ?: __('No variant barcode') }}</div>
                                                </div>
                                                <span class="badge admin-status-badge {{ $choice->status ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $choice->status ? __('Active') : __('Inactive') }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center gap-3">
                                                <div><span class="text-muted small">{{ __('Current stock') }}</span><div class="fw-bold fs-5">{{ (int) $choice->stock }}</div></div>
                                                <a href="{{ route('admin.inventory.adjust', ['product_id' => $matchedProduct->id, 'variant_id' => $choice->id, 'search' => $barcode]) }}" class="btn btn-sm btn-outline-primary">{{ __('Select variant') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <div class="admin-empty-state py-4">
                                            <p class="text-muted mb-0">{{ __('No variants are available for this product. Add a variant before adjusting stock.') }}</p>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="admin-card h-100">
                    <div class="admin-card-body admin-empty-state py-5">
                        <div class="admin-empty-icon"><i class="mdi mdi-barcode-scan"></i></div>
                        <h5>{{ __('Ready for a barcode') }}</h5>
                        <p class="text-muted mb-0">{{ __('Scan one item at a time. Exact variant barcodes go straight to that stock item; parent product barcodes never choose a variant automatically.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('barcodeScanInput');

    if (input) {
        input.focus();
        input.select();
    }
});
</script>
@endsection
