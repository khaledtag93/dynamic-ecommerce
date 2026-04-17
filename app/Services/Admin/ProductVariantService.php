<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductVariantService
{
    public function saveVariants(Product $product, array $variants): void
    {
        DB::transaction(function () use ($product, $variants) {
            $existingVariants = $product->variants()
                ->with('attributes')
                ->get()
                ->keyBy('id');

            $keptVariantIds = [];
            $selectedDefaultVariantId = null;
            $defaultAssigned = false;
            $seenCombinations = [];

            foreach ($variants as $index => $variantData) {
                $variantId = $variantData['id'] ?? null;
                $normalizedAttributes = $this->normalizeVariantAttributes($variantData['attributes'] ?? []);
                $combinationKey = $this->buildCombinationKey($normalizedAttributes);

                if ($combinationKey !== '' && isset($seenCombinations[$combinationKey])) {
                    throw ValidationException::withMessages([
                        'variants' => 'Duplicate variant combination detected. Each variant must have a unique attribute combination.',
                    ]);
                }

                if ($combinationKey !== '') {
                    $seenCombinations[$combinationKey] = true;
                }

                $isDefault = !empty($variantData['is_default']) && !$defaultAssigned;

                if ($isDefault) {
                    $defaultAssigned = true;
                }

                $payload = [
                    'sku' => blank($variantData['sku'] ?? null) ? null : trim((string) $variantData['sku']),
                    'price' => $variantData['price'] ?? 0,
                    'sale_price' => ($variantData['sale_price'] ?? null) === '' ? null : ($variantData['sale_price'] ?? null),
                    'stock' => $variantData['stock'] ?? 0,
                    'is_default' => $isDefault,
                    'status' => $variantData['status'] ?? true,
                    'sort_order' => $variantData['sort_order'] ?? $index,
                ];

                if ($variantId && $existingVariants->has($variantId)) {
                    $variant = $existingVariants->get($variantId);
                    $variant->fill($payload);

                    if ($variant->isDirty()) {
                        $variant->save();
                    }
                } else {
                    $variant = ProductVariant::create(array_merge($payload, [
                        'product_id' => $product->id,
                    ]));
                }

                if ($isDefault) {
                    $selectedDefaultVariantId = $variant->id;
                }

                $keptVariantIds[] = $variant->id;

                $existingAttributes = $existingVariants->has($variant->id)
                    ? $existingVariants->get($variant->id)->attributes->keyBy('id')
                    : collect();

                $this->syncVariantAttributes($variant, $normalizedAttributes, $existingAttributes);
            }

            $removedVariantIds = $product->variants()
                ->whereNotIn('id', $keptVariantIds)
                ->pluck('id');

            if ($removedVariantIds->isNotEmpty()) {
                ProductVariantAttribute::whereIn('variant_id', $removedVariantIds)->delete();
                ProductVariant::whereIn('id', $removedVariantIds)->delete();
            }

            if (!empty($keptVariantIds)) {
                ProductVariant::where('product_id', $product->id)->update(['is_default' => false]);

                $defaultVariantId = $selectedDefaultVariantId ?: $keptVariantIds[0];

                ProductVariant::where('id', $defaultVariantId)->update(['is_default' => true]);
            }
        });
    }

    protected function normalizeVariantAttributes(array $attributes)
    {
        return collect($attributes)
            ->filter(function ($attribute) {
                return !empty($attribute['attribute_id']) && filled($attribute['value']);
            })
            ->map(function ($attribute) {
                return [
                    'id' => $attribute['id'] ?? null,
                    'attribute_id' => (int) $attribute['attribute_id'],
                    'value' => trim((string) $attribute['value']),
                ];
            })
            ->unique(function ($attribute) {
                return $attribute['attribute_id'];
            })
            ->sortBy('attribute_id')
            ->values();
    }

    protected function buildCombinationKey($attributes): string
    {
        return $attributes
            ->map(function ($attribute) {
                return $attribute['attribute_id'] . ':' . mb_strtolower($attribute['value']);
            })
            ->implode('|');
    }

    protected function syncVariantAttributes(ProductVariant $variant, $attributes, $existingAttributes = null): void
    {
        $existingAttributes = $existingAttributes ?: $variant->attributes()->get()->keyBy('id');
        $keptAttributeIds = [];

        foreach ($attributes as $attributeData) {
            $attributeRowId = $attributeData['id'] ?? null;

            $payload = [
                'attribute_id' => $attributeData['attribute_id'],
                'attribute_value' => $attributeData['value'],
            ];

            if ($attributeRowId && $existingAttributes->has($attributeRowId)) {
                $attributeRow = $existingAttributes->get($attributeRowId);
                $attributeRow->fill($payload);

                if ($attributeRow->isDirty()) {
                    $attributeRow->save();
                }
            } else {
                $attributeRow = ProductVariantAttribute::create([
                    'variant_id' => $variant->id,
                    'attribute_id' => $attributeData['attribute_id'],
                    'attribute_value' => $attributeData['value'],
                ]);
            }

            $keptAttributeIds[] = $attributeRow->id;
        }

        $variant->attributes()
            ->when(!empty($keptAttributeIds), function ($query) use ($keptAttributeIds) {
                $query->whereNotIn('id', $keptAttributeIds);
            }, function ($query) {
                $query->whereRaw('1 = 1');
            })
            ->delete();
    }
}