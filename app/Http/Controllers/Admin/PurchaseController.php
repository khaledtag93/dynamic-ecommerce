<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Commerce\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchaseService) {}

    public function index()
    {
        $purchases = Purchase::with('supplier')->latest('id')->paginate(15);
        return view('admin.purchases.index', compact('purchases'));
    }

    public function create()
    {
        return view('admin.purchases.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::with('variants')->orderBy('name')->get(),
        ]);
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'items.product', 'items.variant']);
        return view('admin.purchases.show', compact('purchase'));
    }

    public function store(Request $request)
    {
        $items = collect($request->input('items', []))
            ->filter(fn ($item) => filled($item['product_id'] ?? null) || filled($item['quantity'] ?? null) || filled($item['unit_cost'] ?? null) || filled($item['product_variant_id'] ?? null) || filled($item['expiration_date'] ?? null))
            ->values()
            ->all();

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => __('Add at least one purchase item before saving.'),
            ]);
        }

        $request->merge(['items' => $items]);

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['nullable', 'date'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.expiration_date' => ['nullable', 'date'],
        ]);

        $createdPurchase = DB::transaction(function () use ($data) {
            $subtotal = collect($data['items'])->sum(fn ($item) => ((float) $item['unit_cost']) * ((int) $item['quantity']));
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'status' => Purchase::STATUS_ORDERED,
                'shipping_total' => $data['shipping_total'] ?? 0,
                'tax_total' => $data['tax_total'] ?? 0,
                'subtotal' => $subtotal,
                'grand_total' => $subtotal + (float) ($data['shipping_total'] ?? 0) + (float) ($data['tax_total'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                $variant = ! empty($item['product_variant_id']) ? ProductVariant::find($item['product_variant_id']) : null;

                $purchase->items()->create([
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product?->name ?? 'Product',
                    'variant_name' => $variant?->sku,
                    'sku' => $variant?->sku ?? $product?->sku,
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => (float) $item['unit_cost'],
                    'line_total' => (int) $item['quantity'] * (float) $item['unit_cost'],
                    'expiration_date' => $item['expiration_date'] ?? null,
                ]);
            }

            return $purchase;
        });

        return redirect()->route('admin.purchases.show', $createdPurchase)->with('success', __('Purchase order created successfully.'));
    }

    public function receive(Purchase $purchase)
    {
        $this->purchaseService->receive($purchase);
        return back()->with('success', __('Purchase received and stock updated successfully.'));
    }
}
