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
     *     requires_variant_selection: bool
     * }|null
     */
    public function resolveBarcode(string $barcode): ?array
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return null;
        }

        $products = Product::query()
            ->where('barcode', $barcode)
            ->limit(2)
            ->get();

        $variants = ProductVariant::query()
            ->with('product')
            ->where('barcode', $barcode)
            ->limit(2)
            ->get();

        if (($products->count() + $variants->count()) > 1) {
            throw new ProductIdentifierAmbiguityException($barcode);
        }

        if ($variant = $variants->first()) {
            return [
                'product' => $variant->product,
                'variant' => $variant,
                'match_type' => 'variant',
                'requires_variant_selection' => false,
            ];
        }

        if ($product = $products->first()) {
            return [
                'product' => $product,
                'variant' => null,
                'match_type' => 'product',
                'requires_variant_selection' => (bool) $product->has_variants,
            ];
        }

        return null;
    }
}
