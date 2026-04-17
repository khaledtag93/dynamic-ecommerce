<?php

namespace App\Services\Commerce;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    public function forProduct(Product $product, int $limit = 4): array
    {
        $baseRelations = [
            'translations',
            'category.translations',
            'mainImage',
            'productImages',
            'defaultVariant',
            'activeVariants',
        ];

        $product->loadMissing([
            'bundleProducts',
            'addonProducts',
            'relatedProducts',
        ]);

        $bundleProducts = $this->loadPivotProducts($product->bundleProducts(), $limit, $baseRelations);
        $addonProducts = $this->loadPivotProducts($product->addonProducts(), $limit, $baseRelations);
        $relatedProducts = $this->loadPivotProducts($product->relatedProducts(), $limit, $baseRelations);

        if ($bundleProducts->isEmpty()) {
            $bundleProducts = $this->fallbackByCategory($product, $limit);
        }

        if ($addonProducts->isEmpty()) {
            $addonProducts = $this->fallbackByCategory($product, $limit, $bundleProducts->pluck('id')->all());
        }

        if ($relatedProducts->isEmpty()) {
            $exclude = array_merge([$product->id], $bundleProducts->pluck('id')->all(), $addonProducts->pluck('id')->all());
            $relatedProducts = $this->fallbackByCategory($product, $limit, $exclude);
        }

        return [
            'bundleProducts' => $bundleProducts,
            'addonProducts' => $addonProducts,
            'relatedProducts' => $relatedProducts,
        ];
    }

    public function forCart(Collection $items, int $limit = 3): Collection
    {
        $productIdsInCart = $items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($productIdsInCart->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->with(['bundleProducts', 'addonProducts'])
            ->whereIn('id', $productIdsInCart)
            ->get();

        $candidateIds = collect();

        foreach ($products as $product) {
            $candidateIds = $candidateIds
                ->merge($product->bundleProducts->pluck('id'))
                ->merge($product->addonProducts->pluck('id'));
        }

        $candidateIds = $candidateIds
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $productIdsInCart->contains($id))
            ->unique()
            ->values();

        if ($candidateIds->isNotEmpty()) {
            return Product::query()
                ->with(['translations', 'category.translations', 'mainImage', 'productImages', 'defaultVariant', 'activeVariants'])
                ->where('status', 1)
                ->whereIn('id', $candidateIds)
                ->orderByRaw('case when quantity > 0 then 0 else 1 end')
                ->orderBy('id')
                ->take($limit)
                ->get();
        }

        $categoryIds = Product::query()
            ->whereIn('id', $productIdsInCart)
            ->pluck('category_id')
            ->filter()
            ->unique();

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->with(['translations', 'category.translations', 'mainImage', 'productImages', 'defaultVariant', 'activeVariants'])
            ->where('status', 1)
            ->whereIn('category_id', $categoryIds)
            ->whereNotIn('id', $productIdsInCart)
            ->orderByRaw('case when quantity > 0 then 0 else 1 end')
            ->orderByRaw('case when sale_price is not null and sale_price > 0 and base_price > sale_price then 0 else 1 end')
            ->latest('id')
            ->take($limit)
            ->get();
    }

    public function shippingProgress(float $subtotal, float $goal = 600.0): array
    {
        $remaining = max(0, $goal - $subtotal);
        $progress = $goal > 0 ? min(100, (int) round(($subtotal / $goal) * 100)) : 100;

        return [
            'goal' => round($goal, 2),
            'remaining' => round($remaining, 2),
            'progress' => $progress,
            'qualified' => $remaining <= 0,
        ];
    }

    protected function loadPivotProducts($relation, int $limit, array $with): Collection
    {
        return $relation
            ->with($with)
            ->where('products.status', 1)
            ->orderBy('product_related.sort_order')
            ->orderByDesc('products.id')
            ->take($limit)
            ->get();
    }

    protected function fallbackByCategory(Product $product, int $limit, array $exclude = []): Collection
    {
        $exclude = collect($exclude)->push($product->id)->filter()->unique()->values()->all();

        return Product::query()
            ->with(['translations', 'category.translations', 'mainImage', 'productImages', 'defaultVariant', 'activeVariants'])
            ->where('status', 1)
            ->where('category_id', $product->category_id)
            ->whereNotIn('id', $exclude)
            ->orderByRaw('case when quantity > 0 then 0 else 1 end')
            ->orderByRaw('case when sale_price is not null and sale_price > 0 and base_price > sale_price then 0 else 1 end')
            ->latest('id')
            ->take($limit)
            ->get();
    }
}
