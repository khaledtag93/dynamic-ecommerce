@extends('layouts.admin')

@section('title', __('Point of Sale') . ' | Admin')

@section('content')
<style>
    .pos-shell { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(320px, .75fr); gap: 1.5rem; align-items: start; }
    .pos-checkout-sticky { position: sticky; top: 1rem; }
    .pos-scan-field { min-height: 3.35rem; font-size: 1.05rem; }
    .pos-cart-line { display: grid; grid-template-columns: minmax(0, 1.5fr) 110px 120px 105px 44px; gap: .8rem; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--admin-border); }
    .pos-cart-line:last-child { border-bottom: 0; }
    .pos-cart-line__discount { grid-column: 1 / -1; }
    .pos-discount-panel { border: 1px dashed var(--admin-border); border-radius: .85rem; padding: .7rem .8rem; background: rgba(15, 23, 42, .018); }
    .pos-discount-panel > summary { cursor: pointer; font-weight: 700; list-style: none; }
    .pos-discount-panel > summary::-webkit-details-marker { display: none; }
    .pos-discount-form { display: grid; grid-template-columns: minmax(130px, .7fr) minmax(110px, .55fr) minmax(180px, 1.5fr) auto; gap: .6rem; align-items: end; }
    .pos-line-name { font-weight: 800; }
    .pos-line-meta { color: var(--admin-muted); font-size: .82rem; }
    .pos-money { font-weight: 800; white-space: nowrap; }
    .pos-total-row { display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: .7rem 0; }
    .pos-total-row--grand { padding-top: 1rem; margin-top: .35rem; border-top: 1px solid var(--admin-border); font-size: 1.18rem; }
    .pos-entry-tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .pos-search-result { display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: .75rem; border: 1px solid var(--admin-border); border-radius: .8rem; }
    .pos-live-search { position: relative; }
    .pos-live-results { position: absolute; inset-inline: 0; top: calc(100% + .35rem); z-index: 30; max-height: 360px; overflow-y: auto; padding: .4rem; background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: .9rem; box-shadow: 0 18px 40px rgba(15,23,42,.14); }
    .pos-live-option { width:100%; display:flex; justify-content:space-between; gap:1rem; align-items:center; padding:.7rem .8rem; border:0; border-radius:.65rem; background:transparent; text-align:start; color:inherit; }
    .pos-live-option:hover, .pos-live-option.is-active { background: rgba(15,23,42,.055); }
    .pos-live-option:disabled { opacity:.55; cursor:not-allowed; }
    .pos-shift-history { display:grid; gap:.65rem; }
    .pos-shift-history__row { display:grid; grid-template-columns:minmax(150px,1.2fr) repeat(3,minmax(100px,.65fr)); gap:.75rem; align-items:center; padding:.75rem .9rem; border:1px solid var(--admin-border); border-radius:.85rem; }
    .pos-variance--balanced { color: var(--bs-success); }
    .pos-variance--over { color: var(--bs-primary); }
    .pos-variance--short { color: var(--bs-danger); }
    .pos-action-dock { position: sticky; bottom: .75rem; z-index: 12; margin-top: 1rem; padding: .7rem; border: 1px solid var(--admin-border); border-radius: 1rem; background: color-mix(in srgb, var(--admin-surface) 94%, transparent); backdrop-filter: blur(12px); box-shadow: 0 12px 30px rgba(15,23,42,.10); }
    @media (max-width: 1199.98px) { .pos-shell { grid-template-columns: 1fr; } .pos-checkout-sticky { position: static; } }
    @media (max-width: 767.98px) {
        .pos-cart-line { grid-template-columns: 1fr 1fr; }
        .pos-cart-line__item { grid-column: 1 / -1; }
        .pos-cart-line__remove { justify-self: end; }
        .pos-discount-form { grid-template-columns: 1fr; }
        .pos-shift-history__row { grid-template-columns:1fr 1fr; }
    }
</style>

<x-admin.page-header :kicker="__('Sales')" :title="__('Point of Sale')" :description="__('Scan exact barcodes, build a cashier cart, and complete an inventory-safe counter sale.')">
    @if($heldCarts->isNotEmpty())
        <span class="admin-chip"><i class="mdi mdi-pause-circle-outline me-1"></i>{{ __('Held sales') }}: {{ $heldCarts->count() }}</span>
    @endif
    <span class="admin-chip"><i class="mdi mdi-account-outline me-1"></i>{{ auth()->user()->name }}</span>
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h4 class="mb-0">{{ __('Cash shift') }}</h4>
                        @if($cashShift)
                            <span class="badge badge-soft-success">{{ __('Open') }}</span>
                        @else
                            <span class="badge badge-soft-secondary">{{ __('Not opened') }}</span>
                        @endif
                    </div>
                    <p class="text-muted small mb-0 mt-1">{{ __('Track opening cash, cash sales, refunds, counted cash, and drawer variance for this cashier.') }}</p>
                </div>
                @if($cashShift)
                    <div class="text-end">
                        <div class="text-muted small">{{ __('Opened') }}</div>
                        <div class="fw-semibold">{{ optional($cashShift->opened_at)->format('M d, Y H:i') }}</div>
                    </div>
                @endif
            </div>

            @if($cashShift)
                <div class="row g-3 mt-1">
                    <div class="col-6 col-lg-3"><div class="border rounded-4 p-3 h-100"><div class="text-muted small">{{ __('Opening cash') }}</div><div class="fw-bold fs-5">EGP {{ number_format($cashShiftSummary['opening_cash'], 2) }}</div></div></div>
                    <div class="col-6 col-lg-3"><div class="border rounded-4 p-3 h-100"><div class="text-muted small">{{ __('Cash sales') }}</div><div class="fw-bold fs-5">EGP {{ number_format($cashShiftSummary['cash_sales'], 2) }}</div></div></div>
                    <div class="col-6 col-lg-3"><div class="border rounded-4 p-3 h-100"><div class="text-muted small">{{ __('Cash refunds') }}</div><div class="fw-bold fs-5">EGP {{ number_format($cashShiftSummary['cash_refunds'], 2) }}</div></div></div>
                    <div class="col-6 col-lg-3"><div class="border rounded-4 p-3 h-100"><div class="text-muted small">{{ __('Expected cash') }}</div><div class="fw-bold fs-5">EGP {{ number_format($cashShiftSummary['expected_cash'], 2) }}</div></div></div>
                </div>
                <details class="mt-3">
                    <summary class="fw-semibold" style="cursor:pointer"><i class="mdi mdi-cash-check me-1"></i>{{ __('Close and reconcile shift') }}</summary>
                    <form method="POST" action="{{ route('admin.pos.shifts.close', $cashShift) }}" class="row g-3 mt-1" data-submit-loading data-confirm-title="{{ __('Close cash shift?') }}" data-confirm-message="{{ __('The counted cash will be compared with the expected drawer amount and the variance will be recorded.') }}" data-confirm-ok="{{ __('Close shift') }}">
                        @csrf
                        <div class="col-md-4"><label class="form-label">{{ __('Counted cash') }}</label><input name="closing_cash_counted" type="number" min="0" step="0.01" required class="form-control" placeholder="0.00"></div>
                        <div class="col-md-6"><label class="form-label">{{ __('Closing notes') }} <span class="text-muted">({{ __('optional') }})</span></label><input name="closing_notes" maxlength="1000" class="form-control" placeholder="{{ __('Explain any variance or handover note') }}"></div>
                        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-danger w-100">{{ __('Close shift') }}</button></div>
                    </form>
                </details>
            @else
                <form method="POST" action="{{ route('admin.pos.shifts.open') }}" class="row g-3 mt-1" data-submit-loading>
                    @csrf
                    <div class="col-md-4"><label class="form-label">{{ __('Opening cash') }}</label><input name="opening_cash" type="number" min="0" step="0.01" value="{{ old('opening_cash', '0.00') }}" required class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">{{ __('Opening notes') }} <span class="text-muted">({{ __('optional') }})</span></label><input name="opening_notes" maxlength="1000" class="form-control" placeholder="{{ __('Drawer handover or opening note') }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="mdi mdi-cash-register me-1"></i>{{ __('Open shift') }}</button></div>
                </form>
            @endif
            @error('shift')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

            @if($recentCashShifts->isNotEmpty())
                <details class="mt-3">
                    <summary class="fw-semibold" style="cursor:pointer"><i class="mdi mdi-history me-1"></i>{{ __('Recent reconciliations') }}</summary>
                    <div class="pos-shift-history mt-3">
                        @foreach($recentCashShifts as $recentShift)
                            @php
                                $variance = (float) $recentShift->cash_variance;
                                $varianceState = abs($variance) < 0.005 ? 'balanced' : ($variance > 0 ? 'over' : 'short');
                            @endphp
                            <div class="pos-shift-history__row">
                                <div><div class="fw-semibold">{{ optional($recentShift->closed_at)->format('M d, Y H:i') }}</div><div class="text-muted small">{{ __('Shift #:id', ['id' => $recentShift->id]) }}</div></div>
                                <div><div class="text-muted small">{{ __('Expected') }}</div><div class="fw-semibold">EGP {{ number_format((float) $recentShift->expected_cash, 2) }}</div></div>
                                <div><div class="text-muted small">{{ __('Counted') }}</div><div class="fw-semibold">EGP {{ number_format((float) $recentShift->closing_cash_counted, 2) }}</div></div>
                                <div><div class="text-muted small">{{ __('Variance') }}</div><div class="fw-bold pos-variance--{{ $varianceState }}">{{ $variance > 0 ? '+' : '' }}EGP {{ number_format($variance, 2) }} · {{ __($varianceState === 'balanced' ? 'Balanced' : ($varianceState === 'over' ? 'Over' : 'Short')) }}</div></div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>

    <div class="pos-shell">
        <div>
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Scan item') }}</h4>
                            <p class="text-muted small mb-0">{{ __('Each accepted barcode scan adds exactly one unit. Variant products require their exact variant barcode.') }}</p>
                        </div>
                        <span class="admin-chip"><i class="mdi mdi-keyboard-outline me-1"></i>{{ __('HID scanner ready') }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.pos.scan', $cart) }}" data-submit-loading>
                        @csrf
                        <div class="input-group">
                            <span class="input-group-text"><i class="mdi mdi-barcode-scan"></i></span>
                            <input
                                id="posBarcodeInput"
                                name="barcode"
                                type="text"
                                value="{{ old('barcode') }}"
                                class="form-control pos-scan-field @error('barcode') is-invalid @enderror"
                                placeholder="{{ __('Scan a product or variant barcode') }}"
                                maxlength="255"
                                autocomplete="off"
                                autocapitalize="off"
                                spellcheck="false"
                                enterkeyhint="done"
                                autofocus
                            >
                            <button class="btn btn-primary px-4" data-loading-text="{{ __('Adding...') }}">{{ __('Add item') }}</button>
                        </div>
                        @error('barcode')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        @error('cart')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </form>

                    <details class="mt-3">
                        <summary class="fw-semibold" style="cursor:pointer"><i class="mdi mdi-magnify me-1"></i>{{ __('Find product manually') }}</summary>
                        <div class="pos-live-search mt-3" data-pos-live-search data-url="{{ route('admin.pos.lookups.products') }}" data-kind="product">
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-package-variant-closed"></i></span>
                                <input id="posProductSearch" type="search" minlength="2" maxlength="255" class="form-control" placeholder="{{ __('Search by product name, SKU, or barcode') }}" autocomplete="off" aria-autocomplete="list" aria-expanded="false">
                            </div>
                            <div class="form-text">{{ __('Type at least 2 characters. Use arrow keys and Enter to add an item.') }}</div>
                            <div class="pos-live-results d-none" role="listbox"></div>
                        </div>

                    </details>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Current sale') }}</h4>
                            <div class="text-muted small">{{ __(':lines line(s) · :items unit(s)', ['lines' => $summary['lines_count'], 'items' => $summary['items_count']]) }}</div>
                        </div>
                        @if($cart->items->isNotEmpty())
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <form id="posHoldForm" method="POST" action="{{ route('admin.pos.hold', $cart) }}" class="d-flex gap-2 align-items-center flex-wrap" data-submit-loading>
                                    @csrf
                                    <input type="hidden" name="customer_name" id="posHoldCustomerName">
                                    <input type="hidden" name="notes" id="posHoldNotes">
                                    <div>
                                        <input type="text" name="hold_label" maxlength="80" value="{{ old('hold_label', $cart->hold_label) }}" class="form-control form-control-sm @error('hold_label') is-invalid @enderror" style="width: 190px" placeholder="{{ __('Hold label (optional)') }}">
                                        @error('hold_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <button class="btn btn-light border btn-sm btn-text-icon" data-loading-text="{{ __('Holding...') }}">
                                        <i class="mdi mdi-pause-circle-outline"></i><span>{{ __('Hold sale') }}</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.pos.clear', $cart) }}" data-submit-loading data-confirm-title="{{ __('Clear POS cart?') }}" data-confirm-message="{{ __('Remove every item from the current cashier cart?') }}" data-confirm-ok="{{ __('Clear cart') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-light border btn-sm">{{ __('Clear cart') }}</button>
                                </form>
                            </div>
                        @endif
                    </div>

                    @forelse($cart->items as $item)
                        @php
                            $availableStock = (int) ($item->variant?->stock ?? $item->product?->quantity ?? 0);
                            $lineSummary = $summary['lines'][$item->id] ?? ['gross_total' => 0, 'discount_total' => 0, 'net_total' => 0];
                            $lineGrossTotal = (float) $lineSummary['gross_total'];
                            $lineDiscount = (float) $lineSummary['discount_total'];
                            $lineTotal = (float) $lineSummary['net_total'];
                        @endphp
                        <div class="pos-cart-line">
                            <div class="pos-cart-line__item">
                                <div class="pos-line-name">{{ $item->product_name }}</div>
                                <div class="pos-line-meta">
                                    {{ $item->variant_name ?: __('Simple product') }}
                                    @if($item->sku) · {{ $item->sku }} @endif
                                    @if($item->barcode) · <span class="font-monospace">{{ $item->barcode }}</span> @endif
                                </div>
                                <div class="pos-line-meta mt-1">{{ __('Available stock') }}: {{ $availableStock }}</div>
                            </div>

                            <div>
                                <div class="text-muted small">{{ __('Unit price') }}</div>
                                <div class="pos-money">EGP {{ number_format((float) $item->unit_price, 2) }}</div>
                            </div>

                            <form method="POST" action="{{ route('admin.pos.items.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]) }}" class="d-flex gap-2 align-items-center" data-submit-loading>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="expected_quantity" value="{{ $item->quantity }}">
                                <input type="number" name="quantity" min="1" max="{{ max(1, $availableStock) }}" value="{{ $item->quantity }}" class="form-control form-control-sm @error('quantity') is-invalid @enderror" aria-label="{{ __('Quantity') }}">
                                <button class="btn btn-sm btn-light border" title="{{ __('Update quantity') }}"><i class="mdi mdi-check"></i></button>
                            </form>

                            <div>
                                <div class="text-muted small">{{ __('Line total') }}</div>
                                <div class="pos-money">EGP {{ number_format($lineTotal, 2) }}</div>
                                @if($lineDiscount > 0)
                                    <div class="text-muted small"><s>EGP {{ number_format($lineGrossTotal, 2) }}</s> · -EGP {{ number_format($lineDiscount, 2) }}</div>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('admin.pos.items.destroy', ['posCart' => $cart->id, 'posCartItem' => $item->id]) }}" class="pos-cart-line__remove" data-submit-loading>
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-light border text-danger" title="{{ __('Remove item') }}"><i class="mdi mdi-delete-outline"></i></button>
                            </form>

                            @if($canDiscount || $lineDiscount > 0)
                                <div class="pos-cart-line__discount">
                                    <details class="pos-discount-panel" @if($lineDiscount > 0) open @endif>
                                        <summary class="d-flex justify-content-between align-items-center gap-2">
                                            <span><i class="mdi mdi-sale-outline me-1"></i>{{ __('Line discount') }}</span>
                                            @if($lineDiscount > 0)
                                                <span class="badge badge-soft-success">-EGP {{ number_format($lineDiscount, 2) }}</span>
                                            @endif
                                        </summary>
                                        <div class="mt-3">
                                            @if($canDiscount)
                                                <form method="POST" action="{{ route('admin.pos.items.discount.update', ['posCart' => $cart->id, 'posCartItem' => $item->id]) }}" class="pos-discount-form" data-submit-loading>
                                                    @csrf
                                                    @method('PATCH')
                                                    <div>
                                                        <label class="form-label small fw-semibold">{{ __('Discount type') }}</label>
                                                        <select name="discount_type" class="form-select form-select-sm" required>
                                                            <option value="{{ \App\Services\Commerce\PosService::DISCOUNT_TYPE_FIXED }}" @selected($item->discount_type === \App\Services\Commerce\PosService::DISCOUNT_TYPE_FIXED)>{{ __('Fixed amount') }}</option>
                                                            <option value="{{ \App\Services\Commerce\PosService::DISCOUNT_TYPE_PERCENT }}" @selected($item->discount_type === \App\Services\Commerce\PosService::DISCOUNT_TYPE_PERCENT)>{{ __('Percentage') }}</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label small fw-semibold">{{ __('Discount value') }}</label>
                                                        <input name="discount_value" type="number" min="0.01" step="0.01" max="999999999.99" value="{{ (float) ($item->discount_value ?? 0) ?: '' }}" class="form-control form-control-sm" required>
                                                    </div>
                                                    <div>
                                                        <label class="form-label small fw-semibold">{{ __('Discount reason') }}</label>
                                                        <input name="discount_reason" type="text" maxlength="255" value="{{ $item->discount_reason }}" class="form-control form-control-sm" placeholder="{{ __('Required for audit') }}" required>
                                                    </div>
                                                    <button class="btn btn-sm btn-light border" data-loading-text="{{ __('Applying...') }}">{{ __('Apply discount') }}</button>
                                                </form>
                                            @endif

                                            @if($lineDiscount > 0)
                                                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-2">
                                                    <div class="text-muted small">{{ $item->discount_reason }}</div>
                                                    <form method="POST" action="{{ route('admin.pos.items.discount.destroy', ['posCart' => $cart->id, 'posCartItem' => $item->id]) }}" data-submit-loading>
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-link text-danger p-0">{{ __('Remove discount') }}</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="admin-empty-state py-5">
                            <div class="admin-empty-icon"><i class="mdi mdi-cart-outline"></i></div>
                            <h5>{{ __('POS cart is empty') }}</h5>
                            <p class="text-muted mb-0">{{ __('Scan the first physical item to start this sale.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            @if($heldCarts->isNotEmpty())
                <div class="admin-card mt-4">
                    <div class="admin-card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                            <div>
                                <h4 class="mb-1">{{ __('Held sales') }}</h4>
                                <p class="text-muted small mb-0">{{ __('Resume a paused cashier cart when the current sale is empty. Stock and prices are rechecked before checkout.') }}</p>
                            </div>
                            <span class="admin-chip">{{ $heldCarts->count() }}</span>
                        </div>

                        <div class="row g-3">
                            @foreach($heldCarts as $heldCart)
                                @php
                                    $heldUnits = (int) $heldCart->items->sum('quantity');
                                    $heldSummary = $heldSummaries[$heldCart->id] ?? ['grand_total' => 0, 'discount_total' => 0];
                                    $heldTotal = (float) $heldSummary['grand_total'];
                                @endphp
                                <div class="col-lg-6">
                                    <div class="border rounded-4 p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                            <div>
                                                <div class="fw-bold">{{ $heldCart->hold_label ?: __('Held sale #:id', ['id' => $heldCart->id]) }}</div>
                                                <div class="text-muted small">{{ optional($heldCart->held_at)->format('M d, Y H:i') }} · {{ __(':items unit(s)', ['items' => $heldUnits]) }}</div>
                                            </div>
                                            <span class="fw-bold">EGP {{ number_format($heldTotal, 2) }}</span>
                                        </div>

                                        @if($heldCart->customer_name)
                                            <div class="small mb-1">
                                                <span class="text-muted">{{ __('Customer name') }}:</span> {{ $heldCart->customer_name }}
                                                @if($heldCart->customer)<span class="badge badge-soft-success ms-1">{{ __('Account') }}</span>@endif
                                            </div>
                                        @endif
                                        @if($heldCart->notes)
                                            <div class="small text-muted mb-3">{{ \Illuminate\Support\Str::limit($heldCart->notes, 90) }}</div>
                                        @endif

                                        <div class="d-flex gap-2 flex-wrap mt-3">
                                            <form method="POST" action="{{ route('admin.pos.resume', $heldCart) }}" data-submit-loading>
                                                @csrf
                                                <button class="btn btn-primary btn-sm btn-text-icon" data-loading-text="{{ __('Resuming...') }}">
                                                    <i class="mdi mdi-play-circle-outline"></i><span>{{ __('Resume sale') }}</span>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.pos.held.destroy', $heldCart) }}" data-submit-loading data-confirm-title="{{ __('Discard held sale?') }}" data-confirm-message="{{ __('This removes it from the held queue without changing inventory.') }}" data-confirm-ok="{{ __('Discard') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-light border btn-sm text-danger">{{ __('Discard') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if($recentSales->isNotEmpty())
                <div class="admin-card mt-4">
                    <div class="admin-card-body">
                        <h4 class="mb-1">{{ __('Your recent POS sales') }}</h4>
                        <p class="text-muted small mb-3">{{ __('Quick access to sales completed by this cashier account.') }}</p>
                        <div class="row g-3">
                            @foreach($recentSales as $sale)
                                <div class="col-md-6">
                                    <a href="{{ route('admin.pos.sales.show', $sale) }}" class="d-flex justify-content-between align-items-center gap-3 border rounded-4 p-3 text-decoration-none">
                                        <span>
                                            <span class="d-block fw-semibold">{{ $sale->order_number }}</span>
                                            <span class="text-muted small">{{ optional($sale->placed_at)->format('M d, Y H:i') }}</span>
                                        </span>
                                        <span class="fw-bold">EGP {{ number_format((float) $sale->grand_total, 2) }}</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="pos-checkout-sticky">
            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-1">{{ __('Checkout') }}</h4>
                    <p class="text-muted small mb-4">{{ __('Prices and stock are rechecked under database locks before the sale is written.') }}</p>

                    <div class="border rounded-4 p-3 mb-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                            <div>
                                <div class="fw-semibold">{{ __('Customer') }}</div>
                                <div class="text-muted small">{{ __('Attach an existing customer account or keep this as a walk-in sale.') }}</div>
                            </div>
                            @if($cart->customer)
                                <span class="badge admin-status-badge badge-soft-success">{{ __('Attached') }}</span>
                            @else
                                <span class="badge admin-status-badge badge-soft-secondary">{{ __('Walk-in') }}</span>
                            @endif
                        </div>

                        @if($cart->customer)
                            <div class="border rounded-3 p-3 mb-3">
                                <div class="fw-bold">{{ $cart->customer->name }}</div>
                                <div class="text-muted small">{{ $cart->customer->email }}</div>
                                <form method="POST" action="{{ route('admin.pos.customer.detach', $cart) }}" class="mt-2" data-submit-loading>
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-light border btn-sm">{{ __('Use walk-in instead') }}</button>
                                </form>
                            </div>
                        @endif

                        <div class="pos-live-search mb-2" data-pos-live-search data-url="{{ route('admin.pos.lookups.customers') }}" data-kind="customer">
                            <label for="posCustomerSearch" class="form-label small fw-semibold">{{ __('Find customer') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-account-search-outline"></i></span>
                                <input id="posCustomerSearch" type="search" class="form-control" minlength="2" maxlength="255" placeholder="{{ __('Search by name, email, or phone') }}" autocomplete="off" aria-autocomplete="list" aria-expanded="false">
                            </div>
                            <div class="form-text">{{ __('Type at least 2 characters. Use arrow keys and Enter to attach a customer.') }}</div>
                            <div class="pos-live-results d-none" role="listbox"></div>
                        </div>

                        @error('customer')<div class="text-danger small mb-2">{{ $message }}</div>@enderror


                    </div>

                    <div class="pos-total-row">
                        <span class="text-muted">{{ __('Units') }}</span>
                        <strong>{{ $summary['items_count'] }}</strong>
                    </div>
                    <div class="pos-total-row">
                        <span class="text-muted">{{ __('Subtotal') }}</span>
                        <strong>EGP {{ number_format($summary['subtotal'], 2) }}</strong>
                    </div>
                    @if($summary['line_discount_total'] > 0)
                        <div class="pos-total-row">
                            <span class="text-muted">{{ __('Line discounts') }}</span>
                            <strong>-EGP {{ number_format($summary['line_discount_total'], 2) }}</strong>
                        </div>
                    @endif

                    @if($canDiscount || $summary['order_discount_total'] > 0)
                        <details class="pos-discount-panel my-2" @if($summary['order_discount_total'] > 0) open @endif>
                            <summary class="d-flex justify-content-between align-items-center gap-2">
                                <span><i class="mdi mdi-sale-outline me-1"></i>{{ __('Sale discount') }}</span>
                                @if($summary['order_discount_total'] > 0)
                                    <span class="badge badge-soft-success">-EGP {{ number_format($summary['order_discount_total'], 2) }}</span>
                                @endif
                            </summary>
                            <div class="mt-3">
                                @if($canDiscount)
                                    <form method="POST" action="{{ route('admin.pos.discount.update', $cart) }}" class="d-grid gap-2" data-submit-loading>
                                        @csrf
                                        @method('PATCH')
                                        <div class="row g-2">
                                            <div class="col-sm-5">
                                                <label class="form-label small fw-semibold">{{ __('Discount type') }}</label>
                                                <select name="discount_type" class="form-select form-select-sm" required>
                                                    <option value="{{ \App\Services\Commerce\PosService::DISCOUNT_TYPE_FIXED }}" @selected($cart->discount_type === \App\Services\Commerce\PosService::DISCOUNT_TYPE_FIXED)>{{ __('Fixed amount') }}</option>
                                                    <option value="{{ \App\Services\Commerce\PosService::DISCOUNT_TYPE_PERCENT }}" @selected($cart->discount_type === \App\Services\Commerce\PosService::DISCOUNT_TYPE_PERCENT)>{{ __('Percentage') }}</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-7">
                                                <label class="form-label small fw-semibold">{{ __('Discount value') }}</label>
                                                <input name="discount_value" type="number" min="0.01" step="0.01" max="999999999.99" value="{{ (float) ($cart->discount_value ?? 0) ?: '' }}" class="form-control form-control-sm" required>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label small fw-semibold">{{ __('Discount reason') }}</label>
                                            <input name="discount_reason" type="text" maxlength="255" value="{{ $cart->discount_reason }}" class="form-control form-control-sm" placeholder="{{ __('Required for audit') }}" required>
                                        </div>
                                        <div class="small text-muted">{{ __('Fixed discounts are amounts in EGP; percentage discounts are calculated from current locked prices.') }}</div>
                                        <button class="btn btn-sm btn-light border" data-loading-text="{{ __('Applying...') }}">{{ __('Apply discount') }}</button>
                                    </form>
                                @endif

                                @if($summary['order_discount_total'] > 0)
                                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-2">
                                        <div class="text-muted small">{{ $cart->discount_reason }}</div>
                                        <form method="POST" action="{{ route('admin.pos.discount.destroy', $cart) }}" data-submit-loading>
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0">{{ __('Remove discount') }}</button>
                                        </form>
                                    </div>
                                @endif
                                <div class="small text-muted mt-2">{{ __('The discount reason is kept in the admin audit trail and is not printed on the customer receipt.') }}</div>
                            </div>
                        </details>
                    @endif

                    @if($summary['discount_total'] > 0)
                        <div class="pos-total-row">
                            <span class="text-muted">{{ __('Discount total') }}</span>
                            <strong>-EGP {{ number_format($summary['discount_total'], 2) }}</strong>
                        </div>
                    @endif
                    <div class="pos-total-row pos-total-row--grand">
                        <span>{{ __('Total') }}</span>
                        <strong>EGP {{ number_format($summary['grand_total'], 2) }}</strong>
                    </div>

                    @error('discount_type')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('discount_value')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('discount_reason')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('discount')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('cash_shift')<div class="alert alert-warning py-2 mt-3 mb-0"><i class="mdi mdi-cash-register me-1"></i>{{ $message }}</div>@enderror

                    <form method="POST" action="{{ route('admin.pos.checkout', $cart) }}" class="mt-4" data-submit-loading data-confirm-title="{{ __('Complete POS sale?') }}" data-confirm-message="{{ __('Create the paid sale and deduct the exact quantities from inventory?') }}" data-confirm-subtitle="{{ __('The server will recheck stock, prices, products, variants, and this cashier cart before saving.') }}" data-confirm-ok="{{ __('Complete sale') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="posPaymentMethod" class="form-label fw-semibold">{{ __('Payment method') }}</label>
                            <select id="posPaymentMethod" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                <option value="{{ $cashPaymentMethod }}" @selected(old('payment_method', $cashPaymentMethod) === $cashPaymentMethod) @disabled(!$cashShift)>{{ __('Cash') }} @unless($cashShift)· {{ __('Open shift required') }}@endunless</option>
                                <option value="{{ $cardPaymentMethod }}" @selected(old('payment_method') === $cardPaymentMethod)>{{ __('Card terminal') }}</option>
                            </select>
                            @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3" id="posCashReceivedWrap">
                            <label for="posCashReceived" class="form-label fw-semibold">{{ __('Cash received') }}</label>
                            <input id="posCashReceived" name="cash_received" type="number" min="0" step="0.01" value="{{ old('cash_received', number_format($summary['grand_total'], 2, '.', '')) }}" class="form-control @error('cash_received') is-invalid @enderror">
                            @error('cash_received')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Change is calculated again on the server from the final locked total.') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="posCustomerName" class="form-label fw-semibold">
                                {{ $cart->customer ? __('Attached customer') : __('Walk-in customer name') }}
                                @unless($cart->customer)<span class="text-muted fw-normal">({{ __('optional') }})</span>@endunless
                            </label>
                            <input
                                id="posCustomerName"
                                name="customer_name"
                                type="text"
                                maxlength="255"
                                value="{{ old('customer_name', $cart->customer_name) }}"
                                class="form-control"
                                placeholder="{{ __('Walk-in customer') }}"
                                @readonly((bool) $cart->customer)
                            >
                            @if($cart->customer)
                                <div class="form-text">{{ __('The linked customer account is authoritative for this sale. Remove it above to use a walk-in name.') }}</div>
                            @endif
                        </div>

                        <div class="mb-4">
                            <label for="posNotes" class="form-label fw-semibold">{{ __('Sale notes') }} <span class="text-muted fw-normal">({{ __('optional') }})</span></label>
                            <textarea id="posNotes" name="notes" rows="3" maxlength="1000" class="form-control">{{ old('notes', $cart->notes) }}</textarea>
                        </div>

                        <div class="pos-action-dock">
                        <button class="btn btn-primary w-100 btn-lg btn-text-icon" @disabled($cart->items->isEmpty()) data-loading-text="{{ __('Completing sale...') }}">
                            <i class="mdi mdi-cash-register"></i><span>{{ __('Complete sale') }}</span>
                        </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const barcodeInput = document.getElementById('posBarcodeInput');
    const paymentMethod = document.getElementById('posPaymentMethod');
    const cashWrap = document.getElementById('posCashReceivedWrap');
    const cashInput = document.getElementById('posCashReceived');
    const holdForm = document.getElementById('posHoldForm');
    const holdCustomerName = document.getElementById('posHoldCustomerName');
    const holdNotes = document.getElementById('posHoldNotes');
    const customerName = document.getElementById('posCustomerName');
    const notes = document.getElementById('posNotes');

    if (holdForm) {
        holdForm.addEventListener('submit', function () {
            if (holdCustomerName) {
                holdCustomerName.value = customerName ? customerName.value : '';
            }
            if (holdNotes) {
                holdNotes.value = notes ? notes.value : '';
            }
        });
    }

    const syncPaymentFields = function () {
        const isCash = paymentMethod && paymentMethod.value === @json($cashPaymentMethod);

        if (cashWrap) {
            cashWrap.classList.toggle('d-none', !isCash);
        }

        if (cashInput) {
            cashInput.disabled = !isCash;
            cashInput.required = isCash;
        }
    };

    if (paymentMethod) {
        paymentMethod.addEventListener('change', syncPaymentFields);
        syncPaymentFields();
    }

    if (barcodeInput) {
        barcodeInput.focus();
        barcodeInput.select();
    }

    const csrfToken = @json(csrf_token());
    const addProductUrlTemplate = @json(route('admin.pos.catalog.add', ['posCart' => $cart->id, 'product' => '__PRODUCT__']));
    const attachCustomerUrlTemplate = @json(route('admin.pos.customer.attach', ['posCart' => $cart->id, 'user' => '__CUSTOMER__']));
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#039;'}[char]));

    document.querySelectorAll('[data-pos-live-search]').forEach(function (wrap) {
        const input = wrap.querySelector('input[type="search"]');
        const panel = wrap.querySelector('.pos-live-results');
        const kind = wrap.dataset.kind;
        let timer = null, controller = null, active = -1, results = [];
        const close = () => { panel.classList.add('d-none'); panel.innerHTML=''; input.setAttribute('aria-expanded','false'); active=-1; };
        const paintActive = () => panel.querySelectorAll('.pos-live-option').forEach((el,i)=>el.classList.toggle('is-active',i===active));
        const submitChoice = item => {
            const form = document.createElement('form'); form.method='POST';
            form.action = kind === 'product' ? addProductUrlTemplate.replace('__PRODUCT__', item.product_id) : attachCustomerUrlTemplate.replace('__CUSTOMER__', item.id);
            form.innerHTML = '<input type="hidden" name="_token" value="'+escapeHtml(csrfToken)+'">';
            if (kind === 'product' && item.variant_id) form.innerHTML += '<input type="hidden" name="variant_id" value="'+Number(item.variant_id)+'">';
            document.body.appendChild(form); form.submit();
        };
        const render = items => {
            results=items; active=-1;
            if (!items.length) { panel.innerHTML='<div class="text-muted small p-3">'+@json(__('No matching results.'))+'</div>'; panel.classList.remove('d-none'); input.setAttribute('aria-expanded','true'); return; }
            panel.innerHTML=items.map((item,i)=>{
                if(kind==='product') { const disabled=!item.selectable || Number(item.stock)<1; return '<button type="button" class="pos-live-option" data-index="'+i+'" '+(disabled?'disabled':'')+'><span><span class="d-block fw-semibold">'+escapeHtml(item.label)+'</span><span class="text-muted small">'+escapeHtml(item.sku || @json(__('No SKU')))+(item.barcode?' · '+escapeHtml(item.barcode):'')+' · '+@json(__('Stock'))+': '+Number(item.stock)+'</span></span><strong>EGP '+Number(item.price).toFixed(2)+'</strong></button>'; }
                return '<button type="button" class="pos-live-option" data-index="'+i+'"><span><span class="d-block fw-semibold">'+escapeHtml(item.name)+'</span><span class="text-muted small">'+escapeHtml(item.email)+(item.phone?' · '+escapeHtml(item.phone):'')+'</span></span><span>'+@json(__('Select'))+'</span></button>';
            }).join('');
            panel.classList.remove('d-none'); input.setAttribute('aria-expanded','true');
            panel.querySelectorAll('.pos-live-option:not(:disabled)').forEach(btn=>btn.addEventListener('click',()=>submitChoice(results[Number(btn.dataset.index)])));
        };
        input.addEventListener('input', function(){ clearTimeout(timer); const q=input.value.trim(); if(q.length<2){close();return;} timer=setTimeout(async()=>{ if(controller)controller.abort(); controller=new AbortController(); try{const response=await fetch(wrap.dataset.url+'?q='+encodeURIComponent(q),{headers:{'Accept':'application/json'},signal:controller.signal}); if(!response.ok)return; const data=await response.json(); render(data.results||[]);}catch(e){if(e.name!=='AbortError')close();}},250); });
        input.addEventListener('keydown', function(e){ const buttons=[...panel.querySelectorAll('.pos-live-option:not(:disabled)')]; if(e.key==='Escape'){close();return;} if(panel.classList.contains('d-none')||!buttons.length)return; if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault(); active=e.key==='ArrowDown'?Math.min(active+1,buttons.length-1):Math.max(active-1,0); paintActive(); buttons[active]?.scrollIntoView({block:'nearest'});} else if(e.key==='Enter'&&active>=0){e.preventDefault(); buttons[active]?.click();} });
        document.addEventListener('click', e=>{ if(!wrap.contains(e.target))close(); });
    });
});
</script>
@endsection
