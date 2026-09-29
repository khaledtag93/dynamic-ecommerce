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
        $order->loadMissing('items');
        $items = $order->items->sortBy('id')->values();

        $lineTotalCents = (int) $items->sum(
            fn ($item) => max(0, $this->moneyToCents($item->line_total))
        );
        $realizedRevenueCents = max(
            0,
            $this->moneyToCents($order->realized_revenue)
        );

        if ($items->isEmpty() || $lineTotalCents <= 0) {
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
                $allocatedCents = max(
                    0,
                    $realizedRevenueCents - $allocatedRevenueCents
                );
            } else {
                $allocatedCents = (int) floor(
                    ($realizedRevenueCents * $lineCents) / $lineTotalCents
                );
                $allocatedRevenueCents += $allocatedCents;
            }

            $allocations[(int) $item->id] = $allocatedCents;
        }

        return $allocations;
    }

    private function moneyToCents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
