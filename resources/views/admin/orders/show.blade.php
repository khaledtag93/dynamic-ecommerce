@extends('layouts.admin')

@section('title', $order->order_number . ' | Admin')

@section('content')
@php
    $can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false;
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
    $currency = $order->currency ?: 'EGP';
@endphp

<x-admin.page-header :kicker="__('Order details')" :title="$order->order_number" :description="__('Placed :date', ['date' => optional($order->placed_at)->format('d M Y, h:i A') ?: $order->created_at->format('d M Y, h:i A')])">
    <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to orders') }}</span></a>
    <a href="{{ route('admin.orders.receipt', $order) }}" class="btn btn-primary btn-text-icon" target="_blank" rel="noopener"><i class="mdi mdi-printer-outline"></i><span>{{ __('Print receipt') }}</span></a>
</x-admin.page-header>

@if(data_get($order->meta, 'stock_reservation_exception'))
    <div class="alert alert-danger rounded-4 border-0 mb-4">
        <div class="fw-bold mb-1">{{ __('Paid order requires stock review') }}</div>
        <div>{{ __('Payment was confirmed, but the online stock reservation could not be fully restored. Review inventory and fulfillment before processing this order.') }}</div>
    </div>
@endif

<nav class="admin-order-jump" aria-label="{{ __('Page sections') }}">
    <a href="#order-items">{{ __('Items') }}</a>
    <a href="#order-customer">{{ __('Customer & Shipping') }}</a>
    <a href="#order-summary">{{ __('Order Summary') }}</a>
    @if($can('payments.view'))<a href="#order-payment">{{ __('Payment record') }}</a>@endif
    @if($can('delivery.view'))<a href="#delivery-card">{{ __('Delivery') }}</a>@endif
    <a href="#order-refund">{{ __('Refund') }}</a>
</nav>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card"><div class="admin-card-body"><div class="admin-inline-label">{{ __('Order status') }}</div><span class="badge admin-status-badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span></div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card"><div class="admin-card-body"><div class="admin-inline-label">{{ __('Payment status') }}</div><span class="badge admin-status-badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span></div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card"><div class="admin-card-body"><div class="admin-inline-label">{{ __('Payment method') }}</div><div class="fw-bold fs-4">{{ $order->payment_method_label }}</div></div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card"><div class="admin-card-body"><div class="admin-inline-label">{{ __('Delivery status') }}</div><span class="badge admin-status-badge {{ $order->delivery_status_badge_class }}">{{ $order->delivery_status_label }}</span></div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card mb-4" id="order-items">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                    <div>
                        <h4 class="mb-1">{{ __('Items') }}</h4>
                        <div class="text-muted small">{{ trans_choice(':count item included in this order.|:count items included in this order.', $order->items->count(), ['count' => $order->items->count()]) }}</div>
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
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="text-end fw-bold">{{ $currency }} {{ number_format((float) $item->line_total, 2) }}</td>
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

        <div class="admin-card mb-4" id="order-customer">
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
                                    <strong>{{ $currency }} {{ number_format($refund->amount, 2) }}</strong>
                                </div>
                                <div class="text-muted small">{{ __('Processed :date', ['date' => optional($refund->processed_at)->format('d M Y, h:i A')]) }}@if($refund->processedBy) · {{ __('By :name', ['name' => $refund->processedBy->name]) }}@endif</div>
                                @if($refund->notes)<div class="text-muted small mt-2">{{ $refund->notes }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-4">
        <div class="admin-card mb-4" id="order-summary">
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
                    <div class="summary-row"><span class="text-muted">{{ __('Subtotal') }}</span><strong>{{ $currency }} {{ number_format($order->subtotal, 2) }}</strong></div>
                    <div class="summary-row"><span class="text-muted">{{ __('Discount') }}</span><strong>{{ $currency }} {{ number_format($order->discount_total, 2) }}</strong></div>
                    <div class="summary-row"><span class="text-muted">{{ __('Shipping') }}</span><strong>{{ $currency }} {{ number_format($order->shipping_total, 2) }}</strong></div>
                    <div class="summary-row"><span class="text-muted">{{ __('Tax') }}</span><strong>{{ $currency }} {{ number_format($order->tax_total, 2) }}</strong></div>
                    @if($order->coupon_code)
                        <div class="summary-row"><span class="text-muted">{{ __('Coupon') }}</span><strong>{{ $order->coupon_code }}</strong></div>
                    @endif
                    @if((float) $order->refund_total > 0)
                        <div class="summary-row"><span class="text-muted">{{ __('Refunded') }}</span><strong>{{ $currency }} {{ number_format($order->refund_total, 2) }}</strong></div>
                    @endif
                </div>
                <hr>
                <div class="d-flex justify-content-between fs-5"><span class="fw-bold">{{ __('Grand Total') }}</span><span class="fw-bold">{{ $currency }} {{ number_format($order->grand_total, 2) }}</span></div>
                @if((float) $order->refund_total > 0)
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">{{ __('Net after refunds') }}</span><strong>{{ $currency }} {{ number_format(max(0, (float) $order->grand_total - (float) $order->refund_total), 2) }}</strong></div>
                @endif
                @if($order->notes)
                    <hr>
                    <div class="admin-inline-label">{{ __('Customer notes') }}</div>
                    <div>{{ $order->notes }}</div>
                @endif
            </div>
        </div>

        @if($can('orders.manage'))
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Update Status') }}</h4>
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" data-submit-loading data-order-status-form data-current-status="{{ $order->status }}" data-cancel-status="{{ \App\Models\Order::STATUS_CANCELLED }}">
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
                    <button type="submit" class="btn btn-primary w-100 btn-text-icon justify-content-center" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-check-circle-outline"></i><span>{{ __('Save status') }}</span></button>
                </form>
            </div>
        </div>
        @endif

        @if($can('orders.manage'))
        <div class="modal fade" id="orderCancelConfirmModal" tabindex="-1" aria-labelledby="orderCancelConfirmTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderCancelConfirmTitle">{{ __('Confirm order cancellation') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">{{ __('Cancel this order?') }}</p>
                        <p class="text-muted small mb-0">{{ __('Cancelling the order restores reserved stock and records the cancellation in the audit trail. This action should only be used when the order must not continue to fulfillment.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('Keep order active') }}</button>
                        <button type="button" class="btn btn-danger" data-confirm-order-cancel>{{ __('Cancel order') }}</button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can('payments.view'))
        <div class="admin-card mb-4" id="order-payment">
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
        @endif

        @if($can('delivery.view'))
        <div class="admin-card mb-4" id="delivery-card">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                    <h4 class="mb-0">{{ __('Delivery') }}</h4>
                    <span class="badge admin-status-badge {{ $order->delivery_status_badge_class }}">{{ $order->delivery_status_label }}</span>
                </div>
                <div class="admin-summary-list mb-3">
                    <div class="summary-row"><span class="text-muted">{{ __('Shipped at') }}</span><strong>{{ optional($order->shipped_at)->format('d M Y, h:i A') ?: '—' }}</strong></div>
                    <div class="summary-row"><span class="text-muted">{{ __('Delivered at') }}</span><strong>{{ optional($order->delivered_at)->format('d M Y, h:i A') ?: '—' }}</strong></div>
                </div>
                @if($can('delivery.manage'))
                <form method="POST" action="{{ route('admin.deliveries.update', $order) }}" data-submit-loading data-delivery-form data-current-status="{{ $order->delivery_status }}">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Delivery status') }}</label>
                        <select name="delivery_status" class="form-select">
                            @foreach($deliveryStatusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($order->delivery_status === $value) @disabled(!$order->canTransitionDeliveryTo($value))>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="section-note">{{ __('Only valid next delivery statuses are enabled. Customer notifications and WhatsApp updates are sent only when the status actually changes.') }}</div>
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
                    <button type="submit" class="btn btn-primary w-100 btn-text-icon justify-content-center" data-loading-text="{{ __('Saving...') }}"><i class="mdi mdi-content-save-check-outline"></i><span>{{ __('Save delivery details') }}</span></button>
                </form>
                @else
                    <div class="text-muted small">{{ $order->shipping_provider ?: __('No courier assigned') }} · {{ $order->tracking_number ?: __('No tracking number') }}</div>
                @endif
            </div>
        </div>
        @endif

        @if($can('delivery.manage'))
        <div class="modal fade" id="deliveryStatusConfirmModal" tabindex="-1" aria-labelledby="deliveryStatusConfirmTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deliveryStatusConfirmTitle">{{ __('Confirm delivery status change') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">{{ __('You are changing the delivery status to :status.', ['status' => '__STATUS__']) }}</p>
                        <p class="text-muted small mb-0">{{ __('This status change may notify the customer and queue a WhatsApp delivery update when that channel is enabled.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('Keep current status') }}</button>
                        <button type="button" class="btn btn-primary" data-confirm-delivery-status>{{ __('Confirm status change') }}</button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="modal fade" id="refundConfirmModal" tabindex="-1" aria-labelledby="refundConfirmTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="refundConfirmTitle">{{ __('Confirm refund') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" data-refund-confirm-copy></p>
                        <p class="text-muted small mb-0">{{ __('The refund is recorded in the order ledger and changes the payment status when the refundable balance is fully consumed.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('Review refund') }}</button>
                        <button type="button" class="btn btn-danger" data-confirm-refund>{{ __('Confirm refund') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card mb-4" id="order-refund">
            <div class="admin-card-body">
                <h4 class="mb-3">{{ __('Refund') }}</h4>
                <div class="admin-refund-balance mb-3">
                    <div><span class="text-muted small">{{ __('Refundable balance') }}</span><div class="fw-bold fs-5">{{ $currency }} {{ number_format($order->refundable_balance, 2) }}</div></div>
                    @if((float) $order->refund_total > 0)<span class="badge badge-soft-warning">{{ __('Already refunded') }}: {{ $currency }} {{ number_format($order->refund_total, 2) }}</span>@endif
                </div>
                @if($order->canBeRefunded())
                    @if($can('orders.manage'))
                    <form method="POST" action="{{ route('admin.orders.refund', $order) }}" data-submit-loading data-refund-form data-refund-currency="{{ $currency }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Amount') }}</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $order->refundable_balance }}" name="amount" class="form-control" value="{{ old('amount', $order->refundable_balance) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Reason') }}</label>
                            <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" placeholder="{{ __('Refund reason') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Notes') }}</label>
                            <textarea name="notes" rows="3" class="form-control" placeholder="{{ __('Optional refund notes') }}">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-light border w-100 btn-text-icon justify-content-center" data-loading-text="{{ __('Recording...') }}"><i class="mdi mdi-cash-refund"></i><span>{{ __('Record refund') }}</span></button>
                    </form>
                    @endif
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

@push('styles')
<style>
.admin-refund-balance { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; padding:1rem; border:1px solid var(--admin-border); border-radius:1rem; background:var(--admin-surface); }
.admin-order-jump { display: flex; gap: .5rem; overflow-x: auto; margin-bottom: 1.25rem; padding-bottom: .25rem; scrollbar-width: thin; }
.admin-order-jump a { flex: 0 0 auto; padding: .6rem .9rem; border: 1px solid var(--admin-border); border-radius: 999px; background: var(--admin-surface); color: var(--admin-text); text-decoration: none; font-weight: 700; font-size: .87rem; }
.admin-order-jump a:hover, .admin-order-jump a:focus-visible { color: var(--admin-primary-dark); border-color: var(--admin-primary); }
[id^="order-"], #delivery-card { scroll-margin-top: 6rem; }
</style>
@endpush


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const orderStatusForm = document.querySelector('[data-order-status-form]');
    const orderCancelModalElement = document.getElementById('orderCancelConfirmModal');

    if (orderStatusForm && orderCancelModalElement && typeof bootstrap !== 'undefined') {
        const orderStatusSelect = orderStatusForm.querySelector('[name="status"]');
        const orderCancelConfirmButton = orderCancelModalElement.querySelector('[data-confirm-order-cancel]');
        const orderCancelModal = bootstrap.Modal.getOrCreateInstance(orderCancelModalElement);
        let orderCancelConfirmed = false;

        orderStatusForm.addEventListener('submit', function (event) {
            const isNewCancellation = orderStatusSelect
                && orderStatusSelect.value === orderStatusForm.dataset.cancelStatus
                && orderStatusSelect.value !== orderStatusForm.dataset.currentStatus;

            if (orderCancelConfirmed || !isNewCancellation) {
                return;
            }

            event.preventDefault();
            orderCancelModal.show();
        });

        orderCancelConfirmButton?.addEventListener('click', function () {
            orderCancelConfirmed = true;
            orderCancelModal.hide();

            if (typeof orderStatusForm.requestSubmit === 'function') {
                orderStatusForm.requestSubmit();
            } else {
                orderStatusForm.submit();
            }
        });
    }

    const refundForm = document.querySelector('[data-refund-form]');
    const refundModalElement = document.getElementById('refundConfirmModal');

    if (refundForm && refundModalElement && typeof bootstrap !== 'undefined') {
        const refundAmount = refundForm.querySelector('[name="amount"]');
        const refundConfirmCopy = refundModalElement.querySelector('[data-refund-confirm-copy]');
        const refundConfirmButton = refundModalElement.querySelector('[data-confirm-refund]');
        const refundModal = bootstrap.Modal.getOrCreateInstance(refundModalElement);
        let refundConfirmed = false;

        refundForm.addEventListener('submit', function (event) {
            if (refundConfirmed) {
                return;
            }

            event.preventDefault();
            const amount = refundAmount?.value || '0';
            if (refundConfirmCopy) {
                refundConfirmCopy.textContent = @json(__('Record a refund of :amount?', ['amount' => '__AMOUNT__']))
                    .replace('__AMOUNT__', refundForm.dataset.refundCurrency + ' ' + amount);
            }
            refundModal.show();
        });

        refundConfirmButton?.addEventListener('click', function () {
            refundConfirmed = true;
            refundModal.hide();

            if (typeof refundForm.requestSubmit === 'function') {
                refundForm.requestSubmit();
            } else {
                refundForm.submit();
            }
        });
    }

    const form = document.querySelector('[data-delivery-form]');
    const modalElement = document.getElementById('deliveryStatusConfirmModal');

    if (!form || !modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    const statusSelect = form.querySelector('[name="delivery_status"]');
    const confirmButton = modalElement.querySelector('[data-confirm-delivery-status]');
    const bodyCopy = modalElement.querySelector('.modal-body p');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    let confirmed = false;

    form.addEventListener('submit', function (event) {
        if (confirmed || !statusSelect || statusSelect.value === form.dataset.currentStatus) {
            return;
        }

        event.preventDefault();

        const selectedLabel = statusSelect.options[statusSelect.selectedIndex]?.text || statusSelect.value;
        if (bodyCopy) {
            bodyCopy.textContent = @json(__('You are changing the delivery status to :status.', ['status' => '__STATUS__'])).replace('__STATUS__', selectedLabel);
        }

        modal.show();
    });

    confirmButton?.addEventListener('click', function () {
        confirmed = true;
        modal.hide();

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
});
</script>
@endpush
