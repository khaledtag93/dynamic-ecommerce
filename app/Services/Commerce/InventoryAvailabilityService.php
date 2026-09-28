<?php

namespace App\Services\Commerce;

use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventoryAvailabilityService
{
    public function sellableQuantity(Product $product, ?ProductVariant $variant = null): int
    {
        $lots = InventoryLot::query()
            ->where('product_id', $product->id)
            ->when(
                $variant,
                fn ($query) => $query->where('product_variant_id', $variant->id),
                fn ($query) => $query->whereNull('product_variant_id')
            );

        if (! (clone $lots)->exists()) {
            return max(0, (int) ($variant?->stock ?? $product->quantity));
        }

        return (int) (clone $lots)
            ->where('quantity_on_hand', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', today()->toDateString());
            })
            ->sum('quantity_on_hand');
    }

    public function sellableProductQuantity(Product $product): int
    {
        if (! $product->has_variants) {
            return $this->sellableQuantity($product);
        }

        $variants = $product->relationLoaded('activeVariants')
            ? $product->activeVariants
            : $product->activeVariants()->get();

        return (int) $variants->sum(
            fn (ProductVariant $variant) => $this->sellableQuantity($product, $variant)
        );
    }

    public function hydrateSellableQuantities(Collection $products): Collection
    {
        $instances = $products
            ->filter(fn ($product) => $product instanceof Product)
            ->values();

        if ($instances->isEmpty()) {
            return $products;
        }

        $productIds = $instances->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        $variantsByProduct = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('product_id');

        foreach ($instances as $product) {
            if (! $product->relationLoaded('activeVariants')) {
                $product->setRelation(
                    'activeVariants',
                    $variantsByProduct->get($product->id, collect())->values()
                );
            }
        }

        $lots = InventoryLot::query()
            ->selectRaw(
                'product_id, product_variant_id, COUNT(*) as tracked_count, COALESCE(SUM(CASE WHEN quantity_on_hand > 0 AND (expiration_date IS NULL OR expiration_date >= ?) THEN quantity_on_hand ELSE 0 END), 0) as sellable_stock',
                [today()->toDateString()]
            )
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'product_variant_id')
            ->get()
            ->keyBy(fn ($lot) => ((int) $lot->product_id).':'.((int) ($lot->product_variant_id ?? 0)));

        foreach ($instances as $product) {
            if (! $product->has_variants) {
                $key = ((int) $product->id).':0';
                $summary = $lots->get($key);
                $sellable = $summary
                    ? (int) $summary->sellable_stock
                    : max(0, (int) $product->quantity);

                $product->setAttribute('sellable_quantity', $sellable);

                continue;
            }

            $total = 0;

            foreach ($product->activeVariants as $variant) {
                $key = ((int) $product->id).':'.((int) $variant->id);
                $summary = $lots->get($key);
                $sellable = $summary
                    ? (int) $summary->sellable_stock
                    : max(0, (int) $variant->stock);

                $variant->setAttribute('sellable_quantity', $sellable);
                $total += $sellable;
            }

            $product->setAttribute('sellable_quantity', $total);
        }

        return $products;
    }

    public function lowStockItems(int $limit = 12): Collection
    {
        $today = today()->toDateString();

        $productLotCount = InventoryLot::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('inventory_lots.product_id', 'products.id')
            ->whereNull('inventory_lots.product_variant_id');
        $productSellable = InventoryLot::query()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN quantity_on_hand > 0 AND (expiration_date IS NULL OR expiration_date >= ?) THEN quantity_on_hand ELSE 0 END), 0)',
                [$today]
            )
            ->whereColumn('inventory_lots.product_id', 'products.id')
            ->whereNull('inventory_lots.product_variant_id');

        $productStockExpression = '(CASE WHEN EXISTS (
                SELECT 1 FROM inventory_lots
                WHERE inventory_lots.product_id = products.id
                  AND inventory_lots.product_variant_id IS NULL
            ) THEN (
                SELECT COALESCE(SUM(CASE WHEN quantity_on_hand > 0 AND (expiration_date IS NULL OR expiration_date >= ?) THEN quantity_on_hand ELSE 0 END), 0)
                FROM inventory_lots
                WHERE inventory_lots.product_id = products.id
                  AND inventory_lots.product_variant_id IS NULL
            ) ELSE products.quantity END)';

        $products = Product::query()
            ->select('products.*')
            ->selectSub($productLotCount, 'tracked_lot_count')
            ->selectSub($productSellable, 'sellable_stock')
            ->with('category')
            ->where('has_variants', false)
            ->whereRaw(
                $productStockExpression . ' <= GREATEST(COALESCE(products.reorder_point, 0), COALESCE(products.low_stock_threshold, 0))',
                [$today]
            )
            ->orderByRaw(
                $productStockExpression . ' - GREATEST(COALESCE(products.reorder_point, 0), COALESCE(products.low_stock_threshold, 0))',
                [$today]
            )
            ->take($limit)
            ->get();

        $variantLotCount = InventoryLot::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('inventory_lots.product_variant_id', 'product_variants.id');
        $variantSellable = InventoryLot::query()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN quantity_on_hand > 0 AND (expiration_date IS NULL OR expiration_date >= ?) THEN quantity_on_hand ELSE 0 END), 0)',
                [$today]
            )
            ->whereColumn('inventory_lots.product_variant_id', 'product_variants.id');

        $variantStockExpression = '(CASE WHEN EXISTS (
                SELECT 1 FROM inventory_lots
                WHERE inventory_lots.product_variant_id = product_variants.id
            ) THEN (
                SELECT COALESCE(SUM(CASE WHEN quantity_on_hand > 0 AND (expiration_date IS NULL OR expiration_date >= ?) THEN quantity_on_hand ELSE 0 END), 0)
                FROM inventory_lots
                WHERE inventory_lots.product_variant_id = product_variants.id
            ) ELSE product_variants.stock END)';

        $variants = ProductVariant::query()
            ->select('product_variants.*')
            ->selectSub($variantLotCount, 'tracked_lot_count')
            ->selectSub($variantSellable, 'sellable_stock')
            ->with('product.category')
            ->where('status', true)
            ->whereHas('product', fn ($query) => $query->where('has_variants', true))
            ->whereRaw(
                $variantStockExpression . ' <= COALESCE(product_variants.reorder_point, 0)',
                [$today]
            )
            ->orderByRaw(
                $variantStockExpression . ' - COALESCE(product_variants.reorder_point, 0)',
                [$today]
            )
            ->take($limit)
            ->get();

        return $products
            ->map(fn (Product $product) => [
                'product' => $product,
                'variant' => null,
                'stock' => (int) ((int) $product->tracked_lot_count > 0
                    ? $product->sellable_stock
                    : $product->quantity),
                'threshold' => max(
                    (int) $product->reorder_point,
                    (int) $product->low_stock_threshold
                ),
            ])
            ->concat($variants->map(fn (ProductVariant $variant) => [
                'product' => $variant->product,
                'variant' => $variant,
                'stock' => (int) ((int) $variant->tracked_lot_count > 0
                    ? $variant->sellable_stock
                    : $variant->stock),
                'threshold' => (int) $variant->reorder_point,
            ]))
            ->sortBy(fn (array $item) => $item['stock'] - $item['threshold'])
            ->take($limit)
            ->values();
    }

    public function expiryRiskItems(int $days = 30, int $limit = 12): Collection
    {
        $cutoff = today()->addDays($days);

        $lots = InventoryLot::query()
            ->with(['product.category', 'variant'])
            ->where('quantity_on_hand', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', $cutoff)
            ->orderBy('expiration_date')
            ->orderBy('id')
            ->take($limit)
            ->get()
            ->filter(fn (InventoryLot $lot) => $lot->product !== null)
            ->map(fn (InventoryLot $lot) => [
                'product' => $lot->product,
                'variant' => $lot->variant,
                'expiration_date' => $lot->expiration_date,
                'quantity' => (int) $lot->quantity_on_hand,
                'lot_code' => $lot->lot_code,
                'legacy' => false,
            ]);

        $remaining = max(0, $limit - $lots->count());

        if ($remaining === 0) {
            return $lots->values();
        }

        $legacyProducts = Product::query()
            ->with('category')
            ->where('has_variants', false)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', $cutoff)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('inventory_lots')
                    ->whereColumn('inventory_lots.product_id', 'products.id')
                    ->whereNull('inventory_lots.product_variant_id');
            })
            ->orderBy('expiration_date')
            ->take($remaining)
            ->get()
            ->map(fn (Product $product) => [
                'product' => $product,
                'variant' => null,
                'expiration_date' => $product->expiration_date,
                'quantity' => max(0, (int) $product->quantity),
                'lot_code' => null,
                'legacy' => true,
            ]);

        $legacyVariants = ProductVariant::query()
            ->with('product.category')
            ->where('stock', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', $cutoff)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('inventory_lots')
                    ->whereColumn('inventory_lots.product_variant_id', 'product_variants.id');
            })
            ->orderBy('expiration_date')
            ->take($remaining)
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'product' => $variant->product,
                'variant' => $variant,
                'expiration_date' => $variant->expiration_date,
                'quantity' => max(0, (int) $variant->stock),
                'lot_code' => null,
                'legacy' => true,
            ]);

        return $lots
            ->concat($legacyProducts)
            ->concat($legacyVariants)
            ->sortBy('expiration_date')
            ->take($limit)
            ->values();
    }

    public function applyLowStockProductFilter(Builder $query): Builder
    {
        $today = today()->toDateString();
        $productSellable = $this->directSellableExpression();
        $variantSellable = $this->variantSellableExpression();

        return $query->where(function (Builder $builder) use ($today, $productSellable, $variantSellable) {
            $builder
                ->where(function (Builder $simple) use ($today, $productSellable) {
                    $simple->where('products.has_variants', false)
                        ->whereRaw($productSellable . ' > 0', [$today])
                        ->whereRaw(
                            $productSellable . ' <= GREATEST(COALESCE(products.reorder_point, 0), COALESCE(products.low_stock_threshold, 0))',
                            [$today]
                        );
                })
                ->orWhereExists(function ($variants) use ($today, $variantSellable) {
                    $variants->selectRaw('1')
                        ->from('product_variants')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('product_variants.status', true)
                        ->whereRaw($variantSellable . ' > 0', [$today])
                        ->whereRaw($variantSellable . ' <= COALESCE(product_variants.reorder_point, 0)', [$today]);
                });
        });
    }

    public function applyUnsellableProductFilter(Builder $query): Builder
    {
        $today = today()->toDateString();
        $productSellable = $this->directSellableExpression();
        $variantSellable = $this->variantSellableExpression();

        return $query->where(function (Builder $builder) use ($today, $productSellable, $variantSellable) {
            $builder
                ->where(function (Builder $simple) use ($today, $productSellable) {
                    $simple->where('products.has_variants', false)
                        ->whereRaw($productSellable . ' <= 0', [$today]);
                })
                ->orWhere(function (Builder $variantProduct) use ($today, $variantSellable) {
                    $variantProduct->where('products.has_variants', true)
                        ->whereNotExists(function ($variants) use ($today, $variantSellable) {
                            $variants->selectRaw('1')
                                ->from('product_variants')
                                ->whereColumn('product_variants.product_id', 'products.id')
                                ->where('product_variants.status', true)
                                ->whereRaw($variantSellable . ' > 0', [$today]);
                        });
                });
        });
    }

    public function lowStockItemCount(): int
    {
        $today = today()->toDateString();
        $productSellable = $this->directSellableExpression();
        $variantSellable = $this->variantSellableExpression();

        $products = Product::query()
            ->where('has_variants', false)
            ->whereRaw(
                $productSellable . ' <= GREATEST(COALESCE(products.reorder_point, 0), COALESCE(products.low_stock_threshold, 0))',
                [$today]
            )
            ->count();

        $variants = ProductVariant::query()
            ->where('status', true)
            ->whereHas('product', fn ($query) => $query->where('has_variants', true))
            ->whereRaw($variantSellable . ' <= COALESCE(product_variants.reorder_point, 0)', [$today])
            ->count();

        return $products + $variants;
    }

    public function applySellableProductFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder
                ->where(function (Builder $simple) {
                    $simple->where('products.has_variants', false)
                        ->where(function (Builder $available) {
                            $this->applyDirectProductAvailability($available);
                        });
                })
                ->orWhereExists(function ($variants) {
                    $variants->selectRaw('1')
                        ->from('product_variants')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('product_variants.status', true)
                        ->where(function ($availableVariant) {
                            $availableVariant
                                ->whereExists(function ($lots) {
                                    $lots->selectRaw('1')
                                        ->from('inventory_lots')
                                        ->whereColumn('inventory_lots.product_variant_id', 'product_variants.id')
                                        ->where('inventory_lots.quantity_on_hand', '>', 0)
                                        ->where(function ($expiry) {
                                            $expiry->whereNull('inventory_lots.expiration_date')
                                                ->orWhereDate('inventory_lots.expiration_date', '>=', today()->toDateString());
                                        });
                                })
                                ->orWhere(function ($legacy) {
                                    $legacy->where('product_variants.stock', '>', 0)
                                        ->whereNotExists(function ($lots) {
                                            $lots->selectRaw('1')
                                                ->from('inventory_lots')
                                                ->whereColumn('inventory_lots.product_variant_id', 'product_variants.id');
                                        });
                                });
                        });
                });
        });
    }

    public function applySellableVariantFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $availableVariant) {
            $availableVariant
                ->whereExists(function ($lots) {
                    $lots->selectRaw('1')
                        ->from('inventory_lots')
                        ->whereColumn('inventory_lots.product_variant_id', 'product_variants.id')
                        ->where('inventory_lots.quantity_on_hand', '>', 0)
                        ->where(function ($expiry) {
                            $expiry->whereNull('inventory_lots.expiration_date')
                                ->orWhereDate('inventory_lots.expiration_date', '>=', today()->toDateString());
                        });
                })
                ->orWhere(function ($legacy) {
                    $legacy->where('product_variants.stock', '>', 0)
                        ->whereNotExists(function ($lots) {
                            $lots->selectRaw('1')
                                ->from('inventory_lots')
                                ->whereColumn('inventory_lots.product_variant_id', 'product_variants.id');
                        });
                });
        });
    }

    protected function directSellableExpression(): string
    {
        return "(CASE WHEN EXISTS (SELECT 1 FROM inventory_lots tracked_lots WHERE tracked_lots.product_id = products.id AND tracked_lots.product_variant_id IS NULL) THEN COALESCE((SELECT SUM(CASE WHEN sellable_lots.quantity_on_hand > 0 AND (sellable_lots.expiration_date IS NULL OR sellable_lots.expiration_date >= ?) THEN sellable_lots.quantity_on_hand ELSE 0 END) FROM inventory_lots sellable_lots WHERE sellable_lots.product_id = products.id AND sellable_lots.product_variant_id IS NULL), 0) ELSE GREATEST(COALESCE(products.quantity, 0), 0) END)";
    }

    protected function variantSellableExpression(): string
    {
        return "(CASE WHEN EXISTS (SELECT 1 FROM inventory_lots tracked_variant_lots WHERE tracked_variant_lots.product_variant_id = product_variants.id) THEN COALESCE((SELECT SUM(CASE WHEN sellable_variant_lots.quantity_on_hand > 0 AND (sellable_variant_lots.expiration_date IS NULL OR sellable_variant_lots.expiration_date >= ?) THEN sellable_variant_lots.quantity_on_hand ELSE 0 END) FROM inventory_lots sellable_variant_lots WHERE sellable_variant_lots.product_variant_id = product_variants.id), 0) ELSE GREATEST(COALESCE(product_variants.stock, 0), 0) END)";
    }

    protected function applyDirectProductAvailability(Builder $query): void
    {
        $query
            ->whereExists(function ($lots) {
                $lots->selectRaw('1')
                    ->from('inventory_lots')
                    ->whereColumn('inventory_lots.product_id', 'products.id')
                    ->whereNull('inventory_lots.product_variant_id')
                    ->where('inventory_lots.quantity_on_hand', '>', 0)
                    ->where(function ($expiry) {
                        $expiry->whereNull('inventory_lots.expiration_date')
                            ->orWhereDate('inventory_lots.expiration_date', '>=', today()->toDateString());
                    });
            })
            ->orWhere(function ($legacy) {
                $legacy->where('products.quantity', '>', 0)
                    ->whereNotExists(function ($lots) {
                        $lots->selectRaw('1')
                            ->from('inventory_lots')
                            ->whereColumn('inventory_lots.product_id', 'products.id')
                            ->whereNull('inventory_lots.product_variant_id');
                    });
            });
    }
}
