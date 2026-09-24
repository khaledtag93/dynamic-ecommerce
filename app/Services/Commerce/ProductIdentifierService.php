<?php

namespace App\Services\Commerce;

use App\Exceptions\ProductIdentifierAmbiguityException;
use App\Models\Product;
use App\Models\ProductVariant;

class ProductIdentifierService
{
    /**
     * Resolve one exact barcode without guessing between legacy duplicates.
     *
     * @return array{
     *     product: Product,
     *     variant: ProductVariant|null,
     *     match_type: 'product'|'variant',
     *     matched_by: 'barcode',
     *     requires_variant_selection: bool
     * }|null
     */
    public function resolveBarcode(string $barcode): ?array
    {
        return $this->resolveByColumn('barcode', $barcode);
    }

    /**
     * Resolve one exact SKU across products and variants.
     *
     * @return array{
     *     product: Product,
     *     variant: ProductVariant|null,
     *     match_type: 'product'|'variant',
     *     matched_by: 'sku',
     *     requires_variant_selection: bool
     * }|null
     */
    public function resolveSku(string $sku): ?array
    {
        return $this->resolveByColumn('sku', $sku);
    }

    /**
     * @param 'barcode'|'sku' $column
     */
    protected function resolveByColumn(string $column, string $identifier): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $products = Product::query()
            ->where($column, $identifier)
            ->limit(2)
            ->get();

        $variants = ProductVariant::query()
            ->with('product')
            ->where($column, $identifier)
            ->limit(2)
            ->get();

        if (($products->count() + $variants->count()) > 1) {
            throw new ProductIdentifierAmbiguityException($identifier);
        }

        if ($variant = $variants->first()) {
            return [
                'product' => $variant->product,
                'variant' => $variant,
                'match_type' => 'variant',
                'matched_by' => $column,
                'requires_variant_selection' => false,
            ];
        }

        if ($product = $products->first()) {
            return [
                'product' => $product,
                'variant' => null,
                'match_type' => 'product',
                'matched_by' => $column,
                'requires_variant_selection' => (bool) $product->has_variants,
            ];
        }

        return null;
    }
}
