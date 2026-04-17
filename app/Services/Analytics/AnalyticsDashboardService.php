<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProductDailyStat;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsDashboardService
{
    public function buildSnapshot(Carbon $from, Carbon $to): array
    {
        $periodDays = max(1, $from->diffInDays($to) + 1);
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($periodDays - 1)->startOfDay();

        $current = $this->buildPeriodSnapshot($from, $to);
        $previous = $this->buildPeriodSnapshot($previousFrom, $previousTo);

        return [
            'range' => [
                'from' => $from,
                'to' => $to,
                'days' => $periodDays,
            ],
            'current' => $current,
            'previous' => $previous,
            'comparison' => $this->buildComparison($current['totals'], $previous['totals']),
            'generated_at' => now(),
        ];
    }

    protected function buildPeriodSnapshot(Carbon $from, Carbon $to): array
    {
        $dailyStats = AnalyticsDailyStat::query()
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('stat_date')
            ->get();

        $hasAggregated = $dailyStats->isNotEmpty();

        $totals = $hasAggregated
            ? $this->buildTotalsFromDailyStats($dailyStats)
            : $this->buildTotalsFromRawEvents($from, $to);

        return [
            'is_aggregated' => $hasAggregated,
            'daily_stats' => $hasAggregated
                ? $this->buildFilledDailyStats($from, $to, $dailyStats)
                : $this->buildRawDailyStats($from, $to),
            'totals' => $totals,
            'funnel' => $this->buildFunnel($totals),
            'top_products' => $this->buildTopProducts($from, $to),
            'top_categories' => $this->buildTopCategories($from, $to),
            'coupon_performance' => $this->buildCouponPerformance($from, $to),
            'user_insights' => $this->buildUserInsights($from, $to),
            'last_aggregated_at' => $dailyStats->max('aggregated_at'),
            'last_event_at' => AnalyticsEvent::query()->whereBetween('occurred_at', [$from, $to])->max('occurred_at'),
        ];
    }

    protected function buildTotalsFromDailyStats(Collection $dailyStats): array
    {
        $totals = [
            'product_views' => (int) $dailyStats->sum('product_views'),
            'cart_views' => (int) $dailyStats->sum('cart_views'),
            'add_to_cart_count' => (int) $dailyStats->sum('add_to_cart_count'),
            'remove_from_cart_count' => (int) $dailyStats->sum('remove_from_cart_count'),
            'checkout_starts' => (int) $dailyStats->sum('checkout_starts'),
            'purchases' => (int) $dailyStats->sum('purchases'),
            'orders_count' => (int) $dailyStats->sum('orders_count'),
            'sessions_count' => (int) $dailyStats->sum('sessions_count'),
            'users_count' => (int) $dailyStats->sum('users_count'),
            'revenue_gross' => (float) $dailyStats->sum('revenue_gross'),
            'discount_total' => (float) $dailyStats->sum('discount_total'),
            'shipping_total' => (float) $dailyStats->sum('shipping_total'),
        ];

        return $this->appendDerivedMetrics($totals);
    }

    protected function buildTotalsFromRawEvents(Carbon $from, Carbon $to): array
    {
        $events = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['user_id', 'session_id', 'event_type', 'meta']);

        $eventCounts = $events->countBy('event_type');

        $totals = [
            'product_views' => (int) ($eventCounts[AnalyticsEvent::EVENT_VIEW_PRODUCT] ?? 0),
            'cart_views' => (int) ($eventCounts[AnalyticsEvent::EVENT_VIEW_CART] ?? 0),
            'add_to_cart_count' => (int) ($eventCounts[AnalyticsEvent::EVENT_ADD_TO_CART] ?? 0),
            'remove_from_cart_count' => (int) ($eventCounts[AnalyticsEvent::EVENT_REMOVE_FROM_CART] ?? 0),
            'checkout_starts' => (int) ($eventCounts[AnalyticsEvent::EVENT_CHECKOUT_START] ?? 0),
            'purchases' => (int) ($eventCounts[AnalyticsEvent::EVENT_PURCHASE_SUCCESS] ?? 0),
            'orders_count' => (int) ($eventCounts[AnalyticsEvent::EVENT_PURCHASE_SUCCESS] ?? 0),
            'sessions_count' => (int) $events->pluck('session_id')->filter()->unique()->count(),
            'users_count' => (int) $events->pluck('user_id')->filter()->unique()->count(),
            'revenue_gross' => (float) $events->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'grand_total', 0)),
            'discount_total' => (float) $events->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'discount_total', 0)),
            'shipping_total' => (float) $events->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'shipping_total', 0)),
        ];

        return $this->appendDerivedMetrics($totals);
    }

    protected function appendDerivedMetrics(array $totals): array
    {
        $totals['average_order_value'] = $totals['orders_count'] > 0 ? $totals['revenue_gross'] / $totals['orders_count'] : 0;
        $totals['conversion_rate'] = $totals['sessions_count'] > 0 ? $totals['purchases'] / $totals['sessions_count'] : 0;
        $totals['cart_abandonment_rate'] = $totals['add_to_cart_count'] > 0
            ? max(0, ($totals['add_to_cart_count'] - $totals['purchases']) / $totals['add_to_cart_count'])
            : 0;
        $totals['checkout_completion_rate'] = $totals['checkout_starts'] > 0 ? $totals['purchases'] / $totals['checkout_starts'] : 0;
        $totals['view_to_cart_rate'] = $totals['product_views'] > 0 ? $totals['add_to_cart_count'] / $totals['product_views'] : 0;
        $totals['view_to_purchase_rate'] = $totals['product_views'] > 0 ? $totals['purchases'] / $totals['product_views'] : 0;

        return $totals;
    }

    protected function buildFilledDailyStats(Carbon $from, Carbon $to, Collection $dailyStats): Collection
    {
        $statsByDate = $dailyStats->keyBy(fn (AnalyticsDailyStat $stat) => $stat->stat_date->toDateString());

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($statsByDate) {
                $stat = $statsByDate->get($date->toDateString());

                if ($stat) {
                    return $stat;
                }

                return new AnalyticsDailyStat([
                    'stat_date' => $date->toDateString(),
                    'product_views' => 0,
                    'cart_views' => 0,
                    'add_to_cart_count' => 0,
                    'remove_from_cart_count' => 0,
                    'checkout_starts' => 0,
                    'purchases' => 0,
                    'orders_count' => 0,
                    'sessions_count' => 0,
                    'users_count' => 0,
                    'revenue_gross' => 0,
                    'discount_total' => 0,
                    'shipping_total' => 0,
                    'average_order_value' => 0,
                    'cart_abandonment_rate' => 0,
                    'checkout_completion_rate' => 0,
                    'view_to_cart_rate' => 0,
                    'view_to_purchase_rate' => 0,
                ]);
            })
            ->values();
    }

    protected function buildRawDailyStats(Carbon $from, Carbon $to): Collection
    {
        $events = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['event_type', 'meta', 'occurred_at', 'session_id', 'user_id']);

        $byDate = $events->groupBy(fn (AnalyticsEvent $event) => $event->occurred_at?->toDateString());

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($byDate) {
                $rows = $byDate->get($date->toDateString(), collect());
                $eventCounts = $rows->countBy('event_type');

                return (object) $this->appendDerivedMetrics([
                    'stat_date' => $date->copy(),
                    'product_views' => (int) ($eventCounts[AnalyticsEvent::EVENT_VIEW_PRODUCT] ?? 0),
                    'cart_views' => (int) ($eventCounts[AnalyticsEvent::EVENT_VIEW_CART] ?? 0),
                    'add_to_cart_count' => (int) ($eventCounts[AnalyticsEvent::EVENT_ADD_TO_CART] ?? 0),
                    'remove_from_cart_count' => (int) ($eventCounts[AnalyticsEvent::EVENT_REMOVE_FROM_CART] ?? 0),
                    'checkout_starts' => (int) ($eventCounts[AnalyticsEvent::EVENT_CHECKOUT_START] ?? 0),
                    'purchases' => (int) ($eventCounts[AnalyticsEvent::EVENT_PURCHASE_SUCCESS] ?? 0),
                    'orders_count' => (int) ($eventCounts[AnalyticsEvent::EVENT_PURCHASE_SUCCESS] ?? 0),
                    'sessions_count' => (int) $rows->pluck('session_id')->filter()->unique()->count(),
                    'users_count' => (int) $rows->pluck('user_id')->filter()->unique()->count(),
                    'revenue_gross' => (float) $rows->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'grand_total', 0)),
                    'discount_total' => (float) $rows->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'discount_total', 0)),
                    'shipping_total' => (float) $rows->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'shipping_total', 0)),
                ]);
            })
            ->values();
    }

    protected function buildFunnel(array $totals): array
    {
        $steps = [
            [
                'key' => 'views',
                'label' => __('Product views'),
                'count' => (int) ($totals['product_views'] ?? 0),
            ],
            [
                'key' => 'adds',
                'label' => __('Add to cart'),
                'count' => (int) ($totals['add_to_cart_count'] ?? 0),
            ],
            [
                'key' => 'checkout',
                'label' => __('Checkout starts'),
                'count' => (int) ($totals['checkout_starts'] ?? 0),
            ],
            [
                'key' => 'purchase',
                'label' => __('Purchases'),
                'count' => (int) ($totals['purchases'] ?? 0),
            ],
        ];

        $firstCount = max(1, (int) ($steps[0]['count'] ?? 0));

        foreach ($steps as $index => &$step) {
            $previousCount = $index > 0 ? max(1, (int) $steps[$index - 1]['count']) : $firstCount;
            $step['conversion_from_previous'] = $index === 0 ? 1.0 : ((int) $step['count'] / $previousCount);
            $step['drop_off_from_previous'] = $index === 0 ? 0.0 : max(0, 1 - $step['conversion_from_previous']);
            $step['conversion_from_first'] = (int) $step['count'] / $firstCount;
        }
        unset($step);

        return [
            'steps' => $steps,
            'largest_drop_off' => collect($steps)->slice(1)->sortByDesc('drop_off_from_previous')->first(),
        ];
    }

    protected function buildTopProducts(Carbon $from, Carbon $to): Collection
    {
        $aggregated = AnalyticsProductDailyStat::query()
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->select(
                'product_id',
                DB::raw('MAX(product_name) as product_name'),
                DB::raw('MAX(product_slug) as product_slug'),
                DB::raw('SUM(views) as views'),
                DB::raw('SUM(add_to_cart_count) as add_to_cart_count'),
                DB::raw('SUM(purchases) as purchases'),
                DB::raw('SUM(purchased_quantity) as purchased_quantity'),
                DB::raw('SUM(revenue_gross) as revenue_gross')
            )
            ->groupBy('product_id')
            ->orderByDesc('revenue_gross')
            ->limit(8)
            ->get();

        if ($aggregated->isNotEmpty()) {
            return $aggregated->map(function ($row) {
                $row->conversion_rate = (int) $row->views > 0 ? ((int) $row->purchases / (int) $row->views) : 0;
                return $row;
            });
        }

        $events = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->where('entity_type', AnalyticsEvent::ENTITY_PRODUCT)
            ->whereNotNull('entity_id')
            ->get(['event_type', 'entity_id', 'meta']);

        $products = Product::query()
            ->with('translations')
            ->whereIn('id', $events->pluck('entity_id')->unique())
            ->get()
            ->keyBy('id');

        return $events->groupBy('entity_id')
            ->map(function (Collection $rows, $productId) use ($products) {
                $product = $products->get((int) $productId);
                $views = (int) $rows->where('event_type', AnalyticsEvent::EVENT_VIEW_PRODUCT)->count();
                $adds = (int) $rows->where('event_type', AnalyticsEvent::EVENT_ADD_TO_CART)->count();
                $purchases = (int) $rows->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->count();
                $revenue = (float) $rows->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)->sum(fn (AnalyticsEvent $event) => (float) data_get($event->meta, 'grand_total', 0));

                return (object) [
                    'product_id' => (int) $productId,
                    'product_name' => $product?->name ?? __('Product #:id', ['id' => $productId]),
                    'product_slug' => $product?->slug,
                    'views' => $views,
                    'add_to_cart_count' => $adds,
                    'purchases' => $purchases,
                    'purchased_quantity' => 0,
                    'revenue_gross' => $revenue,
                    'conversion_rate' => $views > 0 ? $purchases / $views : 0,
                ];
            })
            ->sortByDesc('revenue_gross')
            ->take(8)
            ->values();
    }

    protected function buildTopCategories(Carbon $from, Carbon $to): Collection
    {
        $rows = AnalyticsProductDailyStat::query()
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('category_id')
            ->select(
                'category_id',
                DB::raw('SUM(views) as views'),
                DB::raw('SUM(add_to_cart_count) as add_to_cart_count'),
                DB::raw('SUM(purchases) as purchases'),
                DB::raw('SUM(revenue_gross) as revenue_gross')
            )
            ->groupBy('category_id')
            ->orderByDesc('revenue_gross')
            ->limit(6)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $categories = Category::query()
            ->with('translations')
            ->whereIn('id', $rows->pluck('category_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($categories) {
            $category = $categories->get((int) $row->category_id);
            $row->category_name = $category?->name ?? __('Category #:id', ['id' => $row->category_id]);
            $row->conversion_rate = (int) $row->views > 0 ? ((int) $row->purchases / (int) $row->views) : 0;
            return $row;
        });
    }

    protected function buildCouponPerformance(Carbon $from, Carbon $to): Collection
    {
        return Order::query()
            ->whereBetween(DB::raw('DATE(COALESCE(placed_at, created_at))'), [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('coupon_code')
            ->select(
                'coupon_code',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(grand_total) as revenue_gross'),
                DB::raw('SUM(discount_total) as discount_total'),
                DB::raw('AVG(grand_total) as average_order_value')
            )
            ->groupBy('coupon_code')
            ->orderByDesc('revenue_gross')
            ->limit(6)
            ->get();
    }

    protected function buildUserInsights(Carbon $from, Carbon $to): array
    {
        $orderScope = Order::query()
            ->whereBetween(DB::raw('DATE(COALESCE(placed_at, created_at))'), [$from->toDateString(), $to->toDateString()]);

        $orderUsers = (clone $orderScope)
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(grand_total) as revenue_gross'))
            ->groupBy('user_id')
            ->orderByDesc('revenue_gross')
            ->limit(5)
            ->get();

        $users = User::query()
            ->whereIn('id', $orderUsers->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $repeatUsersCount = (clone $orderScope)
            ->whereNotNull('user_id')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $newUsersCount = (clone $orderScope)
            ->whereNotNull('user_id')
            ->whereDoesntHave('user.orders', function ($query) use ($from) {
                $query->whereDate(DB::raw('COALESCE(placed_at, created_at)'), '<', $from->toDateString());
            })
            ->distinct('user_id')
            ->count('user_id');

        $eventScope = AnalyticsEvent::query()->whereBetween('occurred_at', [$from, $to]);

        $topSessions = (clone $eventScope)
            ->select('session_id', DB::raw('COUNT(*) as events_count'))
            ->whereNotNull('session_id')
            ->groupBy('session_id')
            ->orderByDesc('events_count')
            ->limit(5)
            ->get();

        $trackedSessions = (clone $eventScope)
            ->whereNotNull('session_id')
            ->distinct('session_id')
            ->count('session_id');

        $averageEventsPerSession = $trackedSessions > 0
            ? round((float) ((clone $eventScope)->count() / $trackedSessions), 2)
            : 0.0;

        return [
            'new_users_count' => (int) $newUsersCount,
            'repeat_users_count' => (int) $repeatUsersCount,
            'tracked_sessions' => (int) $trackedSessions,
            'top_buyers' => $orderUsers->map(function ($row) use ($users) {
                $user = $users->get((int) $row->user_id);
                $row->user_name = $user?->name ?? __('User #:id', ['id' => $row->user_id]);
                $row->user_email = $user?->email;
                return $row;
            }),
            'top_sessions' => $topSessions,
            'average_events_per_session' => $averageEventsPerSession,
        ];
    }

    protected function buildComparison(array $currentTotals, array $previousTotals): array
    {
        $keys = [
            'revenue_gross',
            'orders_count',
            'conversion_rate',
            'average_order_value',
            'cart_abandonment_rate',
            'product_views',
            'add_to_cart_count',
            'checkout_starts',
            'purchases',
        ];

        $comparison = [];

        foreach ($keys as $key) {
            $current = (float) ($currentTotals[$key] ?? 0);
            $previous = (float) ($previousTotals[$key] ?? 0);
            $delta = $current - $previous;
            $deltaRate = $previous > 0 ? $delta / $previous : ($current > 0 ? 1 : 0);

            $comparison[$key] = [
                'current' => $current,
                'previous' => $previous,
                'delta' => $delta,
                'delta_rate' => $deltaRate,
            ];
        }

        return $comparison;
    }
}
