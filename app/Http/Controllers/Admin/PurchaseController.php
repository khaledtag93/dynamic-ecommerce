<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\Commerce\PurchaseReceivingService;
use App\Services\Commerce\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    private const MONEY_MAX_CENTS = 999999999999;
    private const PURCHASE_QUANTITY_MAX = 4294967295;

    public function __construct(
        protected PurchaseService $purchaseService,
        protected PurchaseReceivingService $purchaseReceivingService,
    ) {}

    public function index(Request $request)
    {
        $search = mb_substr(trim((string) $request->string('search')), 0, 100);
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%' . $escapedSearch . '%';

        $filters = [
            'search' => $search,
            'status' => (string) $request->string('status'),
            'supplier_id' => (string) $request->string('supplier_id'),
            'per_page' => max(15, min(100, (int) $request->integer('per_page', 15))),
        ];

        $purchases = Purchase::query()
            ->with('supplier')
            ->when($filters['search'], function ($query) use ($like) {
                $query->where(function ($inner) use ($like) {
                    $inner->where('reference', 'like', $like)
                        ->orWhereHas('supplier', fn ($supplier) => $supplier
                            ->where('name', 'like', $like)
                            ->orWhere('company', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['supplier_id'], fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $queueStats = [
            'awaiting' => Purchase::where('status', Purchase::STATUS_ORDERED)->count(),
        ];

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.purchases._results', compact('purchases', 'filters', 'queueStats'));
        }

        $stats = $queueStats + [
            'total' => Purchase::count(),
            'received' => Purchase::where('status', Purchase::STATUS_RECEIVED)->count(),
            'value' => (float) Purchase::sum('grand_total'),
        ];

        $suppliers = Supplier::orderBy('name')->get(['id', 'name', 'company']);

        return view('admin.purchases.index', compact('purchases', 'filters', 'stats', 'queueStats', 'suppliers'));
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
        $purchase->load(['supplier', 'items.product', 'items.variant', 'receivingProgress']);
        return view('admin.purchases.show', compact('purchase'));
    }

    public function receiving(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'items.product',
            'items.variant',
            'receivingProgress.lastScannedBy',
        ]);

        $progressByItem = $purchase->receivingProgress->keyBy('purchase_item_id');
        $orderedUnits = (int) $purchase->items->sum(fn ($item) => (int) $item->quantity);
        $verifiedUnits = (int) $purchase->items->sum(
            fn ($item) => min(
                (int) $item->quantity,
                (int) optional($progressByItem->get($item->id))->verified_quantity
            )
        );
        $remainingUnits = max(0, $orderedUnits - $verifiedUnits);
        $complete = $purchase->items->isNotEmpty()
            && $purchase->items->every(
                fn ($item) => (int) optional($progressByItem->get($item->id))->verified_quantity === (int) $item->quantity
            );

        $receivingStats = [
            'ordered_units' => $orderedUnits,
            'verified_units' => $verifiedUnits,
            'remaining_units' => $remainingUnits,
            'complete' => $complete,
        ];

        return view('admin.purchases.receiving', compact(
            'purchase',
            'progressByItem',
            'receivingStats'
        ));
    }

    public function scanReceiving(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
            'purchase_item_id' => ['nullable', 'integer'],
        ]);

        $result = $this->purchaseReceivingService->scan(
            $purchase,
            $data['barcode'],
            (int) $request->user()->id,
            isset($data['purchase_item_id']) ? (int) $data['purchase_item_id'] : null,
        );

        if ($result['status'] === 'needs_line_selection') {
            return redirect()
                ->route('admin.purchases.receiving', $purchase)
                ->with('warning', __('This barcode matches more than one purchase line. Choose the exact line before counting the scan.'))
                ->with('receiving_barcode', trim((string) $data['barcode']))
                ->with('receiving_choices', $result['choices']);
        }

        if ($result['status'] === 'already_complete') {
            return redirect()
                ->route('admin.purchases.receiving', $purchase)
                ->with('warning', __('That purchase line is already fully verified. No extra unit was counted.'));
        }

        return redirect()
            ->route('admin.purchases.receiving', $purchase)
            ->with('success', $result['status'] === 'line_complete'
                ? __('Purchase line fully verified.')
                : __('One unit verified by barcode.'));
    }

    public function undoReceiving(Request $request, Purchase $purchase, PurchaseItem $purchaseItem)
    {
        $changed = $this->purchaseReceivingService->undoOne(
            $purchase,
            $purchaseItem,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.purchases.receiving', $purchase)
            ->with($changed ? 'success' : 'warning', $changed
                ? __('Removed one verified unit from this purchase line.')
                : __('This purchase line has no verified units to undo.'));
    }

    public function receiveVerified(Purchase $purchase)
    {
        $receivedNow = $this->purchaseService->receiveVerified($purchase);

        return redirect()
            ->route('admin.purchases.show', $purchase)
            ->with($receivedNow ? 'success' : 'warning', $receivedNow
                ? __('Barcode-verified purchase received and stock updated successfully.')
                : __('This purchase was already received. No stock was added again.'));
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
            'shipping_total' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'tax_total' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::PURCHASE_QUANTITY_MAX],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'items.*.expiration_date' => ['nullable', 'date'],
        ]);

        $subtotalCents = 0;

        foreach ($data['items'] as $index => &$item) {
            $quantity = (int) $item['quantity'];
            $unitCostCents = $this->moneyToCents($item['unit_cost']);

            if ($unitCostCents > intdiv(self::MONEY_MAX_CENTS, $quantity)) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_cost" => __('This purchase line total exceeds the supported maximum.'),
                ]);
            }

            $lineTotalCents = $unitCostCents * $quantity;

            if ($subtotalCents > self::MONEY_MAX_CENTS - $lineTotalCents) {
                throw ValidationException::withMessages([
                    'items' => __('The purchase total exceeds the supported maximum.'),
                ]);
            }

            $subtotalCents += $lineTotalCents;
            $item['unit_cost'] = $this->centsToMoney($unitCostCents);
            $item['line_total'] = $this->centsToMoney($lineTotalCents);
        }
        unset($item);

        $shippingCents = $this->moneyToCents($data['shipping_total'] ?? 0);
        $taxCents = $this->moneyToCents($data['tax_total'] ?? 0);

        if ($subtotalCents > self::MONEY_MAX_CENTS - $shippingCents) {
            throw ValidationException::withMessages([
                'shipping_total' => __('The purchase total exceeds the supported maximum.'),
            ]);
        }

        $grandTotalCents = $subtotalCents + $shippingCents;

        if ($grandTotalCents > self::MONEY_MAX_CENTS - $taxCents) {
            throw ValidationException::withMessages([
                'tax_total' => __('The purchase total exceeds the supported maximum.'),
            ]);
        }

        $grandTotalCents += $taxCents;
        $data['shipping_total'] = $this->centsToMoney($shippingCents);
        $data['tax_total'] = $this->centsToMoney($taxCents);
        $subtotal = $this->centsToMoney($subtotalCents);
        $grandTotal = $this->centsToMoney($grandTotalCents);

        $createdPurchase = DB::transaction(function () use ($data, $subtotal, $grandTotal) {
            $products = Product::query()->whereIn('id', collect($data['items'])->pluck('product_id')->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $variants = ProductVariant::query()->whereIn('id', collect($data['items'])->pluck('product_variant_id')->filter()->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($data['items'] as $index => $item) {
                $product = $products->get($item['product_id']);
                $variantId = $item['product_variant_id'] ?? null;
                if (! $product || ($variantId && ! $variants->get($variantId))) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => __('Purchase items have missing or mismatched products or variants. Stock was not changed.'),
                    ]);
                }
                if ($product->has_variants && ! $variantId) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_variant_id" => __('Choose a variant for products that use variants.'),
                    ]);
                }
                if ($variantId && (int) $variants->get($variantId)->product_id !== (int) $product->id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_variant_id" => __('The selected variant does not belong to this product.'),
                    ]);
                }
            }

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'status' => Purchase::STATUS_ORDERED,
                'shipping_total' => $data['shipping_total'],
                'tax_total' => $data['tax_total'],
                'subtotal' => $subtotal,
                'grand_total' => $grandTotal,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $variant = ! empty($item['product_variant_id']) ? $variants->get($item['product_variant_id']) : null;

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->sku,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $item['line_total'],
                    'expiration_date' => $item['expiration_date'] ?? null,
                ]);
            }

            return $purchase;
        });

        return redirect()->route('admin.purchases.show', $createdPurchase)->with('success', __('Purchase order created successfully.'));
    }

    protected function moneyToCents(mixed $value): int
    {
        $money = trim((string) ($value ?? '0'));
        [$whole, $fraction] = array_pad(explode('.', $money, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    protected function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function receive(Purchase $purchase)
    {
        $receivedNow = $this->purchaseService->receive($purchase);

        return back()->with($receivedNow ? 'success' : 'warning', $receivedNow
            ? __('Purchase received and stock updated successfully.')
            : __('This purchase was already received. No stock was added again.'));
    }
}
