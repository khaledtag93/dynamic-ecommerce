<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\User;
use App\Services\Commerce\PosService;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function __construct(
        protected PosService $posService,
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
        $customerSearch = trim((string) $request->string('customer_search'));
        $customerResults = collect();

        if (mb_strlen($customerSearch) >= 2) {
            $customerResults = User::query()
                ->select(['id', 'name', 'email'])
                ->where('role_as', 0)
                ->where(function ($query) use ($customerSearch) {
                    $query->where('name', 'like', "%{$customerSearch}%")
                        ->orWhere('email', 'like', "%{$customerSearch}%");
                })
                ->orderBy('name')
                ->orderBy('id')
                ->limit(8)
                ->get();
        }

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
            'customerSearch',
            'customerResults',
            'recentSales',
            'cashPaymentMethod',
            'cardPaymentMethod'
        ));
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
        $order->load(['items', 'payments']);

        if ($request->boolean('receipt')) {
            $paper = (string) $request->query('paper', '80');
            $paper = in_array($paper, ['58', '80', 'a4'], true) ? $paper : '80';
            $settings = $this->storeSettingsService->all();

            return view('admin.pos.receipt', compact('order', 'paper', 'settings'));
        }

        return view('admin.pos.sale', compact('order'));
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
