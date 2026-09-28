<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function increase(Product $product, ?ProductVariant $variant, int $quantity, string $type, array $context = []): InventoryMovement
    {
        return $this->apply($product, $variant, abs($quantity), $type, $context);
    }

    public function decrease(Product $product, ?ProductVariant $variant, int $quantity, string $type, array $context = []): InventoryMovement
    {
        $quantity = abs($quantity);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'cart' => 'Inventory quantity must be greater than zero.',
            ]);
        }

        if ($variant) {
            $affected = ProductVariant::query()
                ->whereKey($variant->getKey())
                ->where('stock', '>=', $quantity)
                ->decrement('stock', $quantity);

            if ($affected !== 1) {
                throw ValidationException::withMessages([
                    'cart' => 'One of the selected product variants no longer has enough stock. Please review your cart.',
                ]);
            }

            $variant->refresh();
            $balanceAfter = (int) $variant->stock;
        } else {
            $affected = Product::query()
                ->whereKey($product->getKey())
                ->where('quantity', '>=', $quantity)
                ->decrement('quantity', $quantity);

            if ($affected !== 1) {
                throw ValidationException::withMessages([
                    'cart' => 'One of the selected products no longer has enough stock. Please review your cart.',
                ]);
            }

            $product->refresh();
            $balanceAfter = (int) $product->quantity;
        }

        return InventoryMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'purchase_id' => $context['purchase_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'type' => $type,
            'reason' => $context['reason'] ?? null,
            'quantity_change' => -$quantity,
            'balance_after' => $balanceAfter,
            'unit_cost' => $context['movement_unit_cost'] ?? $context['unit_cost'] ?? 0,
            'expiration_date' => $context['expiration_date'] ?? null,
            'meta' => $context['meta'] ?? null,
        ]);
    }

    public function apply(Product $product, ?ProductVariant $variant, int $quantityChange, string $type, array $context = []): InventoryMovement
    {
        $valuationUnitCost = null;

        if ($quantityChange > 0) {
            if ($type === InventoryMovement::TYPE_PURCHASE_IN
                && array_key_exists('unit_cost', $context)
                && $context['unit_cost'] !== null) {
                $valuationUnitCost = (float) $context['unit_cost'];
            } elseif (in_array($type, [
                InventoryMovement::TYPE_REFUND_RESTOCK,
                InventoryMovement::TYPE_RETURN_RESTOCK,
                InventoryMovement::TYPE_RESERVATION_RELEASE,
            ], true)
                && array_key_exists('movement_unit_cost', $context)
                && $context['movement_unit_cost'] !== null) {
                $valuationUnitCost = (float) $context['movement_unit_cost'];
            }
        }

        $updatesValuation = $valuationUnitCost !== null;
        $stockBefore = (int) ($variant ? $variant->stock : $product->quantity);
        $costBefore = (float) (
            $variant
                ? ($variant->inventory_cost_price ?? $variant->cost_price ?? 0)
                : ($product->inventory_cost_price ?? $product->cost_price ?? 0)
        );
        $costAfter = $costBefore;

        if ($updatesValuation) {
            $costAfter = $this->movingAverageCost(
                $stockBefore,
                $costBefore,
                $quantityChange,
                $valuationUnitCost
            );
        }

        $target = $variant ?: $product;
        $stockColumn = $variant ? 'stock' : 'quantity';
        $expirationBefore = $target->expiration_date?->toDateString();
        $target->increment($stockColumn, $quantityChange);

        if ($updatesValuation) {
            $target->forceFill(['inventory_cost_price' => $costAfter])->save();
        }

        if (! empty($context['expiration_date'])) {
            $target->forceFill(['expiration_date' => $context['expiration_date']])->save();
        }

        $target->refresh();
        $balanceAfter = (int) $target->{$stockColumn};
        $expirationAfter = $target->expiration_date?->toDateString();
        $meta = $context['meta'] ?? null;

        if (! empty($context['expiration_date'])) {
            $meta = array_merge($meta ?? [], [
                'expiration_before' => $expirationBefore,
                'expiration_after' => $expirationAfter,
            ]);
        }

        if ($updatesValuation) {
            $meta = array_merge($meta ?? [], [
                'valuation_method' => 'moving_weighted_average',
                'valuation_unit_cost' => round($valuationUnitCost, 2),
                'valuation_cost_before' => round($costBefore, 2),
                'valuation_cost_after' => round($costAfter, 2),
                'stock_before' => $stockBefore,
                'stock_after' => $balanceAfter,
            ]);
        }

        return InventoryMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'purchase_id' => $context['purchase_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'type' => $type,
            'reason' => $context['reason'] ?? null,
            'quantity_change' => $quantityChange,
            'balance_after' => $balanceAfter,
            'unit_cost' => $context['movement_unit_cost'] ?? $context['unit_cost'] ?? 0,
            'expiration_date' => $context['expiration_date'] ?? null,
            'meta' => $meta,
        ]);
    }

    protected function movingAverageCost(int $stockBefore, float $costBefore, int $incomingQuantity, float $incomingCost): float
    {
        $valuedStockBefore = max(0, $stockBefore);

        if ($valuedStockBefore === 0) {
            return round(max(0, $incomingCost), 2);
        }

        $totalQuantity = $valuedStockBefore + max(0, $incomingQuantity);

        if ($totalQuantity < 1) {
            return round(max(0, $incomingCost), 2);
        }

        return round(
            (($valuedStockBefore * max(0, $costBefore)) + (max(0, $incomingQuantity) * max(0, $incomingCost)))
                / $totalQuantity,
            2
        );
    }
}
