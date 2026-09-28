<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseSettlement;
use App\Models\Supplier;
use App\Services\Commerce\PurchaseReceivingService;
use App\Services\Commerce\PurchaseReceiptReversalService;
use App\Services\Commerce\PurchaseService;
use App\Services\Commerce\PurchaseSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    private const MONEY_MAX_CENTS = 999999999999;
    private const PURCHASE_QUANTITY_MAX = 4294967295;

    public function __construct(
        protected PurchaseService $purchaseService,
        protected PurchaseReceivingService $purchaseReceivingService,
        protected PurchaseReceiptReversalService $purchaseReceiptReversalService,
        protected PurchaseSettlementService $purchaseSettlementService,
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
            ->when($filters['status'], function ($query, $status) {
                $status === 'awaiting'
                    ? $query->whereIn('status', [Purchase::STATUS_ORDERED, Purchase::STATUS_PARTIALLY_RECEIVED])
                    : $query->where('status', $status);
            })
            ->when($filters['supplier_id'], fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $queueStats = [
            'awaiting' => Purchase::whereIn('status', [Purchase::STATUS_ORDERED, Purchase::STATUS_PARTIALLY_RECEIVED])->count(),
        ];

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.purchases._results', compact('purchases', 'filters', 'queueStats'));
        }

        $procurementValueByCurrency = Purchase::query()
            ->whereIn('status', [Purchase::STATUS_ORDERED, Purchase::STATUS_PARTIALLY_RECEIVED, Purchase::STATUS_RECEIVED])
            ->selectRaw("COALESCE(NULLIF(currency, ''), 'EGP') as currency_code, SUM(grand_total) as total_value")
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->currency_code => (float) $row->total_value])
            ->all();

        $stats = $queueStats + [
            'total' => Purchase::count(),
            'received' => Purchase::where('status', Purchase::STATUS_RECEIVED)->count(),
            'value_by_currency' => $procurementValueByCurrency,
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
        $purchase->load([
            'supplier',
            'items.product',
            'items.variant',
            'receivingProgress',
            'cancelledBy',
            'receipts' => fn ($query) => $query
                ->with(['items.purchaseItem', 'receivedBy', 'reversedBy'])
                ->latest('id'),
            'settlements' => fn ($query) => $query
                ->with(['recordedBy', 'voidedBy'])
                ->latest('paid_at')
                ->latest('id'),
        ]);
        $receiptKey = in_array($purchase->status, [Purchase::STATUS_ORDERED, Purchase::STATUS_PARTIALLY_RECEIVED], true)
            ? (string) Str::uuid()
            : null;
        $settlementKey = (string) Str::uuid();
        $settlementSummary = $this->purchaseSettlementService->summary($purchase);
        $canManageInventory = (bool) request()->user()?->hasPermission('inventory.manage');
        $canManageSupplierSettlement = (bool) request()->user()?->hasPermission('purchasing.settlements.manage');

        return view('admin.purchases.show', compact(
            'purchase',
            'receiptKey',
            'settlementKey',
            'settlementSummary',
            'canManageInventory',
            'canManageSupplierSettlement'
        ));
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
        $receivedNow = $this->purchaseService->receiveVerified($purchase, (int) request()->user()->id);

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
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
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
            $supplier = Supplier::query()
                ->whereKey($data['supplier_id'])
                ->lockForUpdate()
                ->first();

            if (! $supplier || ! $supplier->is_active) {
                throw ValidationException::withMessages([
                    'supplier_id' => __('Choose an active supplier before creating the purchase order.'),
                ]);
            }

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

    public function cancel(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        $cancelledNow = $this->purchaseService->cancel(
            $purchase,
            $data['cancellation_reason'],
            (int) $request->user()->id,
        );

        return redirect()->route('admin.purchases.show', $purchase)
            ->with($cancelledNow ? 'success' : 'warning', $cancelledNow
                ? __('Purchase cancelled. No inventory was changed.')
                : __('This purchase was already cancelled.'));
    }

    public function reverseReceipt(
        Request $request,
        Purchase $purchase,
        PurchaseReceipt $purchaseReceipt
    ) {
        $data = $request->validate([
            'reversal_reason' => ['required', 'string', 'max:1000'],
        ]);

        $reversedNow = $this->purchaseReceiptReversalService->reverse(
            $purchase,
            $purchaseReceipt,
            $data['reversal_reason'],
            (int) $request->user()->id,
        );

        return redirect()->route('admin.purchases.show', $purchase)
            ->with($reversedNow ? 'success' : 'warning', $reversedNow
                ? __('Purchase receipt reversed and inventory restored to its prior state.')
                : __('This purchase receipt was already reversed.'));
    }

    public function recordSettlement(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'payment_method' => ['required', 'string', Rule::in(array_keys(PurchaseSettlement::paymentMethodOptions()))],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'settlement_key' => ['required', 'uuid'],
        ]);

        $recorded = $this->purchaseSettlementService->record(
            $purchase,
            $data['amount'],
            $data['payment_method'],
            $data['reference'] ?? null,
            $data['settlement_key'],
            $data['paid_at'] ?? null,
            (int) $request->user()->id,
        );

        return redirect()->route('admin.purchases.show', $purchase)
            ->with($recorded ? 'success' : 'warning', $recorded
                ? __('Supplier payment recorded successfully.')
                : __('This supplier payment request was already processed.'));
    }

    public function voidSettlement(Request $request, Purchase $purchase, PurchaseSettlement $purchaseSettlement)
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:1000'],
        ]);

        $voided = $this->purchaseSettlementService->void(
            $purchase,
            $purchaseSettlement,
            $data['void_reason'],
            (int) $request->user()->id,
        );

        return redirect()->route('admin.purchases.show', $purchase)
            ->with($voided ? 'success' : 'warning', $voided
                ? __('Supplier payment voided successfully.')
                : __('This supplier payment was already voided.'));
    }

    public function receive(Request $request, Purchase $purchase)
    {
        $receivedNow = $this->purchaseService->receive($purchase, (int) $request->user()->id);

        return back()->with($receivedNow ? 'success' : 'warning', $receivedNow
            ? __('Purchase received and stock updated successfully.')
            : __('This purchase was already received. No stock was added again.'));
    }

    public function receivePartial(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            'receipt_key' => ['required', 'uuid'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:'.self::PURCHASE_QUANTITY_MAX],
        ]);

        $receivedNow = $this->purchaseService->receivePartial(
            $purchase,
            $data['items'],
            $data['receipt_key'],
            (int) $request->user()->id,
        );

        return redirect()->route('admin.purchases.show', $purchase)
            ->with($receivedNow ? 'success' : 'warning', $receivedNow
                ? __('Partial purchase receipt recorded and inventory updated.')
                : __('This receipt request was already processed. No stock was added again.'));
    }
}
