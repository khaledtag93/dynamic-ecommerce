@extends('layouts.admin')

@section('title', __('Point of Sale') . ' | Admin')

@section('content')
<style>
    .pos-shell { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(320px, .75fr); gap: 1.5rem; align-items: start; }
    .pos-checkout-sticky { position: sticky; top: 1rem; }
    .pos-scan-field { min-height: 3.35rem; font-size: 1.05rem; }
    .pos-cart-line { display: grid; grid-template-columns: minmax(0, 1.5fr) 110px 120px 105px 44px; gap: .8rem; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--admin-border); }
    .pos-cart-line:last-child { border-bottom: 0; }
    .pos-line-name { font-weight: 800; }
    .pos-line-meta { color: var(--admin-muted); font-size: .82rem; }
    .pos-money { font-weight: 800; white-space: nowrap; }
    .pos-total-row { display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: .7rem 0; }
    .pos-total-row--grand { padding-top: 1rem; margin-top: .35rem; border-top: 1px solid var(--admin-border); font-size: 1.18rem; }
    @media (max-width: 1199.98px) { .pos-shell { grid-template-columns: 1fr; } .pos-checkout-sticky { position: static; } }
    @media (max-width: 767.98px) {
        .pos-cart-line { grid-template-columns: 1fr 1fr; }
        .pos-cart-line__item { grid-column: 1 / -1; }
        .pos-cart-line__remove { justify-self: end; }
    }
</style>

<x-admin.page-header :kicker="__('Sales')" :title="__('Point of Sale')" :description="__('Scan exact barcodes, build a cashier cart, and complete an inventory-safe counter sale.')">
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
                            <form method="POST" action="{{ route('admin.pos.clear', $cart) }}" data-submit-loading data-confirm-title="{{ __('Clear POS cart?') }}" data-confirm-message="{{ __('Remove every item from the current cashier cart?') }}" data-confirm-ok="{{ __('Clear cart') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-light border btn-sm">{{ __('Clear cart') }}</button>
                            </form>
                        @endif
                    </div>

                    @forelse($cart->items as $item)
                        @php
                            $availableStock = (int) ($item->variant?->stock ?? $item->product?->quantity ?? 0);
                            $lineTotal = (float) $item->unit_price * (int) $item->quantity;
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
                            </div>

                            <form method="POST" action="{{ route('admin.pos.items.destroy', ['posCart' => $cart->id, 'posCartItem' => $item->id]) }}" class="pos-cart-line__remove" data-submit-loading>
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-light border text-danger" title="{{ __('Remove item') }}"><i class="mdi mdi-delete-outline"></i></button>
                            </form>
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

                    <div class="pos-total-row">
                        <span class="text-muted">{{ __('Units') }}</span>
                        <strong>{{ $summary['items_count'] }}</strong>
                    </div>
                    <div class="pos-total-row">
                        <span class="text-muted">{{ __('Subtotal') }}</span>
                        <strong>EGP {{ number_format($summary['subtotal'], 2) }}</strong>
                    </div>
                    <div class="pos-total-row pos-total-row--grand">
                        <span>{{ __('Total') }}</span>
                        <strong>EGP {{ number_format($summary['grand_total'], 2) }}</strong>
                    </div>

                    <form method="POST" action="{{ route('admin.pos.checkout', $cart) }}" class="mt-4" data-submit-loading data-confirm-title="{{ __('Complete POS sale?') }}" data-confirm-message="{{ __('Create the paid sale and deduct the exact quantities from inventory?') }}" data-confirm-subtitle="{{ __('The server will recheck stock, prices, products, variants, and this cashier cart before saving.') }}" data-confirm-ok="{{ __('Complete sale') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="posPaymentMethod" class="form-label fw-semibold">{{ __('Payment method') }}</label>
                            <select id="posPaymentMethod" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                <option value="{{ AppModelsOrder::PAYMENT_METHOD_POS_CASH }}" @selected(old('payment_method', AppModelsOrder::PAYMENT_METHOD_POS_CASH) === AppModelsOrder::PAYMENT_METHOD_POS_CASH)>{{ __('Cash') }}</option>
                                <option value="{{ AppModelsOrder::PAYMENT_METHOD_POS_CARD }}" @selected(old('payment_method') === AppModelsOrder::PAYMENT_METHOD_POS_CARD)>{{ __('Card terminal') }}</option>
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
                            <label for="posCustomerName" class="form-label fw-semibold">{{ __('Customer name') }} <span class="text-muted fw-normal">({{ __('optional') }})</span></label>
                            <input id="posCustomerName" name="customer_name" type="text" maxlength="255" value="{{ old('customer_name') }}" class="form-control" placeholder="{{ __('Walk-in customer') }}">
                        </div>

                        <div class="mb-4">
                            <label for="posNotes" class="form-label fw-semibold">{{ __('Sale notes') }} <span class="text-muted fw-normal">({{ __('optional') }})</span></label>
                            <textarea id="posNotes" name="notes" rows="3" maxlength="1000" class="form-control">{{ old('notes') }}</textarea>
                        </div>

                        <button class="btn btn-primary w-100 btn-lg btn-text-icon" @disabled($cart->items->isEmpty()) data-loading-text="{{ __('Completing sale...') }}">
                            <i class="mdi mdi-cash-register"></i><span>{{ __('Complete sale') }}</span>
                        </button>
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

    const syncPaymentFields = function () {
        const isCash = paymentMethod && paymentMethod.value === @json(AppModelsOrder::PAYMENT_METHOD_POS_CASH);

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
