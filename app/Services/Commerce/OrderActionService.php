<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderActionService
{
    public function __construct(
        protected OrderNotificationService $orderNotificationService,
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

            foreach ($lockedOrder->items()->with(['product', 'variant'])->get() as $item) {
                if ($item->variant) {
                    $item->variant->increment('stock', (int) $item->quantity);
                } elseif ($item->product) {
                    $item->product->increment('quantity', (int) $item->quantity);
                }
            }

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

            $freshOrder = $lockedOrder->fresh(['items', 'refunds', 'user']);

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

            $this->orderNotificationService->notifyStatusUpdated($freshOrder);

            if ($oldDeliveryStatus !== $freshOrder->delivery_status) {
                $this->orderNotificationService->notifyDeliveryUpdated($freshOrder);
            }

            return $freshOrder;
        });
    }

    public function refund(Order $order, float $amount, string $reason, ?string $notes = null, ?int $processedBy = null): Order
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'refund' => 'Refund amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($order, $amount, $reason, $notes, $processedBy) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

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

            $lockedOrder->refunds()->create([
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

            $freshOrder = $lockedOrder->fresh(['refunds', 'user']);

            $this->orderNotificationService->notifyRefundRecorded($freshOrder);

            return $freshOrder;
        });
    }

}
