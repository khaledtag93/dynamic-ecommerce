<?php

namespace App\Http\Livewire\Admin\Product;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Admin\ProductService;
use App\Services\Commerce\InventoryAdjustmentService;
use App\Support\MediaPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Filters
    public $search = '';
    public $statusFilter = '';
    public $categoryFilter = '';
    public $brandFilter = '';
    public $readinessFilter = '';
    public $stockFilter = '';
    public $featuredFilter = '';
    public $savedView = '';
    public $pendingDeleteId = null;
    public $pendingBulkDeleteCount = 0;
    public $perPage = 10;

    // Sorting
    public $sortField = 'id';
    public $sortDirection = 'desc';

    // Selection
    public $selectPage = false;
    public $selectAll = false;
    public $selectedProducts = [];

    // Inline Edit - Base Price
    public $editingBasePriceId = null;
    public $inlineBasePrice = [];

    // Inline Edit - Sale Price
    public $editingSalePriceId = null;
    public $inlineSalePrice = [];

    // Inline Edit - Quantity
    public $editingQtyId = null;
    public $inlineQty = [];
    public $inlineQtyOriginal = [];

    protected $listeners = ['productSaved' => '$refresh'];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'brandFilter' => ['except' => ''],
        'readinessFilter' => ['except' => ''],
        'stockFilter' => ['except' => ''],
        'featuredFilter' => ['except' => ''],
        'savedView' => ['except' => ''],
        'sortField' => ['except' => 'id'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 10],
    ];

    protected array $allowedSortFields = [
        'id',
        'name',
        'base_price',
        'sale_price',
        'quantity',
        'status',
        'updated_at',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatingBrandFilter()
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatingReadinessFilter()
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatingStockFilter()
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatingFeaturedFilter()
    {
        $this->savedView = '';
        $this->resetPage();
        $this->resetSelection();
    }

    public function applySavedView(string $view): void
    {
        $allowedViews = ['attention', 'inventory', 'storefront', 'featured'];

        if (! in_array($view, $allowedViews, true)) {
            return;
        }

        $this->reset([
            'search',
            'statusFilter',
            'categoryFilter',
            'brandFilter',
            'readinessFilter',
            'stockFilter',
            'featuredFilter',
        ]);

        $this->savedView = $view;

        if ($view === 'attention') {
            $this->readinessFilter = 'needs_attention';
        } elseif ($view === 'inventory') {
            $this->stockFilter = 'low';
        } elseif ($view === 'storefront') {
            $this->statusFilter = '1';
        } elseif ($view === 'featured') {
            $this->featuredFilter = '1';
        }

        $this->cancelAllInlineEdits();
        $this->resetPage();
        $this->resetSelection();
    }

    public function clearSavedView(): void
    {
        $this->savedView = '';
    }

    public function updatingPerPage()
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function resetFilters()
    {
        $this->reset([
            'search',
            'statusFilter',
            'categoryFilter',
            'brandFilter',
            'readinessFilter',
            'stockFilter',
            'featuredFilter',
            'savedView',
            'perPage',
        ]);

        $this->perPage = 10;
        $this->sortField = 'id';
        $this->sortDirection = 'desc';

        $this->cancelAllInlineEdits();
        $this->resetPage();
        $this->resetSelection();
        $this->resetValidation();
    }

    public function sortBy($field)
    {
        if (! in_array($field, $this->allowedSortFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSelectPage($value)
    {
        if ($value) {
            $sortField = in_array($this->sortField, $this->allowedSortFields, true)
                ? $this->sortField
                : 'id';

            $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

            $this->selectedProducts = $this->productsQuery()
                ->orderBy($sortField, $sortDirection)
                ->paginate($this->perPage)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->resetSelection();
        }
    }

    public function selectAllMatching()
    {
        $this->selectAll = true;
        $this->selectPage = true;
        $this->selectedProducts = [];
    }

    public function resetSelection()
    {
        $this->selectPage = false;
        $this->selectAll = false;
        $this->selectedProducts = [];
    }

    public function getSelectedCountProperty()
    {
        return $this->selectAll
            ? $this->totalFilteredCount
            : count($this->selectedProducts);
    }

    public function getTotalFilteredCountProperty()
    {
        return $this->productsQuery()->count();
    }

    public function getActiveFilterCountProperty(): int
    {
        return collect([
            trim((string) $this->search),
            $this->statusFilter,
            $this->categoryFilter,
            $this->brandFilter,
            $this->readinessFilter,
            $this->stockFilter,
            $this->featuredFilter,
        ])->filter(fn ($value) => $value !== '' && $value !== null)->count();
    }

    public function getCatalogOperationsProperty(): array
    {
        $health = $this->catalogHealth;

        return [
            'attention' => $health['needs_content'],
            'inventory' => $health['low_stock'] + $health['out_of_stock'],
            'storefront' => $health['active'],
            'featured' => Product::query()->where('is_featured', 1)->count(),
        ];
    }

    public function getCatalogHealthProperty(): array
    {
        $baseQuery = Product::query();

        $total = (clone $baseQuery)->count();
        $active = (clone $baseQuery)->where('status', 1)->count();
        $hidden = max(0, $total - $active);

        $needsContent = Product::query()
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '')
                    ->orWhere(function ($identifierQuery) {
                        $identifierQuery->where(function ($skuQuery) {
                            $skuQuery->whereNull('sku')->orWhere('sku', '');
                        })->where(function ($barcodeQuery) {
                            $barcodeQuery->whereNull('barcode')->orWhere('barcode', '');
                        });
                    })
                    ->orWhereDoesntHave('productImages');
            })
            ->count();

        $lowStock = Product::query()
            ->where(function ($stockQuery) {
                $stockQuery->where(function ($simpleQuery) {
                    $simpleQuery->where('has_variants', false)
                        ->whereNotNull('low_stock_threshold')
                        ->where('quantity', '>', 0)
                        ->whereColumn('quantity', '<=', 'low_stock_threshold');
                })->orWhere(function ($variantQuery) {
                    $variantQuery->where('has_variants', true)
                        ->whereHas('activeVariants', fn ($activeQuery) => $activeQuery
                            ->where('stock', '>', 0)
                            ->whereColumn('stock', '<=', 'reorder_point'));
                });
            })
            ->count();

        $outOfStock = Product::query()
            ->where(function ($stockQuery) {
                $stockQuery->where(function ($simpleQuery) {
                    $simpleQuery->where('has_variants', false)
                        ->where('quantity', '<=', 0);
                })->orWhere(function ($variantQuery) {
                    $variantQuery->where('has_variants', true)
                        ->whereDoesntHave('activeVariants', fn ($activeQuery) => $activeQuery->where('stock', '>', 0));
                });
            })
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'hidden' => $hidden,
            'needs_content' => $needsContent,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
        ];
    }

    public function requestDelete($id): void
    {
        $product = Product::findOrFail($id);

        $this->pendingDeleteId = $product->id;
        $this->dispatch(
            'open-product-delete-confirmation',
            id: $product->id,
            name: $product->name,
        );
    }

    public function cancelDelete(): void
    {
        $this->pendingDeleteId = null;
    }

    public function confirmDelete(): void
    {
        if (! $this->pendingDeleteId) {
            return;
        }

        $product = Product::with('images')->findOrFail($this->pendingDeleteId);

        foreach ($product->images as $image) {
            $path = $image->image_path;

            $filePath = MediaPath::publicUploadPath($path, 'products');

            if ($filePath && File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        $productName = $product->name;
        $product->delete();

        $this->pendingDeleteId = null;
        $this->resetSelection();
        $this->cancelAllInlineEdits();
        $this->dispatch('close-product-delete-confirmation');

        session()->flash('message', __('Product :name deleted successfully.', ['name' => $productName]));
    }

    public function requestBulkDelete(): void
    {
        if ($this->selectAll) {
            session()->flash('error', __('For safety, bulk delete is limited to explicitly selected products. Clear the all-results selection and choose the rows you want to delete.'));
            return;
        }

        if (empty($this->selectedProducts)) {
            session()->flash('error', __('Please select at least one product.'));
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->selectedProducts)));
        $actualIds = Product::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (string) $id)->all();

        if (empty($actualIds)) {
            $this->resetSelection();
            session()->flash('error', __('No matching products were found.'));
            return;
        }

        $this->selectedProducts = $actualIds;
        $this->pendingBulkDeleteCount = count($actualIds);

        $this->dispatch(
            'open-product-bulk-delete-confirmation',
            count: $this->pendingBulkDeleteCount,
        );
    }

    public function cancelBulkDelete(): void
    {
        $this->pendingBulkDeleteCount = 0;
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selectedProducts) || $this->pendingBulkDeleteCount < 1) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->selectedProducts)));

        if (count($ids) !== $this->pendingBulkDeleteCount) {
            $this->pendingBulkDeleteCount = 0;
            $this->dispatch('close-product-bulk-delete-confirmation');
            session()->flash('error', __('The product selection changed. Please review and confirm the deletion again.'));
            return;
        }

        $products = Product::with('images')
            ->whereIn('id', $ids)
            ->get();

        if ($products->count() !== $this->pendingBulkDeleteCount) {
            $this->pendingBulkDeleteCount = 0;
            $this->dispatch('close-product-bulk-delete-confirmation');
            session()->flash('error', __('The selected products changed. Please review and confirm the deletion again.'));
            return;
        }

        foreach ($products as $product) {
            foreach ($product->images as $image) {
                $path = $image->image_path;
                $filePath = MediaPath::publicUploadPath($path, 'products');

                if ($filePath && File::exists($filePath)) {
                    File::delete($filePath);
                }
            }

            $product->delete();
        }

        $deletedCount = $products->count();

        $this->pendingBulkDeleteCount = 0;
        $this->resetSelection();
        $this->cancelAllInlineEdits();
        $this->dispatch('close-product-bulk-delete-confirmation');

        session()->flash('message', __(':count selected product(s) deleted successfully.', ['count' => $deletedCount]));
    }

    protected function productUsesVariants(Product $product): bool
    {
        return (bool) ($product->has_variants ?? false);
    }

    protected function cancelAllInlineEdits(): void
    {
        $this->editingBasePriceId = null;
        $this->editingSalePriceId = null;
        $this->editingQtyId = null;
        $this->inlineQtyOriginal = [];
    }

    protected function resetInlineValidationFor(string $field, int $id): void
    {
        $this->resetErrorBag($field . '.' . $id);
        $this->resetValidation($field . '.' . $id);
    }

    protected function validateInlineBasePriceField(int $id): void
    {
        $this->validate(
            [
                "inlineBasePrice.$id" => ['required', 'numeric', 'min:0'],
            ],
            [
                "inlineBasePrice.$id.required" => __('Base price is required.'),
                "inlineBasePrice.$id.numeric" => __('Base price must be a valid number.'),
                "inlineBasePrice.$id.min" => __('Base price cannot be negative.'),
            ]
        );
    }

    protected function validateInlineSalePriceField(int $id): void
    {
        $this->validate(
            [
                "inlineSalePrice.$id" => ['nullable', 'numeric', 'min:0'],
            ],
            [
                "inlineSalePrice.$id.numeric" => __('Sale price must be a valid number.'),
                "inlineSalePrice.$id.min" => __('Sale price cannot be negative.'),
            ]
        );
    }

    protected function validateInlineQtyField(int $id): void
    {
        $this->validate(
            [
                "inlineQty.$id" => ['required', 'integer', 'min:0'],
            ],
            [
                "inlineQty.$id.required" => __('Quantity is required.'),
                "inlineQty.$id.integer" => __('Quantity must be a whole number.'),
                "inlineQty.$id.min" => __('Quantity cannot be negative.'),
            ]
        );
    }

    protected function addInlineError(string $field, int $id, string $message): void
    {
        $this->addError($field . '.' . $id, $message);
        session()->flash('error', $message);
    }

    public function startEditBasePrice($id, $price)
    {
        $this->cancelAllInlineEdits();
        $this->resetInlineValidationFor('inlineBasePrice', (int) $id);

        $this->editingBasePriceId = $id;
        $this->inlineBasePrice[$id] = $price;
    }

    public function cancelEditBasePrice()
    {
        if ($this->editingBasePriceId) {
            $this->resetInlineValidationFor('inlineBasePrice', (int) $this->editingBasePriceId);
        }

        $this->editingBasePriceId = null;
    }

    public function saveInlineBasePrice($id)
    {
        $id = (int) $id;

        $this->resetInlineValidationFor('inlineBasePrice', $id);
        $this->validateInlineBasePriceField($id);

        $product = Product::findOrFail($id);

        if ($this->productUsesVariants($product)) {
            $this->editingBasePriceId = null;
            session()->flash('error', __('This product uses variants. Please edit price from variant rows.'));
            return;
        }

        $newBasePrice = (float) $this->inlineBasePrice[$id];
        $currentSalePrice = $product->sale_price;

        if (! is_null($currentSalePrice) && (float) $currentSalePrice > $newBasePrice) {
            $this->addInlineError(
                'inlineBasePrice',
                $id,
                __('Base price must be greater than or equal to the current sale price.')
            );
            return;
        }

        Product::whereKey($id)->update([
            'base_price' => $newBasePrice,
        ]);

        $this->editingBasePriceId = null;

        session()->flash('message', __('Base price updated successfully for product #:id.', ['id' => $id]));
    }

    public function startEditSalePrice($id, $price)
    {
        $this->cancelAllInlineEdits();
        $this->resetInlineValidationFor('inlineSalePrice', (int) $id);

        $this->editingSalePriceId = $id;
        $this->inlineSalePrice[$id] = $price;
    }

    public function cancelEditSalePrice()
    {
        if ($this->editingSalePriceId) {
            $this->resetInlineValidationFor('inlineSalePrice', (int) $this->editingSalePriceId);
        }

        $this->editingSalePriceId = null;
    }

    public function saveInlineSalePrice($id)
    {
        $id = (int) $id;

        $this->resetInlineValidationFor('inlineSalePrice', $id);
        $this->validateInlineSalePriceField($id);

        $product = Product::findOrFail($id);

        if ($this->productUsesVariants($product)) {
            $this->editingSalePriceId = null;
            session()->flash('error', __('This product uses variants. Please edit sale price from variant rows.'));
            return;
        }

        $salePrice = $this->inlineSalePrice[$id] ?? null;

        if ($salePrice === '' || $salePrice === null) {
            Product::whereKey($id)->update([
                'sale_price' => null,
            ]);

            $this->editingSalePriceId = null;
            session()->flash('message', __('Sale price removed successfully for product #:id.', ['id' => $id]));
            return;
        }

        $salePrice = (float) $salePrice;
        $basePrice = (float) ($product->base_price ?? 0);

        if ($salePrice > $basePrice) {
            $this->addInlineError(
                'inlineSalePrice',
                $id,
                __('Sale price cannot be greater than base price.')
            );
            return;
        }

        Product::whereKey($id)->update([
            'sale_price' => $salePrice,
        ]);

        $this->editingSalePriceId = null;

        session()->flash('message', __('Sale price updated successfully for product #:id.', ['id' => $id]));
    }

    public function startEditQty($id, $qty)
    {
        $this->cancelAllInlineEdits();
        $this->resetInlineValidationFor('inlineQty', (int) $id);

        $product = Product::findOrFail((int) $id);
        $this->editingQtyId = $id;
        $this->inlineQty[$id] = (int) $product->quantity;
        $this->inlineQtyOriginal[$id] = (int) $product->quantity;
    }

    public function cancelEditQty()
    {
        if ($this->editingQtyId) {
            $this->resetInlineValidationFor('inlineQty', (int) $this->editingQtyId);
            unset($this->inlineQtyOriginal[(int) $this->editingQtyId]);
        }

        $this->editingQtyId = null;
    }

    public function saveInlineQty($id, InventoryAdjustmentService $adjustments)
    {
        $id = (int) $id;

        $this->resetInlineValidationFor('inlineQty', $id);
        $this->validateInlineQtyField($id);

        $product = Product::findOrFail($id);

        if ($this->productUsesVariants($product)) {
            $this->editingQtyId = null;
            session()->flash('error', __('This product uses variants. Please edit stock from variant rows.'));
            return;
        }

        if (! array_key_exists($id, $this->inlineQtyOriginal)) {
            $this->addError("inlineQty.$id", __('Reload the product quantity before editing it.'));
            return;
        }

        try {
            $movement = $adjustments->setStock(
                $id,
                null,
                (int) $this->inlineQtyOriginal[$id],
                (int) $this->inlineQty[$id],
                __('Catalog quick stock update'),
                auth()->id(),
                'catalog_inline'
            );
        } catch (ValidationException $exception) {
            $this->addError("inlineQty.$id", collect($exception->errors())->flatten()->first());
            return;
        }

        $this->editingQtyId = null;
        unset($this->inlineQtyOriginal[$id]);

        session()->flash('message', $movement
            ? __('Quantity updated successfully for product #:id.', ['id' => $id])
            : __('Stock is already at this quantity.'));
    }

    protected function contentReadinessIssues(Product $product): array
    {
        $issues = [];

        if (blank($product->description)) {
            $issues[] = __('description');
        }

        if (blank($product->sku) && blank($product->barcode)) {
            $issues[] = __('SKU or barcode');
        }

        $hasImage = $product->relationLoaded('productImages')
            ? $product->productImages->isNotEmpty()
            : $product->productImages()->exists();

        if (! $hasImage) {
            $issues[] = __('product image');
        }

        return $issues;
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);

        if ($product->status) {
            $product->status = false;
            $product->save();

            session()->flash('message', __('Product #:id is now hidden from the storefront.', ['id' => $product->id]));
            return;
        }

        $issues = $this->contentReadinessIssues($product);

        $product->status = true;
        $product->save();

        if (! empty($issues)) {
            session()->flash(
                'warning',
                __('Product activated, but content still needs: :issues.', ['issues' => implode(', ', $issues)])
            );
            return;
        }

        session()->flash('message', __('Product #:id is now active.', ['id' => $product->id]));
    }

    public function bulkSetFeatured(bool $featured): void
    {
        if ($this->selectedCount < 1) {
            session()->flash('error', __('Please select at least one product.'));
            return;
        }

        $query = $this->selectedProductsQuery();
        $count = (clone $query)->count();

        if ($count < 1) {
            $this->resetSelection();
            session()->flash('error', __('No matching products were found.'));
            return;
        }

        $query->update(['is_featured' => $featured]);
        $this->resetSelection();

        session()->flash(
            'message',
            $featured
                ? __(':count selected product(s) marked as featured.', ['count' => $count])
                : __(':count selected product(s) removed from featured.', ['count' => $count])
        );
    }

    public function bulkSetStatus(bool $active): void
    {
        if ($this->selectedCount < 1) {
            session()->flash('error', __('Please select at least one product.'));
            return;
        }

        $query = $this->selectedProductsQuery();
        $count = (clone $query)->count();

        if ($count < 1) {
            $this->resetSelection();
            session()->flash('error', __('No matching products were found.'));
            return;
        }

        $needsContent = 0;

        if ($active) {
            $needsContentQuery = clone $query;
            $this->applyNeedsContentConstraint($needsContentQuery);
            $needsContent = $needsContentQuery->count();
        }

        $query->update(['status' => $active]);
        $this->resetSelection();

        if ($active && $needsContent > 0) {
            $this->bulkFeedbackType = 'warning';
            $this->bulkFeedbackMessage = __(':count selected product(s) activated; :needs still need content review.', [
                'count' => $count,
                'needs' => $needsContent,
            ]);
            session()->flash('warning', $this->bulkFeedbackMessage);
            return;
        }

        $this->bulkFeedbackType = 'message';
        $this->bulkFeedbackMessage = $active
            ? __(':count selected product(s) activated.', ['count' => $count])
            : __(':count selected product(s) hidden.', ['count' => $count]);

        session()->flash('message', $this->bulkFeedbackMessage);
    }

    public function exportCsv()
    {
        $fileName = 'products-' . now()->format('Y-m-d-His') . '.csv';

        $sortField = in_array($this->sortField, $this->allowedSortFields, true)
            ? $this->sortField
            : 'id';

        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $products = $this->productsQuery()
            ->orderBy($sortField, $sortDirection);

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Name',
                'Slug',
                'SKU',
                'Barcode',
                'Category',
                'Brand',
                'Base Price',
                'Sale Price',
                'Current Price',
                'Quantity',
                'Stock Status',
                'Status',
                'Featured',
                'Has Variants',
                'Updated At',
            ]);

            foreach ($products->lazy(500) as $product) {
                fputcsv($handle, [
                    $product->id,
                    $product->name,
                    $product->slug,
                    $product->sku,
                    $product->barcode,
                    optional($product->category)->name,
                    optional($product->brand)->name,
                    $product->base_price,
                    $product->sale_price,
                    $product->current_price,
                    $product->quantity_value,
                    $product->stock_status,
                    $product->status ? 'Active' : 'Inactive',
                    $product->is_featured ? 'Yes' : 'No',
                    $product->has_variants ? 'Yes' : 'No',
                    optional($product->updated_at)?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function duplicate($id, ProductService $productService)
    {
        $product = Product::with('images')->findOrFail($id);

        $newProduct = $productService->duplicateProduct($product);

        session()->flash('message', __('Product duplicated with zero stock and cleared retail identifiers. You can now edit the copied product.'));

        return redirect()->route('admin.products.edit', $newProduct->id);
    }

    protected function hasProductColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::getColumnListing('products');
        }

        return in_array($column, $columns, true);
    }

    protected function applySearch($query, string $search): void
    {
        $search = mb_substr(trim($search), 0, 100);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = "%{$escaped}%";

        $query->where(function ($innerQuery) use ($like) {
            $innerQuery->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like);

            if ($this->hasProductColumn('sku')) {
                $innerQuery->orWhere('sku', 'like', $like);
            }

            if ($this->hasProductColumn('barcode')) {
                $innerQuery->orWhere('barcode', 'like', $like);
            }

            $innerQuery->orWhereHas('variants', function ($variantQuery) use ($like) {
                $variantQuery->where('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            });
        });
    }

    protected function applyReadinessFilter($query): void
    {
        if ($this->readinessFilter === 'needs_attention') {
            $this->applyNeedsContentConstraint($query);

            return;
        }

        if ($this->readinessFilter === 'ready') {
            $query->whereNotNull('description')
                ->where('description', '!=', '')
                ->where(function ($identifierQuery) {
                    $identifierQuery->whereNotNull('sku')->where('sku', '!=', '')
                        ->orWhere(function ($barcodeQuery) {
                            $barcodeQuery->whereNotNull('barcode')->where('barcode', '!=', '');
                        });
                })
                ->whereHas('productImages');
        }
    }

    protected function applyStockFilter($query): void
    {
        if ($this->stockFilter === 'low') {
            $query->where(function ($stockQuery) {
                $stockQuery->where(function ($simpleQuery) {
                    $simpleQuery->where('has_variants', false)
                        ->whereNotNull('low_stock_threshold')
                        ->where('quantity', '>', 0)
                        ->whereColumn('quantity', '<=', 'low_stock_threshold');
                })->orWhere(function ($variantQuery) {
                    $variantQuery->where('has_variants', true)
                        ->whereHas('activeVariants', fn ($activeQuery) => $activeQuery
                            ->where('stock', '>', 0)
                            ->whereColumn('stock', '<=', 'reorder_point'));
                });
            });

            return;
        }

        if ($this->stockFilter === 'out') {
            $query->where(function ($stockQuery) {
                $stockQuery->where(function ($simpleQuery) {
                    $simpleQuery->where('has_variants', false)
                        ->where('quantity', '<=', 0);
                })->orWhere(function ($variantQuery) {
                    $variantQuery->where('has_variants', true)
                        ->whereDoesntHave('activeVariants', fn ($activeQuery) => $activeQuery->where('stock', '>', 0));
                });
            });

            return;
        }

        if ($this->stockFilter === 'in') {
            $query->where(function ($stockQuery) {
                $stockQuery->where(function ($simpleQuery) {
                    $simpleQuery->where('has_variants', false)
                        ->where('quantity', '>', 0);
                })->orWhere(function ($variantQuery) {
                    $variantQuery->where('has_variants', true)
                        ->whereHas('activeVariants', fn ($activeQuery) => $activeQuery->where('stock', '>', 0));
                });
            });
        }
    }

    protected function applyNeedsContentConstraint($query): void
    {
        $query->where(function ($innerQuery) {
            $innerQuery->whereNull('description')
                ->orWhere('description', '')
                ->orWhere(function ($identifierQuery) {
                    $identifierQuery->where(function ($skuQuery) {
                        $skuQuery->whereNull('sku')->orWhere('sku', '');
                    })->where(function ($barcodeQuery) {
                        $barcodeQuery->whereNull('barcode')->orWhere('barcode', '');
                    });
                })
                ->orWhereDoesntHave('productImages');
        });
    }

    protected function selectedProductsQuery()
    {
        if ($this->selectAll) {
            return $this->productsQuery(false);
        }

        $ids = array_values(array_unique(array_map('intval', $this->selectedProducts)));

        return Product::query()->whereIn('id', $ids);
    }

    protected function productsQuery(bool $withRelations = true)
    {
        $query = Product::query();

        if ($withRelations) {
            $query->with([
                'category',
                'brand',
                'mainImage',
                'productImages',
                'defaultVariant',
            ]);
        }

        return $query
            ->when($this->search, function ($q) {
                $search = trim($this->search);

                if ($search !== '') {
                    $this->applySearch($q, $search);
                }
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->brandFilter, fn ($q) => $q->where('brand_id', $this->brandFilter))
            ->when($this->readinessFilter !== '', fn ($q) => $this->applyReadinessFilter($q))
            ->when($this->stockFilter !== '', fn ($q) => $this->applyStockFilter($q))
            ->when($this->featuredFilter !== '', fn ($q) => $q->where('is_featured', $this->featuredFilter));
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->allowedSortFields, true)
            ? $this->sortField
            : 'id';

        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $products = $this->productsQuery()
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->perPage);

        $categories = Category::orderBy('name')->get();
        $brands = Brand::orderBy('name')->get();

        return view('livewire.admin.product.index', compact('products', 'categories', 'brands'));
    }
}
