<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\ReturnRequestItem;

class OrderRevenueAllocationService
{
    public function allocate(Order $order): array
    {
        return collect($this->allocateCents($order))
            ->map(fn (int $cents) => round($cents / 100, 2))
            ->all();
    }

    public function allocateCents(Order $order): array
    {
        $order->loadMissing(['items', 'refunds.posReturnItems', 'refunds.returnRequest.items']);
        $items = $order->items->sortBy('id')->values();
        $itemsById = $items->keyBy('id');

        if ($items->isEmpty()) {
            return [];
        }

        $lineTotalCents = (int) $items->sum(
            fn ($item) => max(0, $this->moneyToCents($item->line_total))
        );
        $nonMerchandiseCents = max(0, $this->moneyToCents($order->shipping_total))
            + max(0, $this->moneyToCents($order->tax_total));
        $grossMerchandiseCents = min(
            $lineTotalCents,
            max(0, $this->moneyToCents($order->grand_total) - $nonMerchandiseCents)
        );
        $realizedMerchandiseCents = min(
            $grossMerchandiseCents,
            max(0, $this->moneyToCents($order->realized_revenue) - $nonMerchandiseCents)
        );
        $proRataAllocations = $this->allocateTargetCents($items, $realizedMerchandiseCents);

        $refunds = $order->refunds->sortBy('id')->values();
        $posReturnItems = $refunds
            ->flatMap(fn ($refund) => $refund->posReturnItems)
            ->filter(fn ($row) => (int) $row->order_item_id > 0 && $this->moneyToCents($row->amount) > 0)
            ->sortBy('id')
            ->values();
        $rmaRefunds = $refunds
            ->filter(fn ($refund) => $refund->posReturnItems->isEmpty()
                && $refund->returnRequest !== null
                && $refund->returnRequest->items->contains(
                    fn (ReturnRequestItem $item) => $item->requested_resolution === ReturnRequestItem::RESOLUTION_REFUND
                        && (int) $item->received_quantity > 0
                ))
            ->values();

        if ($posReturnItems->isEmpty() && $rmaRefunds->isEmpty()) {
            return $proRataAllocations;
        }

        // Product analytics must never absorb shipping or tax as merchandise revenue.
        // Generic refunds still fall back to pro-rata allocation within this bounded
        // merchandise budget when no exact POS/RMA line provenance exists.
        $refundBudgetCents = max(
            0,
            $grossMerchandiseCents - $realizedMerchandiseCents
        );

        if ($refundBudgetCents <= 0) {
            return $proRataAllocations;
        }

        $grossAllocations = $this->allocateTargetCents($items, $grossMerchandiseCents);
        $deductions = array_fill_keys(array_keys($grossAllocations), 0);
        $remainingRefundCents = $refundBudgetCents;

        foreach ($posReturnItems as $row) {
            if ($remainingRefundCents <= 0) {
                break;
            }

            $itemId = (int) $row->order_item_id;
            if (! array_key_exists($itemId, $grossAllocations)) {
                continue;
            }

            $requestedCents = min(
                $remainingRefundCents,
                max(0, $this->moneyToCents($row->amount))
            );
            $capacityCents = max(
                0,
                $grossAllocations[$itemId] - $deductions[$itemId]
            );
            $appliedCents = min($requestedCents, $capacityCents);

            $deductions[$itemId] += $appliedCents;
            $remainingRefundCents -= $appliedCents;
        }

        foreach ($rmaRefunds as $refund) {
            if ($remainingRefundCents <= 0) {
                break;
            }

            $rmaRefundCents = min(
                $remainingRefundCents,
                max(0, $this->moneyToCents($refund->amount))
            );

            if ($rmaRefundCents <= 0) {
                continue;
            }

            $rmaCapacities = [];
            foreach ($refund->returnRequest->items as $returnItem) {
                if ($returnItem->requested_resolution !== ReturnRequestItem::RESOLUTION_REFUND
                    || (int) $returnItem->received_quantity <= 0) {
                    continue;
                }

                $itemId = (int) $returnItem->order_item_id;
                $orderItem = $itemsById->get($itemId);
                if (! $orderItem || ! array_key_exists($itemId, $grossAllocations)) {
                    continue;
                }

                $soldQuantity = max(1, (int) $orderItem->quantity);
                $receivedQuantity = min($soldQuantity, max(0, (int) $returnItem->received_quantity));
                $eligibleCents = (int) round(
                    ($grossAllocations[$itemId] * $receivedQuantity) / $soldQuantity
                );
                $remainingCapacity = max(
                    0,
                    $grossAllocations[$itemId] - $deductions[$itemId]
                );
                $rmaCapacities[$itemId] = min(
                    $remainingCapacity,
                    (int) ($rmaCapacities[$itemId] ?? 0) + max(0, $eligibleCents)
                );
            }

            $appliedRma = $this->allocateAcrossCapacities($rmaCapacities, $rmaRefundCents);
            $appliedRmaTotal = array_sum($appliedRma);

            foreach ($appliedRma as $itemId => $cents) {
                $deductions[$itemId] += $cents;
            }
            $remainingRefundCents -= $appliedRmaTotal;
        }

        if ($remainingRefundCents > 0) {
            $capacities = [];
            foreach ($grossAllocations as $itemId => $grossCents) {
                $capacities[$itemId] = max(0, $grossCents - $deductions[$itemId]);
            }

            foreach ($this->allocateAcrossCapacities($capacities, $remainingRefundCents) as $itemId => $cents) {
                $deductions[$itemId] += $cents;
            }
        }

        $allocations = [];
        foreach ($grossAllocations as $itemId => $grossCents) {
            $allocations[$itemId] = max(0, $grossCents - $deductions[$itemId]);
        }

        return $allocations;
    }

    private function allocateTargetCents($items, int $targetCents): array
    {
        $lineTotalCents = (int) $items->sum(
            fn ($item) => max(0, $this->moneyToCents($item->line_total))
        );

        if ($lineTotalCents <= 0) {
            return [];
        }

        $lastRevenueIndex = $items
            ->keys()
            ->filter(fn ($index) => $this->moneyToCents($items[$index]->line_total) > 0)
            ->last();

        $allocatedRevenueCents = 0;
        $allocations = [];

        foreach ($items as $index => $item) {
            $lineCents = max(0, $this->moneyToCents($item->line_total));

            if ($lineCents <= 0) {
                $allocatedCents = 0;
            } elseif ($index === $lastRevenueIndex) {
                $allocatedCents = max(0, $targetCents - $allocatedRevenueCents);
            } else {
                $allocatedCents = (int) floor(
                    ($targetCents * $lineCents) / $lineTotalCents
                );
                $allocatedRevenueCents += $allocatedCents;
            }

            $allocations[(int) $item->id] = $allocatedCents;
        }

        return $allocations;
    }

    private function allocateAcrossCapacities(array $capacities, int $targetCents): array
    {
        $allocations = array_fill_keys(array_keys($capacities), 0);
        $positive = array_filter($capacities, fn (int $cents) => $cents > 0);
        $totalCapacity = array_sum($positive);
        $targetCents = min(max(0, $targetCents), $totalCapacity);

        if ($targetCents <= 0 || $totalCapacity <= 0) {
            return $allocations;
        }

        $allocated = 0;
        foreach ($positive as $itemId => $capacityCents) {
            $share = min(
                $capacityCents,
                (int) floor(($targetCents * $capacityCents) / $totalCapacity)
            );
            $allocations[$itemId] = $share;
            $allocated += $share;
        }

        $remainder = $targetCents - $allocated;
        while ($remainder > 0) {
            foreach ($positive as $itemId => $capacityCents) {
                if ($allocations[$itemId] >= $capacityCents) {
                    continue;
                }

                $allocations[$itemId]++;
                $remainder--;

                if ($remainder === 0) {
                    break;
                }
            }
        }

        return $allocations;
    }

    private function moneyToCents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
