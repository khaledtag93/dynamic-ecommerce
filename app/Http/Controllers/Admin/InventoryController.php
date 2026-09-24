<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\Commerce\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(protected InventoryAdjustmentService $adjustmentService) {}

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'type' => (string) $request->string('type'),
            'reference' => (string) $request->string('reference'),
            'per_page' => max(20, min(100, (int) $request->integer('per_page', 20))),
        ];

        $movements = InventoryMovement::query()
            ->with(['product', 'variant', 'purchase', 'order'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('variant', fn ($variant) => $variant->where('sku', 'like', "%{$search}%"))
                        ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['reference'] === 'order', fn ($query) => $query->whereNotNull('order_id'))
            ->when($filters['reference'] === 'purchase', fn ($query) => $query->whereNotNull('purchase_id'))
            ->when($filters['reference'] === 'manual', fn ($query) => $query->whereNull('order_id')->whereNull('purchase_id'))
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();
        $lowStockProducts = Product::query()
            ->where(function ($query) {
                $query->where('has_variants', false)->whereColumn('quantity', '<=', 'reorder_point');
            })
            ->orWhere(function ($query) {
                $query->where('has_variants', false)->whereColumn('quantity', '<=', 'low_stock_threshold');
            })
            ->latest('id')
            ->take(12)
            ->get();

        $nearExpiryProducts = Product::query()
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', now()->addDays(30))
            ->orderBy('expiration_date')
            ->take(12)
            ->get();

        $movementTypes = InventoryMovement::query()
            ->whereNotNull('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return view('admin.inventory.index', compact('movements', 'lowStockProducts', 'nearExpiryProducts', 'filters', 'movementTypes'));
    }

    public function adjustForm(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $products = Product::query()
            ->when($search, fn ($query) => $query->where(fn ($matches) => $matches
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(30)
            ->get();

        $productId = $request->integer('product_id');
        $product = $productId ? Product::query()->with('variants')->findOrFail($productId) : null;
        $variant = null;
        if ($product && $request->filled('variant_id')) {
            abort_unless($product->has_variants, 404);
            $variant = $product->variants->firstWhere('id', $request->integer('variant_id'));
            abort_unless($variant, 404);
        }

        return view('admin.inventory.adjust', compact('products', 'product', 'variant', 'search'));
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'expected_stock' => ['required', 'integer'],
            'new_stock' => ['required', 'integer', 'min:0', 'max:999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            $movement = $this->adjustmentService->setStock(
                (int) $data['product_id'],
                isset($data['variant_id']) ? (int) $data['variant_id'] : null,
                (int) $data['expected_stock'],
                (int) $data['new_stock'],
                $data['reason'],
                (int) $request->user()->id,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.inventory.index')->with(
            $movement ? 'success' : 'warning',
            $movement ? __('Stock adjustment recorded.') : __('Stock already matches the requested quantity. No movement was recorded.'),
        );
    }
}
