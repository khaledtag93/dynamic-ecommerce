<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\PosReturnItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use Illuminate\Support\Collection;

class ProfitService
{
    public function refreshOrderTotals(Order $order): Order
    {
        $items = $order->items()->get();
        $costTotal = (float) $items->sum(
            fn ($item) => (float) $item->unit_cost * (int) $item->quantity
        );
        $recoveredCost = $this->recoveredRestockCost($items);
        $unrecoveredCost = max(0, $costTotal - $recoveredCost);
        $profitTotal = $order->status === Order::STATUS_CANCELLED
            ? 0.0
            : (float) $order->realized_revenue - $unrecoveredCost;

        $order->update([
            'cost_total' => round($costTotal, 2),
            'profit_total' => round($profitTotal, 2),
        ]);

        return $order->fresh();
    }

    protected function recoveredRestockCost(Collection $items): float
    {
        if ($items->isEmpty()) {
            return 0.0;
        }

        $unitCosts = $items->mapWithKeys(
            fn ($item) => [(int) $item->id => (float) $item->unit_cost]
        );
        $itemIds = $unitCosts->keys()->all();

        $posRecovered = PosReturnItem::query()
            ->whereIn('order_item_id', $itemIds)
            ->where('restocked', true)
            ->get(['order_item_id', 'quantity'])
            ->sum(fn (PosReturnItem $item) => (float) ($unitCosts[(int) $item->order_item_id] ?? 0)
                * (int) $item->quantity);

        $rmaRecovered = ReturnRequestItem::query()
            ->whereIn('order_item_id', $itemIds)
            ->where('restock_quantity', '>', 0)
            ->whereHas('returnRequest', fn ($query) => $query->whereIn('status', [
                ReturnRequest::STATUS_RECEIVED,
                ReturnRequest::STATUS_COMPLETED,
            ]))
            ->get(['order_item_id', 'restock_quantity'])
            ->sum(fn (ReturnRequestItem $item) => (float) ($unitCosts[(int) $item->order_item_id] ?? 0)
                * (int) $item->restock_quantity);

        $costTotal = (float) $items->sum(
            fn ($item) => (float) $item->unit_cost * (int) $item->quantity
        );

        return round(min($costTotal, $posRecovered + $rmaRecovered), 2);
    }
}
