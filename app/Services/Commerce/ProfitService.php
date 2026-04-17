<?php

namespace App\Services\Commerce;

use App\Models\Order;

class ProfitService
{
    public function refreshOrderTotals(Order $order): Order
    {
        $order->loadMissing('items');

        $costTotal = (float) $order->items->sum(fn ($item) => (float) $item->unit_cost * (int) $item->quantity);
        $profitTotal = (float) $order->items->sum('profit_amount') - (float) $order->refund_total;

        $order->update([
            'cost_total' => round($costTotal, 2),
            'profit_total' => round($profitTotal, 2),
        ]);

        return $order->fresh();
    }
}
