<?php

namespace App\Services\Commerce;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DeliveryStatusUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        protected WhatsAppServiceInterface $whatsAppService,
    ) {
    }

    /**
     * @return array{order: Order, status_changed: bool}
     */
    public function update(Order $order, array $attributes): array
    {
        $statusChanged = false;

        $updatedOrder = DB::transaction(function () use ($order, $attributes, &$statusChanged): Order {
            $lockedOrder = Order::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            $newStatus = (string) ($attributes['delivery_status'] ?? $lockedOrder->delivery_status);
            $statusChanged = $newStatus !== $lockedOrder->delivery_status;

            if ($statusChanged && ! $lockedOrder->canTransitionDeliveryTo($newStatus)) {
                throw ValidationException::withMessages([
                    'delivery_status' => __('This delivery status transition is not allowed.'),
                ]);
            }

            $updates = [
                'delivery_status' => $newStatus,
                'shipping_provider' => $attributes['shipping_provider'] ?? null,
                'tracking_number' => $attributes['tracking_number'] ?? null,
                'estimated_delivery_date' => $attributes['estimated_delivery_date'] ?? null,
                'delivery_notes' => $attributes['delivery_notes'] ?? null,
            ];

            if ($statusChanged) {
                if ($newStatus === Order::DELIVERY_STATUS_SHIPPED && ! $lockedOrder->shipped_at) {
                    $updates['shipped_at'] = now();
                }

                if ($newStatus === Order::DELIVERY_STATUS_OUT_FOR_DELIVERY && ! $lockedOrder->shipped_at) {
                    throw ValidationException::withMessages([
                        'delivery_status' => __('A delivery must be marked as shipped before it can move out for delivery.'),
                    ]);
                }

                if ($newStatus === Order::DELIVERY_STATUS_DELIVERED && ! $lockedOrder->delivered_at) {
                    $updates['delivered_at'] = now();
                }

                if ($newStatus === Order::DELIVERY_STATUS_CANCELLED) {
                    $updates['shipped_at'] = null;
                    $updates['delivered_at'] = null;
                }
            }

            $lockedOrder->fill($updates);

            if ($lockedOrder->isDirty()) {
                $lockedOrder->save();
            }

            return $lockedOrder->fresh('user');
        });

        if ($statusChanged) {
            $this->sendStatusNotifications($updatedOrder);
        }

        return [
            'order' => $updatedOrder,
            'status_changed' => $statusChanged,
        ];
    }

    protected function sendStatusNotifications(Order $order): void
    {
        if ($order->user) {
            $order->user->notify(new DeliveryStatusUpdatedNotification($order));
        }

        User::query()
            ->where('role_as', 1)
            ->get()
            ->each(function (User $admin) use ($order): void {
                $admin->notify(new DeliveryStatusUpdatedNotification($order));
            });

        $this->whatsAppService->queueDeliveryUpdate($order);
    }
}
