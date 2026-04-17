<?php

namespace App\Http\Livewire\Admin\Product;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Services\Admin\ProductService;
use App\Services\Admin\ProductVariantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class ProductForm extends Component
{
    protected string $logChannel = 'admin_products';
    use WithFileUploads;

    public $productId = null;

    public $name = '';
    public $slug = '';
    public $barcode = '';
    public $brand_id = '';
    public $category_id = '';
    public $description = '';
    public $base_price = '';
    public $sale_price = '';
    public $quantity = 0;
    public $stock_status = 'in_stock';
    public $low_stock_threshold = '';
    public $status = 1;
    public $is_featured = 0;
    public $meta_title = '';
    public $meta_description = '';
    public $video_url = '';

    public $hasVariants = false;
    public $variants = [];
    public $variantGenerator = [];
    public $variantBulk = [
        'price' => '',
        'sale_price' => '',
        'stock' => '',
        'status' => '1',
    ];
    public $variantExpanded = [];
    public $attributeNameMap = [];

    public $saveErrorMessage = '';
    public $lastSavedProductId = null;
    public $isSaving = false;

    public $newImages = [];
    public $existingImages = [];

    public $categoryOptions = [];
    public $brandOptions = [];
    public $attributeOptions = [];

    public $relatedSearch = '';
    public $bundleSearch = '';
    public $addonSearch = '';

    public $relatedProductsManager = [];
    public $bundleProductsManager = [];
    public $addonProductsManager = [];


    protected function logInfo(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->info($message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->error($message, $context);
        Log::error($message, $context);
    }

    protected function rules()
    {
        $productId = $this->productId ?: 'NULL';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug,' . $productId],
            'barcode' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'newImages.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($this->hasVariants) {
            return array_merge($rules, [
                'variants' => ['required', 'array', 'min:1'],
                'variants.*.sku' => ['nullable', 'string', 'max:255'],
                'variants.*.price' => ['required', 'numeric', 'min:0'],
                'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
                'variants.*.stock' => ['nullable', 'integer', 'min:0'],
                'variants.*.is_default' => ['nullable', 'boolean'],
                'variants.*.status' => ['nullable', 'boolean'],
                'variants.*.attributes' => ['nullable', 'array'],
                'variants.*.attributes.*.attribute_id' => ['nullable', 'exists:product_attributes,id'],
                'variants.*.attributes.*.value' => ['nullable', 'string', 'max:255'],
                'variantGenerator' => ['nullable', 'array'],
                'variantGenerator.*.attribute_id' => ['nullable', 'exists:product_attributes,id'],
                'variantGenerator.*.values' => ['nullable', 'string'],
            ]);
        }

        return array_merge($rules, [
            'base_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
            'stock_status' => ['required', 'in:in_stock,out_of_stock,preorder,backorder'],
        ]);
    }

    public function mount($productId = null)
    {
        $this->loadFormOptions();

        if ($productId) {
            $this->loadProduct($productId);
        }

        if (empty($this->variantGenerator)) {
            $this->addGeneratorAttribute();
        }

        $this->syncVariantExpandedState();
    }

    protected function loadFormOptions(): void
    {
        $this->categoryOptions = Category::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
            ->toArray();

        $this->brandOptions = Brand::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
            ->toArray();

        $this->attributeOptions = ProductAttribute::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
            ->toArray();

        $this->refreshAttributeNameMap();
    }

    protected function refreshAttributeNameMap(): void
    {
        $this->attributeNameMap = collect($this->attributeOptions)->pluck('name', 'id')->toArray();
    }

    protected function syncVariantExpandedState(): void
    {
        $newState = [];

        foreach ($this->variants as $index => $variant) {
            $newState[$index] = $this->variantExpanded[$index] ?? ($index === 0);
        }

        $this->variantExpanded = $newState;
    }

    protected function resetSaveFeedback(): void
    {
        $this->saveErrorMessage = '';
        $this->resetErrorBag('save');
    }

    protected function dispatchToast(string $type, string $message, int $duration = 4500): void
    {
        $this->dispatchBrowserEvent('product-form-toast', [
            'type' => $type,
            'message' => $message,
            'duration' => $duration,
        ]);
    }

    protected function dispatchScrollToFirstError(): void
    {
        $this->dispatchBrowserEvent('product-form-scroll-to-first-error');
    }

    public function updated($name, $value)
    {
        if ($this->saveErrorMessage !== '') {
            $this->saveErrorMessage = '';
        }

        $this->resetErrorBag('save');
    }

    public function updatedName($value)
    {
        if (!$this->productId && blank($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedHasVariants($value)
    {
        if ((bool) $value) {
            if (empty($this->variants)) {
                $this->addVariant();
            }

            if (empty($this->variantGenerator)) {
                $this->addGeneratorAttribute();
            }
        }

        if (empty($this->variantGenerator)) {
            $this->addGeneratorAttribute();
        }

        $this->syncVariantExpandedState();
    }

    public function updatedVariants($value, $name)
    {
        if (preg_match('/^(\d+)\.is_default$/', $name, $matches)) {
            $selectedIndex = (int) $matches[1];

            if (!empty($this->variants[$selectedIndex]['is_default'])) {
                $this->setDefaultVariant($selectedIndex);
            }

            return;
        }

        if (preg_match('/^(\d+)\.(price|sale_price|stock|status)$/', $name, $matches)) {
            $variantIndex = (int) $matches[1];

            if (!empty($this->variants[$variantIndex]['is_default'])) {
                $this->syncHeaderFromDefaultVariant();
            }
        }
    }

    public function loadProduct($id)
    {
        $product = Product::with([
            'productImages',
            'variants.attributes.attribute',
        ])->findOrFail($id);

        $this->fillFromProduct($product);
    }

    protected function fillFromProduct(Product $product): void
    {
        $this->fillBasicStateFromProduct($product);
        $this->fillImagesStateFromProduct($product);
        $this->fillVariantsStateFromProduct($product);
        $this->loadAovRelationsFromProduct($product);

        if (empty($this->variantGenerator)) {
            $this->variantGenerator = [['attribute_id' => '', 'values' => '']];
        }

        $this->syncVariantExpandedState();
    }

    protected function fillBasicStateFromProduct(Product $product): void
    {
        $this->productId = $product->id;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->barcode = $product->barcode;
        $this->brand_id = $product->brand_id;
        $this->category_id = $product->category_id;
        $this->description = $product->description;
        $this->base_price = $product->base_price ?? $product->price;
        $this->sale_price = $product->sale_price;
        $this->quantity = $product->quantity_value ?? $product->quantity ?? 0;
        $this->stock_status = $product->stock_status ?: 'in_stock';
        $this->low_stock_threshold = $product->low_stock_threshold;
        $this->status = (int) $product->status;
        $this->is_featured = (int) ($product->is_featured ?? 0);
        $this->meta_title = $product->meta_title;
        $this->meta_description = $product->meta_description;
        $this->video_url = $product->video_url;
        $this->hasVariants = (bool) ($product->has_variants ?? false);
    }

    protected function fillImagesStateFromProduct(Product $product): void
    {
        $images = $product->relationLoaded('productImages')
            ? $product->productImages
            : $product->productImages()->get();

        $this->existingImages = $images
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function ($image) {
                $path = $image->image ?: $image->image_path;

                return [
                    'id' => $image->id,
                    'image' => $image->image,
                    'image_path' => $image->image_path ?? $image->image,
                    'image_url' => $path ? asset('uploads/' . ltrim($path, '/')) : '',
                    'is_main' => (int) $image->is_main,
                    'sort_order' => (int) $image->sort_order,
                ];
            })
            ->toArray();
    }

    protected function fillVariantsStateFromProduct(Product $product): void
    {
        $this->variants = [];

        if (!(bool) ($product->has_variants ?? false)) {
            $this->variantExpanded = [];
            return;
        }

        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->with('attributes.attribute')->get();

        $this->variants = $variants
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'sale_price' => $variant->sale_price,
                    'stock' => $variant->stock,
                    'is_default' => (bool) $variant->is_default,
                    'status' => (bool) $variant->status,
                    'attributes' => $variant->attributes
                        ->map(fn ($attributeRow) => [
                            'id' => $attributeRow->id,
                            'attribute_id' => $attributeRow->attribute_id,
                            'value' => $attributeRow->attribute_value,
                        ])
                        ->values()
                        ->toArray(),
                ];
            })
            ->toArray();

        if (empty($this->variants)) {
            $this->addVariant();
        }

        $this->syncHeaderFromDefaultVariant();
    }

    protected function loadAovRelationsFromProduct(Product $product): void
    {
        if (! $product->exists) {
            $this->relatedProductsManager = [];
            $this->bundleProductsManager = [];
            $this->addonProductsManager = [];
            return;
        }

        $rows = DB::table('product_related as pr')
            ->join('products as p', 'p.id', '=', 'pr.related_product_id')
            ->where('pr.product_id', $product->id)
            ->orderBy('pr.relation_type')
            ->orderBy('pr.sort_order')
            ->orderBy('pr.id')
            ->select([
                'pr.related_product_id',
                'pr.relation_type',
                'pr.sort_order',
                'pr.is_active',
                'p.name',
            ])
            ->get();

        $grouped = [
            'related' => [],
            'bundle' => [],
            'addon' => [],
        ];

        foreach ($rows as $row) {
            if (! isset($grouped[$row->relation_type])) {
                continue;
            }

            $grouped[$row->relation_type][] = [
                'product_id' => (int) $row->related_product_id,
                'name' => (string) $row->name,
                'is_active' => (bool) $row->is_active,
                'sort_order' => (int) $row->sort_order,
            ];
        }

        $this->relatedProductsManager = array_values($grouped['related']);
        $this->bundleProductsManager = array_values($grouped['bundle']);
        $this->addonProductsManager = array_values($grouped['addon']);
    }

    protected function &relationManagerProperty(string $type): array
    {
        if ($type === 'related') {
            return $this->relatedProductsManager;
        }

        if ($type === 'bundle') {
            return $this->bundleProductsManager;
        }

        return $this->addonProductsManager;
    }

    protected function relationSearchValue(string $type): string
    {
        if ($type === 'related') {
            return (string) $this->relatedSearch;
        }

        if ($type === 'bundle') {
            return (string) $this->bundleSearch;
        }

        return (string) $this->addonSearch;
    }

    protected function clearRelationSearch(string $type): void
    {
        if ($type === 'related') {
            $this->relatedSearch = '';
            return;
        }

        if ($type === 'bundle') {
            $this->bundleSearch = '';
            return;
        }

        $this->addonSearch = '';
    }

    public function addAovRelation(string $type, int $productId): void
    {
        $manager = &$this->relationManagerProperty($type);

        if ($this->productId && (int) $this->productId === $productId) {
            return;
        }

        foreach ($manager as $item) {
            if ((int) ($item['product_id'] ?? 0) === $productId) {
                $this->clearRelationSearch($type);
                return;
            }
        }

        $product = Product::query()->select('id', 'name')->find($productId);

        if (! $product) {
            return;
        }

        $manager[] = [
            'product_id' => (int) $product->id,
            'name' => (string) $product->name,
            'is_active' => true,
            'sort_order' => count($manager),
        ];

        $manager = array_values($manager);
        $this->clearRelationSearch($type);
    }

    public function removeAovRelation(string $type, int $index): void
    {
        $manager = &$this->relationManagerProperty($type);

        if (! isset($manager[$index])) {
            return;
        }

        unset($manager[$index]);
        $manager = array_values($manager);
        $this->normalizeRelationSortOrders($type);
    }

    public function moveAovRelationUp(string $type, int $index): void
    {
        $manager = &$this->relationManagerProperty($type);

        if ($index <= 0 || ! isset($manager[$index], $manager[$index - 1])) {
            return;
        }

        [$manager[$index - 1], $manager[$index]] = [$manager[$index], $manager[$index - 1]];
        $manager = array_values($manager);
        $this->normalizeRelationSortOrders($type);
    }

    public function moveAovRelationDown(string $type, int $index): void
    {
        $manager = &$this->relationManagerProperty($type);

        if (! isset($manager[$index], $manager[$index + 1])) {
            return;
        }

        [$manager[$index + 1], $manager[$index]] = [$manager[$index], $manager[$index + 1]];
        $manager = array_values($manager);
        $this->normalizeRelationSortOrders($type);
    }

    public function toggleAovRelationActive(string $type, int $index): void
    {
        $manager = &$this->relationManagerProperty($type);

        if (! isset($manager[$index])) {
            return;
        }

        $manager[$index]['is_active'] = ! (bool) ($manager[$index]['is_active'] ?? true);
    }

    protected function normalizeRelationSortOrders(string $type): void
    {
        $manager = &$this->relationManagerProperty($type);

        foreach ($manager as $index => $item) {
            $manager[$index]['sort_order'] = $index;
        }
    }

    protected function syncAovRelations(Product $product): void
    {
        $this->syncAovRelationType($product, 'related', $this->relatedProductsManager);
        $this->syncAovRelationType($product, 'bundle', $this->bundleProductsManager);
        $this->syncAovRelationType($product, 'addon', $this->addonProductsManager);
    }

    protected function syncAovRelationType(Product $product, string $type, array $items): void
    {
        DB::table('product_related')
            ->where('product_id', $product->id)
            ->where('relation_type', $type)
            ->delete();

        $payload = collect($items)
            ->filter(fn ($item) => ! empty($item['product_id']))
            ->unique(fn ($item) => (int) $item['product_id'])
            ->values()
            ->map(function ($item, $index) use ($product, $type) {
                return [
                    'product_id' => $product->id,
                    'related_product_id' => (int) $item['product_id'],
                    'relation_type' => $type,
                    'sort_order' => $index,
                    'is_active' => (bool) ($item['is_active'] ?? true),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->all();

        if (! empty($payload)) {
            DB::table('product_related')->insert($payload);
        }
    }

    protected function searchProductsForAov(string $type): array
    {
        $search = trim($this->relationSearchValue($type));

        if (mb_strlen($search) < 2) {
            return [];
        }

        $selectedIds = collect($this->relationManagerProperty($type))
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $excludeIds = array_filter(array_unique(array_merge(
            $selectedIds,
            $this->productId ? [(int) $this->productId] : []
        )));

        return Product::query()
            ->select('id', 'name', 'slug')
            ->when(! empty($excludeIds), fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn ($product) => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'slug' => (string) $product->slug,
            ])
            ->toArray();
    }

    protected function refreshImagesOnly(): void
    {
        if (!$this->productId) {
            $this->existingImages = [];
            return;
        }

        $product = Product::with('productImages')->findOrFail($this->productId);
        $this->fillImagesStateFromProduct($product);
    }

    protected function startPerformanceTrace(): array
    {
        return [
            'started_at' => microtime(true),
            'marks' => [],
        ];
    }

    protected function markPerformance(array &$trace, string $label): void
    {
        $trace['marks'][$label] = round((microtime(true) - $trace['started_at']) * 1000, 2);
    }

    protected function logSavePerformance(array $trace, array $context = []): void
    {
        $this->logInfo('ProductForm save performance', array_merge([
            'product_id' => $this->productId,
            'has_variants' => (bool) $this->hasVariants,
            'new_images_count' => is_array($this->newImages) ? count($this->newImages) : 0,
            'existing_images_count' => is_array($this->existingImages) ? count($this->existingImages) : 0,
            'variants_count' => is_array($this->variants) ? count($this->variants) : 0,
            'marks_ms' => $trace['marks'],
            'total_ms' => round((microtime(true) - $trace['started_at']) * 1000, 2),
        ], $context));
    }

    public function addVariant()
    {
        $this->variants[] = [
            'id' => null,
            'sku' => '',
            'price' => is_numeric($this->base_price) ? (float) $this->base_price : '',
            'sale_price' => is_numeric($this->sale_price) ? (float) $this->sale_price : '',
            'stock' => 0,
            'is_default' => count($this->variants) === 0,
            'status' => true,
            'attributes' => [
                [
                    'id' => null,
                    'attribute_id' => '',
                    'value' => '',
                ],
            ],
        ];

        $this->variantExpanded[count($this->variants) - 1] = true;

        if (count($this->variants) === 1) {
            $this->syncHeaderFromDefaultVariant();
        }
    }

    public function duplicateVariant($index)
    {
        if (!isset($this->variants[$index])) {
            return;
        }

        $variant = $this->variants[$index];
        $clonedAttributes = collect($variant['attributes'] ?? [])->map(fn ($attribute) => [
            'id' => null,
            'attribute_id' => $attribute['attribute_id'] ?? '',
            'value' => $attribute['value'] ?? '',
        ])->values()->toArray();

        if (empty($clonedAttributes)) {
            $clonedAttributes = [[
                'id' => null,
                'attribute_id' => '',
                'value' => '',
            ]];
        }

        $this->variants[] = [
            'id' => null,
            'sku' => '',
            'price' => $variant['price'] ?? '',
            'sale_price' => $variant['sale_price'] ?? '',
            'stock' => $variant['stock'] ?? 0,
            'is_default' => false,
            'status' => !empty($variant['status']),
            'attributes' => $clonedAttributes,
        ];

        $this->variantExpanded[count($this->variants) - 1] = true;
        $this->dispatchToast('success', 'Variant duplicated successfully.');
    }

    protected function syncHeaderFromDefaultVariant(): void
    {
        if (!$this->hasVariants || empty($this->variants)) {
            return;
        }

        $defaultIndex = collect($this->variants)->search(
            fn ($variant) => !empty($variant['is_default'])
        );

        if ($defaultIndex === false || !isset($this->variants[$defaultIndex])) {
            return;
        }

        $defaultVariant = $this->variants[$defaultIndex];
        $stock = (int) ($defaultVariant['stock'] ?? 0);

        $this->base_price = $defaultVariant['price'] ?? '';
        $this->sale_price = $defaultVariant['sale_price'] ?? '';
        $this->quantity = $stock;
        $this->stock_status = $stock > 0 ? 'in_stock' : 'out_of_stock';
    }


    public function setDefaultVariant($index)
    {
        if (!isset($this->variants[$index])) {
            return;
        }

        foreach ($this->variants as $i => $variant) {
            $this->variants[$i]['is_default'] = $i === $index;
        }

        $this->syncHeaderFromDefaultVariant();
    }

    public function removeVariant($index)
    {
        if (!isset($this->variants[$index])) {
            return;
        }

        $wasDefault = !empty($this->variants[$index]['is_default']);

        unset($this->variants[$index], $this->variantExpanded[$index]);
        $this->variants = array_values($this->variants);
        $this->variantExpanded = array_values($this->variantExpanded);

        if ($wasDefault && !empty($this->variants)) {
            foreach ($this->variants as $i => $variant) {
                $this->variants[$i]['is_default'] = $i === 0;
            }
        }

        if ($this->hasVariants && empty($this->variants)) {
            $this->addVariant();
        }

        $this->syncVariantExpandedState();
        $this->syncHeaderFromDefaultVariant();
    }

    public function moveVariantUp($index)
    {
        if ($index <= 0 || !isset($this->variants[$index])) {
            return;
        }

        $this->swapVariants($index, $index - 1);
    }

    public function moveVariantDown($index)
    {
        if (!isset($this->variants[$index], $this->variants[$index + 1])) {
            return;
        }

        $this->swapVariants($index, $index + 1);
    }

    protected function swapVariants($firstIndex, $secondIndex): void
    {
        [$this->variants[$firstIndex], $this->variants[$secondIndex]] = [$this->variants[$secondIndex], $this->variants[$firstIndex]];

        $firstExpanded = $this->variantExpanded[$firstIndex] ?? false;
        $secondExpanded = $this->variantExpanded[$secondIndex] ?? false;

        $this->variantExpanded[$firstIndex] = $secondExpanded;
        $this->variantExpanded[$secondIndex] = $firstExpanded;

        $this->variants = array_values($this->variants);
        $this->variantExpanded = array_values($this->variantExpanded);
    }

    public function toggleVariant($index)
    {
        if (!isset($this->variants[$index])) {
            return;
        }

        $this->variantExpanded[$index] = !($this->variantExpanded[$index] ?? false);
    }

    public function expandAllVariants()
    {
        foreach ($this->variants as $index => $variant) {
            $this->variantExpanded[$index] = true;
        }
    }

    public function collapseAllVariants()
    {
        foreach ($this->variants as $index => $variant) {
            $this->variantExpanded[$index] = false;
        }
    }

    public function isVariantExpanded($index): bool
    {
        return (bool) ($this->variantExpanded[$index] ?? false);
    }

    public function addVariantAttribute($variantIndex)
    {
        if (!isset($this->variants[$variantIndex])) {
            return;
        }

        $this->variants[$variantIndex]['attributes'][] = [
            'id' => null,
            'attribute_id' => '',
            'value' => '',
        ];

        $this->variantExpanded[$variantIndex] = true;
    }

    public function removeVariantAttribute($variantIndex, $attrIndex)
    {
        if (!isset($this->variants[$variantIndex]['attributes'][$attrIndex])) {
            return;
        }

        unset($this->variants[$variantIndex]['attributes'][$attrIndex]);
        $this->variants[$variantIndex]['attributes'] = array_values($this->variants[$variantIndex]['attributes']);

        if (empty($this->variants[$variantIndex]['attributes'])) {
            $this->variants[$variantIndex]['attributes'][] = [
                'id' => null,
                'attribute_id' => '',
                'value' => '',
            ];
        }

        $this->variantExpanded[$variantIndex] = true;
    }

    public function addGeneratorAttribute()
    {
        $this->variantGenerator[] = ['attribute_id' => '', 'values' => ''];
    }

    public function removeGeneratorAttribute($index)
    {
        if (!isset($this->variantGenerator[$index])) {
            return;
        }

        unset($this->variantGenerator[$index]);
        $this->variantGenerator = array_values($this->variantGenerator);

        if (empty($this->variantGenerator)) {
            $this->addGeneratorAttribute();
        }
    }

    public function clearVariantGenerator()
    {
        $this->variantGenerator = [['attribute_id' => '', 'values' => '']];
        $this->resetErrorBag('variantGenerator');
    }

    public function applyBulkToVariants()
    {
        if (empty($this->variants)) {
            return;
        }

        foreach ($this->variants as $index => $variant) {
            if ($this->variantBulk['price'] !== '' && $this->variantBulk['price'] !== null) {
                $this->variants[$index]['price'] = (float) $this->variantBulk['price'];
            }

            if ($this->variantBulk['sale_price'] !== '' && $this->variantBulk['sale_price'] !== null) {
                $this->variants[$index]['sale_price'] = (float) $this->variantBulk['sale_price'];
            }

            if ($this->variantBulk['stock'] !== '' && $this->variantBulk['stock'] !== null) {
                $this->variants[$index]['stock'] = (int) $this->variantBulk['stock'];
            }

            if ($this->variantBulk['status'] !== '' && $this->variantBulk['status'] !== null) {
                $this->variants[$index]['status'] = (bool) $this->variantBulk['status'];
            }
        }

        $this->dispatchToast('success', 'Bulk values applied to all variants.');
    }

    public function generateVariants()
    {
        $groups = collect($this->variantGenerator)
            ->filter(fn ($group) => !empty($group['attribute_id']) && filled($group['values']))
            ->map(function ($group) {
                $values = preg_split('/[,|\n]+/', (string) $group['values']);

                return [
                    'attribute_id' => (int) $group['attribute_id'],
                    'values' => collect($values)
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->unique(fn ($value) => mb_strtolower($value))
                        ->values()
                        ->toArray(),
                ];
            })
            ->filter(fn ($group) => !empty($group['values']))
            ->values()
            ->toArray();

        if (empty($groups)) {
            $this->addError('variantGenerator', 'Please select at least one attribute with one or more values.');
            $this->dispatchToast('error', 'Variant generation failed. Please complete the generator fields.');
            $this->dispatchScrollToFirstError();
            return;
        }

        $attributeIds = collect($groups)->pluck('attribute_id')->toArray();

        if (count($attributeIds) !== count(array_unique($attributeIds))) {
            $this->addError('variantGenerator', 'Each generator attribute can only be selected once.');
            $this->dispatchToast('error', 'Variant generation failed. Duplicate generator attributes are not allowed.');
            $this->dispatchScrollToFirstError();
            return;
        }

        $this->resetErrorBag('variantGenerator');
        $combinations = $this->cartesianProduct($groups);
        $existingKeys = collect($this->variants)
            ->map(fn ($variant) => $this->variantKey($variant['attributes'] ?? []))
            ->filter()
            ->values()
            ->toArray();

        $addedCount = 0;

        foreach ($combinations as $combination) {
            $key = $this->variantKey($combination);

            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $this->variants[] = [
                'id' => null,
                'sku' => '',
                'price' => is_numeric($this->base_price) ? (float) $this->base_price : '',
                'sale_price' => is_numeric($this->sale_price) ? (float) $this->sale_price : '',
                'stock' => 0,
                'is_default' => false,
                'status' => true,
                'attributes' => collect($combination)->map(fn ($item) => [
                    'id' => null,
                    'attribute_id' => $item['attribute_id'],
                    'value' => $item['value'],
                ])->toArray(),
            ];

            $this->variantExpanded[count($this->variants) - 1] = false;
            $existingKeys[] = $key;
            $addedCount++;
        }

        if (!collect($this->variants)->contains(fn ($variant) => !empty($variant['is_default'])) && !empty($this->variants)) {
            $this->variants[0]['is_default'] = true;
        }

        $this->syncVariantExpandedState();

        if ($addedCount === 0) {
            $this->dispatchToast('success', 'No new variants were generated. All generated combinations already exist.');
            return;
        }

        $this->dispatchToast('success', $addedCount . ' variant(s) generated successfully.');
    }

    protected function cartesianProduct(array $groups): array
    {
        $result = [[]];

        foreach ($groups as $group) {
            $append = [];

            foreach ($result as $product) {
                foreach ($group['values'] as $value) {
                    $append[] = array_merge($product, [[
                        'attribute_id' => $group['attribute_id'],
                        'value' => $value,
                    ]]);
                }
            }

            $result = $append;
        }

        return $result;
    }

    protected function variantKey(array $attributes): string
    {
        return collect($attributes)
            ->filter(fn ($attribute) => !empty($attribute['attribute_id']) && filled($attribute['value'] ?? null))
            ->map(fn ($attribute) => [
                'attribute_id' => (int) $attribute['attribute_id'],
                'value' => mb_strtolower(trim((string) $attribute['value'])),
            ])
            ->sortBy('attribute_id')
            ->map(fn ($attribute) => $attribute['attribute_id'] . ':' . $attribute['value'])
            ->implode('|');
    }

    protected function ensureNoDuplicateVariantCombinations(array $variants): void
    {
        $seen = [];

        foreach ($variants as $index => $variant) {
            $key = $this->variantKey($variant['attributes'] ?? []);

            if (blank($key)) {
                continue;
            }

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'variants' => 'Duplicate variant combinations are not allowed. Please remove or edit the repeated combination.',
                ]);
            }

            $seen[$key] = $index;
        }
    }

    protected function ensureSimpleProductBusinessRules(array $validated): void
    {
        if ($this->hasVariants) {
            return;
        }

        $basePrice = $validated['base_price'] ?? null;
        $salePrice = $validated['sale_price'] ?? null;

        if ($salePrice !== null && $salePrice !== '' && (float) $salePrice > (float) $basePrice) {
            throw ValidationException::withMessages([
                'sale_price' => 'Sale price cannot be greater than the base price.',
            ]);
        }
    }

    protected function ensureVariantBusinessRules(array $variants): void
    {
        foreach ($variants as $variantIndex => $variant) {
            $usedAttributeIds = [];

            foreach ($variant['attributes'] ?? [] as $attributeIndex => $attribute) {
                $attributeId = (int) ($attribute['attribute_id'] ?? 0);
                $value = trim((string) ($attribute['value'] ?? ''));

                if ($attributeId && $value === '') {
                    throw ValidationException::withMessages([
                        'variants.' . $variantIndex . '.attributes.' . $attributeIndex . '.value' => 'Please enter a value for the selected attribute.',
                    ]);
                }

                if ($attributeId) {
                    if (in_array($attributeId, $usedAttributeIds, true)) {
                        throw ValidationException::withMessages([
                            'variants.' . $variantIndex . '.attributes' => 'The same attribute cannot be selected twice inside one variant.',
                        ]);
                    }

                    $usedAttributeIds[] = $attributeId;
                }
            }

            $price = (float) ($variant['price'] ?? 0);
            $salePrice = $variant['sale_price'];

            if ($salePrice !== null && $salePrice !== '' && (float) $salePrice > $price) {
                throw ValidationException::withMessages([
                    'variants.' . $variantIndex . '.sale_price' => 'Sale price cannot be greater than the variant price.',
                ]);
            }
        }
    }

    protected function normalizeVariantsForSave(): array
    {
        $variants = collect($this->variants)
            ->map(function ($variant, $index) {
                $attributes = collect($variant['attributes'] ?? [])
                    ->filter(fn ($attribute) => !empty($attribute['attribute_id']) && filled($attribute['value']))
                    ->map(fn ($attribute) => [
                        'id' => $attribute['id'] ?? null,
                        'attribute_id' => (int) $attribute['attribute_id'],
                        'value' => trim((string) $attribute['value']),
                    ])
                    ->values()
                    ->toArray();

                return [
                    'id' => $variant['id'] ?? null,
                    'sku' => filled($variant['sku'] ?? null) ? trim((string) $variant['sku']) : null,
                    'price' => (float) ($variant['price'] ?? 0),
                    'sale_price' => ($variant['sale_price'] ?? '') !== '' ? (float) $variant['sale_price'] : null,
                    'stock' => ($variant['stock'] ?? '') !== '' ? (int) $variant['stock'] : 0,
                    'is_default' => !empty($variant['is_default']),
                    'status' => !empty($variant['status']),
                    'sort_order' => $index + 1,
                    'attributes' => $attributes,
                ];
            })
            ->values()
            ->toArray();

        $defaultFound = collect($variants)->contains(fn ($variant) => !empty($variant['is_default']));

        if (!$defaultFound && !empty($variants)) {
            $variants[0]['is_default'] = true;
        }

        return $variants;
    }

    public function getVariantLabel($index): string
    {
        if (!isset($this->variants[$index])) {
            return 'Variant';
        }

        $parts = collect($this->variants[$index]['attributes'] ?? [])
            ->filter(fn ($attribute) => !empty($attribute['attribute_id']) && filled($attribute['value'] ?? null))
            ->map(function ($attribute) {
                $attributeId = (int) ($attribute['attribute_id'] ?? 0);
                $attributeName = $this->attributeNameMap[$attributeId] ?? 'Attribute';
                $value = trim((string) $attribute['value']);

                return $attributeName . ': ' . $value;
            })
            ->values()
            ->toArray();

        return empty($parts) ? 'Variant #' . ($index + 1) : implode(' / ', $parts);
    }

    public function getVariantStats(): array
    {
        $count = count($this->variants);
        $active = collect($this->variants)->filter(fn ($variant) => !empty($variant['status']))->count();
        $inactive = $count - $active;
        $totalStock = collect($this->variants)->sum(fn ($variant) => (int) ($variant['stock'] ?? 0));
        $defaultIndex = collect($this->variants)->search(fn ($variant) => !empty($variant['is_default']));

        return [
            'count' => $count,
            'active' => $active,
            'inactive' => $inactive,
            'total_stock' => $totalStock,
            'default_label' => $defaultIndex === false ? 'None' : $this->getVariantLabel($defaultIndex),
        ];
    }

    public function getVariantStockState($index): string
    {
        $stock = (int) ($this->variants[$index]['stock'] ?? 0);
        $threshold = (int) ($this->low_stock_threshold ?: 0);

        if ($stock <= 0) {
            return 'Out of stock';
        }

        if ($threshold > 0 && $stock <= $threshold) {
            return 'Low stock';
        }

        return 'In stock';
    }

    public function removeNewImage($index): void
    {
        if (!isset($this->newImages[$index])) {
            return;
        }

        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);
    }

    public function reorderNewImages(array $orderedIndexes): void
    {
        if (empty($orderedIndexes) || empty($this->newImages)) {
            return;
        }

        $reordered = [];

        foreach ($orderedIndexes as $index) {
            $index = (int) $index;
            if (isset($this->newImages[$index])) {
                $reordered[] = $this->newImages[$index];
            }
        }

        if (!empty($reordered)) {
            $this->newImages = $reordered;
        }
    }

    public function setMainExistingImage(int $imageId, ProductService $productService): void
    {
        $productService->setMainImage($imageId);
        $this->refreshImagesOnly();
        $this->dispatchToast('success', 'Main image updated.');
    }

    public function deleteExistingImage(int $imageId, ProductService $productService): void
    {
        $productService->deleteImage($imageId);
        $this->refreshImagesOnly();
        $this->dispatchToast('success', 'Image deleted successfully.');
    }

    public function reorderExistingImages(array $orderedIds, ProductService $productService): void
    {
        $productService->reorderExistingImages($orderedIds);
        $this->refreshImagesOnly();
    }

    protected function applyOptimisticSaveState(Product $product, bool $wasEditing): void
    {
        $this->productId = $product->id;
        $this->lastSavedProductId = $product->id;
        $this->saveErrorMessage = '';
        $this->newImages = [];

        if (!$this->hasVariants) {
            $this->variantExpanded = [];
        } else {
            $this->syncVariantExpandedState();
        }

        $this->loadAovRelationsFromProduct($product);

        $action = $wasEditing ? 'updated' : 'created';
        $productName = $this->name ?: 'Product';

        $this->dispatchBrowserEvent('product-form-save-succeeded', [
            'product_id' => $product->id,
            'action' => $action,
            'saved_at' => now()->format('H:i'),
        ]);

        $this->dispatchToast(
            'success',
            'Product "' . $productName . '" ' . $action . ' successfully. ID: #' . $product->id,
            6500
        );
    }

    public function save(ProductService $productService, ProductVariantService $productVariantService)
    {
        if ($this->isSaving) {
            return;
        }

        $this->isSaving = true;
        $this->resetSaveFeedback();
        $this->dispatchBrowserEvent('product-form-saving-state', ['saving' => true]);

        $trace = $this->startPerformanceTrace();
        $isVariantMode = (bool) $this->hasVariants;
        $wasEditing = !is_null($this->productId);

        try {
            $this->logInfo('ProductForm save started', [
                'product_id' => $this->productId,
                'is_editing' => $wasEditing,
                'has_variants' => $isVariantMode,
                'new_images_count' => is_array($this->newImages) ? count($this->newImages) : 0,
            ]);

            $validated = $this->validate();
            $this->markPerformance($trace, 'validated');

            $validated['has_variants'] = $isVariantMode;
            $normalizedVariants = [];

            if ($isVariantMode) {
                $normalizedVariants = $this->normalizeVariantsForSave();
                $this->ensureNoDuplicateVariantCombinations($normalizedVariants);
                $this->ensureVariantBusinessRules($normalizedVariants);

                $defaultVariant = collect($normalizedVariants)->firstWhere('is_default', true) ?? ($normalizedVariants[0] ?? null);
                $validated['base_price'] = $defaultVariant['price'] ?? (is_numeric($this->base_price) ? $this->base_price : 0);
                $validated['sale_price'] = $defaultVariant['sale_price'] ?? null;
                $validated['quantity'] = 0;
                $validated['stock_status'] = 'in_stock';
            } else {
                $this->ensureSimpleProductBusinessRules($validated);
            }

            $this->markPerformance($trace, 'normalized');

            $product = $productService->save(
                $validated,
                $this->productId ? (int) $this->productId : null,
                $this->newImages
            );

            $this->syncAovRelations($product);

            $this->markPerformance($trace, 'product_saved');

            if ($isVariantMode) {
                $productVariantService->saveVariants($product, $normalizedVariants);
                $this->markPerformance($trace, 'variants_saved');
                $this->refreshImagesOnly();
            } else {
                if ($product->variants()->exists()) {
                    $product->variants()->delete();
                }
                $this->markPerformance($trace, 'variants_cleared');

                if (!empty($this->newImages)) {
                    $this->refreshImagesOnly();
                }
            }

            $this->applyOptimisticSaveState($product, $wasEditing);
            $this->markPerformance($trace, 'optimistic_sync_done');
            $this->logSavePerformance($trace, [
                'mode' => 'optimistic_patch',
                'saved_product_id' => $product->id,
                'was_editing' => $wasEditing,
            ]);
        } catch (ValidationException $e) {
    $errors = $e->validator->errors();

    $this->setErrorBag($errors);

    $allMessages = collect($errors->toArray())
        ->flatten()
        ->filter()
        ->values();

    $firstMessage = $allMessages->first() ?: 'Validation failed. Please review the highlighted fields.';

    $this->saveErrorMessage = $firstMessage;

    $this->addError('save', $this->saveErrorMessage);

    $this->dispatchToast('error', $firstMessage, 7000);

    $this->dispatchBrowserEvent('product-form-save-failed', [
        'message' => $firstMessage,
        'messages' => $allMessages->toArray(),
    ]);

    $this->dispatchScrollToFirstError();

    $this->markPerformance($trace, 'validation_exception');

    $this->logSavePerformance($trace, [
        'result' => 'validation_failed',
        'errors_count' => $errors->count(),
        'errors' => $errors->toArray(),
    ]);
}catch (Throwable $e) {
            report($e);

            $this->logError('ProductForm save failed with throwable', [
                'product_id' => $this->productId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'new_images_count' => is_array($this->newImages) ? count($this->newImages) : 0,
                'has_variants' => (bool) $this->hasVariants,
            ]);

            $this->saveErrorMessage = 'Save failed بسبب مشكلة غير متوقعة. لم يتم حفظ التعديلات. جرّب مرة تانية، ولو المشكلة مستمرة راجع اللوج.';
            $this->addError('save', $this->saveErrorMessage);
            $this->dispatchToast('error', $this->saveErrorMessage, 6500);
            $this->dispatchScrollToFirstError();
            $this->dispatchBrowserEvent('product-form-save-failed', ['message' => $this->saveErrorMessage]);
            $this->markPerformance($trace, 'throwable_exception');
            $this->logSavePerformance($trace, [
                'result' => 'exception',
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);
        } finally {
            $this->isSaving = false;
            $this->dispatchBrowserEvent('product-form-saving-state', ['saving' => false]);
        }
    }

    public function render()
    {
        return view('livewire.admin.product.product-form', [
            'categories' => $this->categoryOptions,
            'brands' => $this->brandOptions,
            'attributes' => $this->attributeOptions,
            'relatedSearchResults' => $this->searchProductsForAov('related'),
            'bundleSearchResults' => $this->searchProductsForAov('bundle'),
            'addonSearchResults' => $this->searchProductsForAov('addon'),
        ]);
    }
}