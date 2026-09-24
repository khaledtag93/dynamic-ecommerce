@extends('layouts.admin')

@section('title', __('Cost Calculator') . ' | Admin')

@section('content')
@php
    $currencyLabel = config('app.currency', 'EGP');
@endphp

<style>
    .cost-calculator-page .cost-workflow-card {
        border: 1px solid color-mix(in srgb, var(--admin-primary) 22%, var(--admin-border));
        background: linear-gradient(135deg, color-mix(in srgb, var(--admin-primary-soft) 64%, var(--admin-surface)), color-mix(in srgb, var(--admin-surface) 96%, var(--admin-bg)));
        border-radius: 18px;
        padding: 16px;
    }
    .cost-calculator-page .cost-step {
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .cost-calculator-page .cost-step-number {
        width: 30px;
        height: 30px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--admin-primary), var(--admin-accent));
        color: #fff;
        box-shadow: 0 8px 18px color-mix(in srgb, var(--admin-primary) 22%, transparent);
        font-weight: 800;
        flex: 0 0 30px;
    }
    .cost-calculator-page .cost-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }
    .cost-calculator-page .cost-section-title h5,
    .cost-calculator-page .cost-section-title h6 { margin-bottom: 0; }
    .cost-calculator-page .cost-summary-card {
        border: 1px solid color-mix(in srgb, var(--admin-primary) 16%, var(--admin-border));
        background: color-mix(in srgb, var(--admin-primary-soft) 24%, var(--admin-surface));
    }
    .cost-calculator-page .cost-section-nav {
        display: flex;
        gap: .55rem;
        flex-wrap: wrap;
    }
    .cost-calculator-page .cost-section-nav a {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .58rem .82rem;
        border-radius: 999px;
        border: 1px solid var(--admin-border);
        background: var(--admin-surface);
        color: var(--admin-text);
        text-decoration: none;
        font-size: .82rem;
        font-weight: 800;
    }
    .cost-calculator-page .cost-section-nav a:hover {
        color: var(--admin-primary-dark);
        border-color: color-mix(in srgb, var(--admin-primary) 30%, var(--admin-border));
        background: var(--admin-primary-soft);
    }
    .cost-calculator-page [id^="cost-"] { scroll-margin-top: 1.5rem; }
    .cost-calculator-page .cost-table th,
    .cost-calculator-page .cost-table td {
        vertical-align: middle;
        white-space: nowrap;
    }
    .cost-calculator-page .material-total {
        min-width: 90px;
        display: inline-block;
    }
    html[dir="rtl"] .cost-calculator-page .form-select {
        background-position: left .75rem center;
        padding-left: 2.25rem;
        padding-right: .75rem;
    }
</style>

<x-admin.page-header
    :kicker="__('Inventory & sourcing')"
    :title="__('Cost Calculator')"
    :description="__('Build reusable raw materials, save a product recipe, and calculate cost, profit, and margin.')"
/>

<div class="admin-page-shell cost-calculator-page">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>{{ __('Please review the highlighted fields.') }}</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-flask-outline"></i></span>
                <div class="admin-stat-label">{{ __('Raw Materials') }}</div>
                <div class="admin-stat-value">{{ number_format($totals['materials_count']) }}</div>
                <div class="text-muted small mt-2">{{ __('Reusable material price list.') }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-clipboard-list-outline"></i></span>
                <div class="admin-stat-label">{{ __('Saved Recipes') }}</div>
                <div class="admin-stat-value">{{ number_format($totals['products_with_cost']) }}</div>
                <div class="text-muted small mt-2">{{ __('Products with calculated recipes.') }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-cash-multiple"></i></span>
                <div class="admin-stat-label">{{ __('Total Production Cost') }}</div>
                <div class="admin-stat-value">{{ number_format($totals['total_cost_value'], 2) }}</div>
                <div class="text-muted small mt-2">{{ __('Total saved product costs.') }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="admin-card admin-stat-card h-100">
                <span class="admin-stat-icon"><i class="mdi mdi-trending-up"></i></span>
                <div class="admin-stat-label">{{ __('Expected Profit') }}</div>
                <div class="admin-stat-value">{{ number_format($totals['total_profit_value'], 2) }}</div>
                <div class="text-muted small mt-2">{{ __('Total expected profit from saved recipes.') }}</div>
            </div>
        </div>
    </div>

    <nav class="cost-section-nav" aria-label="{{ __('Cost calculator sections') }}">
        <a href="#cost-materials"><i class="mdi mdi-flask-outline"></i>{{ __('Raw Materials') }}</a>
        <a href="#cost-recipe"><i class="mdi mdi-clipboard-list-outline"></i>{{ __('Product Recipe') }}</a>
        @if($selectedProduct)
            <a href="#cost-results"><i class="mdi mdi-chart-line"></i>{{ __('Profit Calculation') }}</a>
        @endif
    </nav>

    <div class="cost-workflow-card mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="cost-step">
                    <span class="cost-step-number">1</span>
                    <div>
                        <strong>{{ __('Raw Materials') }}</strong>
                        <div class="text-muted small">{{ __('Add each material once with its unit and unit price.') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="cost-step">
                    <span class="cost-step-number">2</span>
                    <div>
                        <strong>{{ __('Product Recipe') }}</strong>
                        <div class="text-muted small">{{ __('Choose a product and add the quantities used from each material.') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="cost-step">
                    <span class="cost-step-number">3</span>
                    <div>
                        <strong>{{ __('Profit Calculation') }}</strong>
                        <div class="text-muted small">{{ __('The page calculates total cost, profit, and margin automatically.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4" id="cost-materials">
            <div class="admin-card h-100">
                <div class="cost-section-title">
                    <div>
                        <h5>{{ __('Raw Materials') }}</h5>
                        <p class="text-muted mb-0">{{ __('This is your reusable material price list.') }}</p>
                    </div>
                    <span class="badge bg-light text-dark">{{ $materials->count() }}</span>
                </div>

                <form method="POST" action="{{ route('admin.cost-calculator.materials.store') }}" class="mb-4" data-submit-loading>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('Material name') }}</label>
                        <input type="text" name="name" class="form-control" placeholder="{{ __('Example: MDF wood') }}" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Code') }}</label>
                            <input type="text" name="code" class="form-control" placeholder="{{ __('Optional') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Unit') }}</label>
                            <input type="text" name="unit" class="form-control" placeholder="{{ __('Meter') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Unit price') }}</label>
                            <input type="number" step="0.01" min="0" name="unit_price" class="form-control" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">{{ __('Notes') }}</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="{{ __('Optional') }}"></textarea>
                    </div>
                    <button class="btn btn-primary w-100 mt-3" type="submit">
                        <i class="mdi mdi-plus"></i> {{ __('Add material') }}
                    </button>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle cost-table">
                        <thead>
                            <tr>
                                <th>{{ __('Material') }}</th>
                                <th>{{ __('Unit') }}</th>
                                <th class="text-end">{{ __('Unit price') }}</th>
                                <th class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($materials as $material)
                                <tr>
                                    <td>
                                        <strong>{{ $material->name }}</strong>
                                        @if($material->code)
                                            <div class="small text-muted">{{ $material->code }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $material->unit }}</td>
                                    <td class="text-end">{{ number_format($material->unit_price, 2) }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.cost-calculator.materials.destroy', $material) }}" data-submit-loading data-confirm-title="{{ __('Delete material') }}" data-confirm-message="{{ __('Delete this material?') }}" data-confirm-subtitle="{{ __('This removes the reusable material record. Saved recipe history should be reviewed before continuing.') }}" data-confirm-ok="{{ __('Delete') }}" data-confirm-cancel="{{ __('Cancel') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ __('Delete') }}">
                                                <i class="mdi mdi-delete-outline"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">{{ __('No raw materials yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-8" id="cost-recipe">
            <div class="admin-card">
                <div class="cost-section-title">
                    <div>
                        <h5>{{ __('Product Recipe & Cost') }}</h5>
                        <p class="text-muted mb-0">{{ __('Select a product, define its recipe, add expenses, then save the final cost.') }}</p>
                    </div>
                    @if($summary)
                        <span class="badge bg-success">{{ __('Last saved') }}: {{ $summary->updated_at?->diffForHumans() }}</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('admin.cost-calculator.index') }}" class="row g-2 mb-4">
                    <div class="col-md-9">
                        <label class="form-label">{{ __('Product') }}</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">{{ __('Select product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected(optional($selectedProduct)->id === $product->id)>
                                    {{ $product->name }} — {{ __('Selling price') }}: {{ number_format($product->sale_price ?: $product->base_price, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-outline-primary w-100" type="submit">{{ __('Open recipe') }}</button>
                    </div>
                </form>

                @if($selectedProduct)
                    <form method="POST" action="{{ route('admin.cost-calculator.save') }}" id="costCalculatorForm" data-submit-loading>
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $selectedProduct->id }}">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Selected product') }}</label>
                                <input type="text" class="form-control" value="{{ $selectedProduct->name }}" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Selling price') }}</label>
                                <input type="number" step="0.01" min="0" name="selling_price" id="sellingPrice" class="form-control" value="{{ old('selling_price', $summary->selling_price ?? ($selectedProduct->sale_price ?: $selectedProduct->base_price)) }}" required>
                            </div>
                        </div>

                        <div class="cost-section-title">
                            <div>
                                <h6>{{ __('Used Materials') }}</h6>
                                <div class="text-muted small">{{ __('Choose a material to fill unit and price automatically, then enter the used quantity.') }}</div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" type="button" id="addMaterialRow">
                                <i class="mdi mdi-plus"></i> {{ __('Add material row') }}
                            </button>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table align-middle cost-table" id="materialsTable">
                                <thead>
                                    <tr>
                                        <th style="min-width: 260px">{{ __('Material') }}</th>
                                        <th style="min-width: 130px">{{ __('Quantity') }}</th>
                                        <th style="min-width: 120px">{{ __('Unit') }}</th>
                                        <th style="min-width: 140px">{{ __('Unit price') }}</th>
                                        <th class="text-end" style="min-width: 110px">{{ __('Line total') }}</th>
                                        <th class="text-end" style="width: 60px">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="cost-section-title">
                            <div>
                                <h6>{{ __('Extra Expenses') }}</h6>
                                <div class="text-muted small">{{ __('Add labor, packaging, electricity, shipping, or any other fixed expense.') }}</div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" type="button" id="addExtraRow">
                                <i class="mdi mdi-plus"></i> {{ __('Add expense row') }}
                            </button>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table align-middle cost-table" id="extrasTable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Expense name') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th class="text-end">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="row g-3 mb-4" id="cost-results">
                            <div class="col-md-6 col-xl-3">
                                <div class="admin-card admin-stat-card cost-summary-card h-100">
                                    <div class="text-muted small">{{ __('Materials cost') }}</div>
                                    <h4 class="mb-0"><span id="materialsCostLabel">0.00</span></h4>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="admin-card admin-stat-card cost-summary-card h-100">
                                    <div class="text-muted small">{{ __('Extra cost') }}</div>
                                    <h4 class="mb-0"><span id="extraCostLabel">0.00</span></h4>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="admin-card admin-stat-card cost-summary-card h-100">
                                    <div class="text-muted small">{{ __('Total cost') }}</div>
                                    <h4 class="mb-0"><span id="totalCostLabel">0.00</span></h4>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="admin-card admin-stat-card cost-summary-card h-100">
                                    <div class="text-muted small">{{ __('Profit / Margin') }}</div>
                                    <h4 class="mb-0"><span id="profitLabel">0.00</span></h4>
                                    <div class="small text-muted"><span id="marginLabel">0.00</span>%</div>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit">
                            <i class="mdi mdi-content-save-outline"></i> {{ __('Save product cost') }}
                        </button>
                    </form>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="mdi mdi-calculator-variant-outline display-4 d-block mb-2"></i>
                        {{ __('Select a product to start calculating its material cost and profit.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($selectedProduct)
@php
    $materialsJson = $materials->map(function ($m) {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'unit' => $m->unit,
            'unit_price' => (float) $m->unit_price,
        ];
    })->values();

    $savedMaterialsJson = $materialItems->map(function ($item) {
        return [
            'raw_material_id' => $item->raw_material_id,
            'material_name' => $item->material_name,
            'unit' => $item->unit,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
        ];
    })->values();

    $savedExtrasJson = $extraItems->map(function ($item) {
        return [
            'name' => $item->name,
            'amount' => (float) $item->amount,
        ];
    })->values();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const materials = @json($materialsJson);
    const savedMaterials = @json($savedMaterialsJson);
    const savedExtras = @json($savedExtrasJson);
    const labels = {
        chooseMaterial: @json(__('Choose raw material')),
        manualMaterialName: @json(__('Manual material name')),
        labor: @json(__('Labor')),
        packaging: @json(__('Packaging')),
        expensePlaceholder: @json(__('Labor, packaging, electricity...')),
    };

    const materialBody = document.querySelector('#materialsTable tbody');
    const extraBody = document.querySelector('#extrasTable tbody');
    const sellingPrice = document.getElementById('sellingPrice');

    let materialIndex = 0;
    let extraIndex = 0;

    function money(value) {
        return Number(value || 0).toFixed(2);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function materialOptions(selectedId) {
        let options = `<option value="">${escapeHtml(labels.chooseMaterial)}</option>`;

        materials.forEach(function (material) {
            const selected = String(material.id) === String(selectedId) ? 'selected' : '';
            options += `<option value="${escapeHtml(material.id)}" data-name="${escapeHtml(material.name)}" data-unit="${escapeHtml(material.unit)}" data-price="${escapeHtml(material.unit_price)}" ${selected}>${escapeHtml(material.name)} - ${escapeHtml(material.unit)}</option>`;
        });

        return options;
    }

    function addMaterialRow(row = {}) {
        const index = materialIndex++;
        const tr = document.createElement('tr');

        tr.innerHTML = `
            <td>
                <select name="materials[${index}][raw_material_id]" class="form-select material-select mb-2">
                    ${materialOptions(row.raw_material_id || '')}
                </select>
                <input type="text" name="materials[${index}][material_name]" class="form-control material-name" value="${escapeHtml(row.material_name || '')}" placeholder="${escapeHtml(labels.manualMaterialName)}">
            </td>
            <td><input type="number" step="0.001" min="0" name="materials[${index}][quantity]" class="form-control material-qty" value="${escapeHtml(row.quantity || '')}"></td>
            <td><input type="text" name="materials[${index}][unit]" class="form-control material-unit" value="${escapeHtml(row.unit || '')}"></td>
            <td><input type="number" step="0.01" min="0" name="materials[${index}][unit_price]" class="form-control material-price" value="${escapeHtml(row.unit_price || '')}"></td>
            <td class="text-end"><strong class="material-total">0.00</strong></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="mdi mdi-close"></i></button></td>
        `;

        materialBody.appendChild(tr);
        calculate();
    }

    function addExtraRow(row = {}) {
        const index = extraIndex++;
        const tr = document.createElement('tr');

        tr.innerHTML = `
            <td><input type="text" name="extras[${index}][name]" class="form-control" value="${escapeHtml(row.name || '')}" placeholder="${escapeHtml(labels.expensePlaceholder)}"></td>
            <td><input type="number" step="0.01" min="0" name="extras[${index}][amount]" class="form-control extra-amount" value="${escapeHtml(row.amount || '')}"></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="mdi mdi-close"></i></button></td>
        `;

        extraBody.appendChild(tr);
        calculate();
    }

    function calculate() {
        let materialsCost = 0;

        materialBody.querySelectorAll('tr').forEach(function (tr) {
            const qty = parseFloat(tr.querySelector('.material-qty')?.value || 0);
            const price = parseFloat(tr.querySelector('.material-price')?.value || 0);
            const total = qty * price;
            materialsCost += total;
            tr.querySelector('.material-total').textContent = money(total);
        });

        let extraCost = 0;
        extraBody.querySelectorAll('.extra-amount').forEach(function (input) {
            extraCost += parseFloat(input.value || 0);
        });

        const totalCost = materialsCost + extraCost;
        const sale = parseFloat(sellingPrice.value || 0);
        const profit = sale - totalCost;
        const margin = sale > 0 ? (profit / sale) * 100 : 0;

        document.getElementById('materialsCostLabel').textContent = money(materialsCost);
        document.getElementById('extraCostLabel').textContent = money(extraCost);
        document.getElementById('totalCostLabel').textContent = money(totalCost);
        document.getElementById('profitLabel').textContent = money(profit);
        document.getElementById('marginLabel').textContent = Number(margin || 0).toFixed(2);
    }

    document.getElementById('addMaterialRow').addEventListener('click', function () {
        addMaterialRow();
    });

    document.getElementById('addExtraRow').addEventListener('click', function () {
        addExtraRow();
    });

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('material-select')) {
            const option = event.target.selectedOptions[0];
            const tr = event.target.closest('tr');

            if (option && option.value) {
                tr.querySelector('.material-name').value = option.dataset.name || '';
                tr.querySelector('.material-unit').value = option.dataset.unit || '';
                tr.querySelector('.material-price').value = option.dataset.price || '';
            }

            calculate();
        }
    });

    document.addEventListener('input', function (event) {
        if (
            event.target.classList.contains('material-qty') ||
            event.target.classList.contains('material-price') ||
            event.target.classList.contains('extra-amount') ||
            event.target.id === 'sellingPrice'
        ) {
            calculate();
        }
    });

    document.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.remove-row');

        if (removeButton) {
            removeButton.closest('tr').remove();
            calculate();
        }
    });

    if (savedMaterials.length) {
        savedMaterials.forEach(addMaterialRow);
    } else {
        addMaterialRow();
    }

    if (savedExtras.length) {
        savedExtras.forEach(addExtraRow);
    } else {
        addExtraRow({name: labels.labor});
        addExtraRow({name: labels.packaging});
    }

    calculate();
});
</script>
@endif
@endsection
