<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected AdminActivityLogService $activityLogService,
    ) {}

    public function setStock(int $productId, ?int $variantId, int $expectedStock, int $newStock, string $reason, ?int $actorId, string $source = 'manual_adjustment'): ?InventoryMovement
    {
        if ($newStock < 0 || trim($reason) === '') {
            throw ValidationException::withMessages([
                'new_stock' => __('Enter a valid stock count and reason.'),
            ]);
        }

        return DB::transaction(function () use ($productId, $variantId, $expectedStock, $newStock, $reason, $actorId, $source) {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->first();
            if (! $product) {
                throw ValidationException::withMessages([
                    'product_id' => __('The selected product is no longer available.'),
                ]);
            }

            $variant = $variantId
                ? ProductVariant::query()->whereKey($variantId)->lockForUpdate()->first()
                : null;

            if (($product->has_variants && (! $variant || (int) $variant->product_id !== (int) $product->id)) ||
                (! $product->has_variants && $variantId)) {
                throw ValidationException::withMessages([
                    'variant_id' => __('Choose a variant belonging to this product.'),
                ]);
            }

            $currentStock = (int) ($variant ? $variant->stock : $product->quantity);
            if ($currentStock !== $expectedStock) {
                throw ValidationException::withMessages([
                    'expected_stock' => __('Stock changed since you opened this form. Reload and review the latest quantity.'),
                ]);
            }

            $change = $newStock - $currentStock;
            if ($change === 0) {
                return null;
            }

            $movement = $this->inventoryService->apply($product, $variant, $change, InventoryMovement::TYPE_ADJUSTMENT, [
                'reason' => trim($reason),
                'movement_unit_cost' => (float) ($variant?->cost_price ?? $product->cost_price ?? 0),
                'meta' => [
                    'source' => $source,
                    'stock_before' => $currentStock,
                    'stock_after' => $newStock,
                    'admin_user_id' => $actorId,
                ],
            ]);

            $this->activityLogService->log(
                'inventory',
                'stock_adjusted',
                __('Stock adjusted from :before to :after.', [
                    'before' => $currentStock,
                    'after' => $newStock,
                ]),
                $actorId,
                $movement,
                ['product_id' => $product->id, 'variant_id' => $variant?->id, 'stock_before' => $currentStock, 'stock_after' => $newStock],
            );

            return $movement;
        });
    }
}
