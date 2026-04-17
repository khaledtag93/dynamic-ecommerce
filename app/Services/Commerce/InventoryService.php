<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;

class InventoryService
{
    public function increase(Product $product, ?ProductVariant $variant, int $quantity, string $type, array $context = []): InventoryMovement
    {
        return $this->apply($product, $variant, abs($quantity), $type, $context);
    }

    public function decrease(Product $product, ?ProductVariant $variant, int $quantity, string $type, array $context = []): InventoryMovement
    {
        return $this->apply($product, $variant, -abs($quantity), $type, $context);
    }

    public function apply(Product $product, ?ProductVariant $variant, int $quantityChange, string $type, array $context = []): InventoryMovement
    {
        if ($variant) {
            $variant->increment('stock', $quantityChange);
            if (array_key_exists('unit_cost', $context) && $context['unit_cost'] !== null) {
                $variant->forceFill(['cost_price' => $context['unit_cost']])->save();
            }
            if (! empty($context['expiration_date'])) {
                $variant->forceFill(['expiration_date' => $context['expiration_date']])->save();
            }
            $balanceAfter = (int) $variant->fresh()->stock;
        } else {
            $product->increment('quantity', $quantityChange);
            if (array_key_exists('unit_cost', $context) && $context['unit_cost'] !== null) {
                $product->forceFill(['cost_price' => $context['unit_cost']])->save();
            }
            if (! empty($context['expiration_date'])) {
                $product->forceFill(['expiration_date' => $context['expiration_date']])->save();
            }
            $balanceAfter = (int) $product->fresh()->quantity;
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
            'unit_cost' => $context['unit_cost'] ?? 0,
            'expiration_date' => $context['expiration_date'] ?? null,
            'meta' => $context['meta'] ?? null,
        ]);
    }
}
