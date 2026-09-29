<?php

namespace App\Services\Commerce;

use App\Models\InventoryLotMovement;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosReturnItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use Illuminate\Support\Collection;

class ProfitService
{
    public function calculateOrderEconomics(Order $order): array
    {
        $items = $order->items()->get();
        $originalConsumedCostCents = (int) $items->sum(
            fn (OrderItem $item) => $this->itemCostCents($item)
        );
        $recoveredRestockCostCents = $this->recoveredRestockCostCents($items);
        $realizedRevenueCents = $order->status === Order::STATUS_CANCELLED
            ? 0
            : $this->moneyToCents($order->realized_revenue);
        $realizedCogsCents = $order->status === Order::STATUS_CANCELLED
            ? 0
            : max(0, $originalConsumedCostCents - $recoveredRestockCostCents);
        $profitTotalCents = $realizedRevenueCents - $realizedCogsCents;

        return [
            'original_consumed_cost' => $this->centsToMoney($originalConsumedCostCents),
            'recovered_restock_cost' => $this->centsToMoney($recoveredRestockCostCents),
            'realized_cogs' => $this->centsToMoney($realizedCogsCents),
            'realized_revenue' => $this->centsToMoney($realizedRevenueCents),
            'profit_total' => $this->centsToMoney($profitTotalCents),
        ];
    }

    public function calculateOrderTotals(Order $order): array
    {
        $economics = $this->calculateOrderEconomics($order);

        return [
            // cost_total is the persisted legacy field for original consumed cost.
            // Realized COGS is original consumed cost less recovered restock cost.
            'cost_total' => $economics['original_consumed_cost'],
            'profit_total' => $economics['profit_total'],
        ];
    }

    public function calculateOrderItemProfitAmount(OrderItem $item): string
    {
        return $this->centsToMoney(
            $this->moneyToCents($item->line_total) - $this->itemCostCents($item)
        );
    }

    public function countOrderItemProfitChanges(Order $order): int
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->get();

        return $items->filter(
            fn (OrderItem $item) => $this->moneyToCents($item->profit_amount)
                !== $this->moneyToCents($this->calculateOrderItemProfitAmount($item))
        )->count();
    }

    public function refreshOrderItemProfits(Order $order): int
    {
        $changed = 0;

        foreach ($order->items()->get() as $item) {
            $expected = $this->calculateOrderItemProfitAmount($item);

            if ($this->moneyToCents($item->profit_amount) === $this->moneyToCents($expected)) {
                continue;
            }

            $item->forceFill(['profit_amount' => $expected])->save();
            $changed++;
        }

        return $changed;
    }

    public function refreshOrderTotals(Order $order): Order
    {
        $this->refreshOrderItemProfits($order);
        $order->update($this->calculateOrderTotals($order));

        return $order->fresh();
    }

    protected function itemCostCents(OrderItem $item): int
    {
        return $this->allocationCostCents($item)
            ?? ($this->moneyToCents($item->unit_cost) * (int) $item->quantity);
    }

    protected function allocationCostCents(OrderItem $item): ?int
    {
        $allocations = data_get($item->meta, 'inventory_lot_allocations');

        if (! is_array($allocations) || $allocations === []) {
            return null;
        }

        $quantity = 0;
        $costCents = 0;

        foreach ($allocations as $allocation) {
            if (! is_array($allocation)
                || ! array_key_exists('quantity', $allocation)
                || ! array_key_exists('unit_cost', $allocation)) {
                return null;
            }

            $allocatedQuantity = (int) $allocation['quantity'];

            if ($allocatedQuantity < 1) {
                return null;
            }

            $quantity += $allocatedQuantity;
            $costCents += $allocatedQuantity * $this->moneyToCents($allocation['unit_cost']);
        }

        return $quantity === (int) $item->quantity ? $costCents : null;
    }

    protected function recoveredRestockCostCents(Collection $items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $lotTrackedItemIds = $items
            ->filter(fn (OrderItem $item) => $this->allocationCostCents($item) !== null)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $lotRecoveredCents = $lotTrackedItemIds === []
            ? 0
            : (int) InventoryLotMovement::query()
                ->with('lot:id,unit_cost')
                ->whereIn('order_item_id', $lotTrackedItemIds)
                ->where('quantity_change', '>', 0)
                ->whereHas('inventoryMovement', fn ($query) => $query->whereIn('type', [
                    InventoryMovement::TYPE_REFUND_RESTOCK,
                    InventoryMovement::TYPE_RETURN_RESTOCK,
                ]))
                ->get()
                ->sum(fn (InventoryLotMovement $movement) =>
                    (int) $movement->quantity_change
                    * $this->moneyToCents($movement->lot?->unit_cost ?? 0)
                );

        $legacyItems = $items->reject(
            fn (OrderItem $item) => in_array((int) $item->id, $lotTrackedItemIds, true)
        );

        if ($legacyItems->isEmpty()) {
            return min(
                (int) $items->sum(fn (OrderItem $item) => $this->itemCostCents($item)),
                $lotRecoveredCents
            );
        }

        $unitCostCents = $legacyItems->mapWithKeys(
            fn (OrderItem $item) => [(int) $item->id => $this->moneyToCents($item->unit_cost)]
        );
        $legacyItemIds = $unitCostCents->keys()->all();

        $posRecoveredCents = (int) PosReturnItem::query()
            ->whereIn('order_item_id', $legacyItemIds)
            ->where('restocked', true)
            ->get(['order_item_id', 'quantity'])
            ->sum(fn (PosReturnItem $item) =>
                (int) ($unitCostCents[(int) $item->order_item_id] ?? 0) * (int) $item->quantity
            );

        $rmaRecoveredCents = (int) ReturnRequestItem::query()
            ->whereIn('order_item_id', $legacyItemIds)
            ->where('restock_quantity', '>', 0)
            ->whereHas('returnRequest', fn ($query) => $query->whereIn('status', [
                ReturnRequest::STATUS_RECEIVED,
                ReturnRequest::STATUS_COMPLETED,
            ]))
            ->get(['order_item_id', 'restock_quantity'])
            ->sum(fn (ReturnRequestItem $item) =>
                (int) ($unitCostCents[(int) $item->order_item_id] ?? 0) * (int) $item->restock_quantity
            );

        $costTotalCents = (int) $items->sum(
            fn (OrderItem $item) => $this->itemCostCents($item)
        );

        return min(
            $costTotalCents,
            $lotRecoveredCents + $posRecoveredCents + $rmaRecoveredCents
        );
    }

    private function moneyToCents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
