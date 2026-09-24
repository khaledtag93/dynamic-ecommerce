<?php

namespace App\Services\Admin;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\AdminActivityLogService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CatalogStockAuditService
{
    public function __construct(protected AdminActivityLogService $activityLogService) {}

    /**
     * Called inside the product editor's transaction before any catalog writes.
     *
     * @return array{0: Product, 1: Collection}
     */
    public function lockAndCheck(int $productId, ?int $expectedQuantity, array $expectedVariantStocks): array
    {
        $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
        $variants = $product->variants()->lockForUpdate()->get()->keyBy('id');

        $expected = collect($expectedVariantStocks)
            ->mapWithKeys(fn ($stock, $id) => [(int) $id => (int) $stock]);

        if ($expectedQuantity === null || (int) $product->quantity !== $expectedQuantity ||
            $variants->keys()->sort()->values()->all() !== $expected->keys()->sort()->values()->all()) {
            $this->staleStock();
        }

        foreach ($variants as $variant) {
            if ((int) $variant->stock !== $expected->get($variant->id)) {
                $this->staleStock();
            }
        }

        return [$product, $variants];
    }

    public function guardStructure(?Product $original, Collection $existingVariants, bool $hasVariants, array $submittedVariants): void
    {
        if (! $original) {
            return;
        }

        if (! $original->has_variants && $hasVariants && (int) $original->quantity !== 0) {
            throw ValidationException::withMessages([
                'variants' => __('Set simple-product stock to zero before switching to variants.'),
            ]);
        }

        $keptIds = collect($submittedVariants)->pluck('id')->filter()->map(fn ($id) => (int) $id);

        foreach ($existingVariants as $variant) {
            if ((! $hasVariants || ! $keptIds->contains((int) $variant->id)) && (int) $variant->stock !== 0) {
                throw ValidationException::withMessages([
                    'variants' => __('Set a variant stock to zero before removing it.'),
                ]);
            }
        }
    }

    /** Record the committed stock difference inside the same transaction as the catalog save. */
    public function recordChanges(?Product $original, Collection $existingVariants, Product $saved, ?int $actorId): void
    {
        $saved->refresh();
        $this->record($saved, null, (int) ($original?->quantity ?? 0), (int) $saved->quantity, $actorId);

        foreach ($saved->variants()->get() as $variant) {
            $this->record(
                $saved,
                $variant,
                (int) ($existingVariants->get($variant->id)?->stock ?? 0),
                (int) $variant->stock,
                $actorId
            );
        }
    }

    protected function record(Product $product, ?ProductVariant $variant, int $before, int $after, ?int $actorId): void
    {
        if ($before === $after) {
            return;
        }

        $movement = InventoryMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'reason' => __('Product editor stock update'),
            'quantity_change' => $after - $before,
            'balance_after' => $after,
            'unit_cost' => $variant?->cost_price ?? $product->cost_price ?? 0,
            'meta' => [
                'source' => 'catalog_editor',
                'stock_before' => $before,
                'stock_after' => $after,
                'admin_user_id' => $actorId,
                'variant_sku' => $variant?->sku,
            ],
        ]);

        $this->activityLogService->log(
            'inventory',
            'stock_adjusted',
            __('Stock adjusted from :before to :after.', ['before' => $before, 'after' => $after]),
            $actorId,
            $movement,
            ['product_id' => $product->id, 'variant_id' => $variant?->id, 'stock_before' => $before, 'stock_after' => $after]
        );
    }

    protected function staleStock(): void
    {
        throw ValidationException::withMessages([
            'stock' => __('Stock changed since you opened this form. Reload and review the latest quantity.'),
        ]);
    }
}
