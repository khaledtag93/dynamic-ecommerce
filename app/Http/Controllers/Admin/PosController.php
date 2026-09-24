<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Services\Commerce\PosService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function __construct(protected PosService $posService) {}

    public function index(Request $request)
    {
        $cart = $this->posService->cartFor($request->user());
        $summary = $this->posService->summary($cart);

        $recentSales = Order::query()
            ->where('sales_channel', Order::SALES_CHANNEL_POS)
            ->where('meta->cashier_user_id', (int) $request->user()->id)
            ->latest('id')
            ->take(8)
            ->get();

        return view('admin.pos.index', compact('cart', 'summary', 'recentSales'));
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

    public function clear(Request $request, PosCart $posCart)
    {
        $this->posService->clear($posCart, (int) $request->user()->id);

        return redirect()
            ->route('admin.pos.index')
            ->with('success', __('POS cart cleared.'));
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
        abort_unless($order->sales_channel === Order::SALES_CHANNEL_POS, 404);

        $cashierUserId = (int) data_get($order->meta, 'cashier_user_id', 0);
        $canReviewAllOrders = $request->user()->isSuperAdmin()
            || $request->user()->hasPermission('orders.view');

        abort_unless($canReviewAllOrders || $cashierUserId === (int) $request->user()->id, 403);

        $order->load(['items', 'payments']);

        return view('admin.pos.sale', compact('order'));
    }
}
