<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Services\Commerce\AIRecommendationEngine;
use App\Services\Commerce\BehaviorTrackingService;
use App\Services\Commerce\OfferAutomationService;
use App\Services\Commerce\OrderActionService;
use App\Services\Commerce\PaymentService;
use App\Services\Frontend\CartService;
use App\Services\Frontend\CheckoutService;
use App\Services\Commerce\StoreSettingsService;
use App\Services\Commerce\ProductRecommendationService;
use App\Services\Commerce\SmartMerchandisingService;
use App\Services\Commerce\ShippingService;
use App\Services\Commerce\ReturnRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService,
        protected OrderActionService $orderActionService,
        protected PaymentService $paymentService,
        protected StoreSettingsService $storeSettingsService,
        protected ProductRecommendationService $recommendationService,
        protected SmartMerchandisingService $smartMerchandisingService,
        protected BehaviorTrackingService $behaviorTrackingService,
        protected OfferAutomationService $offerAutomationService,
        protected AIRecommendationEngine $aiRecommendationEngine,
        protected ShippingService $shippingService,
        protected ReturnRequestService $returnRequestService,
    ) {
    }

    public function index(Request $request)
    {
        $cart = $this->cartService->summary();

        if ($cart['items']->isEmpty()) {
            return redirect()
                ->route('cart.index')
                ->with('status', 'Your cart is empty.');
        }

        $paymentOptions = $this->paymentService->paymentOptionsForCheckout();
        $savedAddresses = $request->user()->addresses()->orderByDesc('is_default_shipping')->orderBy('id')->get();
        $requestedAddress = $request->query('address');
        abort_if($requestedAddress !== null && $requestedAddress !== 'new' &&
            (! is_string($requestedAddress) || ! ctype_digit($requestedAddress)), 404);
        $selectedShippingAddress = $requestedAddress === 'new' ? null :
            ($requestedAddress === null ? $savedAddresses->firstWhere('is_default_shipping', true) :
                $savedAddresses->firstWhere('id', (int) $requestedAddress));
        abort_if($requestedAddress !== null && $requestedAddress !== 'new' && ! $selectedShippingAddress, 404);
        $defaultBillingAddress = $savedAddresses->firstWhere('is_default_billing', true);
        $deliveryOptions = $this->shippingService->methodOptions();
        $upsellProducts = $this->recommendationService->forCart($cart['items'], 3);
        $shippingGoal = null;
        $merchandising = $this->smartMerchandisingService->forCart($cart['items'], 4);
        $this->behaviorTrackingService->track(BehaviorTrackingService::EVENT_CHECKOUT_START, null, [
            'items_count' => (int) $cart['items_count'],
            'subtotal' => (float) $cart['subtotal'],
            'discount' => (float) ($cart['discount'] ?? 0),
            'total' => (float) ($cart['total'] ?? 0),
            'coupon_code' => $cart['coupon_code'] ?? null,
            'product_ids' => $cart['items']->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]);
        $behavioralOffers = $this->offerAutomationService->forCheckout($cart['items'], (float) $cart['subtotal']);
        $aiRecommendations = $this->aiRecommendationEngine->forCheckout($cart['items'], 3);

        return view('frontend.checkout.index', [
            'cart' => $cart,
            'savedAddresses' => $savedAddresses,
            'selectedShippingAddress' => $selectedShippingAddress,
            'defaultBillingAddress' => $defaultBillingAddress,
            'paymentOptions' => $paymentOptions,
            'deliveryOptions' => $deliveryOptions,
            'upsellProducts' => $upsellProducts,
            'shippingGoal' => $shippingGoal,
            'recentlyViewedProducts' => $merchandising['recentlyViewed'],
            'personalizedProducts' => $merchandising['recommendedForYou'],
            'offerSignals' => $merchandising['offerSignals'],
            'behavioralOffers' => $behavioralOffers,
            'aiRecommendedProducts' => $aiRecommendations['products'],
            'aiRecommendationInsight' => $aiRecommendations['insight'],
        ]);
    }
    public function shippingQuote(Request $request)
    {
        $data = $request->validate([
            'delivery_method' => ['required', 'string', 'max:60'],
            'shipping_city' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'max:120'],
        ]);

        $cart = $this->cartService->summary();

        if ($cart['items']->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => __('Your cart is empty.'),
            ]);
        }

        $quote = $this->shippingService->quote(
            $data['delivery_method'],
            (string) ($data['shipping_city'] ?? ''),
            (string) ($data['shipping_country'] ?? ''),
            (float) $cart['subtotal'],
            (float) $cart['discount'],
        );

        $total = round(max(
            0,
            (float) $cart['subtotal']
                + (float) $quote['amount']
                + (float) $cart['tax']
                - (float) $cart['discount']
        ), 2);

        return response()->json([
            'shipping' => (float) $quote['amount'],
            'total' => $total,
            'currency' => 'EGP',
            'method_name' => $quote['method_name'],
            'zone_name' => $quote['zone_name'],
            'eta_label' => $quote['eta_label'],
            'pickup' => $quote['pickup'],
            'free_shipping_threshold' => $quote['free_shipping_threshold'],
            'free_shipping_remaining' => $quote['free_shipping_remaining'],
            'free_shipping_progress' => $quote['free_shipping_progress'],
            'free_shipping_qualified' => $quote['free_shipping_qualified'],
        ]);
    }

public function store(Request $request): RedirectResponse
{
    $billingSameAsShipping = $request->boolean('billing_same_as_shipping', true);
    $enabledMethods = $this->paymentService->enabledMethods();

    $data = $request->validate([
        'customer_name' => ['required', 'string', 'max:255'],
        'customer_email' => ['required', 'email', 'max:255'],
        'customer_phone' => ['required', 'string', 'max:50'],

        'shipping_address_line_1' => ['required', 'string', 'max:255'],
        'shipping_address_line_2' => ['nullable', 'string', 'max:255'],
        'shipping_city' => ['required', 'string', 'max:255'],
        'shipping_state' => ['nullable', 'string', 'max:255'],
        'shipping_postal_code' => ['nullable', 'string', 'max:50'],
        'shipping_country' => ['required', 'string', 'max:120'],

        'billing_same_as_shipping' => ['nullable', 'boolean'],

        'billing_address_line_1' => [Rule::requiredIf(! $billingSameAsShipping), 'nullable', 'string', 'max:255'],
        'billing_address_line_2' => ['nullable', 'string', 'max:255'],
        'billing_city' => [Rule::requiredIf(! $billingSameAsShipping), 'nullable', 'string', 'max:255'],
        'billing_state' => ['nullable', 'string', 'max:255'],
        'billing_postal_code' => ['nullable', 'string', 'max:50'],
        'billing_country' => [Rule::requiredIf(! $billingSameAsShipping), 'nullable', 'string', 'max:120'],

        'payment_method' => ['required', Rule::in($enabledMethods)],
        'delivery_method' => [
            'required',
            Rule::exists('shipping_methods', 'code')->where(fn ($query) => $query->where('is_active', true)),
        ],
        'notes' => ['nullable', 'string', 'max:1000'],
    ]);

    $data['billing_same_as_shipping'] = $billingSameAsShipping;

    try {
        $order = $this->checkoutService->place($data, $request->user());
        $this->behaviorTrackingService->track(BehaviorTrackingService::EVENT_ORDER_COMPLETE, null, [
            'order_id' => (int) $order->id,
            'order_number' => (string) $order->order_number,
            'grand_total' => (float) $order->grand_total,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'shipping_total' => (float) $order->shipping_total,
            'payment_method' => (string) $order->payment_method,
            'delivery_method' => (string) $order->delivery_method,
            'coupon_code' => $order->coupon_code,
            'items_count' => (int) $order->items()->sum('quantity'),
            'product_ids' => $order->items()->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]);

        if ($order->payment_method === Order::PAYMENT_METHOD_ONLINE) {
            return redirect()
                ->route('payments.paymob.redirect', $order)
                ->with('success', 'Order placed successfully. Redirecting to secure payment...');
        }

        return redirect()
            ->route('orders.success', $order)
            ->with('success', 'Order placed successfully.');
    } catch (ValidationException $e) {
        $errors = $e->errors();

        if (isset($errors['shipping_city']) || isset($errors['delivery_method'])) {
            return redirect()
                ->route('checkout.index')
                ->withInput()
                ->withErrors($errors);
        }

        return redirect()
            ->route('cart.index')
            ->withErrors($errors)
            ->with('error', $errors['cart'][0] ?? $errors['coupon'][0] ?? 'Some cart items are no longer available in the requested quantity.');
    }
}

    public function success(Order $order)
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);

        $order->load(['items', 'refunds', 'payments']);
        $paymentInstructions = $this->paymentService->checkoutInstructionsFor($order);

        return view('frontend.orders.success', compact('order', 'paymentInstructions'));
    }

    public function orders(Request $request)
    {
        $orders = Order::query()
            ->withCount('items')
            ->where('user_id', auth()->id())
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('frontend.orders._results', compact('orders'));
        }

        return view('frontend.orders.index', compact('orders'));
    }

    public function showOrder(Order $order)
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);

        $order->load(['items', 'refunds', 'payments', 'returnRequests.items']);
        $paymentInstructions = $this->paymentService->checkoutInstructionsFor($order);
        $canRequestReturn = $this->returnRequestService->canCustomerRequest($order, auth()->user());
        $remainingReturnableTotal = $order->items->sum(
            fn ($item) => $this->returnRequestService->remainingReturnableQuantity($item)
        );

        return view('frontend.orders.show', compact(
            'order',
            'paymentInstructions',
            'canRequestReturn',
            'remainingReturnableTotal'
        ));
    }

    public function receipt(Order $order)
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);

        $order->load(['items', 'payments', 'refunds']);

        return view('orders.receipt', [
            'order' => $order,
            'settings' => $this->storeSettingsService->all(),
            'backUrl' => route('orders.show', $order),
            'backLabel' => __('Back to order'),
        ]);
    }

    public function cancelOrder(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);

        if (($this->storeSettingsService->all()['orders_allow_customer_cancellation'] ?? '1') !== '1') {
            return back()->with('error', __('Customer-side cancellation is disabled right now. Please contact support.'));
        }

        $data = $request->validate([
            'cancelled_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->orderActionService->cancel($order, $data['cancelled_reason'] ?? 'Cancelled by customer.', (int) auth()->id());

            return redirect()
                ->route('orders.show', $order)
                ->with('success', __('Order cancelled successfully. Stock was restored and the order timeline was updated.'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }
}
