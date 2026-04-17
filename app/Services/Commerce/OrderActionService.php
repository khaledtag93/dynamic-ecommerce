<?php

namespace App\Services\Commerce;

use App\Models\Order;
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
        if (! $order->canTransitionTo(Order::STATUS_CANCELLED)) {
            throw ValidationException::withMessages([
                'status' => 'This order cannot be cancelled anymore.',
            ]);
        }

        return DB::transaction(function () use ($order, $reason, $actorId) {
            foreach ($order->items()->with(['product', 'variant'])->get() as $item) {
                if ($item->variant) {
                    $item->variant->increment('stock', (int) $item->quantity);
                } elseif ($item->product) {
                    $item->product->increment('quantity', (int) $item->quantity);
                }
            }

            $meta = $order->meta ?? [];
            $meta['cancelled_by'] = $actorId;

            $paymentStatus = $order->payment_status;
            if ($paymentStatus === Order::PAYMENT_STATUS_PENDING) {
                $paymentStatus = Order::PAYMENT_STATUS_FAILED;
            }

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'delivery_status' => Order::DELIVERY_STATUS_CANCELLED,
                'payment_status' => $paymentStatus,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
                'meta' => $meta,
            ]);

            $freshOrder = $order->fresh(['items', 'refunds', 'user']);

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
        if (! $order->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid order status transition.',
            ]);
        }

        return DB::transaction(function () use ($order, $newStatus) {
            $updates = [
                'status' => $newStatus,
            ];

            if ($newStatus === Order::STATUS_PROCESSING && $order->delivery_status === Order::DELIVERY_STATUS_PENDING) {
                $updates['delivery_status'] = Order::DELIVERY_STATUS_PREPARING;
            }

            if ($newStatus === Order::STATUS_COMPLETED && $order->payment_method === Order::PAYMENT_METHOD_COD) {
                $updates['payment_status'] = Order::PAYMENT_STATUS_PAID;

                if ($order->delivery_status !== Order::DELIVERY_STATUS_DELIVERED) {
                    $updates['delivery_status'] = Order::DELIVERY_STATUS_DELIVERED;
                    $updates['delivered_at'] = now();
                }
            }

            $oldDeliveryStatus = $order->delivery_status;

            $order->update($updates);

            $freshOrder = $order->fresh(['user']);

            $this->orderNotificationService->notifyStatusUpdated($freshOrder);

            if ($oldDeliveryStatus !== $freshOrder->delivery_status) {
                $this->orderNotificationService->notifyDeliveryUpdated($freshOrder);
            }

            return $freshOrder;
        });
    }

    public function refund(Order $order, float $amount, string $reason, ?string $notes = null, ?int $processedBy = null): Order
    {
        if (! $order->canBeRefunded()) {
            throw ValidationException::withMessages([
                'refund' => 'This order cannot be refunded in its current state.',
            ]);
        }

        if ($amount <= 0 || $amount > $order->refundable_balance) {
            throw ValidationException::withMessages([
                'refund' => 'Refund amount must be greater than zero and within the refundable balance.',
            ]);
        }

        return DB::transaction(function () use ($order, $amount, $reason, $notes, $processedBy) {
            $order->refunds()->create([
                'amount' => $amount,
                'reason' => $reason,
                'notes' => $notes,
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);

            $newRefundTotal = round((float) $order->refunds()->sum('amount'), 2);
            $newPaymentStatus = $newRefundTotal >= (float) $order->grand_total
                ? Order::PAYMENT_STATUS_REFUNDED
                : Order::PAYMENT_STATUS_PARTIALLY_REFUNDED;

            $order->update([
                'refund_total' => $newRefundTotal,
                'refunded_at' => now(),
                'payment_status' => $newPaymentStatus,
            ]);

            $freshOrder = $order->fresh(['refunds', 'user']);

            $this->orderNotificationService->notifyRefundRecorded($freshOrder);

            return $freshOrder;
        });
    }
}
