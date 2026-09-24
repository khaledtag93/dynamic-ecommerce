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
    .pos-action-dock { position: sticky; bottom: .75rem; z-index: 12; margin-top: 1rem; padding: .7rem; border: 1px solid var(--admin-border); border-radius: 1rem; background: color-mix(in srgb, var(--admin-surface) 94%, transparent); backdrop-filter: blur(12px); box-shadow: 0 12px 30px rgba(15,23,42,.10); }
    @media (max-width: 1199.98px) { .pos-shell { grid-template-columns: 1fr; } .pos-checkout-sticky { position: static; } }
    @media (max-width: 767.98px) {
        .pos-cart-line { grid-template-columns: 1fr 1fr; }
        .pos-cart-line__item { grid-column: 1 / -1; }
        .pos-cart-line__remove { justify-self: end; }
        .pos-discount-form { grid-template-columns: 1fr; }
    }
</style>

<x-admin.page-header :kicker="__('Sales')" :title="__('Point of Sale')" :description="__('Scan exact barcodes, build a cashier cart, and complete an inventory-safe counter sale.')">
    @if($heldCarts->isNotEmpty())
        <span class="admin-chip"><i class="mdi mdi-pause-circle-outline me-1"></i>{{ __('Held sales') }}: {{ $heldCarts->count() }}</span>
    @endif
    <span class="admin-chip"><i class="mdi mdi-account-outline me-1"></i>{{ auth()->user()->name }}</span>
</x-admin.page-header>

<div class="admin-page-shell">
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

                    <details class="mt-3" @if(mb_strlen($productSearch) >= 2) open @endif>
                        <summary class="fw-semibold" style="cursor:pointer"><i class="mdi mdi-magnify me-1"></i>{{ __('Find product manually') }}</summary>
                        <form method="GET" action="{{ route('admin.pos.index') }}" class="mt-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-package-variant-closed"></i></span>
                                <input type="search" name="product_search" value="{{ $productSearch }}" minlength="2" maxlength="255" class="form-control" placeholder="{{ __('Search by product name, SKU, or barcode') }}" autocomplete="off">
                                <button class="btn btn-light border">{{ __('Search') }}</button>
                            </div>
                        </form>
                        @if(mb_strlen($productSearch) >= 2)
                            <div class="d-grid gap-2 mt-3">
                                @forelse($productResults as $result)
                                    <div class="pos-search-result">
                                        <div class="min-w-0">
                                            <div class="fw-semibold">{{ $result['label'] }}</div>
                                            <div class="text-muted small">{{ $result['sku'] ?: __('No SKU') }} @if($result['barcode']) · <span class="font-monospace">{{ $result['barcode'] }}</span>@endif · {{ __('Stock') }}: {{ $result['stock'] }} · EGP {{ number_format($result['price'], 2) }}</div>
                                        </div>
                                        @if($result['selectable'] && $result['stock'] > 0)
                                            <form method="POST" action="{{ route('admin.pos.scan', $cart) }}" data-submit-loading>
                                                @csrf
                                                <input type="hidden" name="barcode" value="{{ $result['barcode'] }}">
                                                <button class="btn btn-primary btn-sm">{{ __('Add') }}</button>
                                            </form>
                                        @elseif(!$result['selectable'])
                                            <span class="badge badge-soft-warning">{{ __('Barcode required') }}</span>
                                        @else
                                            <span class="badge badge-soft-secondary">{{ __('Out of stock') }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-muted small">{{ __('No sellable products match this search.') }}</div>
                                @endforelse
                            </div>
                        @endif
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

                        <form method="GET" action="{{ route('admin.pos.index') }}" class="mb-2">
                            <label for="posCustomerSearch" class="form-label small fw-semibold">{{ __('Find customer') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="mdi mdi-account-search-outline"></i></span>
                                <input
                                    id="posCustomerSearch"
                                    type="search"
                                    name="customer_search"
                                    value="{{ $customerSearch }}"
                                    class="form-control"
                                    minlength="2"
                                    maxlength="255"
                                    placeholder="{{ __('Search by name, email, or phone') }}"
                                    autocomplete="off"
                                >
                                <button class="btn btn-light border">{{ __('Search') }}</button>
                            </div>
                            <div class="form-text">{{ __('Enter at least 2 characters. Matching customer phone numbers from saved addresses are included.') }}</div>
                        </form>

                        @error('customer')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                        @if(mb_strlen($customerSearch) >= 2)
                            @if($customerResults->isNotEmpty())
                                <div class="d-grid gap-2 mt-3">
                                    @foreach($customerResults as $customerResult)
                                        <div class="d-flex justify-content-between align-items-center gap-3 border rounded-3 p-2">
                                            <div class="min-w-0">
                                                <div class="fw-semibold text-truncate">{{ $customerResult->name }}</div>
                                                <div class="text-muted small text-truncate">{{ $customerResult->email }}</div>
                                                @php($customerPhone = optional($customerResult->addresses->sortByDesc('is_default_shipping')->first())->phone)
                                                @if($customerPhone)<div class="text-muted small text-truncate"><i class="mdi mdi-phone-outline me-1"></i>{{ $customerPhone }}</div>@endif
                                            </div>
                                            <form method="POST" action="{{ route('admin.pos.customer.attach', ['posCart' => $cart->id, 'user' => $customerResult->id]) }}" data-submit-loading>
                                                @csrf
                                                <button class="btn btn-primary btn-sm" data-loading-text="{{ __('Attaching...') }}">{{ __('Attach') }}</button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small mt-3">{{ __('No customer accounts match this search.') }}</div>
                            @endif
                        @endif
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

                    <form method="POST" action="{{ route('admin.pos.checkout', $cart) }}" class="mt-4" data-submit-loading data-confirm-title="{{ __('Complete POS sale?') }}" data-confirm-message="{{ __('Create the paid sale and deduct the exact quantities from inventory?') }}" data-confirm-subtitle="{{ __('The server will recheck stock, prices, products, variants, and this cashier cart before saving.') }}" data-confirm-ok="{{ __('Complete sale') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="posPaymentMethod" class="form-label fw-semibold">{{ __('Payment method') }}</label>
                            <select id="posPaymentMethod" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                <option value="{{ $cashPaymentMethod }}" @selected(old('payment_method', $cashPaymentMethod) === $cashPaymentMethod)>{{ __('Cash') }}</option>
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
});
</script>
@endsection
