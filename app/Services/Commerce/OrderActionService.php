<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderActionService
{
    public function __construct(
        protected OrderNotificationService $orderNotificationService,
        protected InventoryService $inventoryService,
        protected StockReservationService $stockReservationService,
        protected CouponService $couponService,
        protected AnalyticsTracker $analyticsTracker,
        protected ProfitService $profitService,
    ) {
    }

    public function cancel(Order $order, ?string $reason = null, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $actorId) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Cancellation is idempotent. A repeated request must never restore
            // inventory more than once.
            if ($lockedOrder->status === Order::STATUS_CANCELLED) {
                return $lockedOrder->fresh(['items', 'refunds', 'user']);
            }

            if (! $lockedOrder->canTransitionTo(Order::STATUS_CANCELLED)) {
                throw ValidationException::withMessages([
                    'status' => 'This order cannot be cancelled anymore.',
                ]);
            }

            if (! in_array($lockedOrder->delivery_status, [
                Order::DELIVERY_STATUS_PENDING,
                Order::DELIVERY_STATUS_PREPARING,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => __('Orders cannot be cancelled after shipping has started. Use the return workflow instead.'),
                ]);
            }

            if ($lockedOrder->refundable_balance > 0 && in_array($lockedOrder->payment_status, [
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => __('Refund the remaining paid balance before cancelling this order.'),
                ]);
            }

            if ($lockedOrder->payment_method === Order::PAYMENT_METHOD_ONLINE) {
                $this->stockReservationService->releaseForOrder(
                    $lockedOrder,
                    'order_cancelled'
                );
            }

            foreach ($lockedOrder->items()->with(['product', 'variant', 'stockReservation'])->get() as $item) {
                if (
                    $lockedOrder->payment_method === Order::PAYMENT_METHOD_ONLINE
                    && $item->stockReservation
                    && in_array($item->stockReservation->status, [
                        \App\Models\OrderStockReservation::STATUS_RELEASED,
                        \App\Models\OrderStockReservation::STATUS_EXPIRED,
                    ], true)
                ) {
                    continue;
                }

                if (! $item->product) {
                    throw ValidationException::withMessages([
                        'status' => __('Order cancellation cannot restore stock because a catalog product no longer exists.'),
                    ]);
                }

                $this->assertRestockTarget($item);

                $this->inventoryService->increase(
                    $item->product,
                    $item->variant,
                    (int) $item->quantity,
                    InventoryMovement::TYPE_REFUND_RESTOCK,
                    [
                        'order_id' => $lockedOrder->id,
                        'reason' => 'Order cancellation restock',
                        'movement_unit_cost' => (float) ($item->unit_cost ?? 0),
                        'expiration_date' => $item->expires_at,
                        'meta' => [
                            'order_number' => $lockedOrder->order_number,
                            'cancelled_by' => $actorId,
                        ],
                    ]
                );
            }

            $this->couponService->releaseUsageForCancelledOrder($lockedOrder);

            $meta = $lockedOrder->meta ?? [];
            $meta['cancelled_by'] = $actorId;

            $paymentStatus = $lockedOrder->payment_status;
            if ($paymentStatus === Order::PAYMENT_STATUS_PENDING) {
                $paymentStatus = Order::PAYMENT_STATUS_FAILED;
            }

            $lockedOrder->payments()
                ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_AUTHORIZED])
                ->update([
                    'status' => Payment::STATUS_FAILED,
                    'failed_at' => now(),
                ]);

            $lockedOrder->update([
                'status' => Order::STATUS_CANCELLED,
                'delivery_status' => Order::DELIVERY_STATUS_CANCELLED,
                'payment_status' => $paymentStatus,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
                'meta' => $meta,
            ]);

            $this->profitService->refreshOrderTotals($lockedOrder);
            $freshOrder = $lockedOrder->fresh(['items', 'refunds', 'user']);
            $this->analyticsTracker->syncRealizedPurchase($freshOrder);

            $this->orderNotificationService->notifyCancelled(
                $freshOrder,
                __('Your order :order was cancelled.', ['order' => $freshOrder->order_number])
            );

            $this->orderNotificationService->notifyDeliveryUpdated(
                $freshOrder,
                __('Delivery for order :order is now :status.', [
                    'order' => $freshOrder->order_number,
                    'status' => $freshOrder->delivery_status_label,
                ])
            );

            return $freshOrder;
        });
    }

    private function assertRestockTarget(OrderItem $item): void
    {
        $lineWasVariantBased = filled($item->variant_name) || $item->product_variant_id !== null;

        if ($lineWasVariantBased && ! $item->variant) {
            throw ValidationException::withMessages([
                'status' => __('Order cancellation cannot restore stock because a product variant no longer exists.'),
            ]);
        }

        if ($item->variant && (int) $item->variant->product_id !== (int) $item->product_id) {
            throw ValidationException::withMessages([
                'status' => __('Order cancellation cannot restore stock because the product variant no longer matches the product.'),
            ]);
        }
    }

    public function updateStatus(Order $order, string $newStatus): Order
    {
        return DB::transaction(function () use ($order, $newStatus) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === $newStatus) {
                return $lockedOrder->fresh(['user']);
            }

            if (! $lockedOrder->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid order status transition.',
                ]);
            }

            if (in_array($newStatus, [Order::STATUS_PROCESSING, Order::STATUS_COMPLETED], true)) {
                $this->assertFulfillmentReady($lockedOrder);
            }

            if (
                $newStatus === Order::STATUS_COMPLETED
                && $lockedOrder->sales_channel !== Order::SALES_CHANNEL_POS
                && $lockedOrder->payment_method !== Order::PAYMENT_METHOD_COD
                && $lockedOrder->delivery_status !== Order::DELIVERY_STATUS_DELIVERED
            ) {
                throw ValidationException::withMessages([
                    'status' => __('Storefront orders cannot be completed before delivery is marked Delivered.'),
                ]);
            }

            $updates = [
                'status' => $newStatus,
            ];

            if ($newStatus === Order::STATUS_PROCESSING && $lockedOrder->delivery_status === Order::DELIVERY_STATUS_PENDING) {
                $updates['delivery_status'] = Order::DELIVERY_STATUS_PREPARING;
            }

            if ($newStatus === Order::STATUS_COMPLETED && $lockedOrder->payment_method === Order::PAYMENT_METHOD_COD) {
                $updates['payment_status'] = Order::PAYMENT_STATUS_PAID;

                if ($lockedOrder->delivery_status !== Order::DELIVERY_STATUS_DELIVERED) {
                    $updates['delivery_status'] = Order::DELIVERY_STATUS_DELIVERED;
                    $updates['delivered_at'] = now();
                }
            }

            $oldDeliveryStatus = $lockedOrder->delivery_status;

            if ($newStatus === Order::STATUS_COMPLETED && $lockedOrder->payment_method === Order::PAYMENT_METHOD_COD) {
                $lockedOrder->payments()
                    ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_AUTHORIZED])
                    ->update([
                        'status' => Payment::STATUS_PAID,
                        'paid_at' => now(),
                        'failed_at' => null,
                    ]);
            }

            $lockedOrder->update($updates);

            $freshOrder = $lockedOrder->fresh(['user']);
            $this->analyticsTracker->syncRealizedPurchase($freshOrder);

            $this->orderNotificationService->notifyStatusUpdated($freshOrder);

            if ($oldDeliveryStatus !== $freshOrder->delivery_status) {
                $this->orderNotificationService->notifyDeliveryUpdated($freshOrder);
            }

            return $freshOrder;
        });
    }

    private function assertFulfillmentReady(Order $order): void
    {
        if (! in_array($order->payment_method, [
            Order::PAYMENT_METHOD_ONLINE,
            Order::PAYMENT_METHOD_BANK_TRANSFER,
        ], true)) {
            return;
        }

        if (! in_array($order->payment_status, [
            Order::PAYMENT_STATUS_PAID,
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => $order->payment_method === Order::PAYMENT_METHOD_BANK_TRANSFER
                    ? __('Bank transfer orders cannot enter fulfillment until payment is confirmed.')
                    : __('Online orders cannot enter fulfillment until payment is confirmed.'),
            ]);
        }

        if (
            $order->payment_method === Order::PAYMENT_METHOD_ONLINE
            && data_get($order->meta, 'stock_reservation_exception')
        ) {
            throw ValidationException::withMessages([
                'status' => __('This paid online order requires stock review before fulfillment can continue.'),
            ]);
        }
    }

    public function refund(Order $order, float $amount, string $reason, ?string $notes = null, ?int $processedBy = null, ?int $returnRequestId = null, ?string $idempotencyKey = null): array
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'refund' => 'Refund amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($order, $amount, $reason, $notes, $processedBy, $returnRequestId, $idempotencyKey) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $idempotencyKey = filled($idempotencyKey) ? trim((string) $idempotencyKey) : null;
            $reason = trim($reason);
            $notes = filled($notes) ? trim((string) $notes) : null;

            if ($idempotencyKey) {
                $existingRefund = $lockedOrder->refunds()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingRefund) {
                    $samePayload = round((float) $existingRefund->amount, 2) === round($amount, 2)
                        && (string) $existingRefund->reason === $reason
                        && (string) ($existingRefund->notes ?? '') === (string) ($notes ?? '')
                        && (int) ($existingRefund->processed_by ?? 0) === (int) ($processedBy ?? 0);

                    if (! $samePayload) {
                        throw ValidationException::withMessages([
                            'refund' => __('This refund request key was already used with different details.'),
                        ]);
                    }

                    return [
                        'order' => $lockedOrder->fresh(['refunds', 'user']),
                        'refund' => $existingRefund,
                        'created' => false,
                    ];
                }
            }

            $alreadyRefunded = round((float) $lockedOrder->refunds()->sum('amount'), 2);
            $refundableBalance = round(max(0, (float) $lockedOrder->grand_total - $alreadyRefunded), 2);

            if (! in_array($lockedOrder->payment_status, [
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            ], true) || $refundableBalance <= 0) {
                throw ValidationException::withMessages([
                    'refund' => 'This order cannot be refunded in its current state.',
                ]);
            }

            if ($amount > $refundableBalance) {
                throw ValidationException::withMessages([
                    'refund' => 'Refund amount must be within the remaining refundable balance.',
                ]);
            }

            $refund = $lockedOrder->refunds()->create([
                'return_request_id' => $returnRequestId,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount,
                'reason' => $reason,
                'notes' => $notes,
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);

            $newRefundTotal = round($alreadyRefunded + $amount, 2);
            $newPaymentStatus = $newRefundTotal >= (float) $lockedOrder->grand_total
                ? Order::PAYMENT_STATUS_REFUNDED
                : Order::PAYMENT_STATUS_PARTIALLY_REFUNDED;

            $lockedOrder->update([
                'refund_total' => $newRefundTotal,
                'refunded_at' => now(),
                'payment_status' => $newPaymentStatus,
            ]);

            if ($newPaymentStatus === Order::PAYMENT_STATUS_REFUNDED) {
                $refundedAt = now();

                $orderMeta = $lockedOrder->meta ?? [];
                if (data_get($orderMeta, 'payment_exception.code') === 'paid_after_cancellation') {
                    data_set($orderMeta, 'payment_exception.refund_required', false);
                    data_set($orderMeta, 'payment_exception.resolved_at', $refundedAt->toIso8601String());
                    $lockedOrder->update(['meta' => $orderMeta]);
                }

                $lockedOrder->payments()
                    ->where('status', Payment::STATUS_PAID)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (Payment $payment) use ($refundedAt) {
                        $meta = $payment->meta ?? [];
                        $events = $meta['events'] ?? [];
                        $events[] = [
                            'event' => 'order_refund_completed',
                            'message' => __('Payment was marked refunded after the order refund ledger reached the full paid amount.'),
                            'at' => $refundedAt->toDateTimeString(),
                        ];
                        $meta['events'] = array_slice($events, -20);

                        if (data_get($meta, 'payment_exception.code') === 'paid_after_cancellation') {
                            data_set($meta, 'payment_exception.refund_required', false);
                            data_set($meta, 'payment_exception.resolved_at', $refundedAt->toIso8601String());
                        }

                        $payment->update([
                            'status' => Payment::STATUS_REFUNDED,
                            'refunded_at' => $refundedAt,
                            'meta' => $meta,
                        ]);
                    });
            }

            $this->profitService->refreshOrderTotals($lockedOrder);
            $freshOrder = $lockedOrder->fresh(['refunds', 'user']);
            $this->analyticsTracker->syncRealizedPurchase($freshOrder);

            $this->orderNotificationService->notifyRefundRecorded($freshOrder);

            return [
                'order' => $freshOrder,
                'refund' => $refund,
                'created' => true,
            ];
        });
    }

}
