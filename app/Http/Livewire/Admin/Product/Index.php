<?php

namespace App\Http\Livewire\Admin\Product;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Admin\ProductService;
use App\Support\MediaPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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

    protected $listeners = ['productSaved' => '$refresh'];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'brandFilter' => ['except' => ''],
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

        $this->selectedProducts = $this->productsQuery()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function resetSelection()
    {
        $this->selectPage = false;
        $this->selectAll = false;
        $this->selectedProducts = [];
    }

    public function getSelectedCountProperty()
    {
        return count($this->selectedProducts);
    }

    public function getTotalFilteredCountProperty()
    {
        return $this->productsQuery()->count();
    }

    public function deleteSingle($id)
    {
        $product = Product::with('images')->findOrFail($id);

        foreach ($product->images as $image) {
            $path = $image->image_path;

            $filePath = MediaPath::publicUploadPath($path, 'products');

            if ($filePath && File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        $product->delete();

        $this->resetSelection();
        $this->cancelAllInlineEdits();

        session()->flash('message', 'Product deleted successfully.');
    }

    public function bulkDelete()
    {
        if (empty($this->selectedProducts)) {
            session()->flash('error', 'Please select at least one product.');
            return;
        }

        $products = Product::with('images')
            ->whereIn('id', $this->selectedProducts)
            ->get();

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

        $this->resetSelection();
        $this->cancelAllInlineEdits();

        session()->flash('message', 'Selected products deleted successfully.');
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
                "inlineBasePrice.$id.required" => 'Base price is required.',
                "inlineBasePrice.$id.numeric" => 'Base price must be a valid number.',
                "inlineBasePrice.$id.min" => 'Base price cannot be negative.',
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
                "inlineSalePrice.$id.numeric" => 'Sale price must be a valid number.',
                "inlineSalePrice.$id.min" => 'Sale price cannot be negative.',
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
                "inlineQty.$id.required" => 'Quantity is required.',
                "inlineQty.$id.integer" => 'Quantity must be a whole number.',
                "inlineQty.$id.min" => 'Quantity cannot be negative.',
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
            session()->flash('error', 'This product uses variants. Please edit price from variant rows.');
            return;
        }

        $newBasePrice = (float) $this->inlineBasePrice[$id];
        $currentSalePrice = $product->sale_price;

        if (! is_null($currentSalePrice) && (float) $currentSalePrice > $newBasePrice) {
            $this->addInlineError(
                'inlineBasePrice',
                $id,
                'Base price must be greater than or equal to the current sale price.'
            );
            return;
        }

        Product::whereKey($id)->update([
            'base_price' => $newBasePrice,
        ]);

        $this->editingBasePriceId = null;

        session()->flash('message', "Base price updated successfully for product #{$id}.");
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
            session()->flash('error', 'This product uses variants. Please edit sale price from variant rows.');
            return;
        }

        $salePrice = $this->inlineSalePrice[$id] ?? null;

        if ($salePrice === '' || $salePrice === null) {
            Product::whereKey($id)->update([
                'sale_price' => null,
            ]);

            $this->editingSalePriceId = null;
            session()->flash('message', "Sale price removed successfully for product #{$id}.");
            return;
        }

        $salePrice = (float) $salePrice;
        $basePrice = (float) ($product->base_price ?? 0);

        if ($salePrice > $basePrice) {
            $this->addInlineError(
                'inlineSalePrice',
                $id,
                'Sale price cannot be greater than base price.'
            );
            return;
        }

        Product::whereKey($id)->update([
            'sale_price' => $salePrice,
        ]);

        $this->editingSalePriceId = null;

        session()->flash('message', "Sale price updated successfully for product #{$id}.");
    }

    public function startEditQty($id, $qty)
    {
        $this->cancelAllInlineEdits();
        $this->resetInlineValidationFor('inlineQty', (int) $id);

        $this->editingQtyId = $id;
        $this->inlineQty[$id] = $qty;
    }

    public function cancelEditQty()
    {
        if ($this->editingQtyId) {
            $this->resetInlineValidationFor('inlineQty', (int) $this->editingQtyId);
        }

        $this->editingQtyId = null;
    }

    public function saveInlineQty($id)
    {
        $id = (int) $id;

        $this->resetInlineValidationFor('inlineQty', $id);
        $this->validateInlineQtyField($id);

        $product = Product::findOrFail($id);

        if ($this->productUsesVariants($product)) {
            $this->editingQtyId = null;
            session()->flash('error', 'This product uses variants. Please edit stock from variant rows.');
            return;
        }

        Product::whereKey($id)->update([
            'quantity' => (int) $this->inlineQty[$id],
        ]);

        $this->editingQtyId = null;

        session()->flash('message', "Quantity updated successfully for product #{$id}.");
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $product->status = ! $product->status;
        $product->save();
    }

    public function exportCsv()
    {
        $fileName = 'products-' . now()->format('Y-m-d-His') . '.csv';

        $sortField = in_array($this->sortField, $this->allowedSortFields, true)
            ? $this->sortField
            : 'id';

        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $products = $this->productsQuery()
            ->orderBy($sortField, $sortDirection)
            ->get();

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

            foreach ($products as $product) {
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

        session()->flash('message', 'Product duplicated successfully. You can now edit the copied product.');

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
        $query->where(function ($innerQuery) use ($search) {
            $innerQuery->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");

            if ($this->hasProductColumn('sku')) {
                $innerQuery->orWhere('sku', 'like', "%{$search}%");
            }

            if ($this->hasProductColumn('barcode')) {
                $innerQuery->orWhere('barcode', 'like', "%{$search}%");
            }
        });
    }

    protected function productsQuery()
    {
        return Product::query()
            ->with([
                'category',
                'brand',
                'mainImage',
                'productImages',
                'defaultVariant',
            ])
            ->when($this->search, function ($q) {
                $search = trim($this->search);

                if ($search !== '') {
                    $this->applySearch($q, $search);
                }
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->brandFilter, fn ($q) => $q->where('brand_id', $this->brandFilter));
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