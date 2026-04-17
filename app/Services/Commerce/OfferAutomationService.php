<?php

namespace App\Services\Commerce;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\PromotionRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OfferAutomationService
{
    public function __construct(protected BehaviorTrackingService $behaviorTrackingService)
    {
    }

    public function forProduct(Product $product): array
    {
        $events = $this->behaviorTrackingService->currentVisitorQuery()->get();
        $viewCount = (int) $events->where('event', BehaviorTrackingService::EVENT_VIEW_PRODUCT)
            ->where('product_id', $product->id)
            ->count();
        $cartCount = (int) $events->where('event', BehaviorTrackingService::EVENT_ADD_TO_CART)
            ->where('product_id', $product->id)
            ->count();

        $cards = collect();

        if ($viewCount >= 3) {
            $cards->push([
                'tone' => 'warning',
                'icon' => 'bi-lightning-charge-fill',
                'title' => __('High-intent product'),
                'body' => __('This visitor returned to the same product multiple times. It is a strong moment to reinforce urgency and move toward checkout.'),
            ]);
        }

        if ($cartCount >= 1) {
            $cards->push([
                'tone' => 'success',
                'icon' => 'bi-bag-check-fill',
                'title' => __('Already added before'),
                'body' => __('This product was added to cart earlier in the same shopping journey. A fast buy-now path removes friction for returning intent.'),
            ]);
        }

        if ($offer = $this->bestPromotionOffer()) {
            $cards->push($offer);
        }

        if ($coupon = $this->bestCouponOffer()) {
            $cards->push($coupon);
        }

        return [
            'cards' => $cards->take(3)->values(),
            'stats' => [
                'view_count' => $viewCount,
                'add_to_cart_count' => $cartCount,
            ],
        ];
    }

    public function forCart(Collection $items, float $subtotal): array
    {
        $events = $this->behaviorTrackingService->currentVisitorQuery()->latest('id')->get();
        $lastAdd = $events->firstWhere('event', BehaviorTrackingService::EVENT_ADD_TO_CART);
        $lastCheckout = $events->firstWhere('event', BehaviorTrackingService::EVENT_CHECKOUT_START);
        $completedOrder = $events->firstWhere('event', BehaviorTrackingService::EVENT_ORDER_COMPLETE);

        $cards = collect();

        $lastAddAt = $lastAdd?->occurred_at;
        $lastCheckoutAt = $lastCheckout?->occurred_at;

        $shouldShowWarmCartCard = false;

        if ($lastAdd && ! $completedOrder) {
            if (! $lastCheckout) {
                $shouldShowWarmCartCard = true;
            } elseif ($lastAddAt && $lastCheckoutAt && $lastAddAt->gt($lastCheckoutAt)) {
                $shouldShowWarmCartCard = true;
            }
        }

        if ($shouldShowWarmCartCard && $lastAddAt) {
            $minutes = max(1, now()->diffInMinutes($lastAddAt));
            $cards->push([
                'tone' => 'warning',
                'icon' => 'bi-clock-history',
                'title' => __('Cart momentum is still warm'),
                'body' => __('Products were added about :minutes minutes ago. Completing checkout while intent is fresh usually protects conversion.', ['minutes' => $minutes]),
            ]);
        }

        if ($subtotal >= 500) {
            $cards->push([
                'tone' => 'success',
                'icon' => 'bi-gift-fill',
                'title' => __('High-value basket'),
                'body' => __('This basket already looks strong. Premium reassurance, shipping clarity, and small rewards matter more on bigger totals.'),
            ]);
        }

        if ($coupon = $this->bestCouponOffer($subtotal)) {
            $cards->push($coupon);
        }

        if ($offer = $this->bestPromotionOffer($subtotal)) {
            $cards->push($offer);
        }

        return [
            'cards' => $cards->take(4)->values(),
            'has_abandoned_signal' => (bool) ($lastAdd && ! $completedOrder),
        ];
    }

    public function forCheckout(Collection $items, float $subtotal): array
    {
        $events = $this->behaviorTrackingService->currentVisitorQuery()->latest('id')->get();
        $checkoutStarts = (int) $events->where('event', BehaviorTrackingService::EVENT_CHECKOUT_START)->count();
        $cards = collect();

        if ($checkoutStarts > 1) {
            $cards->push([
                'tone' => 'primary',
                'icon' => 'bi-stars',
                'title' => __('Returning checkout visitor'),
                'body' => __('This customer came back to checkout again. Keep distractions low and reinforce trust, delivery clarity, and secure payment language.'),
            ]);
        }

        if ($coupon = $this->bestCouponOffer($subtotal)) {
            $cards->push($coupon);
        }

        if ($offer = $this->bestPromotionOffer($subtotal)) {
            $cards->push($offer);
        }

        $cards->push([
            'tone' => 'success',
            'icon' => 'bi-shield-lock-fill',
            'title' => __('Behavior-based reassurance'),
            'body' => __('This customer already showed intent through views and cart actions. The final step now is clarity, not more friction.'),
        ]);

        return [
            'cards' => $cards->take(4)->values(),
            'checkout_attempts' => $checkoutStarts,
        ];
    }

    protected function bestCouponOffer(float $subtotal = 0): ?array
    {
        $query = Coupon::query()->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });

        if (Schema::hasColumn('coupons', 'is_featured')) {
            $query->orderByDesc('is_featured');
        }

        $coupon = $query->orderByDesc('value')->get()->first(function (Coupon $coupon) use ($subtotal) {
            return $coupon->hasRemainingUsage() && ($coupon->min_order_amount === null || $subtotal >= (float) $coupon->min_order_amount);
        });

        if (! $coupon) {
            return null;
        }

        $discountValue = $coupon->type === Coupon::TYPE_PERCENT
            ? __(':value% off', ['value' => rtrim(rtrim(number_format((float) $coupon->value, 2, '.', ''), '0'), '.')])
            : __('EGP :value off', ['value' => number_format((float) $coupon->value, 2)]);

        return [
            'tone' => 'primary',
            'icon' => 'bi-ticket-perforated-fill',
            'title' => __('Offer signal: :code', ['code' => $coupon->code]),
            'body' => __('Active coupon detected for this visit. Put the saving in front of the customer early: :discount.', ['discount' => $discountValue]),
        ];
    }

    protected function bestPromotionOffer(float $subtotal = 0): ?array
    {
        $rule = PromotionRule::active()
            ->orderByDesc('priority')
            ->orderByDesc('discount_value')
            ->get()
            ->first(function (PromotionRule $rule) use ($subtotal) {
                return ! $rule->min_subtotal || $subtotal >= (float) $rule->min_subtotal;
            });

        if (! $rule) {
            return null;
        }

        $copy = match ($rule->type) {
            PromotionRule::TYPE_BUY_X_GET_Y => __('Buy :buy and get :get automatically. This works well as a behavioral trigger because the reward feels immediate.', ['buy' => (int) $rule->buy_quantity, 'get' => (int) $rule->get_quantity]),
            PromotionRule::TYPE_ORDER_PERCENTAGE => __('A live order-level discount is available right now. Surface the saving near totals so the customer sees the reward before leaving the page.'),
            PromotionRule::TYPE_ORDER_FIXED => __('A fixed-value promotion is active. Flat savings usually work best when the cart is already close to conversion.'),
            default => __('A promotion rule is active for this session. Use it as a timely nudge inside the cart and checkout flow.'),
        };

        return [
            'tone' => 'success',
            'icon' => 'bi-megaphone-fill',
            'title' => $rule->name ?: __('Active promotion'),
            'body' => $copy,
        ];
    }
}
