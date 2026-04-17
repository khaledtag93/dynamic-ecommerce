<?php

namespace App\Services\Commerce;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIRecommendationEngine
{
    protected array $productWith = [
        'translations',
        'category.translations',
        'brand',
        'mainImage',
        'productImages',
        'defaultVariant',
        'activeVariants',
    ];

    public function __construct(protected BehaviorTrackingService $behaviorTrackingService)
    {
    }

    public function forHome(int $limit = 8): array
    {
        $profile = $this->visitorProfile();

        $candidateIds = collect($profile['viewed_product_ids'])
            ->merge($profile['cart_product_ids'])
            ->unique()
            ->values();

        $query = Product::query()
            ->with($this->productWith)
            ->where('status', 1)
            ->when($candidateIds->isNotEmpty(), function ($builder) use ($candidateIds, $profile) {
                $builder->where(function ($q) use ($candidateIds, $profile) {
                    $q->whereIn('category_id', $profile['top_category_ids'])
                        ->orWhereIn('brand_id', $profile['top_brand_ids'])
                        ->orWhereIn('id', $candidateIds->all());
                });
            })
            ->orderByDesc('is_featured')
            ->latest('id')
            ->take(max(24, $limit * 3));

        $products = $query->get();

        return [
            'products' => $this->scoreCandidates($products, [
                'context' => 'home',
                'top_category_ids' => $profile['top_category_ids'],
                'top_brand_ids' => $profile['top_brand_ids'],
                'viewed_product_ids' => $profile['viewed_product_ids'],
                'cart_product_ids' => $profile['cart_product_ids'],
            ], $limit),
            'insight' => __('The recommendation layer blends browsing history, cart intent, active offers, and lightweight popularity signals to rank products dynamically.'),
        ];
    }

    public function forProduct(Product $anchor, int $limit = 6): array
    {
        $profile = $this->visitorProfile();
        $anchor->loadMissing(['bundleProducts', 'addonProducts', 'relatedProducts']);

        $relationWeights = collect()
            ->merge($anchor->bundleProducts->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => 'bundle']))
            ->merge($anchor->addonProducts->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => 'addon']))
            ->merge($anchor->relatedProducts->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => 'related']));

        $candidateIds = $relationWeights->keys()
            ->merge($profile['viewed_product_ids'])
            ->merge($profile['cart_product_ids'])
            ->unique()
            ->values();

        $products = Product::query()
            ->with($this->productWith)
            ->where('status', 1)
            ->where('id', '!=', $anchor->id)
            ->where(function ($q) use ($anchor, $profile, $candidateIds) {
                $q->where('category_id', $anchor->category_id)
                    ->orWhere('brand_id', $anchor->brand_id)
                    ->orWhereIn('category_id', $profile['top_category_ids'])
                    ->orWhereIn('brand_id', $profile['top_brand_ids']);

                if ($candidateIds->isNotEmpty()) {
                    $q->orWhereIn('id', $candidateIds->all());
                }
            })
            ->latest('id')
            ->take(max(30, $limit * 4))
            ->get();

        return [
            'products' => $this->scoreCandidates($products, [
                'context' => 'product',
                'anchor_product_id' => (int) $anchor->id,
                'anchor_category_id' => (int) $anchor->category_id,
                'anchor_brand_id' => (int) $anchor->brand_id,
                'top_category_ids' => $profile['top_category_ids'],
                'top_brand_ids' => $profile['top_brand_ids'],
                'viewed_product_ids' => $profile['viewed_product_ids'],
                'cart_product_ids' => $profile['cart_product_ids'],
                'relation_map' => $relationWeights->all(),
            ], $limit),
            'insight' => __('These recommendations prioritize products that pair with the current item, match the visitor\'s browsing path, and still look commercially strong.'),
        ];
    }

    public function forCart(Collection $items, int $limit = 4): array
    {
        $profile = $this->visitorProfile();
        $productIds = $items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $cartProducts = Product::query()
            ->with(['bundleProducts', 'addonProducts', 'relatedProducts'])
            ->whereIn('id', $productIds)
            ->get();

        $relationMap = [];
        foreach ($cartProducts as $cartProduct) {
            foreach ($cartProduct->bundleProducts as $related) {
                $relationMap[(int) $related->id] = 'bundle';
            }
            foreach ($cartProduct->addonProducts as $related) {
                $relationMap[(int) $related->id] = 'addon';
            }
            foreach ($cartProduct->relatedProducts as $related) {
                $relationMap[(int) $related->id] = 'related';
            }
        }

        $categoryIds = Product::query()->whereIn('id', $productIds)->pluck('category_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $brandIds = Product::query()->whereIn('id', $productIds)->pluck('brand_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $candidateIds = collect(array_keys($relationMap))
            ->merge($profile['viewed_product_ids'])
            ->reject(fn ($id) => $productIds->contains((int) $id))
            ->unique()
            ->values();

        $products = Product::query()
            ->with($this->productWith)
            ->where('status', 1)
            ->whereNotIn('id', $productIds->all())
            ->where(function ($q) use ($categoryIds, $brandIds, $candidateIds, $profile) {
                if ($categoryIds->isNotEmpty()) {
                    $q->whereIn('category_id', $categoryIds->all());
                }
                if ($brandIds->isNotEmpty()) {
                    $q->orWhereIn('brand_id', $brandIds->all());
                }
                if ($candidateIds->isNotEmpty()) {
                    $q->orWhereIn('id', $candidateIds->all());
                }
                if (! empty($profile['top_category_ids'])) {
                    $q->orWhereIn('category_id', $profile['top_category_ids']);
                }
            })
            ->latest('id')
            ->take(max(30, $limit * 4))
            ->get();

        return [
            'products' => $this->scoreCandidates($products, [
                'context' => 'cart',
                'top_category_ids' => array_values(array_unique(array_merge($profile['top_category_ids'], $categoryIds->all()))),
                'top_brand_ids' => array_values(array_unique(array_merge($profile['top_brand_ids'], $brandIds->all()))),
                'viewed_product_ids' => $profile['viewed_product_ids'],
                'cart_product_ids' => $productIds->all(),
                'relation_map' => $relationMap,
            ], $limit),
            'insight' => __('The engine shifts toward attach-rate logic in cart: bundles, add-ons, and high-fit products that can lift AOV without slowing checkout.'),
        ];
    }

    public function forCheckout(Collection $items, int $limit = 3): array
    {
        $result = $this->forCart($items, max(4, $limit));

        $products = collect($result['products'])
            ->map(function (Product $product) {
                $product->setAttribute('ai_reason_chip', __('Fast add'));
                $product->setAttribute('ai_reason_detail', __('Chosen for late-stage checkout because it fits the current basket without adding too much decision friction.'));
                $product->setAttribute('ai_confidence', min(99, (int) ($product->ai_confidence ?? 84) + 4));
                return $product;
            })
            ->take($limit)
            ->values();

        return [
            'products' => $products,
            'insight' => __('Checkout recommendations are intentionally narrower: fewer choices, stronger fit, faster decision support.'),
        ];
    }

    protected function scoreCandidates(Collection $products, array $context, int $limit): Collection
    {
        $popularSignals = $this->popularSignals($products->pluck('id')->all());

        return $products
            ->map(function (Product $product) use ($context, $popularSignals) {
                $score = 0;
                $reasons = [];
                $contextName = $context['context'] ?? 'general';
                $productId = (int) $product->id;
                $relationType = $context['relation_map'][$productId] ?? null;

                if (! $product->in_stock) {
                    $score -= 100;
                } else {
                    $score += 18;
                    $reasons[] = ['weight' => 18, 'chip' => __('Ready now'), 'text' => __('Currently in stock and easy to convert without extra friction.')];
                }

                if ($relationType === 'bundle') {
                    $score += 36;
                    $reasons[] = ['weight' => 36, 'chip' => __('Pairs well'), 'text' => __('Bundle relation says this product is a strong companion to the current shopping intent.')];
                } elseif ($relationType === 'addon') {
                    $score += 32;
                    $reasons[] = ['weight' => 32, 'chip' => __('Easy add-on'), 'text' => __('Recommended as a low-friction add-on that can raise order value quickly.')];
                } elseif ($relationType === 'related') {
                    $score += 24;
                    $reasons[] = ['weight' => 24, 'chip' => __('Close match'), 'text' => __('Related-product logic found a close match for the current journey.')];
                }

                if (! empty($context['anchor_category_id']) && (int) $product->category_id === (int) $context['anchor_category_id']) {
                    $score += 22;
                    $reasons[] = ['weight' => 22, 'chip' => __('Same category'), 'text' => __('It belongs to the same category as the product the visitor is evaluating right now.')];
                }

                if (! empty($context['anchor_brand_id']) && (int) $product->brand_id === (int) $context['anchor_brand_id']) {
                    $score += 12;
                    $reasons[] = ['weight' => 12, 'chip' => __('Brand continuity'), 'text' => __('Keeps the visitor inside the same brand lane, which usually reduces hesitation.')];
                }

                if (in_array((int) $product->category_id, $context['top_category_ids'] ?? [], true)) {
                    $score += 16;
                    $reasons[] = ['weight' => 16, 'chip' => __('Browsing match'), 'text' => __('Matches the visitor\'s strongest category interest in this session.')];
                }

                if (in_array((int) $product->brand_id, $context['top_brand_ids'] ?? [], true)) {
                    $score += 10;
                    $reasons[] = ['weight' => 10, 'chip' => __('Brand preference'), 'text' => __('Aligned with the brand pattern this visitor keeps returning to.')];
                }

                if (in_array($productId, $context['viewed_product_ids'] ?? [], true)) {
                    $score += 8;
                    $reasons[] = ['weight' => 8, 'chip' => __('Seen before'), 'text' => __('Already touched earlier in the session, so the recommendation brings the shopper back to a known option.')];
                }

                if (in_array($productId, $context['cart_product_ids'] ?? [], true)) {
                    $score -= 60;
                }

                $basePrice = (float) ($product->base_price ?? 0);
                $currentPrice = (float) ($product->current_price ?? 0);
                if ($basePrice > 0 && $currentPrice > 0 && $currentPrice < $basePrice) {
                    $score += 9;
                    $reasons[] = ['weight' => 9, 'chip' => __('Deal active'), 'text' => __('A live discount makes the recommendation easier to justify commercially.')];
                }

                if ((bool) ($product->is_featured ?? false)) {
                    $score += 5;
                }

                $popularity = $popularSignals[$productId] ?? ['views' => 0, 'carts' => 0];
                $popularityScore = min(20, ((int) $popularity['views']) + (((int) $popularity['carts']) * 4));
                if ($popularityScore > 0) {
                    $score += $popularityScore;
                    $reasons[] = ['weight' => $popularityScore, 'chip' => __('Trending'), 'text' => __('Recent shopper activity suggests this product is performing well right now.')];
                }

                if ($contextName === 'checkout') {
                    $score += 4;
                }

                $topReason = collect($reasons)->sortByDesc('weight')->first();
                $confidence = max(63, min(98, 58 + (int) round($score / 2)));

                $product->setAttribute('ai_score', $score);
                $product->setAttribute('ai_confidence', $confidence);
                $product->setAttribute('ai_reason_chip', $topReason['chip'] ?? __('Smart pick'));
                $product->setAttribute('ai_reason', $this->headlineForContext($contextName, $topReason['chip'] ?? null));
                $product->setAttribute('ai_reason_detail', $topReason['text'] ?? __('This product scored well across intent, compatibility, and conversion readiness signals.'));

                return $product;
            })
            ->sortByDesc(fn (Product $product) => (int) ($product->ai_score ?? 0))
            ->take($limit)
            ->values();
    }

    protected function visitorProfile(): array
    {
        $events = $this->behaviorTrackingService->currentVisitorQuery()
            ->latest('id')
            ->take(180)
            ->get();

        $viewedProductIds = $events
            ->where('event', BehaviorTrackingService::EVENT_VIEW_PRODUCT)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $cartProductIds = $events
            ->whereIn('event', [BehaviorTrackingService::EVENT_ADD_TO_CART, BehaviorTrackingService::EVENT_REMOVE_FROM_CART])
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $categoryIds = $events
            ->pluck('meta')
            ->map(fn ($meta) => (int) ($meta['category_id'] ?? 0))
            ->filter()
            ->values();

        $brandIds = collect();
        if (! empty($viewedProductIds) || ! empty($cartProductIds)) {
            $productMeta = Product::query()
                ->select('id', 'category_id', 'brand_id')
                ->whereIn('id', array_values(array_unique(array_merge($viewedProductIds, $cartProductIds))))
                ->get();

            $categoryIds = $categoryIds->merge($productMeta->pluck('category_id')->filter()->map(fn ($id) => (int) $id));
            $brandIds = $brandIds->merge($productMeta->pluck('brand_id')->filter()->map(fn ($id) => (int) $id));
        }

        return [
            'viewed_product_ids' => $viewedProductIds,
            'cart_product_ids' => $cartProductIds,
            'top_category_ids' => $categoryIds->countBy()->sortDesc()->keys()->map(fn ($id) => (int) $id)->take(4)->values()->all(),
            'top_brand_ids' => $brandIds->countBy()->sortDesc()->keys()->map(fn ($id) => (int) $id)->take(4)->values()->all(),
        ];
    }

    protected function popularSignals(array $productIds): array
    {
        if (empty($productIds) || ! Schema::hasTable('user_behaviors')) {
            return [];
        }

        $rows = DB::table('user_behaviors')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN event = ? THEN 1 ELSE 0 END) as views", [BehaviorTrackingService::EVENT_VIEW_PRODUCT])
            ->selectRaw("SUM(CASE WHEN event = ? THEN 1 ELSE 0 END) as carts", [BehaviorTrackingService::EVENT_ADD_TO_CART])
            ->whereIn('product_id', $productIds)
            ->whereNotNull('product_id')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->groupBy('product_id')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [
            (int) $row->product_id => [
                'views' => (int) ($row->views ?? 0),
                'carts' => (int) ($row->carts ?? 0),
            ],
        ])->all();
    }

    protected function headlineForContext(string $context, ?string $chip = null): string
    {
        return match ($context) {
            'product' => __('AI matched this to your product journey'),
            'cart' => __('AI picked this for your basket'),
            'checkout' => __('AI chose this as a final add'),
            default => __('AI ranked this for you'),
        };
    }
}
