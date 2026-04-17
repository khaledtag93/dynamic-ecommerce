@extends('layouts.admin')

@section('title', $order->order_number . ' | Admin')

@section('content')
@php
    $steps = [
        ['title' => __('Pending'), 'copy' => __('Order created and waiting for review.')],
        ['title' => __('Processing'), 'copy' => __('Items are being prepared and checked.')],
        ['title' => __('Completed'), 'copy' => __('Order has been completed successfully.')],
    ];
    $activeStatusOrder = [
        \App\Models\Order::STATUS_PENDING => 1,
        \App\Models\Order::STATUS_PROCESSING => 2,
        \App\Models\Order::STATUS_COMPLETED => 3,
        \App\Models\Order::STATUS_CANCELLED => 1,
    ][$order->status] ?? 1;
@endphp

<x-admin.page-header :kicker="__('Order details')" :title="$order->order_number" :description="__('Placed :date', ['date' => optional($order->placed_at)->format('d M Y, h:i A') ?: $order->created_at->format('d M Y, h:i A')])">
    <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to orders') }}</span></a>
</x-admin.page-header>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="admin-card"><div class="admin-card-body"><div class="admin-inline-label">{{ __('Order status') }}</div><span class="badge admin-status-badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span></div></div>
    </div>
    <div class="col-md-4">
        <div class="admin-card"><div class="admin-card-body"><div class="admin-inline-label">{{ __('Payment status') }}</div><span class="badge admin-status-badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span></div></div>
    </div>
    <div class="col-md-3">
        <div class="admin-card"><div class="admin-card-body"><div class="admin-inline-label">{{ __('Payment method') }}</div><div class="fw-bold fs-4">{{ $order->payment_method_label }}</div></div></div>
    </div>
    <div class="col-md-3">
        <div class="admin-card"><div class="admin-card-body"><div class="admin-inline-label">{{ __('Delivery status') }}</div><span class="badge admin-status-badge {{ $order->delivery_status_badge_class }}">{{ $order->delivery_status_label }}</span></div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                    <div>
                        <h4 class="mb-1">{{ __('Items') }}</h4>
                        <div class="text-muted small">{{ $order->items->count() }} item(s) included in this order.</div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge admin-status-badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                        <span class="badge admin-status-badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('SKU') }}</th>
                                <th class="text-center">{{ __('Qty') }}</th>
                                <th class="text-end">{{ __('Unit price') }}</th>
                                <th class="text-end">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="{{ $item->image_url ?: 'https://via.placeholder.com/100x100?text=No+Image' }}" alt="{{ $item->product_name ?: __('Order item') }}" width="70" height="70" class="rounded-4" style="object-fit:cover;">
                                            <div>
                                                <div class="fw-bold">{{ $item->product_name ?: __('Deleted product') }}</div>
                                                @if($item->variant_name)<div class="text-muted small">{{ $item->variant_name }}</div>@endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $item->sku ?: '—' }}</td>
                                    <td class="text-center">{{ (int) $item->quantity }}</td>
                                    <td class="text-end">EGP {{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="text-end fw-bold">EGP {{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-5">
                                        <div class="admin-empty-state py-4">
                                            <div class="empty-icon"><i class="mdi mdi-package-variant-closed-remove"></i></div>
                                            <h5 class="mb-2">{{ __('No order items found') }}</h5>
                                            <p class="text-muted mb-0">{{ __('This order currently has no visible line items. The record may be incomplete or cleaned up.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Customer & Shipping') }}</h4>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="admin-inline-label">{{ __('Customer') }}</div>
                        <div class="fw-semibold">{{ $order->customer_name ?: __('Guest customer') }}</div>
                        <div>{{ $order->customer_email ?: __('No email provided') }}</div>
                        <div>{{ $order->customer_phone ?: __('No phone provided') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="admin-inline-label">{{ __('Shipping address') }}</div>
                        <div>{{ $order->shipping_address_line_1 ?: __('No shipping address provided') }}</div>
                        @if($order->shipping_address_line_2)<div>{{ $order->shipping_address_line_2 }}</div>@endif
                        <div>{{ $order->shipping_city }}{{ $order->shipping_state ? ', ' . $order->shipping_state : '' }}</div>
                        @if($order->shipping_postal_code)<div>{{ $order->shipping_postal_code }}</div>@endif
                        <div>{{ $order->shipping_country }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Billing') }}</h4>
                @if($order->billing_same_as_shipping)
                    <div class="text-muted">{{ __('Billing address is the same as the shipping address.') }}</div>
                @else
                    <div>{{ $order->billing_address_line_1 ?: __('No billing address provided') }}</div>
                    @if($order->billing_address_line_2)<div>{{ $order->billing_address_line_2 }}</div>@endif
                    <div>{{ $order->billing_city }}{{ $order->billing_state ? ', ' . $order->billing_state : '' }}</div>
                    @if($order->billing_postal_code)<div>{{ $order->billing_postal_code }}</div>@endif
                    <div>{{ $order->billing_country }}</div>
                @endif
            </div>
        </div>

        @if($order->refunds->count())
            <div class="admin-card">
                <div class="admin-card-body">
                    <h4 class="mb-3">{{ __('Refund History') }}</h4>
                    <div class="d-flex flex-column gap-3">
                        @foreach($order->refunds as $refund)
                            <div class="admin-refund-item">
                                <div class="d-flex justify-content-between gap-3 flex-wrap mb-1">
                                    <strong>{{ $refund->reason }}</strong>
                                    <strong>EGP {{ number_format($refund->amount, 2) }}</strong>
                                </div>
                                <div class="text-muted small">Processed {{ optional($refund->processed_at)->format('d M Y, h:i A') }}{{ $refund->processedBy ? ' by ' . $refund->processedBy->name : '' }}</div>
                                @if($refund->notes)<div class="text-muted small mt-2">{{ $refund->notes }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-4">
        <div class="admin-card admin-card-sticky mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Order flow') }}</h4>
                @if($order->status === \App\Models\Order::STATUS_CANCELLED)
                    <div class="badge admin-status-badge badge-soft-danger mb-3">{{ __('Cancelled') }}</div>
                    <div class="text-muted small mb-3">{{ __('This order was cancelled before reaching completion.') }}</div>
                    @if($order->cancelled_reason)<div class="admin-refund-item mb-3">{{ $order->cancelled_reason }}</div>@endif
                @endif
                <div class="admin-order-flow mb-4">
                    @foreach($steps as $index => $step)
                        @php
                            $stepNumber = $index + 1;
                            $isActive = $stepNumber <= $activeStatusOrder && $order->status !== \App\Models\Order::STATUS_CANCELLED;
                            $isCurrent = $stepNumber === $activeStatusOrder && $order->status !== \App\Models\Order::STATUS_CANCELLED;
                        @endphp
                        <div class="admin-order-step {{ $isActive ? 'active' : '' }} {{ $isCurrent ? 'current' : '' }}">
                            <div class="admin-order-step-dot">{{ $isActive ? '✓' : $stepNumber }}</div>
                            <div class="admin-order-step-title">{{ $step['title'] }}</div>
                            <div class="admin-order-step-copy">{{ $step['copy'] }}</div>
                        </div>
                    @endforeach
                </div>

                <h4 class="mb-3">{{ __('Order Summary') }}</h4>
                <div class="admin-summary-list">
                    <div class="summary-row"><span class="text-muted">{{ __('Subtotal') }}</span><strong>EGP {{ number_format($order->subtotal, 2) }}</strong></div>
                    <div class="summary-row"><span class="text-muted">{{ __('Discount') }}</span><strong>EGP {{ number_format($order->discount_total, 2) }}</strong></div>
                    <div class="summary-row"><span class="text-muted">{{ __('Shipping') }}</span><strong>EGP {{ number_format($order->shipping_total, 2) }}</strong></div>
                    <div class="summary-row"><span class="text-muted">{{ __('Tax') }}</span><strong>EGP {{ number_format($order->tax_total, 2) }}</strong></div>
                    @if($order->coupon_code)
                        <div class="summary-row"><span class="text-muted">{{ __('Coupon') }}</span><strong>{{ $order->coupon_code }}</strong></div>
                    @endif
                    @if((float) $order->refund_total > 0)
                        <div class="summary-row"><span class="text-muted">{{ __('Refunded') }}</span><strong>EGP {{ number_format($order->refund_total, 2) }}</strong></div>
                    @endif
                </div>
                <hr>
                <div class="d-flex justify-content-between fs-5"><span class="fw-bold">{{ __('Grand Total') }}</span><span class="fw-bold">EGP {{ number_format($order->grand_total, 2) }}</span></div>
                @if($order->notes)
                    <hr>
                    <div class="admin-inline-label">{{ __('Customer notes') }}</div>
                    <div>{{ $order->notes }}</div>
                @endif
            </div>
        </div>

        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Update Status') }}</h4>
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" data-submit-loading>
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Order status') }}</label>
                        <select name="status" class="form-select">
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($order->status === $value) @disabled(!$order->canTransitionTo($value) && $order->status !== $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="section-note">{{ __('Allowed flow: Pending → Processing → Completed. Cancel can happen before completion.') }}</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-text-icon justify-content-center" data-loading-text="Saving..."><i class="mdi mdi-check-circle-outline"></i><span>{{ __('Save status') }}</span></button>
                </form>
            </div>
        </div>

        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Payment record') }}</h4>
                @php($latestPayment = $order->payments->first())
                @if($latestPayment)
                    <div class="admin-summary-list mb-3">
                        <div class="summary-row"><span class="text-muted">{{ __('Reference') }}</span><strong>{{ $latestPayment->transaction_reference ?: '—' }}</strong></div>
                        <div class="summary-row"><span class="text-muted">{{ __('Status') }}</span><span class="badge admin-status-badge {{ $latestPayment->status_badge_class }}">{{ $latestPayment->status_label }}</span></div>
                        <div class="summary-row"><span class="text-muted">{{ __('Provider') }}</span><strong>{{ $latestPayment->provider ?: '—' }}</strong></div>
                    </div>
                    <a href="{{ route('admin.payments.show', $latestPayment) }}" class="btn btn-light border w-100">{{ __('Open payment record') }}</a>
                @else
                    <div class="admin-refund-item">{{ __('No payment record exists for this order yet.') }}</div>
                @endif
            </div>
        </div>

        <div class="admin-card mb-4" id="delivery-card">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Delivery') }}</h4>
                <form method="POST" action="{{ route('admin.deliveries.update', $order) }}" data-submit-loading>
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Delivery status') }}</label>
                        <select name="delivery_status" class="form-select">
                            @foreach($deliveryStatusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($order->delivery_status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Courier') }}</label>
                        <input type="text" name="shipping_provider" class="form-control" value="{{ old('shipping_provider', $order->shipping_provider) }}" placeholder="{{ __('Courier name') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Tracking number') }}</label>
                        <input type="text" name="tracking_number" class="form-control" value="{{ old('tracking_number', $order->tracking_number) }}" placeholder="{{ __('Tracking number') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Estimated delivery date') }}</label>
                        <input type="date" name="estimated_delivery_date" class="form-control" value="{{ old('estimated_delivery_date', optional($order->estimated_delivery_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Delivery notes') }}</label>
                        <textarea name="delivery_notes" rows="3" class="form-control">{{ old('delivery_notes', $order->delivery_notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-light border w-100">{{ __('Save delivery details') }}</button>
                </form>
            </div>
        </div>

        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Refund') }}</h4>
                <div class="text-muted small mb-3">{{ __('Refundable balance') }}: <strong>EGP {{ number_format($order->refundable_balance, 2) }}</strong></div>
                @if($order->canBeRefunded())
                    <form method="POST" action="{{ route('admin.orders.refund', $order) }}" data-submit-loading>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Amount') }}</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $order->refundable_balance }}" name="amount" class="form-control" value="{{ old('amount', $order->refundable_balance) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Reason') }}</label>
                            <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" placeholder="{{ __('Refund') }} reason">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Notes') }}</label>
                            <textarea name="notes" rows="3" class="form-control" placeholder="{{ __('Optional refund notes') }}">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-light border w-100 btn-text-icon justify-content-center" data-loading-text="Recording..."><i class="mdi mdi-cash-refund"></i><span>{{ __('Record refund') }}</span></button>
                    </form>
                @else
                    <div class="admin-refund-item">{{ __('This order is not currently eligible for a refund. Mark it paid/completed first, or it may already be fully refunded.') }}</div>
                @endif
                <hr>
                <div class="admin-inline-label">{{ __('Payment method') }}</div>
                <div class="fw-semibold mb-3">{{ $order->payment_method_label }}</div>
                <div class="admin-inline-label">{{ __('Payment status') }}</div>
                <span class="badge admin-status-badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
