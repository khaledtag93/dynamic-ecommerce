<?php

namespace App\Services\Commerce;

use App\Models\Order;

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
        $order->loadMissing(['items', 'refunds.posReturnItems']);
        $items = $order->items->sortBy('id')->values();

        if ($items->isEmpty()) {
            return [];
        }

        $realizedRevenueCents = max(0, $this->moneyToCents($order->realized_revenue));
        $proRataAllocations = $this->allocateTargetCents($items, $realizedRevenueCents);

        $posReturnItems = $order->refunds
            ->flatMap(fn ($refund) => $refund->posReturnItems)
            ->filter(fn ($row) => (int) $row->order_item_id > 0 && $this->moneyToCents($row->amount) > 0)
            ->sortBy('id')
            ->values();

        if ($posReturnItems->isEmpty()) {
            return $proRataAllocations;
        }

        $grossRevenueCents = max(0, $this->moneyToCents($order->grand_total));
        $refundBudgetCents = min(
            $grossRevenueCents,
            max(0, $this->moneyToCents($order->refund_total))
        );

        if ($refundBudgetCents <= 0) {
            return $proRataAllocations;
        }

        $grossAllocations = $this->allocateTargetCents($items, $grossRevenueCents);
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
