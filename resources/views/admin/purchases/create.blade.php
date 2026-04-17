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
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.purchases.store') }}" id="purchaseForm">
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
                    <h4 class="admin-section-title mb-3">{{ __('Notes & totals') }}</h4>
                    <div class="mt-0">
                    <label class="form-label">{{ __('Notes') }}</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>

                    <div class="admin-actions-stack mt-3">
                        <button class="btn btn-primary btn-text-icon">
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

            function variantOptions(productId, selected = '') {
                const product = products.find(p => String(p.id) === String(productId));
                const variants = product && Array.isArray(product.variants) ? product.variants : [];
                let html = `<option value="">{{ __('No variant / main product') }}</option>`;

                variants.forEach(v => {
                    html += `<option value="${v.id}" ${String(selected) === String(v.id) ? 'selected' : ''}>${v.name}</option>`;
                });

                return html;
            }

            function productOptions(selected = '') {
                let html = `<option value="">{{ __('Select product') }}</option>`;

                products.forEach(p => {
                    html += `<option value="${p.id}" ${String(selected) === String(p.id) ? 'selected' : ''}>${p.name}</option>`;
                });

                return html;
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
                            value="${item.quantity || 1}"
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
                            value="${item.unit_cost || ''}"
                            required
                        >
                    </td>
                    <td>
                        <input
                            type="date"
                            name="items[${i}][expiration_date]"
                            class="form-control"
                            value="${item.expiration_date || ''}"
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
            }

            tableBody.addEventListener('change', function (e) {
                if (e.target.classList.contains('js-product')) {
                    const row = e.target.closest('tr');
                    const variantSelect = row.querySelector('.js-variant');
                    variantSelect.innerHTML = variantOptions(e.target.value);
                }
            });

            tableBody.addEventListener('click', function (e) {
                const btn = e.target.closest('.js-remove');
                if (!btn) return;

                if (tableBody.querySelectorAll('tr').length === 1) {
                    return;
                }

                btn.closest('tr').remove();
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
