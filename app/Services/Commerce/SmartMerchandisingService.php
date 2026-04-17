<?php

namespace App\Services\Commerce;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\PromotionRule;
use Illuminate\Support\Collection;

class SmartMerchandisingService
{
    protected array $productWith = [
        'translations',
        'category.translations',
        'mainImage',
        'productImages',
        'defaultVariant',
        'activeVariants',
    ];

    public function recordProductView(Product $product, int $max = 18): void
    {
        $ids = collect(session()->get('storefront.recently_viewed_product_ids', []))
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $product->id)
            ->prepend((int) $product->id)
            ->unique()
            ->take($max)
            ->values()
            ->all();

        session()->put('storefront.recently_viewed_product_ids', $ids);
    }

    public function recentlyViewed(int $limit = 8, array $exclude = []): Collection
    {
        $ids = collect(session()->get('storefront.recently_viewed_product_ids', []))
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => in_array($id, $exclude, true))
            ->take($limit)
            ->values()
            ->all();

        return $this->hydrateProductsByIds($ids);
    }

    public function homeCollections(int $continueLimit = 8, int $recommendationLimit = 8): array
    {
        $continueShopping = $this->recentlyViewed($continueLimit);

        $categoryIds = $continueShopping
            ->pluck('category_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $recommended = collect();

        if ($categoryIds->isNotEmpty()) {
            $recommended = Product::query()
                ->with($this->productWith)
                ->where('status', 1)
                ->whereIn('category_id', $categoryIds)
                ->whereNotIn('id', $continueShopping->pluck('id')->all())
                ->orderByRaw('case when quantity > 0 then 0 else 1 end')
                ->orderByRaw('case when sale_price is not null and sale_price > 0 and base_price > sale_price then 0 else 1 end')
                ->orderByDesc('is_featured')
                ->latest('id')
                ->take($recommendationLimit)
                ->get();
        }

        return [
            'continueShopping' => $continueShopping,
            'recommendedForYou' => $recommended,
        ];
    }

    public function forProduct(Product $product, int $limit = 4): array
    {
        $recentlyViewed = $this->recentlyViewed($limit, [(int) $product->id]);

        $categoryIds = $recentlyViewed
            ->pluck('category_id')
            ->push((int) $product->category_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $excludeIds = $recentlyViewed->pluck('id')
            ->push((int) $product->id)
            ->unique()
            ->values()
            ->all();

        $recommendedForYou = Product::query()
            ->with($this->productWith)
            ->where('status', 1)
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('category_id', $categoryIds->all()))
            ->whereNotIn('id', $excludeIds)
            ->orderByRaw('case when category_id = ? then 0 else 1 end', [(int) $product->category_id])
            ->orderByRaw('case when quantity > 0 then 0 else 1 end')
            ->orderByRaw('case when sale_price is not null and sale_price > 0 and base_price > sale_price then 0 else 1 end')
            ->orderByDesc('is_featured')
            ->latest('id')
            ->take($limit)
            ->get();

        return [
            'recentlyViewed' => $recentlyViewed,
            'recommendedForYou' => $recommendedForYou,
            'offerSignals' => $this->offerSignals(0.0, collect([$product->id])),
        ];
    }

    public function forCart(Collection $items, int $limit = 4): array
    {
        $productIds = $items->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $recentlyViewed = $this->recentlyViewed($limit, $productIds->all());

        $categoryIds = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('category_id')
            ->merge($recentlyViewed->pluck('category_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $recommendedForYou = Product::query()
            ->with($this->productWith)
            ->where('status', 1)
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('category_id', $categoryIds->all()))
            ->whereNotIn('id', $productIds->merge($recentlyViewed->pluck('id'))->unique()->all())
            ->orderByRaw('case when quantity > 0 then 0 else 1 end')
            ->orderByRaw('case when sale_price is not null and sale_price > 0 and base_price > sale_price then 0 else 1 end')
            ->orderByDesc('is_featured')
            ->latest('id')
            ->take($limit)
            ->get();

        return [
            'recentlyViewed' => $recentlyViewed,
            'recommendedForYou' => $recommendedForYou,
            'offerSignals' => $this->offerSignals((float) $items->sum('line_total'), $productIds),
        ];
    }

    public function offerSignals(float $subtotal, Collection|array $productIds = []): Collection
    {
        $productIds = collect($productIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $now = now();

        $couponCards = Coupon::query()
            ->where('is_active', true)
            ->orderByRaw('case when ends_at is null then 1 else 0 end')
            ->orderBy('ends_at')
            ->limit(6)
            ->get()
            ->filter(fn (Coupon $coupon) => $coupon->isUsable($now))
            ->map(function (Coupon $coupon) use ($subtotal) {
                $threshold = (float) ($coupon->min_order_amount ?? 0);
                $remaining = max(0, $threshold - $subtotal);
                $discountLabel = $coupon->type === Coupon::TYPE_PERCENT
                    ? __('Save :value% with code :code', ['value' => rtrim(rtrim(number_format((float) $coupon->value, 2, '.', ''), '0'), '.'), 'code' => $coupon->code])
                    : __('Save EGP :value with code :code', ['value' => number_format((float) $coupon->value, 2), 'code' => $coupon->code]);

                return [
                    'type' => 'coupon',
                    'chip' => __('Personalized code'),
                    'headline' => $discountLabel,
                    'copy' => $remaining > 0
                        ? __('Add EGP :amount more to unlock this coupon right away.', ['amount' => number_format($remaining, 2)])
                        : __('This code already matches the current basket conditions.'),
                    'emphasis' => $remaining > 0 ? __('Unlock') : __('Ready now'),
                    'sort' => $remaining,
                ];
            });

        $promotionCards = PromotionRule::active()
            ->orderByDesc('priority')
            ->orderBy('ends_at')
            ->limit(6)
            ->get()
            ->map(function (PromotionRule $rule) use ($subtotal, $now) {
                $remaining = max(0, (float) ($rule->min_subtotal ?? 0) - $subtotal);
                $soonEnds = $rule->ends_at && $rule->ends_at->diffInHours($now, false) >= 0 && $rule->ends_at->diffInHours($now) <= 72;

                $headline = match ($rule->type) {
                    PromotionRule::TYPE_BUY_X_GET_Y => __('Buy :buy get :get', ['buy' => (int) $rule->buy_quantity, 'get' => (int) $rule->get_quantity]),
                    PromotionRule::TYPE_ORDER_PERCENTAGE => __('Automatic :value% discount', ['value' => rtrim(rtrim(number_format((float) $rule->discount_value, 2, '.', ''), '0'), '.')]),
                    PromotionRule::TYPE_ORDER_FIXED => __('Automatic EGP :value off', ['value' => number_format((float) $rule->discount_value, 2)]),
                    PromotionRule::TYPE_CATEGORY_PERCENTAGE => __('Category deal: :value% off', ['value' => rtrim(rtrim(number_format((float) $rule->discount_value, 2, '.', ''), '0'), '.')]),
                    default => (string) $rule->name,
                };

                return [
                    'type' => 'promotion',
                    'chip' => $soonEnds ? __('Ending soon') : __('Smart rule'),
                    'headline' => $rule->name ?: $headline,
                    'copy' => $remaining > 0
                        ? __('You are EGP :amount away from activating this rule.', ['amount' => number_format($remaining, 2)])
                        : __('This rule is already available for the current order path.'),
                    'emphasis' => $soonEnds ? __('Limited time') : __('Auto applied'),
                    'sort' => $remaining + ($soonEnds ? -25 : 0),
                ];
            });

        return $couponCards
            ->merge($promotionCards)
            ->sortBy('sort')
            ->take(4)
            ->values();
    }

    protected function hydrateProductsByIds(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $products = Product::query()
            ->with($this->productWith)
            ->where('status', 1)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $products->get((int) $id))
            ->filter()
            ->values();
    }
}
