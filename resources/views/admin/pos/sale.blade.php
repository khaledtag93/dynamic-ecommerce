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
                                <td>{{ $item->quantity }}</td>
                                <td>EGP {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="fw-bold">EGP {{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">{{ __('Total') }}</th>
                            <th>EGP {{ number_format((float) $order->grand_total, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-light border mb-0">
        <div class="fw-semibold mb-1">{{ __('Sale recorded safely') }}</div>
        <div class="small text-muted">{{ __('The order, paid payment record, order items, inventory deductions, profit snapshot, and cashier audit entry were written in one database transaction.') }}</div>
    </div>
</div>
@endsection
