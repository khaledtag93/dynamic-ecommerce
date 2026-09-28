<?php

namespace App\Services\Commerce;

use App\Models\InventoryLot;
use App\Models\InventoryLotMovement;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryLotService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recordMovement(
        InventoryMovement $movement,
        Product $product,
        ?ProductVariant $variant,
        array $context = []
    ): array {
        if ((int) $movement->quantity_change === 0) {
            return [];
        }

        $physicalBefore = max(
            0,
            (int) $movement->balance_after - (int) $movement->quantity_change
        );
        $physicalAfter = max(0, (int) $movement->balance_after);
        $effectiveChange = $physicalAfter - $physicalBefore;

        $this->bootstrapOpeningBalance($movement, $product, $variant);

        if ($effectiveChange === 0) {
            $this->assertTargetBalance($movement, $product, $variant);

            return [];
        }

        $allocations = $effectiveChange > 0
            ? $this->recordIncrease($movement, $product, $variant, $context, $effectiveChange)
            : $this->recordDecrease($movement, $product, $variant, $context, abs($effectiveChange));

        $this->assertTargetBalance($movement, $product, $variant);

        if ((int) $movement->quantity_change < 0) {
            $this->syncOrderItemSnapshot($movement, $context, $allocations);
        }

        return $allocations;
    }

    protected function bootstrapOpeningBalance(
        InventoryMovement $movement,
        Product $product,
        ?ProductVariant $variant
    ): void {
        $expectedBefore = max(
            0,
            (int) $movement->balance_after - (int) $movement->quantity_change
        );
        $trackedBefore = (int) $this->targetLots($product, $variant)
            ->lockForUpdate()
            ->sum('quantity_on_hand');

        if ($trackedBefore === $expectedBefore) {
            return;
        }

        if ($trackedBefore === 0 && $expectedBefore === 0) {
            return;
        }

        if ($trackedBefore !== 0 || $expectedBefore < 1) {
            throw ValidationException::withMessages([
                'inventory' => __('Lot-tracked stock does not match the inventory balance before this movement.'),
            ]);
        }

        $meta = $movement->meta ?? [];
        $target = $variant ?: $product;
        $openingCost = data_get($meta, 'valuation_cost_before');

        if ($openingCost === null) {
            $openingCost = $target->inventory_cost_price ?? $target->cost_price ?? 0;
        }

        $openingExpiry = array_key_exists('expiration_before', $meta)
            ? $meta['expiration_before']
            : $target->expiration_date?->toDateString();

        InventoryLot::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'lot_code' => $this->lotCode('OPEN', $product, $variant, $movement->id),
            'source_type' => 'untracked_opening_balance',
            'initial_quantity' => $expectedBefore,
            'quantity_on_hand' => $expectedBefore,
            'unit_cost' => round((float) $openingCost, 2),
            'expiration_date' => $openingExpiry,
            'received_at' => now(),
            'meta' => [
                'bootstrapped_before_inventory_movement_id' => $movement->id,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recordIncrease(
        InventoryMovement $movement,
        Product $product,
        ?ProductVariant $variant,
        array $context,
        int $quantity
    ): array {

        if ($movement->type === InventoryMovement::TYPE_PURCHASE_IN) {
            return [$this->createSourceLot(
                $movement,
                $product,
                $variant,
                $quantity,
                'purchase_receipt',
                $context
            )];
        }

        if (in_array($movement->type, [
            InventoryMovement::TYPE_RESERVATION_RELEASE,
            InventoryMovement::TYPE_REFUND_RESTOCK,
            InventoryMovement::TYPE_RETURN_RESTOCK,
        ], true)) {
            return $this->restoreOriginalLots(
                $movement,
                $product,
                $variant,
                $quantity,
                $context
            );
        }

        return [$this->createSourceLot(
            $movement,
            $product,
            $variant,
            $quantity,
            'inventory_adjustment',
            $context
        )];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recordDecrease(
        InventoryMovement $movement,
        Product $product,
        ?ProductVariant $variant,
        array $context,
        int $quantity
    ): array {

        if ($movement->type === InventoryMovement::TYPE_PURCHASE_REVERSAL) {
            return [$this->reversePurchaseLot(
                $movement,
                $product,
                $variant,
                $quantity,
                $context
            )];
        }

        $sellableOnly = in_array($movement->type, [
            InventoryMovement::TYPE_ORDER_OUT,
            InventoryMovement::TYPE_ORDER_RESERVATION,
        ], true);

        $lots = $this->targetLots($product, $variant)
            ->where('quantity_on_hand', '>', 0)
            ->when($sellableOnly, fn ($query) => $query->where(function ($inner) {
                $inner->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', today()->toDateString());
            }))
            ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiration_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ((int) $lots->sum('quantity_on_hand') < $quantity) {
            $field = $sellableOnly
                ? 'cart'
                : ($movement->type === InventoryMovement::TYPE_ADJUSTMENT ? 'new_stock' : 'inventory');

            throw ValidationException::withMessages([
                $field => $sellableOnly
                    ? __('Not enough non-expired lot-tracked stock is available for this item.')
                    : __('Not enough lot-tracked stock is available for this inventory movement.'),
            ]);
        }

        $remaining = $quantity;
        $allocations = [];

        foreach ($lots as $lot) {
            if ($remaining < 1) {
                break;
            }

            $take = min($remaining, (int) $lot->quantity_on_hand);
            $lot->decrement('quantity_on_hand', $take);
            $lot->refresh();

            $this->createLotMovement(
                $lot,
                $movement,
                -$take,
                $context,
                ['allocation_policy' => $sellableOnly ? 'fefo' : 'inventory_reconciliation']
            );

            $allocations[] = $this->allocationPayload($lot, $take);
            $remaining -= $take;
        }

        return $allocations;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createSourceLot(
        InventoryMovement $movement,
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        string $sourceType,
        array $context
    ): array {
        $meta = $movement->meta ?? [];
        $lot = InventoryLot::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'source_inventory_movement_id' => $movement->id,
            'purchase_id' => $movement->purchase_id,
            'purchase_receipt_id' => data_get($meta, 'purchase_receipt_id'),
            'purchase_item_id' => data_get($meta, 'purchase_item_id'),
            'lot_code' => $this->lotCode(
                $sourceType === 'purchase_receipt' ? 'PO' : 'ADJ',
                $product,
                $variant,
                $movement->id
            ),
            'source_type' => $sourceType,
            'initial_quantity' => $quantity,
            'quantity_on_hand' => $quantity,
            'unit_cost' => (float) $movement->unit_cost,
            'expiration_date' => $movement->expiration_date,
            'received_at' => now(),
            'meta' => [
                'inventory_movement_id' => $movement->id,
                'reason' => $movement->reason,
            ],
        ]);

        $this->createLotMovement(
            $lot,
            $movement,
            $quantity,
            $context,
            ['source_type' => $sourceType]
        );

        return $this->allocationPayload($lot, $quantity);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function restoreOriginalLots(
        InventoryMovement $movement,
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        array $context
    ): array {
        $reservationId = $this->contextId($context, 'reservation_id');
        $orderItemId = $this->contextId($context, 'order_item_id');
        $historyQuery = InventoryLotMovement::query()
            ->whereHas('lot', fn ($query) => $this->scopeTarget($query, $product, $variant));

        if ($movement->type === InventoryMovement::TYPE_RESERVATION_RELEASE && $reservationId) {
            $historyQuery->where('order_stock_reservation_id', $reservationId);
        } elseif ($orderItemId) {
            $historyQuery->where('order_item_id', $orderItemId);
        } else {
            return [$this->createSourceLot(
                $movement,
                $product,
                $variant,
                $quantity,
                'legacy_restock',
                $context
            )];
        }

        $history = $historyQuery
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $outstanding = $history
            ->groupBy('inventory_lot_id')
            ->map(function (Collection $rows, $lotId) {
                $net = (int) $rows->sum('quantity_change');

                return [
                    'lot_id' => (int) $lotId,
                    'quantity' => max(0, -$net),
                    'first_out_id' => (int) optional(
                        $rows->first(fn (InventoryLotMovement $row) => $row->quantity_change < 0)
                    )->id,
                ];
            })
            ->filter(fn (array $row) => $row['quantity'] > 0)
            ->sortBy('first_out_id')
            ->values();

        $remaining = $quantity;
        $restored = [];

        foreach ($outstanding as $row) {
            if ($remaining < 1) {
                break;
            }

            $lot = InventoryLot::query()
                ->whereKey($row['lot_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $restore = min($remaining, $row['quantity']);
            $lot->increment('quantity_on_hand', $restore);
            $lot->refresh();

            $this->createLotMovement(
                $lot,
                $movement,
                $restore,
                $context,
                ['restored_original_allocation' => true]
            );
            $restored[] = $this->allocationPayload($lot, $restore);
            $remaining -= $restore;
        }

        if ($remaining > 0) {
            $restored[] = $this->createSourceLot(
                $movement,
                $product,
                $variant,
                $remaining,
                'legacy_restock',
                $context
            );
        }

        return $restored;
    }

    /**
     * @return array<string, mixed>
     */
    protected function reversePurchaseLot(
        InventoryMovement $movement,
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        array $context
    ): array {
        $originalMovementId = (int) data_get(
            $movement->meta,
            'original_inventory_movement_id'
        );

        $lot = InventoryLot::query()
            ->where('source_inventory_movement_id', $originalMovementId)
            ->lockForUpdate()
            ->first();

        if (! $lot
            || (int) $lot->product_id !== (int) $product->id
            || (int) ($lot->product_variant_id ?? 0) !== (int) ($variant?->id ?? 0)
            || (int) $lot->quantity_on_hand < $quantity) {
            throw ValidationException::withMessages([
                'receipt' => __('The purchase lot no longer matches the receipt being reversed.'),
            ]);
        }

        $lot->decrement('quantity_on_hand', $quantity);
        $lot->refresh();
        $this->createLotMovement(
            $lot,
            $movement,
            -$quantity,
            $context,
            [
                'purchase_receipt_reversal' => true,
                'original_inventory_movement_id' => $originalMovementId,
            ]
        );

        return $this->allocationPayload($lot, $quantity);
    }

    protected function createLotMovement(
        InventoryLot $lot,
        InventoryMovement $movement,
        int $quantityChange,
        array $context,
        array $meta = []
    ): InventoryLotMovement {
        return InventoryLotMovement::query()->create([
            'inventory_lot_id' => $lot->id,
            'inventory_movement_id' => $movement->id,
            'order_id' => $movement->order_id,
            'order_item_id' => $this->contextId($context, 'order_item_id'),
            'order_stock_reservation_id' => $this->contextId($context, 'reservation_id'),
            'quantity_change' => $quantityChange,
            'balance_after' => (int) $lot->quantity_on_hand,
            'meta' => $meta ?: null,
        ]);
    }

    protected function assertTargetBalance(
        InventoryMovement $movement,
        Product $product,
        ?ProductVariant $variant
    ): void {
        $tracked = (int) $this->targetLots($product, $variant)->sum('quantity_on_hand');
        $expected = max(0, (int) $movement->balance_after);

        if ($tracked !== $expected) {
            throw ValidationException::withMessages([
                'inventory' => __('Lot-tracked stock no longer matches the aggregate inventory balance.'),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocations
     */
    protected function syncOrderItemSnapshot(
        InventoryMovement $movement,
        array $context,
        array $allocations
    ): void {
        if (! in_array($movement->type, [
            InventoryMovement::TYPE_ORDER_OUT,
            InventoryMovement::TYPE_ORDER_RESERVATION,
        ], true)) {
            return;
        }

        $orderItemId = $this->contextId($context, 'order_item_id');

        if (! $orderItemId || $allocations === []) {
            return;
        }

        $item = OrderItem::query()->whereKey($orderItemId)->lockForUpdate()->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'inventory' => __('The order item for this lot allocation no longer exists.'),
            ]);
        }

        $meta = $item->meta ?? [];
        $meta['inventory_lot_allocations'] = $allocations;
        $expiries = collect($allocations)
            ->pluck('expiration_date')
            ->filter()
            ->sort()
            ->values();

        $item->forceFill([
            'expires_at' => $expiries->first(),
            'meta' => $meta,
        ])->save();
    }

    protected function targetLots(Product $product, ?ProductVariant $variant)
    {
        return $this->scopeTarget(InventoryLot::query(), $product, $variant);
    }

    protected function scopeTarget($query, Product $product, ?ProductVariant $variant)
    {
        return $query
            ->where('product_id', $product->id)
            ->when(
                $variant,
                fn ($inner) => $inner->where('product_variant_id', $variant->id),
                fn ($inner) => $inner->whereNull('product_variant_id')
            );
    }

    protected function contextId(array $context, string $key): ?int
    {
        $value = $context[$key] ?? data_get($context, 'meta.'.$key);

        return $value === null ? null : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function allocationPayload(InventoryLot $lot, int $quantity): array
    {
        return [
            'lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'quantity' => $quantity,
            'unit_cost' => (float) $lot->unit_cost,
            'expiration_date' => $lot->expiration_date?->toDateString(),
        ];
    }

    protected function lotCode(
        string $prefix,
        Product $product,
        ?ProductVariant $variant,
        int $movementId
    ): string {
        return sprintf(
            '%s-%s-%d-M%d',
            $prefix,
            $variant ? 'V'.$variant->id : 'P'.$product->id,
            $variant?->id ?? $product->id,
            $movementId
        );
    }
}
