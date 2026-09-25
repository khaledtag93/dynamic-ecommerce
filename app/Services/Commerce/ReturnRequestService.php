<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnRequestService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected OrderActionService $orderActionService,
        protected AdminActivityLogService $activityLogService,
    ) {
    }

    public function canCustomerRequest(Order $order, User $user): bool
    {
        if ((int) $order->user_id !== (int) $user->id) {
            return false;
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            return false;
        }

        if (! in_array($order->payment_status, [
            Order::PAYMENT_STATUS_PAID,
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ], true)) {
            return false;
        }

        return $order->status === Order::STATUS_COMPLETED
            || $order->delivery_status === Order::DELIVERY_STATUS_DELIVERED;
    }

    public function remainingReturnableQuantity(OrderItem $item): int
    {
        $reserved = ReturnRequestItem::query()
            ->where('order_item_id', $item->id)
            ->with('returnRequest:id,status')
            ->get()
            ->sum(function (ReturnRequestItem $returnItem) {
                $status = $returnItem->returnRequest?->status;

                return match ($status) {
                    ReturnRequest::STATUS_REQUESTED => (int) $returnItem->requested_quantity,
                    ReturnRequest::STATUS_APPROVED,
                    ReturnRequest::STATUS_RECEIVED,
                    ReturnRequest::STATUS_COMPLETED => (int) ($returnItem->approved_quantity ?? $returnItem->requested_quantity),
                    default => 0,
                };
            });

        return max(0, (int) $item->quantity - (int) $reserved);
    }

    public function createForCustomer(Order $order, User $user, array $items, ?string $notes = null): ReturnRequest
    {
        return DB::transaction(function () use ($order, $user, $items, $notes) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->canCustomerRequest($lockedOrder, $user)) {
                throw ValidationException::withMessages([
                    'return' => __('This order is not currently eligible for a return request.'),
                ]);
            }

            $selected = collect($items)
                ->filter(fn ($row) => (int) ($row['quantity'] ?? 0) > 0)
                ->values();

            if ($selected->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => __('Select at least one item and quantity to return.'),
                ]);
            }

            $orderItems = $lockedOrder->items()
                ->whereIn('id', $selected->pluck('order_item_id')->map(fn ($id) => (int) $id)->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($orderItems->count() !== $selected->count()) {
                throw ValidationException::withMessages([
                    'items' => __('One or more selected items do not belong to this order.'),
                ]);
            }

            $returnRequest = ReturnRequest::query()->create([
                'reference' => $this->nextReference(),
                'order_id' => $lockedOrder->id,
                'user_id' => $user->id,
                'status' => ReturnRequest::STATUS_REQUESTED,
                'customer_notes' => $this->nullableTrim($notes),
                'requested_at' => now(),
            ]);

            foreach ($selected as $row) {
                $orderItem = $orderItems[(int) $row['order_item_id']];
                $quantity = (int) $row['quantity'];
                $remaining = $this->remainingReturnableQuantity($orderItem);

                if ($quantity < 1 || $quantity > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => __('Requested return quantity exceeds the remaining returnable quantity for :product.', [
                            'product' => $orderItem->product_name,
                        ]),
                    ]);
                }

                $reasonCode = (string) ($row['reason_code'] ?? '');
                if (! array_key_exists($reasonCode, ReturnRequestItem::reasonOptions())) {
                    throw ValidationException::withMessages([
                        'items' => __('Choose a valid return reason for every selected item.'),
                    ]);
                }

                $resolution = (string) ($row['requested_resolution'] ?? ReturnRequestItem::RESOLUTION_REFUND);
                if (! array_key_exists($resolution, ReturnRequestItem::resolutionOptions())) {
                    throw ValidationException::withMessages([
                        'items' => __('Choose a valid requested resolution for every selected item.'),
                    ]);
                }

                ReturnRequestItem::query()->create([
                    'return_request_id' => $returnRequest->id,
                    'order_item_id' => $orderItem->id,
                    'requested_quantity' => $quantity,
                    'approved_quantity' => null,
                    'received_quantity' => 0,
                    'restock_quantity' => 0,
                    'reason_code' => $reasonCode,
                    'reason_details' => $this->nullableTrim($row['reason_details'] ?? null),
                    'requested_resolution' => $resolution,
                ]);
            }

            $this->activityLogService->log(
                'returns',
                'return_requested',
                __('Return request :reference was created.', ['reference' => $returnRequest->reference]),
                null,
                $returnRequest,
                [
                    'order_id' => $lockedOrder->id,
                    'user_id' => $user->id,
                ]
            );

            return $returnRequest->fresh(['items.orderItem', 'order']);
        });
    }

    public function approve(ReturnRequest $returnRequest, array $approvedQuantities, ?string $notes, User $actor): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest, $approvedQuantities, $notes, $actor) {
            $locked = $this->lockRequest($returnRequest);

            if (! $locked->canTransitionTo(ReturnRequest::STATUS_APPROVED)) {
                throw ValidationException::withMessages([
                    'status' => __('Only requested returns can be approved.'),
                ]);
            }

            $items = $locked->items()->orderBy('id')->lockForUpdate()->get();
            $approvedCount = 0;

            foreach ($items as $item) {
                $approved = max(0, (int) ($approvedQuantities[$item->id] ?? 0));

                if ($approved > (int) $item->requested_quantity) {
                    throw ValidationException::withMessages([
                        'items' => __('Approved quantity cannot exceed requested quantity.'),
                    ]);
                }

                $item->update(['approved_quantity' => $approved]);
                $approvedCount += $approved;
            }

            if ($approvedCount < 1) {
                throw ValidationException::withMessages([
                    'items' => __('Approve at least one unit, or reject the return request instead.'),
                ]);
            }

            $locked->update([
                'status' => ReturnRequest::STATUS_APPROVED,
                'review_notes' => $this->nullableTrim($notes),
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->activityLogService->log(
                'returns',
                'return_approved',
                __('Return request :reference was approved.', ['reference' => $locked->reference]),
                $actor->id,
                $locked,
                ['approved_units' => $approvedCount]
            );

            return $locked->fresh(['items.orderItem', 'order']);
        });
    }

    public function reject(ReturnRequest $returnRequest, string $notes, User $actor): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest, $notes, $actor) {
            $locked = $this->lockRequest($returnRequest);

            if (! $locked->canTransitionTo(ReturnRequest::STATUS_REJECTED)) {
                throw ValidationException::withMessages([
                    'status' => __('Only requested returns can be rejected.'),
                ]);
            }

            $locked->update([
                'status' => ReturnRequest::STATUS_REJECTED,
                'review_notes' => trim($notes),
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->activityLogService->log(
                'returns',
                'return_rejected',
                __('Return request :reference was rejected.', ['reference' => $locked->reference]),
                $actor->id,
                $locked,
            );

            return $locked->fresh(['items.orderItem', 'order']);
        });
    }

    public function cancelByCustomer(ReturnRequest $returnRequest, User $user): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest, $user) {
            $locked = $this->lockRequest($returnRequest);

            if ((int) $locked->user_id !== (int) $user->id) {
                abort(403);
            }

            if (! $locked->canTransitionTo(ReturnRequest::STATUS_CANCELLED)) {
                throw ValidationException::withMessages([
                    'status' => __('Only a requested return can be cancelled by the customer.'),
                ]);
            }

            $locked->update([
                'status' => ReturnRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            $this->activityLogService->log(
                'returns',
                'return_cancelled_by_customer',
                __('Return request :reference was cancelled by the customer.', ['reference' => $locked->reference]),
                null,
                $locked,
                ['user_id' => $user->id]
            );

            return $locked->fresh(['items.orderItem', 'order']);
        });
    }

    public function receive(
        ReturnRequest $returnRequest,
        array $receivedQuantities,
        array $restockQuantities,
        User $actor,
    ): ReturnRequest {
        return DB::transaction(function () use ($returnRequest, $receivedQuantities, $restockQuantities, $actor) {
            $locked = $this->lockRequest($returnRequest);

            if (! $locked->canTransitionTo(ReturnRequest::STATUS_RECEIVED)) {
                throw ValidationException::withMessages([
                    'status' => __('Only approved returns can be marked received.'),
                ]);
            }

            $items = $locked->items()
                ->with(['orderItem.product', 'orderItem.variant'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $receivedTotal = 0;
            $restockedTotal = 0;

            foreach ($items as $item) {
                $approved = (int) ($item->approved_quantity ?? 0);
                $received = max(0, (int) ($receivedQuantities[$item->id] ?? 0));
                $restock = max(0, (int) ($restockQuantities[$item->id] ?? 0));

                if ($approved === 0) {
                    if ($received !== 0 || $restock !== 0) {
                        throw ValidationException::withMessages([
                            'items' => __('Unapproved return items cannot be received or restocked.'),
                        ]);
                    }

                    continue;
                }

                if ($received !== $approved) {
                    throw ValidationException::withMessages([
                        'items' => __('V1 requires the full approved quantity to be received before moving the return to Received.'),
                    ]);
                }

                if ($restock > $received) {
                    throw ValidationException::withMessages([
                        'items' => __('Restock quantity cannot exceed received quantity.'),
                    ]);
                }

                $orderItem = $item->orderItem;
                if ($restock > 0 && ! $orderItem?->product) {
                    throw ValidationException::withMessages([
                        'items' => __('A received item cannot be restocked because its catalog product no longer exists.'),
                    ]);
                }

                if ($restock > 0) {
                    $this->inventoryService->increase(
                        $orderItem->product,
                        $orderItem->variant,
                        $restock,
                        InventoryMovement::TYPE_RETURN_RESTOCK,
                        [
                            'order_id' => $locked->order_id,
                            'reason' => 'Received RMA restock',
                            'movement_unit_cost' => (float) ($orderItem->unit_cost ?? 0),
                            'expiration_date' => $orderItem->expires_at,
                            'meta' => [
                                'return_request_id' => $locked->id,
                                'return_reference' => $locked->reference,
                                'return_request_item_id' => $item->id,
                                'received_by' => $actor->id,
                            ],
                        ]
                    );
                }

                $item->update([
                    'received_quantity' => $received,
                    'restock_quantity' => $restock,
                ]);

                $receivedTotal += $received;
                $restockedTotal += $restock;
            }

            if ($receivedTotal < 1) {
                throw ValidationException::withMessages([
                    'items' => __('At least one approved unit must be received.'),
                ]);
            }

            $locked->update([
                'status' => ReturnRequest::STATUS_RECEIVED,
                'received_by_user_id' => $actor->id,
                'received_at' => now(),
            ]);

            $this->activityLogService->log(
                'returns',
                'return_received',
                __('Return request :reference was received.', ['reference' => $locked->reference]),
                $actor->id,
                $locked,
                [
                    'received_units' => $receivedTotal,
                    'restocked_units' => $restockedTotal,
                ]
            );

            return $locked->fresh(['items.orderItem', 'order']);
        });
    }

    public function complete(
        ReturnRequest $returnRequest,
        float $refundAmount,
        ?int $exchangeOrderId,
        ?string $notes,
        User $actor,
    ): ReturnRequest {
        return DB::transaction(function () use ($returnRequest, $refundAmount, $exchangeOrderId, $notes, $actor) {
            $locked = $this->lockRequest($returnRequest);

            if (! $locked->canTransitionTo(ReturnRequest::STATUS_COMPLETED)) {
                throw ValidationException::withMessages([
                    'status' => __('Only received returns can be completed.'),
                ]);
            }

            $refundAmount = round(max(0, $refundAmount), 2);
            $notes = $this->nullableTrim($notes);

            $exchangeOrder = null;
            if ($exchangeOrderId) {
                if ((int) $exchangeOrderId === (int) $locked->order_id) {
                    throw ValidationException::withMessages([
                        'exchange_order_id' => __('The exchange order must be different from the original order.'),
                    ]);
                }

                $exchangeOrder = Order::query()->find($exchangeOrderId);
                if (! $exchangeOrder) {
                    throw ValidationException::withMessages([
                        'exchange_order_id' => __('The selected exchange order does not exist.'),
                    ]);
                }
            }

            if ($refundAmount <= 0 && ! $exchangeOrder && ! $notes) {
                throw ValidationException::withMessages([
                    'completion_notes' => __('Record a refund, link an exchange order, or enter completion notes before closing the return.'),
                ]);
            }

            if ($refundAmount > 0) {
                $this->orderActionService->refund(
                    $locked->order()->firstOrFail(),
                    $refundAmount,
                    __('Return :reference completed.', ['reference' => $locked->reference]),
                    $notes,
                    $actor->id,
                    $locked->id,
                );
            }

            $locked->update([
                'status' => ReturnRequest::STATUS_COMPLETED,
                'completion_notes' => $notes,
                'exchange_order_id' => $exchangeOrder?->id,
                'completed_by_user_id' => $actor->id,
                'completed_at' => now(),
            ]);

            $this->activityLogService->log(
                'returns',
                'return_completed',
                __('Return request :reference was completed.', ['reference' => $locked->reference]),
                $actor->id,
                $locked,
                [
                    'refund_amount' => $refundAmount,
                    'exchange_order_id' => $exchangeOrder?->id,
                ]
            );

            return $locked->fresh(['items.orderItem', 'order', 'refunds', 'exchangeOrder']);
        });
    }

    private function lockRequest(ReturnRequest $returnRequest): ReturnRequest
    {
        return ReturnRequest::query()
            ->whereKey($returnRequest->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function nextReference(): string
    {
        do {
            $reference = 'RMA-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (ReturnRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
