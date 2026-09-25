<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStockReservation;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReservationService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected StoreSettingsService $storeSettingsService,
    ) {
    }

    public function reservationMinutes(): int
    {
        $settings = $this->storeSettingsService->all();
        $minutes = (int) ($settings['payment_stock_reservation_minutes'] ?? 30);

        return max(5, min(1440, $minutes));
    }

    public function reserveOrderItem(
        Order $order,
        OrderItem $orderItem,
        Product $product,
        ?ProductVariant $variant = null,
        ?Carbon $expiresAt = null,
    ): OrderStockReservation {
        return DB::transaction(function () use ($order, $orderItem, $product, $variant, $expiresAt) {
            $expiresAt ??= now()->addMinutes($this->reservationMinutes());

            $reservation = OrderStockReservation::query()
                ->where('order_item_id', $orderItem->id)
                ->lockForUpdate()
                ->first();

            if ($reservation?->isCommitted()) {
                return $reservation;
            }

            if ($reservation?->isReserved()) {
                if ($reservation->expires_at === null || $reservation->expires_at->lt($expiresAt)) {
                    $reservation->update(['expires_at' => $expiresAt]);
                }

                return $reservation->fresh();
            }

            if ($reservation) {
                $reservation->update([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => (int) $orderItem->quantity,
                    'status' => OrderStockReservation::STATUS_RESERVED,
                    'reserved_at' => now(),
                    'expires_at' => $expiresAt,
                    'committed_at' => null,
                    'released_at' => null,
                    'release_reason' => null,
                    'meta' => array_merge($reservation->meta ?? [], [
                        're_reserved_at' => now()->toDateTimeString(),
                    ]),
                ]);
            } else {
                $reservation = OrderStockReservation::query()->create([
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => (int) $orderItem->quantity,
                    'status' => OrderStockReservation::STATUS_RESERVED,
                    'reserved_at' => now(),
                    'expires_at' => $expiresAt,
                    'meta' => [
                        'order_number' => $order->order_number,
                    ],
                ]);
            }

            $this->inventoryService->decrease(
                $product,
                $variant,
                (int) $orderItem->quantity,
                InventoryMovement::TYPE_ORDER_RESERVATION,
                [
                    'order_id' => $order->id,
                    'reason' => 'Online payment stock reservation',
                    'movement_unit_cost' => (float) ($orderItem->unit_cost ?? 0),
                    'expiration_date' => $orderItem->expires_at,
                    'meta' => [
                        'order_number' => $order->order_number,
                        'order_item_id' => $orderItem->id,
                        'reservation_id' => $reservation->id,
                        'reservation_expires_at' => $expiresAt->toDateTimeString(),
                    ],
                ]
            );

            return $reservation->fresh();
        });
    }

    public function ensureReservedForOrder(Order $order, bool $extendExpiration = true): int
    {
        if ($order->payment_method !== Order::PAYMENT_METHOD_ONLINE) {
            return 0;
        }

        return DB::transaction(function () use ($order, $extendExpiration) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === Order::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'payment' => __('Cancelled orders cannot reserve stock for online payment.'),
                ]);
            }

            $expiresAt = now()->addMinutes($this->reservationMinutes());
            $count = 0;

            $items = $lockedOrder->items()
                ->with(['product', 'variant', 'stockReservation'])
                ->orderBy('id')
                ->get();

            foreach ($items as $item) {
                if (! $item->product) {
                    throw ValidationException::withMessages([
                        'payment' => __('One or more products for this order are no longer available. Contact support before retrying payment.'),
                    ]);
                }

                $reservation = $item->stockReservation;

                if ($reservation?->isCommitted()) {
                    continue;
                }

                if ($reservation?->isReserved()) {
                    if ($extendExpiration && ($reservation->expires_at === null || $reservation->expires_at->lt($expiresAt))) {
                        $reservation->update(['expires_at' => $expiresAt]);
                    }

                    $count++;
                    continue;
                }

                $this->reserveOrderItem(
                    $lockedOrder,
                    $item,
                    $item->product,
                    $item->variant,
                    $expiresAt
                );

                $count++;
            }

            return $count;
        });
    }

    public function commitForOrder(Order $order): int
    {
        return DB::transaction(function () use ($order) {
            $reservations = OrderStockReservation::query()
                ->where('order_id', $order->id)
                ->where('status', OrderStockReservation::STATUS_RESERVED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update([
                    'status' => OrderStockReservation::STATUS_COMMITTED,
                    'committed_at' => now(),
                    'expires_at' => null,
                    'released_at' => null,
                    'release_reason' => null,
                ]);
            }

            return $reservations->count();
        });
    }

    public function releaseForOrder(Order $order, string $reason, bool $expired = false): int
    {
        return DB::transaction(function () use ($order, $reason, $expired) {
            $reservations = OrderStockReservation::query()
                ->with(['product', 'variant', 'orderItem'])
                ->where('order_id', $order->id)
                ->where('status', OrderStockReservation::STATUS_RESERVED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $released = 0;

            foreach ($reservations as $reservation) {
                if ($reservation->product) {
                    $this->inventoryService->increase(
                        $reservation->product,
                        $reservation->variant,
                        (int) $reservation->quantity,
                        InventoryMovement::TYPE_RESERVATION_RELEASE,
                        [
                            'order_id' => $order->id,
                            'reason' => $reason,
                            'movement_unit_cost' => (float) ($reservation->orderItem?->unit_cost ?? 0),
                            'expiration_date' => $reservation->orderItem?->expires_at,
                            'meta' => [
                                'order_number' => $order->order_number,
                                'order_item_id' => $reservation->order_item_id,
                                'reservation_id' => $reservation->id,
                                'expired' => $expired,
                            ],
                        ]
                    );
                }

                $reservation->update([
                    'status' => $expired
                        ? OrderStockReservation::STATUS_EXPIRED
                        : OrderStockReservation::STATUS_RELEASED,
                    'released_at' => now(),
                    'release_reason' => $reason,
                    'expires_at' => null,
                ]);

                $released++;
            }

            return $released;
        });
    }

    public function activeReservationExpiry(Order $order): ?Carbon
    {
        $value = OrderStockReservation::query()
            ->where('order_id', $order->id)
            ->where('status', OrderStockReservation::STATUS_RESERVED)
            ->min('expires_at');

        return $value ? Carbon::parse($value) : null;
    }
}
