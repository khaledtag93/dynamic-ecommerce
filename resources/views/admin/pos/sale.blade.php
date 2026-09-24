@extends('layouts.admin')

@section('title', __('POS Sale') . ' ' . $order->order_number . ' | Admin')

@section('content')
<x-admin.page-header :kicker="__('Point of Sale')" :title="__('Sale complete')" :description="$order->order_number">
    <a href="{{ route('admin.pos.sales.show', $order) }}?receipt=1&paper=80" target="_blank" rel="noopener" class="btn btn-light border btn-text-icon"><i class="mdi mdi-printer-outline"></i><span>{{ __('Print receipt') }}</span></a>
    <a href="{{ route('admin.pos.index') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-cash-register"></i><span>{{ __('Start new sale') }}</span></a>
    @if(auth()->user()->hasPermission('orders.view'))
        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-open-in-new"></i><span>{{ __('Open order record') }}</span></a>
    @endif
</x-admin.page-header>

<div class="admin-page-shell">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="admin-card admin-stat-card h-100">
                <div class="admin-stat-label">{{ __('Sale total') }}</div>
                <div class="admin-stat-value">EGP {{ number_format((float) $order->grand_total, 2) }}</div>
                <div class="text-muted small mt-2">{{ $order->customer_name }}</div>
                @if($order->user_id && $order->customer_email)
                    <div class="small mt-1">
                        <span class="badge badge-soft-success">{{ __('Customer account') }}</span>
                        <span class="text-muted ms-1">{{ $order->customer_email }}</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card admin-stat-card h-100">
                <div class="admin-stat-label">{{ __('Payment') }}</div>
                <div class="admin-stat-value fs-5">{{ $order->payment_method_label }}</div>
                <div class="text-muted small mt-2">{{ __('Paid at counter') }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card admin-stat-card h-100">
                <div class="admin-stat-label">{{ __('Change due') }}</div>
                <div class="admin-stat-value">EGP {{ number_format((float) data_get($order->meta, 'pos.change_due', 0), 2) }}</div>
                <div class="text-muted small mt-2">{{ __('Cash change recorded for this sale.') }}</div>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div>
                    <h4 class="mb-1">{{ __('Sold items') }}</h4>
                    <div class="text-muted small">{{ optional($order->placed_at)->format('M d, Y H:i') }} · {{ __('Cashier') }} #{{ data_get($order->meta, 'cashier_user_id') }}</div>
                </div>
                <span class="badge admin-status-badge badge-soft-success">{{ __('Paid') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('SKU') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Unit price') }}</th>
                            <th>{{ __('Discount') }}</th>
                            <th>{{ __('Line total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->product_name }}</div>
                                    @if($item->variant_name)<div class="text-muted small">{{ $item->variant_name }}</div>@endif
                                </td>
                                <td>{{ $item->sku ?: '—' }}</td>
                                <td>
                                    {{ $item->quantity }}
                                    @php($returnedQty = (int) ($returnedQuantities[$item->id] ?? 0))
                                    @if($returnedQty > 0)
                                        <div class="small text-muted">{{ __('Returned') }}: {{ $returnedQty }}</div>
                                    @endif
                                </td>
                                <td>EGP {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>
                                    @php($itemDiscount = (float) data_get($item->meta, 'pos.discount.total_amount', 0))
                                    {{ $itemDiscount > 0 ? '-EGP ' . number_format($itemDiscount, 2) : '—' }}
                                </td>
                                <td class="fw-bold">EGP {{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-end">{{ __('Subtotal') }}</th>
                            <th>EGP {{ number_format((float) $order->subtotal, 2) }}</th>
                        </tr>
                        @if((float) $order->discount_total > 0)
                            <tr>
                                <th colspan="5" class="text-end">{{ __('Discount') }}</th>
                                <th>-EGP {{ number_format((float) $order->discount_total, 2) }}</th>
                            </tr>
                        @endif
                        <tr>
                            <th colspan="5" class="text-end">{{ __('Total') }}</th>
                            <th>EGP {{ number_format((float) $order->grand_total, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if((float) data_get($order->meta, 'pos.discounts.order_discount.amount', 0) > 0)
                <div class="alert alert-light border mt-3 mb-0">
                    <div class="fw-semibold">{{ __('Sale discount') }}</div>
                    <div class="small text-muted">{{ data_get($order->meta, 'pos.discounts.order_discount.reason') }}</div>
                </div>
            @endif
        </div>
    </div>

    @if($canReturn)
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                        <h4 class="mb-1">{{ __('Return items') }}</h4>
                        <div class="text-muted small">{{ __('Select only the quantities physically returned. Inventory and refund records are updated together.') }}</div>
                    </div>
                    <span class="badge badge-soft-warning">{{ __('Controlled action') }}</span>
                </div>

                <form method="POST" action="{{ route('admin.pos.sales.return', $order) }}" data-submit-loading data-confirm-title="{{ __('Confirm POS return?') }}" data-confirm-message="{{ __('Returned quantities will be restocked and a linked refund will be recorded. This action cannot be casually undone.') }}" data-confirm-ok="{{ __('Process return') }}">
                    @csrf
                    <div class="table-responsive mb-3">
                        <table class="table admin-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('Sold') }}</th>
                                    <th>{{ __('Already returned') }}</th>
                                    <th style="width: 170px">{{ __('Return quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                    @php
                                        $returnedQty = (int) ($returnedQuantities[$item->id] ?? 0);
                                        $remainingQty = max(0, (int) $item->quantity - $returnedQty);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->product_name }}</div>
                                            @if($item->variant_name)<div class="text-muted small">{{ $item->variant_name }}</div>@endif
                                        </td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ $returnedQty }}</td>
                                        <td>
                                            @if($remainingQty > 0)
                                                <input type="number" name="items[{{ $item->id }}]" min="0" max="{{ $remainingQty }}" value="{{ old('items.'.$item->id, 0) }}" class="form-control form-control-sm" aria-label="{{ __('Return quantity') }}">
                                                <div class="text-muted small mt-1">{{ __('Maximum') }}: {{ $remainingQty }}</div>
                                            @else
                                                <span class="badge badge-soft-success">{{ __('Fully returned') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Return reason') }}</label>
                            <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255" required class="form-control @error('reason') is-invalid @enderror" placeholder="{{ __('e.g. Customer return, damaged item, wrong item') }}">
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <input type="text" name="notes" value="{{ old('notes') }}" maxlength="1000" class="form-control" placeholder="{{ __('Optional return notes') }}">
                        </div>
                    </div>
                    @error('items')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('return')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

                    <div class="d-flex justify-content-end mt-3">
                        <button class="btn btn-warning btn-text-icon" data-loading-text="{{ __('Processing return...') }}"><i class="mdi mdi-keyboard-return"></i><span>{{ __('Process return') }}</span></button>
                    </div>
                </form>
            </div>
        </div>
    @elseif((float) $order->refund_total > 0)
        <div class="alert alert-light border">
            <div class="fw-semibold">{{ __('Return status') }}</div>
            <div class="small text-muted">{{ __('Refunded so far') }}: EGP {{ number_format((float) $order->refund_total, 2) }}</div>
        </div>
    @endif

    <div class="alert alert-light border mb-0">
        <div class="fw-semibold mb-1">{{ __('Sale recorded safely') }}</div>
        <div class="small text-muted">{{ __('The order, paid payment record, order items, inventory deductions, profit snapshot, and cashier audit entry were written in one database transaction.') }}</div>
    </div>
</div>
@endsection
