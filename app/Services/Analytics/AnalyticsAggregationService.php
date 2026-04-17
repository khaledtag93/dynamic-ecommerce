<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProductDailyStat;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsAggregationService
{
    public function aggregateDay(Carbon|string $date): array
    {
        $day = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        $start = $day->copy();
        $end = $day->copy()->endOfDay();

        $events = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('id')
            ->get(['id', 'user_id', 'session_id', 'event_type', 'entity_type', 'entity_id', 'meta', 'occurred_at']);

        $eventCounts = $events->countBy('event_type');

        $productViews = (int) ($eventCounts[AnalyticsEvent::EVENT_VIEW_PRODUCT] ?? 0);
        $cartViews = (int) ($eventCounts[AnalyticsEvent::EVENT_VIEW_CART] ?? 0);
        $addToCart = (int) ($eventCounts[AnalyticsEvent::EVENT_ADD_TO_CART] ?? 0);
        $removeFromCart = (int) ($eventCounts[AnalyticsEvent::EVENT_REMOVE_FROM_CART] ?? 0);
        $checkoutStarts = (int) ($eventCounts[AnalyticsEvent::EVENT_CHECKOUT_START] ?? 0);
        $purchases = (int) ($eventCounts[AnalyticsEvent::EVENT_PURCHASE_SUCCESS] ?? 0);

        $revenueGross = (float) $events
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'grand_total', 0));

        $discountTotal = (float) $events
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'discount_total', 0));

        $shippingTotal = (float) $events
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'shipping_total', 0));

        $sessionsCount = (int) $events->pluck('session_id')->filter()->unique()->count();
        $usersCount = (int) $events->pluck('user_id')->filter()->unique()->count();

        $daily = AnalyticsDailyStat::query()->updateOrCreate(
            ['stat_date' => $day->toDateString()],
            [
                'product_views' => $productViews,
                'cart_views' => $cartViews,
                'add_to_cart_count' => $addToCart,
                'remove_from_cart_count' => $removeFromCart,
                'checkout_starts' => $checkoutStarts,
                'purchases' => $purchases,
                'orders_count' => $purchases,
                'sessions_count' => $sessionsCount,
                'users_count' => $usersCount,
                'revenue_gross' => round($revenueGross, 2),
                'discount_total' => round($discountTotal, 2),
                'shipping_total' => round($shippingTotal, 2),
                'average_order_value' => round($purchases > 0 ? $revenueGross / $purchases : 0, 2),
                'cart_abandonment_rate' => round($addToCart > 0 ? max(0, ($addToCart - $purchases) / $addToCart) : 0, 4),
                'checkout_completion_rate' => round($checkoutStarts > 0 ? $purchases / $checkoutStarts : 0, 4),
                'view_to_cart_rate' => round($productViews > 0 ? $addToCart / $productViews : 0, 4),
                'view_to_purchase_rate' => round($productViews > 0 ? $purchases / $productViews : 0, 4),
                'meta' => [
                    'event_counts' => $eventCounts->all(),
                    'source' => 'analytics_events',
                ],
                'aggregated_at' => now(),
            ]
        );

        $this->aggregateProductDay($day, $events);

        return [
            'stat_date' => $day->toDateString(),
            'events_processed' => $events->count(),
            'product_views' => $productViews,
            'add_to_cart_count' => $addToCart,
            'checkout_starts' => $checkoutStarts,
            'purchases' => $purchases,
            'revenue_gross' => round($revenueGross, 2),
            'daily_stat_id' => $daily->id,
        ];
    }

    public function aggregateRange(Carbon|string $from, Carbon|string $to): array
    {
        $start = $from instanceof Carbon ? $from->copy()->startOfDay() : Carbon::parse($from)->startOfDay();
        $end = $to instanceof Carbon ? $to->copy()->startOfDay() : Carbon::parse($to)->startOfDay();

        $results = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $results[] = $this->aggregateDay($cursor->copy());
        }

        return $results;
    }

    protected function aggregateProductDay(Carbon $day, Collection $events): void
    {
        $views = $events
            ->where('event_type', AnalyticsEvent::EVENT_VIEW_PRODUCT)
            ->groupBy(fn (AnalyticsEvent $event) => (int) data_get($event->meta, 'product_id', $event->entity_id));

        $adds = $events
            ->where('event_type', AnalyticsEvent::EVENT_ADD_TO_CART)
            ->groupBy(fn (AnalyticsEvent $event) => (int) data_get($event->meta, 'product_id', $event->entity_id));

        $purchaseBuckets = [];

        foreach ($events->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS) as $event) {
            foreach ((array) data_get($event->meta, 'line_items', []) as $lineItem) {
                $productId = (int) data_get($lineItem, 'product_id');
                if ($productId <= 0) {
                    continue;
                }

                if (! isset($purchaseBuckets[$productId])) {
                    $purchaseBuckets[$productId] = [
                        'purchases' => 0,
                        'purchased_quantity' => 0,
                        'revenue_gross' => 0.0,
                    ];
                }

                $purchaseBuckets[$productId]['purchases']++;
                $purchaseBuckets[$productId]['purchased_quantity'] += (int) data_get($lineItem, 'quantity', 0);
                $purchaseBuckets[$productId]['revenue_gross'] += (float) data_get($lineItem, 'line_total', 0);
            }
        }

        $productIds = collect(array_merge($views->keys()->all(), $adds->keys()->all(), array_keys($purchaseBuckets)))
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            AnalyticsProductDailyStat::query()->whereDate('stat_date', $day->toDateString())->delete();
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->get(['id', 'name', 'slug', 'category_id'])
            ->keyBy('id');

        $rows = [];

        foreach ($productIds as $productId) {
            $viewCount = (int) optional($views->get($productId))->count();
            $addCount = (int) optional($adds->get($productId))->count();
            $purchaseCount = (int) data_get($purchaseBuckets, $productId . '.purchases', 0);
            $purchasedQuantity = (int) data_get($purchaseBuckets, $productId . '.purchased_quantity', 0);
            $revenueGross = (float) data_get($purchaseBuckets, $productId . '.revenue_gross', 0);
            $product = $products->get((int) $productId);

            $rows[] = [
                'stat_date' => $day->toDateString(),
                'product_id' => (int) $productId,
                'product_name' => $product?->name ?? __('Deleted product #') . $productId,
                'product_slug' => $product?->slug,
                'category_id' => $product?->category_id,
                'views' => $viewCount,
                'add_to_cart_count' => $addCount,
                'purchases' => $purchaseCount,
                'purchased_quantity' => $purchasedQuantity,
                'revenue_gross' => round($revenueGross, 2),
                'conversion_rate' => round($viewCount > 0 ? $purchaseCount / $viewCount : 0, 4),
                'meta' => json_encode([
                    'source' => 'analytics_events',
                ], JSON_UNESCAPED_UNICODE),
                'aggregated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($day, $rows) {
            AnalyticsProductDailyStat::query()->whereDate('stat_date', $day->toDateString())->delete();
            AnalyticsProductDailyStat::query()->insert($rows);
        });
    }
}
