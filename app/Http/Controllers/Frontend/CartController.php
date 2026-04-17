<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\Commerce\AIRecommendationEngine;
use App\Services\Commerce\BehaviorTrackingService;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Commerce\CouponService;
use App\Services\Commerce\OfferAutomationService;
use App\Services\Commerce\ProductRecommendationService;
use App\Services\Commerce\SmartMerchandisingService;
use App\Services\Frontend\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CouponService $couponService,
        protected ProductRecommendationService $recommendationService,
        protected SmartMerchandisingService $smartMerchandisingService,
        protected BehaviorTrackingService $behaviorTrackingService,
        protected OfferAutomationService $offerAutomationService,
        protected AIRecommendationEngine $aiRecommendationEngine,
    ) {
    }

    public function index()
    {
        $cart = $this->cartService->summary();
        $this->behaviorTrackingService->track(BehaviorTrackingService::EVENT_VIEW_CART, null, [
            'items_count' => (int) ($cart['items_count'] ?? 0),
            'subtotal' => (float) ($cart['subtotal'] ?? 0),
            'discount' => (float) ($cart['discount'] ?? 0),
            'total' => (float) ($cart['total'] ?? 0),
            'coupon_code' => $cart['coupon_code'] ?? null,
            'product_ids' => $cart['items']->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]);
        $upsellProducts = $this->recommendationService->forCart($cart['items'], 3);
        $shippingGoal = $this->recommendationService->shippingProgress((float) $cart['subtotal']);
        $merchandising = $this->smartMerchandisingService->forCart($cart['items'], 4);
        $behavioralOffers = $this->offerAutomationService->forCart($cart['items'], (float) $cart['subtotal']);
        $aiRecommendations = $this->aiRecommendationEngine->forCart($cart['items'], 4);

        return view('frontend.cart.index', [
            'cart' => $cart,
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

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $product->loadMissing([
            'mainImage',
            'productImages',
            'defaultVariant.attributes.attribute',
            'activeVariants.attributes.attribute',
        ]);

        try {
            $this->cartService->add(
                $product,
                (int) ($data['quantity'] ?? 1),
                $data['variant_id'] ?? null
            );

            $this->behaviorTrackingService->track(BehaviorTrackingService::EVENT_ADD_TO_CART, $product->id, [
                'quantity' => (int) ($data['quantity'] ?? 1),
                'variant_id' => $data['variant_id'] ?? null,
            ]);

            $message = __('Product added to cart successfully.');
            $redirectTo = (string) $request->input('redirect_to', 'back');

            return match ($redirectTo) {
                'checkout' => redirect()->route('checkout.index')->with('success', $message),
                'cart' => redirect()->route('cart.index')->with('success', $message),
                default => back()->with('success', $message),
            };
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function storeBundle(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer'],
            'bundle_product_ids' => ['nullable', 'array'],
            'bundle_product_ids.*' => ['integer', 'distinct'],
        ]);

        $product->loadMissing([
            'bundleProducts',
            'addonProducts',
            'mainImage',
            'productImages',
            'defaultVariant.attributes.attribute',
            'activeVariants.attributes.attribute',
        ]);

        $allowedIds = $product->bundleProducts->pluck('id')
            ->merge($product->addonProducts->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique();

        $selectedIds = collect($data['bundle_product_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allowedIds->contains($id))
            ->unique()
            ->values();

        try {
            $this->cartService->add(
                $product,
                (int) ($data['quantity'] ?? 1),
                $data['variant_id'] ?? null
            );

            $this->behaviorTrackingService->track(BehaviorTrackingService::EVENT_ADD_TO_CART, $product->id, [
                'quantity' => (int) ($data['quantity'] ?? 1),
                'variant_id' => $data['variant_id'] ?? null,
            ]);

            if ($selectedIds->isNotEmpty()) {
                $extraProducts = Product::query()
                    ->with(['mainImage', 'productImages', 'defaultVariant.attributes.attribute', 'activeVariants.attributes.attribute'])
                    ->where('status', 1)
                    ->whereIn('id', $selectedIds)
                    ->get();

                foreach ($extraProducts as $extraProduct) {
                    $this->cartService->add($extraProduct, 1);
                    $this->behaviorTrackingService->track(BehaviorTrackingService::EVENT_ADD_TO_CART, $extraProduct->id, ['quantity' => 1, 'flow' => 'bundle_extra']);
                }
            }

            $message = $selectedIds->isNotEmpty()
                ? __('Bundle added to cart successfully.')
                : __('Main product added to cart successfully.');

            $redirectTo = (string) $request->input('redirect_to', 'cart');

            return match ($redirectTo) {
                'checkout' => redirect()->route('checkout.index')->with('success', $message),
                'back' => back()->with('success', $message),
                default => redirect()->route('cart.index')->with('success', $message),
            };
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $this->cartService->updateQuantity(
            $cartItem->load(['product', 'variant']),
            (int) $data['quantity']
        );

        return back()->with('success', __('Cart updated successfully.'));
    }

    public function destroy(CartItem $cartItem): RedirectResponse
    {
        $this->behaviorTrackingService->track(BehaviorTrackingService::EVENT_REMOVE_FROM_CART, (int) $cartItem->product_id, ['quantity' => (int) $cartItem->quantity]);
        $this->cartService->remove($cartItem);

        return back()->with('success', __('Item removed from cart.'));
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
        ]);

        $summary = $this->cartService->summary();

        try {
            $coupon = $this->couponService->applyFromCode($data['coupon_code'], (float) $summary['subtotal']);

            return back()->with('success', __('Coupon :code applied successfully.', ['code' => $coupon->code]));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->couponService->remove();

        return back()->with('status', __('Coupon removed from cart.'));
    }
}
