<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\PosCashShift;
use App\Models\User;
use App\Services\Commerce\PosService;
use App\Services\Commerce\PosReturnService;
use App\Services\Commerce\PosCashShiftService;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function __construct(
        protected PosService $posService,
        protected PosReturnService $posReturnService,
        protected PosCashShiftService $posCashShiftService,
        protected StoreSettingsService $storeSettingsService,
    ) {}

    public function index(Request $request)
    {
        $cart = $this->posService->cartFor($request->user());
        $summary = $this->posService->summary($cart);
        $heldCarts = $this->posService->heldCartsFor($request->user());
        $heldSummaries = $heldCarts->mapWithKeys(
            fn (PosCart $heldCart) => [$heldCart->id => $this->posService->summary($heldCart)]
        );
        $canDiscount = $request->user()->hasPermission('pos.discount');
        $cashShift = PosCashShift::query()
            ->where('cashier_user_id', $request->user()->id)
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
        $cashShiftSummary = $cashShift ? $this->posCashShiftService->summary($cashShift) : null;
        $recentCashShifts = PosCashShift::query()
            ->where('cashier_user_id', $request->user()->id)
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->take(5)
            ->get();
        $recentSales = Order::query()
            ->where('sales_channel', Order::SALES_CHANNEL_POS)
            ->where('meta->cashier_user_id', (int) $request->user()->id)
            ->latest('id')
            ->take(8)
            ->get();

        $cashPaymentMethod = Order::PAYMENT_METHOD_POS_CASH;
        $cardPaymentMethod = Order::PAYMENT_METHOD_POS_CARD;

        return view('admin.pos.index', compact(
            'cart',
            'summary',
            'heldCarts',
            'heldSummaries',
            'canDiscount',
            'cashShift',
            'cashShiftSummary',
            'recentCashShifts',
            'recentSales',
            'cashPaymentMethod',
            'cardPaymentMethod'
        ));
    }

    public function productLookup(Request $request)
    {
        $term = trim((string) $request->string('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $products = Product::query()->where('status', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            })->orderBy('name')->limit(8)->get();
        $variants = ProductVariant::query()->where('status', true)
            ->whereHas('product', fn ($query) => $query->where('status', true))
            ->where(function ($query) use ($term) {
                $query->where('sku', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%");
            })->with('product')->limit(8)->get();

        $results = $products->map(fn ($product) => [
            'product_id' => $product->id, 'variant_id' => null, 'label' => $product->name,
            'sku' => $product->sku, 'barcode' => $product->barcode, 'stock' => (int) $product->quantity_value,
            'price' => (float) $product->current_price, 'selectable' => ! $product->has_variants,
        ])->concat($variants->map(fn ($variant) => [
            'product_id' => $variant->product_id, 'variant_id' => $variant->id,
            'label' => $variant->product->name . ' · ' . $variant->variant_name,
            'sku' => $variant->sku, 'barcode' => $variant->barcode, 'stock' => (int) $variant->stock,
            'price' => (float) $variant->current_price, 'selectable' => true,
        ]))->take(10)->values();

        return response()->json(['results' => $results]);
    }

    public function customerLookup(Request $request)
    {
        $term = trim((string) $request->string('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $customers = User::query()->select(['users.id', 'users.name', 'users.email'])
            ->where('role_as', 0)
            ->where(function ($query) use ($term) {
                $query->where('users.name', 'like', "%{$term}%")
                    ->orWhere('users.email', 'like', "%{$term}%")
                    ->orWhereHas('addresses', fn ($addressQuery) => $addressQuery->where('phone', 'like', "%{$term}%"));
            })->with(['addresses:id,user_id,phone,is_default_shipping'])
            ->orderBy('users.name')->limit(8)->get();

        return response()->json(['results' => $customers->map(function ($customer) {
            $phone = optional($customer->addresses->sortByDesc('is_default_shipping')->first())->phone;
            return ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email, 'phone' => $phone];
        })->values()]);
    }

    public function shifts(Request $request)
    {
        $status = (string) $request->string('status');
        $cashierSearch = trim((string) $request->string('cashier'));

        $query = PosCashShift::query()->with('cashier')->latest('opened_at');

        if ($status === 'open') {
            $query->whereNull('closed_at');
        } elseif ($status === 'closed') {
            $query->whereNotNull('closed_at');
        } elseif ($status === 'variance') {
            $query->whereNotNull('closed_at')->where('cash_variance', '!=', 0);
        }

        if ($cashierSearch !== '') {
            $query->whereHas('cashier', function ($cashierQuery) use ($cashierSearch) {
                $cashierQuery->where('name', 'like', "%{$cashierSearch}%")
                    ->orWhere('email', 'like', "%{$cashierSearch}%");
            });
        }

        $cashShifts = $query->paginate(25)->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.pos.shifts._results', compact('cashShifts'));
        }

        $shiftMetrics = [
            'open' => PosCashShift::query()->whereNull('closed_at')->count(),
            'closed' => PosCashShift::query()->whereNotNull('closed_at')->count(),
            'with_variance' => PosCashShift::query()->whereNotNull('closed_at')->where('cash_variance', '!=', 0)->count(),
            'short_total' => abs((float) PosCashShift::query()->where('cash_variance', '<', 0)->sum('cash_variance')),
            'over_total' => (float) PosCashShift::query()->where('cash_variance', '>', 0)->sum('cash_variance'),
        ];

        return view('admin.pos.shifts.index', compact('cashShifts', 'status', 'cashierSearch', 'shiftMetrics'));
    }

    public function openShift(Request $request)
    {
        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'opening_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->posCashShiftService->openShift(
            $request->user(),
            (float) $data['opening_cash'],
            $data['opening_notes'] ?? null,
        );

        return redirect()->route('admin.pos.index')->with('success', __('Cash shift opened.'));
    }

    public function closeShift(Request $request, PosCashShift $posCashShift)
    {
        $data = $request->validate([
            'closing_cash_counted' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $closed = $this->posCashShiftService->closeShift(
            $posCashShift,
            $request->user(),
            (float) $data['closing_cash_counted'],
            $data['closing_notes'] ?? null,
        );

        return redirect()->route('admin.pos.index')->with('success', __('Cash shift closed. Variance: EGP :variance', [
            'variance' => number_format((float) $closed->cash_variance, 2),
        ]));
    }

    public function attachCustomer(Request $request, PosCart $posCart, User $user)
    {
        $this->posService->attachCustomer(
            $posCart,
            $user,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('Customer attached to POS sale.'));
    }

    public function detachCustomer(Request $request, PosCart $posCart)
    {
        $this->posService->detachCustomer(
            $posCart,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('Customer removed from POS sale.'));
    }

    public function scan(Request $request, PosCart $posCart)
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ]);

        $item = $this->posService->scan(
            $posCart,
            $data['barcode'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('Added :item to the POS cart.', [
                'item' => $item->variant_name ?: $item->product_name,
            ]));
    }

    public function addCatalogItem(Request $request, PosCart $posCart, Product $product)
    {
        $data = $request->validate([
            'variant_id' => ['nullable', 'integer'],
        ]);

        $variant = null;
        if (! empty($data['variant_id'])) {
            $variant = ProductVariant::query()
                ->whereKey((int) $data['variant_id'])
                ->where('product_id', $product->id)
                ->firstOrFail();
        }

        $item = $this->posService->addCatalogItem(
            $posCart,
            $product,
            $variant,
            (int) $request->user()->id,
        );

        return redirect()->route('admin.pos.index')->with('success', __('Added :item to the POS cart.', [
            'item' => $item->variant_name ?: $item->product_name,
        ]));
    }

    public function updateQuantity(Request $request, PosCart $posCart, PosCartItem $posCartItem)
    {
        $data = $request->validate([
            'expected_quantity' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $this->posService->updateQuantity(
            $posCart,
            $posCartItem,
            (int) $data['expected_quantity'],
            (int) $data['quantity'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('POS quantity updated.'));
    }

    public function removeItem(Request $request, PosCart $posCart, PosCartItem $posCartItem)
    {
        $this->posService->removeItem(
            $posCart,
            $posCartItem,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('Item removed from the POS cart.'));
    }

    public function updateItemDiscount(Request $request, PosCart $posCart, PosCartItem $posCartItem)
    {
        $data = $request->validate([
            'discount_type' => ['required', Rule::in([
                PosService::DISCOUNT_TYPE_FIXED,
                PosService::DISCOUNT_TYPE_PERCENT,
            ])],
            'discount_value' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'discount_reason' => ['required', 'string', 'max:255'],
        ]);

        $this->posService->updateItemDiscount(
            $posCart,
            $posCartItem,
            $data['discount_type'],
            (float) $data['discount_value'],
            $data['discount_reason'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('POS line discount updated.'));
    }

    public function clearItemDiscount(Request $request, PosCart $posCart, PosCartItem $posCartItem)
    {
        $this->posService->clearItemDiscount(
            $posCart,
            $posCartItem,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('POS line discount removed.'));
    }

    public function updateCartDiscount(Request $request, PosCart $posCart)
    {
        $data = $request->validate([
            'discount_type' => ['required', Rule::in([
                PosService::DISCOUNT_TYPE_FIXED,
                PosService::DISCOUNT_TYPE_PERCENT,
            ])],
            'discount_value' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'discount_reason' => ['required', 'string', 'max:255'],
        ]);

        $this->posService->updateCartDiscount(
            $posCart,
            $data['discount_type'],
            (float) $data['discount_value'],
            $data['discount_reason'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('POS sale discount updated.'));
    }

    public function clearCartDiscount(Request $request, PosCart $posCart)
    {
        $this->posService->clearCartDiscount($posCart, (int) $request->user()->id);

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('POS sale discount removed.'));
    }

    public function clear(Request $request, PosCart $posCart)
    {
        $this->posService->clear($posCart, (int) $request->user()->id);

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('POS cart cleared.'));
    }

    public function hold(Request $request, PosCart $posCart)
    {
        $data = $request->validate([
            'hold_label' => ['nullable', 'string', 'max:80'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->posService->hold($posCart, $data, (int) $request->user()->id);

        return redirect()->route('admin.pos.index')->with('success', __('POS sale held.'));
    }

    public function resume(Request $request, PosCart $posCart)
    {
        $this->posService->resume($posCart, (int) $request->user()->id);

        return redirect()->route('admin.pos.index')->with('success', __('POS sale resumed.'));
    }

    public function discardHeld(Request $request, PosCart $posCart)
    {
        $this->posService->discardHeld($posCart, (int) $request->user()->id);

        return redirect()->route('admin.pos.index')->with('success', __('Held POS sale discarded.'));
    }

    public function checkout(Request $request, PosCart $posCart)
    {
        $data = $request->validate([
            'payment_method' => [
                'required',
                Rule::in([
                    Order::PAYMENT_METHOD_POS_CASH,
                    Order::PAYMENT_METHOD_POS_CARD,
                ]),
            ],
            'cash_received' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->posService->checkout(
            $posCart,
            $data,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.sales.show', $result['order'])
            ->with(
                $result['created'] ? 'success' : 'warning',
                $result['created']
                    ? __('POS sale completed successfully.')
                    : __('This POS cart was already completed. The original sale is shown below.')
            );
    }

    public function sale(Request $request, Order $order)
    {
        $this->authorizeSaleAccess($request, $order);
        $order->load(['items', 'payments', 'refunds.posReturnItems']);
        $returnedQuantities = $this->posReturnService->returnedQuantities($order);
        $canReturn = $request->user()->hasPermission('pos.return') && $order->canBeRefunded();

        if ($request->boolean('receipt')) {
            $paper = (string) $request->query('paper', '80');
            $paper = in_array($paper, ['58', '80', 'a4'], true) ? $paper : '80';
            $settings = $this->storeSettingsService->all();

            return view('admin.pos.receipt', compact('order', 'paper', 'settings'));
        }

        return view('admin.pos.sale', compact('order', 'returnedQuantities', 'canReturn'));
    }

    public function processReturn(Request $request, Order $order)
    {
        $this->authorizeSaleAccess($request, $order);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->posReturnService->process(
            $order,
            $data['items'],
            $data['reason'],
            $data['notes'] ?? null,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.pos.sales.show', $order)
            ->with('success', __('POS return recorded successfully.'));
    }

    protected function authorizeSaleAccess(Request $request, Order $order): void
    {
        abort_unless($order->sales_channel === Order::SALES_CHANNEL_POS, 404);

        $cashierUserId = (int) data_get($order->meta, 'cashier_user_id', 0);
        $canReviewAllOrders = $request->user()->isSuperAdmin()
            || $request->user()->hasPermission('orders.view');

        abort_unless($canReviewAllOrders || $cashierUserId === (int) $request->user()->id, 403);
    }
}
