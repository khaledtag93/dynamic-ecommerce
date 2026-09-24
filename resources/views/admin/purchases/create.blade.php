@extends('layouts.admin')

@section('title', __('New Purchase') . ' | Admin')

@section('content')
    <x-admin.page-header :kicker="__('Procurement')" :title="__('New Purchase')" :description="__('Create a purchase order and receive stock later without breaking inventory flow.')">
        <a href="{{ route('admin.purchases.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to purchases') }}</span></a>
    </x-admin.page-header>

    <div class="admin-page-shell">

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <div class="fw-bold mb-2">{{ __('Please review the purchase form details:') }}</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="admin-card">
        <div class="admin-card-body border-bottom">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div><span class="badge badge-soft-info mb-2">{{ __('Purchase workflow') }}</span><h4 class="mb-1">{{ __('Build purchase order') }}</h4><p class="text-muted small mb-0">{{ __('Choose a supplier, add stock lines, review the live total, then save the order for later receiving.') }}</p></div>
                <div class="d-flex gap-2 flex-wrap"><span class="admin-chip"><i class="mdi mdi-truck-outline"></i> {{ __('Supplier') }}</span><span class="admin-chip"><i class="mdi mdi-package-variant"></i> {{ __('Items') }}</span><span class="admin-chip"><i class="mdi mdi-calculator-variant-outline"></i> {{ __('Totals') }}</span></div>
            </div>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.purchases.store') }}" id="purchaseForm" data-submit-loading>
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Supplier') }}</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">{{ __('Select supplier') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">{{ __('Purchase date') }}</label>
                        <input
                            type="date"
                            name="purchase_date"
                            class="form-control"
                            value="{{ old('purchase_date', now()->toDateString()) }}"
                        >
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">{{ __('Shipping') }}</label>
                        <input
                            type="number"
                            step="0.01"
                            name="shipping_total"
                            class="form-control"
                            value="{{ old('shipping_total', 0) }}"
                        >
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">{{ __('Tax') }}</label>
                        <input
                            type="number"
                            step="0.01"
                            name="tax_total"
                            class="form-control"
                            value="{{ old('tax_total', 0) }}"
                        >
                    </div>
                </div>

                <div class="admin-section-card mb-4">
                    <div class="admin-section-heading">
                        <div>
                            <h4 class="admin-section-title">{{ __('Purchase items') }}</h4>
                            <div class="admin-section-subtitle">{{ __('Add products, variants, quantities, and cost lines in a cleaner responsive table.') }}</div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm btn-text-icon" id="addPurchaseItem">
                            <i class="mdi mdi-plus"></i>
                            <span>{{ __('Add item') }}</span>
                        </button>
                    </div>

                    <div class="table-responsive">
                    <table class="table admin-table align-middle" id="purchaseItemsTable">
                        <thead>
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Variant') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Unit cost') }}</th>
                                <th>{{ __('Expiration date') }}</th>
                                <th class="text-end">{{ __('Remove') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    </div>
                </div>

                <div class="admin-section-card">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3"><h4 class="admin-section-title mb-0">{{ __('Notes & totals') }}</h4><div class="text-end"><div class="text-muted small">{{ __('Estimated total') }}</div><div class="fw-bold fs-4" id="purchaseGrandTotal">EGP 0.00</div></div></div>
                    <div class="mt-0">
                    <label class="form-label">{{ __('Notes') }}</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>

                    <div class="admin-actions-stack mt-3">
                        <button class="btn btn-primary btn-text-icon" data-loading-text="{{ __('Creating...') }}">
                            <i class="mdi mdi-content-save-outline"></i>
                            <span>{{ __('Create purchase order') }}</span>
                        </button>
                        <a href="{{ route('admin.purchases.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-close"></i><span>{{ __('Cancel') }}</span></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @php
        $purchaseProducts = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'has_variants' => $p->has_variants,
                'variants' => $p->variants->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'name' => $v->sku ?: ($v->name ?? ('#' . $v->id)),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        $purchaseOldItems = old('items', [
            [
                'product_id' => '',
                'product_variant_id' => '',
                'quantity' => 1,
                'unit_cost' => '',
                'expiration_date' => '',
            ],
        ]);
    @endphp

    </div>

    <script>
        window.purchaseProducts = @json($purchaseProducts);
        window.purchaseOldItems = @json($purchaseOldItems);
    </script>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tableBody = document.querySelector('#purchaseItemsTable tbody');
            const addBtn = document.getElementById('addPurchaseItem');
            const products = window.purchaseProducts || [];
            let index = 0;

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, character => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                })[character]);
            }

            function updateVariantRequirement(row) {
                const productId = row.querySelector('.js-product').value;
                const product = products.find(p => String(p.id) === productId);
                row.querySelector('.js-variant').required = Boolean(product?.has_variants);
            }

            function variantOptions(productId, selected = '') {
                const product = products.find(p => String(p.id) === String(productId));
                const variants = product && Array.isArray(product.variants) ? product.variants : [];
                let html = `<option value="">${product?.has_variants ? @json(__('Select variant')) : @json(__('No variant / main product'))}</option>`;

                variants.forEach(v => {
                    html += `<option value="${escapeHtml(v.id)}" ${String(selected) === String(v.id) ? 'selected' : ''}>${escapeHtml(v.name)}</option>`;
                });

                return html;
            }

            function productOptions(selected = '') {
                let html = `<option value="">{{ __('Select product') }}</option>`;

                products.forEach(p => {
                    html += `<option value="${escapeHtml(p.id)}" ${String(selected) === String(p.id) ? 'selected' : ''}>${escapeHtml(p.name)}</option>`;
                });

                return html;
            }

            function recalculateTotal() {
                let subtotal = 0;
                tableBody.querySelectorAll('tr').forEach(row => {
                    const quantity = parseFloat(row.querySelector('[name*="[quantity]"]')?.value || 0);
                    const unitCost = parseFloat(row.querySelector('[name*="[unit_cost]"]')?.value || 0);
                    subtotal += quantity * unitCost;
                });
                const shipping = parseFloat(document.querySelector('[name="shipping_total"]')?.value || 0);
                const tax = parseFloat(document.querySelector('[name="tax_total"]')?.value || 0);
                const total = subtotal + shipping + tax;
                const output = document.getElementById('purchaseGrandTotal');
                if (output) output.textContent = 'EGP ' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            function addRow(item = {}) {
                const i = index++;
                const row = document.createElement('tr');

                row.innerHTML = `
                    <td>
                        <select name="items[${i}][product_id]" class="form-select js-product" required>
                            ${productOptions(item.product_id || '')}
                        </select>
                    </td>
                    <td>
                        <select name="items[${i}][product_variant_id]" class="form-select js-variant">
                            ${variantOptions(item.product_id || '', item.product_variant_id || '')}
                        </select>
                    </td>
                    <td>
                        <input
                            type="number"
                            min="1"
                            name="items[${i}][quantity]"
                            class="form-control"
                            value="${escapeHtml(item.quantity ?? 1)}"
                            required
                        >
                    </td>
                    <td>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="items[${i}][unit_cost]"
                            class="form-control"
                            value="${escapeHtml(item.unit_cost ?? '')}"
                            required
                        >
                    </td>
                    <td>
                        <input
                            type="date"
                            name="items[${i}][expiration_date]"
                            class="form-control"
                            value="${escapeHtml(item.expiration_date ?? '')}"
                        >
                    </td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn-table-icon btn-delete js-remove"
                            title="{{ __('Remove item') }}"
                        >
                            <i class="mdi mdi-trash-can-outline"></i>
                        </button>
                    </td>
                `;

                tableBody.appendChild(row);
                updateVariantRequirement(row);
                recalculateTotal();
            }

            tableBody.addEventListener('input', recalculateTotal);
            document.querySelectorAll('[name="shipping_total"], [name="tax_total"]').forEach(input => input.addEventListener('input', recalculateTotal));

            tableBody.addEventListener('change', function (e) {
                if (e.target.classList.contains('js-product')) {
                    const row = e.target.closest('tr');
                    const variantSelect = row.querySelector('.js-variant');
                    variantSelect.innerHTML = variantOptions(e.target.value);
                    updateVariantRequirement(row);
                }
            });

            tableBody.addEventListener('click', function (e) {
                const btn = e.target.closest('.js-remove');
                if (!btn) return;

                if (tableBody.querySelectorAll('tr').length === 1) {
                    return;
                }

                btn.closest('tr').remove();
                recalculateTotal();
            });

            addBtn?.addEventListener('click', function () {
                addRow();
            });

            (window.purchaseOldItems || []).forEach(function (item) {
                addRow(item);
            });

            if (!tableBody.querySelector('tr')) {
                addRow();
            }
        });
    </script>
@endpush
