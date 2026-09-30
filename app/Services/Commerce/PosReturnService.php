<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosCashShift;
use App\Models\PosReturnItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Growth\GrowthAttributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosReturnService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected AdminActivityLogService $activityLogService,
        protected ReturnRequestService $returnRequestService,
        protected OrderRevenueAllocationService $orderRevenueAllocationService,
        protected ProfitService $profitService,
        protected AnalyticsTracker $analyticsTracker,
        protected GrowthAttributionService $growthAttributionService,
    ) {
    }

    /**
     * @param  array<int,int>  $quantities
     */
    public function process(Order $order, array $quantities, string $reason, ?string $notes, int $actorId): Order
    {
        return DB::transaction(function () use ($order, $quantities, $reason, $notes, $actorId) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->sales_channel !== Order::SALES_CHANNEL_POS) {
                throw ValidationException::withMessages([
                    'return' => __('Only POS sales can be returned from the cashier workspace.'),
                ]);
            }

            if (! in_array($lockedOrder->payment_status, [
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            ], true)) {
                throw ValidationException::withMessages([
                    'return' => __('This POS sale is not eligible for a return.'),
                ]);
            }

            if ($lockedOrder->payment_method === Order::PAYMENT_METHOD_POS_CASH) {
                $cashShift = PosCashShift::query()
                    ->where('cashier_user_id', $actorId)
                    ->whereNull('closed_at')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $cashShift) {
                    throw ValidationException::withMessages([
                        'cash_shift' => __('Open a cash shift before processing a cash refund.'),
                    ]);
                }
            }

            $items = OrderItem::query()
                ->where('order_id', $lockedOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $previous = PosReturnItem::query()
                ->selectRaw('order_item_id, SUM(quantity) as returned_quantity')
                ->whereIn('order_item_id', $items->keys())
                ->groupBy('order_item_id')
                ->pluck('returned_quantity', 'order_item_id');

            $itemIds = $items->keys()->map(fn ($id) => (int) $id)->all();
            $rmaReserved = $this->returnRequestService->rmaReservedQuantities($itemIds);
            $rmaReceivedRefunds = ReturnRequestItem::query()
                ->selectRaw('order_item_id, SUM(received_quantity) as returned_quantity')
                ->whereIn('order_item_id', $itemIds)
                ->where('requested_resolution', ReturnRequestItem::RESOLUTION_REFUND)
                ->where('received_quantity', '>', 0)
                ->whereHas('returnRequest', fn ($query) => $query->whereIn('status', [
                    ReturnRequest::STATUS_RECEIVED,
                    ReturnRequest::STATUS_COMPLETED,
                ]))
                ->groupBy('order_item_id')
                ->pluck('returned_quantity', 'order_item_id');
            $allocationOrder = $lockedOrder->fresh(['items', 'refunds.posReturnItems', 'refunds.returnRequest.items']);
            $grossAllocations = $this->orderRevenueAllocationService->grossAllocateCents($allocationOrder);
            $realizedAllocations = $this->orderRevenueAllocationService->allocateCents($allocationOrder);

            $selected = [];
            $refundAmountCents = 0;

            foreach ($quantities as $itemId => $quantity) {
                $quantity = (int) $quantity;

                if ($quantity <= 0) {
                    continue;
                }

                /** @var OrderItem|null $item */
                $item = $items->get((int) $itemId);

                if (! $item) {
                    throw ValidationException::withMessages([
                        'return' => __('One of the selected POS items does not belong to this sale.'),
                    ]);
                }

                $alreadyReturned = (int) ($previous[$item->id] ?? 0);
                $reservedByRma = (int) ($rmaReserved[$item->id] ?? 0);
                $remainingQuantity = max(0, (int) $item->quantity - $alreadyReturned - $reservedByRma);

                if ($quantity > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'items' => __('Return quantity cannot exceed the remaining sold quantity.'),
                    ]);
                }

                $soldQuantity = max(1, (int) $item->quantity);
                $linePaidCents = max(0, (int) ($grossAllocations[$item->id] ?? 0));
                $alreadyRefundedCents = max(
                    0,
                    $linePaidCents - (int) ($realizedAllocations[$item->id] ?? 0)
                );
                $physicallyReturnedBefore = min(
                    $soldQuantity,
                    $alreadyReturned + (int) ($rmaReceivedRefunds[$item->id] ?? 0)
                );
                $physicallyReturnedAfter = min(
                    $soldQuantity,
                    $physicallyReturnedBefore + $quantity
                );
                $cumulativeCapacityCents = (int) round(
                    ($linePaidCents * $physicallyReturnedAfter) / $soldQuantity
                );
                $lineAmountCents = max(
                    0,
                    $cumulativeCapacityCents - $alreadyRefundedCents
                );

                $selected[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'amount' => round($lineAmountCents / 100, 2),
                ];
                $refundAmountCents += $lineAmountCents;
            }

            if ($selected === []) {
                throw ValidationException::withMessages([
                    'items' => __('Select at least one item quantity to return.'),
                ]);
            }

            $refundAmount = round($refundAmountCents / 100, 2);

            $alreadyRefunded = round((float) $lockedOrder->refunds()->sum('amount'), 2);
            $refundableBalance = round(max(0, (float) $lockedOrder->grand_total - $alreadyRefunded), 2);

            if ($refundAmount > $refundableBalance) {
                throw ValidationException::withMessages([
                    'return' => __('Calculated return amount exceeds the remaining refundable balance.'),
                ]);
            }

            $refund = $lockedOrder->refunds()->create([
                'amount' => $refundAmount,
                'reason' => $reason,
                'notes' => $notes,
                'processed_by' => $actorId,
                'processed_at' => now(),
            ]);

            foreach ($selected as $line) {
                /** @var OrderItem $item */
                $item = $line['item'];
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
                $variant = $item->product_variant_id
                    ? ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->first()
                    : null;

                $lineWasVariantBased = filled($item->variant_name) || $item->product_variant_id !== null;

                if (
                    ! $product
                    || ($lineWasVariantBased && ! $variant)
                    || ($variant && (int) $variant->product_id !== (int) $product->id)
                ) {
                    throw ValidationException::withMessages([
                        'return' => __('A returned item can no longer be matched to its inventory record.'),
                    ]);
                }

                PosReturnItem::query()->create([
                    'order_refund_id' => $refund->id,
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $item->id,
                    'quantity' => $line['quantity'],
                    'amount' => $line['amount'],
                    'restocked' => true,
                ]);

                $this->inventoryService->increase(
                    $product,
                    $variant,
                    $line['quantity'],
                    InventoryMovement::TYPE_REFUND_RESTOCK,
                    [
                        'order_id' => $lockedOrder->id,
                        'reason' => 'POS item return',
                        'movement_unit_cost' => (float) ($item->unit_cost ?? 0),
                        'expiration_date' => $item->expires_at,
                        'meta' => [
                            'order_number' => $lockedOrder->order_number,
                            'order_refund_id' => $refund->id,
                            'order_item_id' => $item->id,
                            'returned_by' => $actorId,
                        ],
                    ]
                );
            }

            $newRefundTotal = round($alreadyRefunded + $refundAmount, 2);
            $newPaymentStatus = $lockedOrder->payment_status;

            if ($refundAmount > 0) {
                $newPaymentStatus = $newRefundTotal >= (float) $lockedOrder->grand_total
                    ? Order::PAYMENT_STATUS_REFUNDED
                    : Order::PAYMENT_STATUS_PARTIALLY_REFUNDED;

                $lockedOrder->update([
                    'refund_total' => $newRefundTotal,
                    'refunded_at' => now(),
                    'payment_status' => $newPaymentStatus,
                ]);
            }

            $this->profitService->refreshOrderTotals($lockedOrder);
            $this->growthAttributionService->refreshOrderAttribution((int) $lockedOrder->id);
            $this->analyticsTracker->syncRealizedPurchase($lockedOrder->fresh(['items']));

            if ($newPaymentStatus === Order::PAYMENT_STATUS_REFUNDED) {
                $refundedAt = now();

                $lockedOrder->payments()
                    ->where('status', Payment::STATUS_PAID)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (Payment $payment) use ($refundedAt) {
                        $meta = $payment->meta ?? [];
                        $events = $meta['events'] ?? [];
                        $events[] = [
                            'event' => 'pos_return_completed',
                            'message' => __('Payment was marked refunded after all POS sale items were returned.'),
                            'at' => $refundedAt->toDateTimeString(),
                        ];
                        $meta['events'] = array_slice($events, -20);

                        $payment->update([
                            'status' => Payment::STATUS_REFUNDED,
                            'refunded_at' => $refundedAt,
                            'meta' => $meta,
                        ]);
                    });
            }

            $this->activityLogService->log(
                'pos',
                'pos_return_completed',
                __('POS return recorded for sale :order.', ['order' => $lockedOrder->order_number]),
                $actorId,
                $lockedOrder,
                [
                    'order_refund_id' => $refund->id,
                    'refund_amount' => $refundAmount,
                    'reason' => $reason,
                    'items' => collect($selected)->map(fn (array $line) => [
                        'order_item_id' => $line['item']->id,
                        'quantity' => $line['quantity'],
                        'amount' => $line['amount'],
                    ])->values()->all(),
                ]
            );

            return $lockedOrder->fresh(['items', 'refunds', 'payments']);
        });
    }

    /**
     * @return array<int,int>
     */
    public function returnedQuantities(Order $order): array
    {
        return PosReturnItem::query()
            ->selectRaw('pos_return_items.order_item_id, SUM(pos_return_items.quantity) as returned_quantity')
            ->join('order_items', 'order_items.id', '=', 'pos_return_items.order_item_id')
            ->where('order_items.order_id', $order->id)
            ->groupBy('pos_return_items.order_item_id')
            ->pluck('returned_quantity', 'order_item_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }
}
