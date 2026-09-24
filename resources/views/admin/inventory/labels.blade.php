@extends('layouts.admin')

@section('title', __('Barcode labels') . ' | Admin')

@section('content')
<style>
    .label-preview-sheet {
        display: flex;
        flex-wrap: wrap;
        align-content: flex-start;
        gap: 6mm;
        padding: 8mm;
        min-height: 180mm;
        background: #f8fafc;
        border: 1px solid var(--admin-border);
        border-radius: 1rem;
        overflow: auto;
    }

    .barcode-label {
        width: var(--label-width);
        height: var(--label-height);
        box-sizing: border-box;
        padding: 2.2mm;
        border: .25mm solid #cbd5e1;
        border-radius: 1.5mm;
        background: #fff;
        color: #111827;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
        direction: ltr;
    }

    .barcode-label__name {
        font-size: 9pt;
        line-height: 1.15;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .barcode-label__variant,
    .barcode-label__meta {
        font-size: 7pt;
        line-height: 1.1;
    }

    .barcode-label__variant {
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .barcode-label__barcode {
        width: 100%;
        min-height: 12mm;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .barcode-label__barcode .code128-barcode {
        display: block;
        width: 100%;
        height: 14mm;
        max-width: 100%;
    }

    .barcode-label__footer {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 2mm;
    }

    .barcode-label__sku {
        min-width: 0;
        max-width: 70%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .barcode-label__price {
        font-size: 9pt;
        font-weight: 800;
        white-space: nowrap;
    }

    @media print {
        @page {
            margin: 5mm;
        }

        body {
            background: #fff !important;
        }

        body * {
            visibility: hidden !important;
        }

        #barcodeLabelPrintArea,
        #barcodeLabelPrintArea * {
            visibility: visible !important;
        }

        #barcodeLabelPrintArea {
            position: absolute;
            inset: 0;
            width: 100%;
            min-height: 0;
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
            gap: 2mm;
            background: #fff;
            border: 0;
            border-radius: 0;
            overflow: visible;
        }

        .barcode-label {
            break-inside: avoid;
            page-break-inside: avoid;
            border-color: #d1d5db;
            box-shadow: none;
        }
    }
</style>

<x-admin.page-header :kicker="__('Inventory')" :title="__('Barcode labels')" :description="__('Preview real-size Code 128 labels before sending them to your browser or label printer.')">
    <a href="{{ route('admin.inventory.scan', ['barcode' => $barcode]) }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-barcode-scan"></i><span>{{ __('Back to scanner') }}</span></a>
    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-open-in-new"></i><span>{{ __('Open product') }}</span></a>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h4 class="mb-1">{{ $product->name }}</h4>
                    @if($variant)
                        <div class="text-muted small">{{ $variantName ?: ($variant->sku ?: ('#' . $variant->id)) }}</div>
                    @endif
                    <div class="text-muted small mt-1 font-monospace">{{ $barcode ?: __('No barcode') }}</div>
                </div>
                @if($barcodeSvg)
                    <span class="badge admin-status-badge badge-soft-success">{{ __('Code 128B ready') }}</span>
                @else
                    <span class="badge admin-status-badge badge-soft-warning">{{ __('Label not printable yet') }}</span>
                @endif
            </div>

            @if($labelError)
                <div class="alert alert-warning mt-4 mb-0">
                    <div class="fw-semibold mb-1">{{ __('Barcode label needs attention') }}</div>
                    <div class="small">{{ $labelError }}</div>
                </div>
            @else
                <form method="GET" action="{{ route('admin.inventory.labels', ['product' => $product->id]) }}" class="row g-3 align-items-end mt-2">
                    @if($variant)<input type="hidden" name="variant_id" value="{{ $variant->id }}">@endif
                    <div class="col-sm-6 col-lg-3">
                        <label for="labelCopies" class="form-label fw-semibold">{{ __('Copies') }}</label>
                        <input id="labelCopies" type="number" name="copies" min="1" max="100" value="{{ $copies }}" class="form-control @error('copies') is-invalid @enderror">
                        @error('copies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label for="labelSize" class="form-label fw-semibold">{{ __('Label size') }}</label>
                        <select id="labelSize" name="size" class="form-select">
                            <option value="50x30" @selected($size === '50x30')>50 × 30 mm</option>
                            <option value="60x40" @selected($size === '60x40')>60 × 40 mm</option>
                            <option value="70x40" @selected($size === '70x40')>70 × 40 mm</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label for="showPrice" class="form-label fw-semibold">{{ __('Price on label') }}</label>
                        <select id="showPrice" name="show_price" class="form-select">
                            <option value="1" @selected($showPrice)>{{ __('Show price') }}</option>
                            <option value="0" @selected(! $showPrice)>{{ __('Hide price') }}</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3 d-flex gap-2">
                        <button class="btn btn-light border flex-fill">{{ __('Update preview') }}</button>
                        <button type="button" class="btn btn-primary flex-fill" onclick="window.print()"><i class="mdi mdi-printer-outline me-1"></i>{{ __('Print') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @if($barcodeSvg)
        @php
            [$labelWidth, $labelHeight] = array_map('intval', explode('x', $size));
        @endphp

        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-1">{{ __('Print preview') }}</h4>
                <div class="text-muted small">{{ __('The label dimensions below use millimetres so browser print scaling can stay predictable.') }}</div>
            </div>
            <span class="admin-chip">{{ $copies }} × {{ $labelWidth }} × {{ $labelHeight }} mm</span>
        </div>

        <div
            id="barcodeLabelPrintArea"
            class="label-preview-sheet"
            style="--label-width: {{ $labelWidth }}mm; --label-height: {{ $labelHeight }}mm;"
        >
            @for($copy = 0; $copy < $copies; $copy++)
                <article class="barcode-label" data-barcode-label="{{ $copy + 1 }}">
                    <div>
                        <div class="barcode-label__name">{{ $product->name }}</div>
                        @if($variant)
                            <div class="barcode-label__variant">{{ $variantName ?: ($variant->sku ?: ('#' . $variant->id)) }}</div>
                        @endif
                    </div>

                    <div class="barcode-label__barcode">{!! $barcodeSvg !!}</div>

                    <div class="barcode-label__footer barcode-label__meta">
                        <span class="barcode-label__sku">{{ $sku ?: __('No SKU') }}</span>
                        @if($showPrice)
                            <span class="barcode-label__price">{{ number_format($price, 2) }}</span>
                        @endif
                    </div>
                </article>
            @endfor
        </div>

        <div class="alert alert-light border mt-4 mb-0">
            <div class="fw-semibold mb-1">{{ __('Printing tip') }}</div>
            <div class="small text-muted">{{ __('Use 100% scale / Actual size in the browser print dialog when your printer driver supports it. Disable headers and footers for clean labels.') }}</div>
        </div>
    @endif
</div>
@endsection
