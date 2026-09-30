<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDailyStat;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionRule;
use App\Services\Analytics\AnalyticsDashboardService;
use App\Services\Analytics\AnalyticsRevenueService;
use App\Services\Analytics\GrowthAutomationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsDashboardService $dashboardService,
        protected GrowthAutomationService $growthAutomationService,
        protected AnalyticsRevenueService $analyticsRevenueService
    )
    {
    }

    public function index(Request $request)
    {
        [$range, $from, $to] = $this->resolveRangeFromRequest($request);

        $cacheKey = sprintf(
            'admin.analytics.snapshot.%s.%s.%s',
            $range,
            $from->format('Ymd'),
            $to->format('Ymd')
        );

        $snapshot = Cache::remember($cacheKey, now()->addMinutes(5), fn () => $this->dashboardService->buildSnapshot($from, $to));

        return view('admin.analytics.index', [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'snapshot' => $snapshot,
            'trust' => $this->buildOverviewTrust($snapshot),
            'uiState' => $this->buildOverviewUiState($snapshot),
        ]);
    }

    public function growth(Request $request)
    {
        [$range, $from, $to] = $this->resolveRangeFromRequest($request);

        $cacheKey = sprintf(
            'admin.analytics.growth.%s.%s.%s',
            $range,
            $from->format('Ymd'),
            $to->format('Ymd')
        );

        $snapshot = Cache::remember($cacheKey, now()->addMinutes(5), fn () => $this->growthAutomationService->buildSnapshot($from, $to));

        return view('admin.analytics.growth', [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'snapshot' => $snapshot,
            'trust' => $this->buildGrowthTrust($snapshot),
            'uiState' => $this->buildGrowthUiState($snapshot),
        ]);
    }

    public function product(Request $request, Product $product)
    {
        [$range, $from, $to] = $this->resolveRangeFromRequest($request);

        $cacheKey = sprintf(
            'admin.analytics.product.%s.%d.%s.%s',
            $range,
            $product->id,
            $from->format('Ymd'),
            $to->format('Ymd')
        );

        $drilldown = $this->dashboardService->hasDirtyDailyStats($from, $to)
            ? $this->dashboardService->buildProductDrilldown($product, $from, $to)
            : Cache::remember(
                $cacheKey,
                now()->addMinutes(5),
                fn () => $this->dashboardService->buildProductDrilldown($product, $from, $to)
            );

        return view('admin.analytics.product', [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'product' => $product,
            'drilldown' => $drilldown,
            'trust' => $this->buildProductTrust($drilldown),
            'uiState' => $this->buildProductUiState($drilldown),
        ]);
    }

    public function offers(Request $request)
    {
        [$range, $from, $to] = $this->resolveRangeFromRequest($request);

        $cacheKey = sprintf(
            'admin.analytics.offers.%s.%s.%s',
            $range,
            $from->format('Ymd'),
            $to->format('Ymd')
        );

        $drilldown = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($from, $to) {
            $couponRows = $this->analyticsRevenueService->couponPerformance($from, $to);

            $discountedOrders = Order::query()
                ->where('status', Order::STATUS_COMPLETED)
                ->whereIn('payment_status', [
                    Order::PAYMENT_STATUS_PAID,
                    Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
                    Order::PAYMENT_STATUS_REFUNDED,
                ])
                ->whereBetween(DB::raw('DATE(COALESCE(placed_at, created_at))'), [$from->toDateString(), $to->toDateString()])
                ->where('discount_total', '>', 0)
                ->selectRaw("SUM(CASE WHEN payment_status != '".Order::PAYMENT_STATUS_REFUNDED."' THEN 1 ELSE 0 END) as orders_count")
                ->selectRaw("SUM(CASE WHEN payment_status != '".Order::PAYMENT_STATUS_REFUNDED."' THEN discount_total ELSE 0 END) as discount_total")
                ->selectRaw('SUM(grand_total - refund_total) as revenue_gross')
                ->selectRaw('SUM(profit_total) as profit_total')
                ->first();

            $activePromotions = PromotionRule::query()
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->orderByDesc('discount_value')
                ->get();

            $topDiscountedProduct = $this->analyticsRevenueService
                ->topDiscountedProduct($from, $to);

            return [
                'coupon_rows' => $couponRows,
                'discounted_orders' => $discountedOrders,
                'active_promotions' => $activePromotions,
                'top_discounted_product' => $topDiscountedProduct,
            ];
        });

        return view('admin.analytics.offers', [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'drilldown' => $drilldown,
            'trust' => $this->buildOffersTrust($drilldown),
            'uiState' => $this->buildOffersUiState($drilldown),
        ]);
    }



    protected function buildOverviewTrust(array $snapshot): array
    {
        $current = $snapshot['current'] ?? [];
        $dailyStats = collect($current['daily_stats'] ?? []);
        $generatedAt = data_get($snapshot, 'generated_at');
        $lastAggregatedAt = data_get($current, 'last_aggregated_at');
        $lastEventAt = data_get($current, 'last_event_at');
        $isAggregated = (bool) data_get($current, 'is_aggregated', false);

        return [
            'source_label' => $isAggregated ? __('Aggregated analytics snapshot') : __('Live event fallback'),
            'source_tone' => $isAggregated ? 'good' : 'warn',
            'generated_at' => $generatedAt,
            'last_sync_at' => $lastAggregatedAt ?: $lastEventAt,
            'coverage_days' => $dailyStats->count(),
            'has_data' => $dailyStats->isNotEmpty(),
            'help' => $isAggregated
                ? __('This view is reading from prepared daily analytics tables for faster executive reporting.')
                : __('This view is falling back to live event data because aggregated daily stats are not available for the full window.'),
        ];
    }

    protected function buildGrowthTrust(array $snapshot): array
    {
        $generatedAt = data_get($snapshot, 'generated_at');
        $summary = $snapshot['summary'] ?? [];
        $signalCount = collect($summary)->sum(fn ($value) => (int) $value);

        return [
            'source_label' => __('Live growth snapshot'),
            'source_tone' => $signalCount > 0 ? 'good' : 'neutral',
            'generated_at' => $generatedAt,
            'last_sync_at' => $generatedAt,
            'coverage_days' => null,
            'has_data' => $signalCount > 0,
            'help' => __('Growth signals are calculated from current event and order activity inside the selected reporting window.'),
        ];
    }

    protected function buildOffersTrust(array $drilldown): array
    {
        $couponRows = collect($drilldown['coupon_rows'] ?? []);
        $discountedOrders = (int) data_get($drilldown, 'discounted_orders.orders_count', 0);
        $hasData = $couponRows->isNotEmpty() || $discountedOrders > 0;

        return [
            'source_label' => __('Order discount activity'),
            'source_tone' => $hasData ? 'good' : 'neutral',
            'generated_at' => now(),
            'last_sync_at' => now(),
            'coverage_days' => null,
            'has_data' => $hasData,
            'help' => __('Offer analytics are calculated from order-level coupon and discount activity in the selected range.'),
        ];
    }

    protected function buildProductTrust(array $drilldown): array
    {
        $daily = collect($drilldown['daily'] ?? []);
        $hasData = $daily->isNotEmpty() && $daily->sum(fn ($row) => (int) data_get($row, 'views', 0) + (int) data_get($row, 'purchases', 0)) > 0;
        $lastSyncAt = $daily->max('stat_date');

        return [
            'source_label' => __('Product daily analytics'),
            'source_tone' => $hasData ? 'good' : 'neutral',
            'generated_at' => now(),
            'last_sync_at' => $lastSyncAt,
            'coverage_days' => $daily->count(),
            'has_data' => $hasData,
            'help' => __('This drilldown is built from product daily analytics rows plus order item mix inside the selected window.'),
        ];
    }

    protected function buildOverviewUiState(array $snapshot): array
    {
        $current = $snapshot['current'] ?? [];
        $hasEconomicProductData = collect(data_get($current, 'top_products', []))
            ->contains(fn ($row) => abs((float) data_get($row, 'realized_cogs', 0)) > 0.00001
                || abs((float) data_get($row, 'profit_total', 0)) > 0.00001
                || abs((float) data_get($row, 'revenue_gross', 0)) > 0.00001);

        $hasHeadlineData = ((float) data_get($current, 'totals.revenue_gross', 0)) > 0
            || ((int) data_get($current, 'totals.orders_count', 0)) > 0
            || collect(data_get($current, 'daily_stats', []))->sum(fn ($row) => (int) data_get($row, 'sessions_count', 0)) > 0
            || filled(data_get($current, 'last_event_at'))
            || $hasEconomicProductData;

        return [
            'empty' => ! $hasHeadlineData,
            'show_drilldowns' => $hasHeadlineData,
            'show_watchlist' => collect(data_get($current, 'user_insights.top_sessions', []))->isNotEmpty(),
        ];
    }

    protected function buildGrowthUiState(array $snapshot): array
    {
        $summary = $snapshot['summary'] ?? [];
        $campaigns = collect($snapshot['campaigns'] ?? []);
        $products = collect($snapshot['product_opportunities'] ?? []);
        $rules = collect($snapshot['active_rules'] ?? []);
        $promotions = collect($snapshot['active_promotions'] ?? []);
        $hasData = collect($summary)->sum(fn ($value) => (int) $value) > 0 || $campaigns->isNotEmpty() || $products->isNotEmpty() || $rules->isNotEmpty() || $promotions->isNotEmpty();

        return [
            'empty' => ! $hasData,
            'show_products' => $products->isNotEmpty(),
            'show_rules' => $rules->isNotEmpty(),
            'show_promotions' => $promotions->isNotEmpty(),
        ];
    }

    protected function buildOffersUiState(array $drilldown): array
    {
        $couponRows = collect($drilldown['coupon_rows'] ?? []);
        $promotions = collect($drilldown['active_promotions'] ?? []);
        $hasData = $couponRows->isNotEmpty() || (int) data_get($drilldown, 'discounted_orders.orders_count', 0) > 0 || $promotions->isNotEmpty();

        return [
            'empty' => ! $hasData,
            'show_coupon_charts' => $couponRows->isNotEmpty(),
            'show_promotions' => $promotions->isNotEmpty(),
        ];
    }

    protected function buildProductUiState(array $drilldown): array
    {
        $daily = collect($drilldown['daily'] ?? []);
        $topVariants = collect($drilldown['top_variants'] ?? []);
        $hasData = $daily->sum(fn ($row) => (int) data_get($row, 'views', 0) + (int) data_get($row, 'purchases', 0)) > 0 || $topVariants->isNotEmpty();

        return [
            'empty' => ! $hasData,
            'show_charts' => $daily->isNotEmpty(),
            'show_variants' => $topVariants->isNotEmpty(),
        ];
    }

    protected function resolveRangeFromRequest(Request $request): array
    {
        $range = (string) $request->input('range', '7d');
        $allowedRanges = ['today', '7d', '30d', '90d', 'custom'];
        $range = in_array($range, $allowedRanges, true) ? $range : '7d';

        if ($range === 'custom') {
            $from = $request->filled('from_date')
                ? Carbon::parse((string) $request->input('from_date'))->startOfDay()
                : now()->subDays(29)->startOfDay();

            $to = $request->filled('to_date')
                ? Carbon::parse((string) $request->input('to_date'))->endOfDay()
                : now()->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            return [$range, $from, $to];
        }

        $to = now()->endOfDay();
        $from = match ($range) {
            'today' => now()->startOfDay(),
            '30d' => now()->subDays(29)->startOfDay(),
            '90d' => now()->subDays(89)->startOfDay(),
            default => now()->subDays(6)->startOfDay(),
        };

        return [$range, $from, $to];
    }
}
