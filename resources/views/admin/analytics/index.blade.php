@extends('layouts.admin')

@section('title', __('Revenue Intelligence'))




@php

    $current = $snapshot['current'] ?? [];
    $totals = $current['totals'] ?? [];
    $comparison = $snapshot['comparison'] ?? [];
    $funnel = collect($current['funnel']['steps'] ?? []);
    $largestDrop = $current['funnel']['largest_drop_off'] ?? null;
    $topProducts = collect($current['top_products'] ?? []);
    $topCategories = collect($current['top_categories'] ?? []);
    $couponPerformance = collect($current['coupon_performance'] ?? []);
    $userInsights = $current['user_insights'] ?? [];
    $dailyStats = collect($current['daily_stats'] ?? []);
    $isAggregated = (bool) data_get($current, 'is_aggregated', false);
    $lastAggregatedAt = data_get($current, 'last_aggregated_at');
    $lastEventAt = data_get($current, 'last_event_at');
    $topSessions = collect($userInsights['top_sessions'] ?? []);

    $topProductForFlow = $topProducts->first();
    $topProductFlowMeta = $topProductForFlow
        ? __('Top product right now: :name', ['name' => $topProductForFlow->product_name ?? __('Product #:id', ['id' => $topProductForFlow->product_id])])
        : __('Product drilldowns will appear here once product performance is tracked.');
    $bestCouponForFlow = $couponPerformance->sortByDesc('revenue_gross')->first();
    $offersFlowMeta = $bestCouponForFlow
        ? __('Leading coupon: :code', ['code' => $bestCouponForFlow->coupon_code])
        : __('Open offers drilldown to inspect discount pressure and coupon contribution.');
    $analyticsFlowTitle = __('Cross-page flow');
    $analyticsFlowSubtitle = __('Move from revenue signals into the exact drilldown that explains the next action.');
    $analyticsFlowItems = [
        [
            'label' => __('Dashboard'),
            'description' => __('Return to the executive board for the fastest operational view and next-step actions.'),
            'meta' => __('Executive summary'),
            'icon' => 'mdi-view-dashboard-outline',
            'url' => route('admin.dashboard'),
        ],
        [
            'label' => __('Revenue Intelligence'),
            'description' => __('Stay in the main analytics overview to compare periods, leakage, and commercial concentration.'),
            'meta' => __('You are here'),
            'icon' => 'mdi-chart-areaspline',
            'url' => route('admin.analytics.index', array_filter(['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])),
            'active' => true,
        ],
        [
            'label' => __('Offers Drilldown'),
            'description' => __('Jump into discount efficiency, promotion pressure, and coupon quality without losing the same range.'),
            'meta' => $offersFlowMeta,
            'icon' => 'mdi-ticket-percent-outline',
            'url' => route('admin.analytics.offers', array_filter(['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])),
        ],
        [
            'label' => __('Product Drilldown'),
            'description' => __('Open the strongest product directly when the overview points to product-level opportunity or leakage.'),
            'meta' => $topProductFlowMeta,
            'icon' => 'mdi-cube-outline',
            'url' => $topProductForFlow
                ? route('admin.analytics.products.show', array_filter(['product' => $topProductForFlow->product_id, 'range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]))
                : null,
        ],
    ];

    $deltaBadge = function (string $key, bool $inverse = false) use ($comparison) {
        $item = $comparison[$key] ?? null;

        if (! $item) {
            return ['text' => '—', 'class' => 'text-muted', 'rate' => 0.0];
        }

        $deltaRate = (float) ($item['delta_rate'] ?? 0);
        $good = $inverse ? $deltaRate <= 0 : $deltaRate >= 0;

        return [
            'text' => ($deltaRate >= 0 ? '+' : '') . number_format($deltaRate * 100, 1) . '%',
            'class' => $good ? 'text-success' : 'text-danger',
            'rate' => $deltaRate,
        ];
    };

    $returningShare = ((int) ($userInsights['new_users_count'] ?? 0) + (int) ($userInsights['repeat_users_count'] ?? 0)) > 0
        ? ((int) ($userInsights['repeat_users_count'] ?? 0) / (((int) ($userInsights['new_users_count'] ?? 0)) + ((int) ($userInsights['repeat_users_count'] ?? 0))))
        : 0;

    $checkoutDropCount = max(0, (int) ($totals['checkout_starts'] ?? 0) - (int) ($totals['purchases'] ?? 0));
    $maxRevenue = max(1, (float) $dailyStats->max(fn ($stat) => (float) data_get($stat, 'revenue_gross', 0)));
    $maxOrders = max(1, (int) $dailyStats->max(fn ($stat) => (int) data_get($stat, 'orders_count', 0)));
    $maxAov = max(1, (float) $dailyStats->max(fn ($stat) => (float) data_get($stat, 'average_order_value', 0)));

    $dailyChartRows = $dailyStats->values()->map(function ($stat) use ($maxRevenue, $maxOrders, $maxAov) {
        $statDateValue = data_get($stat, 'stat_date');
        $dateObject = $statDateValue instanceof \Carbon\CarbonInterface
            ? $statDateValue
            : \Illuminate\Support\Carbon::parse((string) $statDateValue);
        $revenueValue = (float) data_get($stat, 'revenue_gross', 0);
        $ordersValue = (int) data_get($stat, 'orders_count', 0);
        $aovValue = (float) data_get($stat, 'average_order_value', 0);

        return [
            'label' => $dateObject->format('m-d'),
            'full_label' => $dateObject->toDateString(),
            'revenue' => $revenueValue,
            'orders_count' => $ordersValue,
            'conversion_rate' => (float) data_get($stat, 'conversion_rate', 0),
            'aov' => $aovValue,
            'revenue_width' => min(100, ($revenueValue / $maxRevenue) * 100),
            'orders_width' => min(100, ($ordersValue / $maxOrders) * 100),
            'aov_width' => min(100, ($aovValue / $maxAov) * 100),
        ];
    });


    $chartLabelStep = $dailyChartRows->count() > 60 ? 7 : ($dailyChartRows->count() > 30 ? 5 : ($dailyChartRows->count() > 14 ? 3 : 1));
    $axisLabels = $dailyChartRows->values()->filter(function ($row, $index) use ($chartLabelStep, $dailyChartRows) {
        return $index === 0 || $index === ($dailyChartRows->count() - 1) || ($index % $chartLabelStep) === 0;
    })->values();

    $buildSvgPath = function ($rows, string $field, float $maxValue, int $width = 520, int $height = 180) {
        if ($rows->count() < 1) {
            return '';
        }

        $count = max(1, $rows->count() - 1);
        $points = $rows->values()->map(function ($row, $index) use ($field, $maxValue, $width, $height, $count) {
            $x = $count === 0 ? 0 : ($index / $count) * $width;
            $y = $height - ((((float) data_get($row, $field, 0)) / max(1, $maxValue)) * ($height - 16)) - 8;
            return round($x, 2) . ',' . round($y, 2);
        })->implode(' ');

        if ($points === '') {
            return '';
        }

        $firstPoint = explode(' ', $points)[0];
        $lastPoint = explode(' ', $points)[count(explode(' ', $points)) - 1];
        $firstX = explode(',', $firstPoint)[0] ?? 0;
        $lastX = explode(',', $lastPoint)[0] ?? $width;

        return [
            'line' => $points,
            'area' => $points . ' ' . $lastX . ',' . $height . ' ' . $firstX . ',' . $height,
        ];
    };

    $revenueSvg = $buildSvgPath($dailyChartRows, 'revenue', $maxRevenue);
    $ordersSvg = $buildSvgPath($dailyChartRows, 'orders_count', $maxOrders);
    $aovSvg = $buildSvgPath($dailyChartRows, 'aov', $maxAov);

    $bestDay = $dailyChartRows->sortByDesc('revenue')->first();
    $weakestDay = $dailyChartRows->sortBy('revenue')->first();
    $averageDailyRevenue = $dailyStats->count() > 0 ? ((float) ($totals['revenue_gross'] ?? 0) / $dailyStats->count()) : 0;
    $averageDailyOrders = $dailyStats->count() > 0 ? ((float) ($totals['orders_count'] ?? 0) / $dailyStats->count()) : 0;

    $splitIndex = max(1, (int) floor(max(1, $dailyStats->count()) / 2));
    $firstHalf = $dailyStats->slice(0, $splitIndex);
    $secondHalf = $dailyStats->slice($splitIndex);
    if ($secondHalf->isEmpty()) {
        $secondHalf = $firstHalf;
    }

    $firstRevenue = (float) $firstHalf->sum(fn ($stat) => (float) data_get($stat, 'revenue_gross', 0));
    $secondRevenue = (float) $secondHalf->sum(fn ($stat) => (float) data_get($stat, 'revenue_gross', 0));
    $revenueMomentum = $firstRevenue > 0 ? (($secondRevenue - $firstRevenue) / $firstRevenue) : ($secondRevenue > 0 ? 1 : 0);
    $firstOrders = (int) $firstHalf->sum(fn ($stat) => (int) data_get($stat, 'orders_count', 0));
    $secondOrders = (int) $secondHalf->sum(fn ($stat) => (int) data_get($stat, 'orders_count', 0));
    $ordersMomentum = $firstOrders > 0 ? (($secondOrders - $firstOrders) / $firstOrders) : ($secondOrders > 0 ? 1 : 0);

    $revenueDelta = $deltaBadge('revenue_gross');
    $ordersDelta = $deltaBadge('orders_count');
    $conversionDelta = $deltaBadge('conversion_rate');
    $aovDelta = $deltaBadge('average_order_value');
    $abandonmentDelta = $deltaBadge('cart_abandonment_rate', true);

    $executiveSummary = [
        $revenueDelta['rate'] >= 0
            ? __('Revenue is ahead of the previous matching period at :delta.', ['delta' => $revenueDelta['text']])
            : __('Revenue is trailing the previous matching period at :delta.', ['delta' => $revenueDelta['text']]),
        $conversionDelta['rate'] >= 0
            ? __('Conversion improved to :rate, which suggests the path to purchase is healthier.', ['rate' => number_format(((float) ($totals['conversion_rate'] ?? 0)) * 100, 1) . '%'])
            : __('Conversion softened to :rate, so traffic quality or checkout friction needs intervention.', ['rate' => number_format(((float) ($totals['conversion_rate'] ?? 0)) * 100, 1) . '%']),
        $checkoutDropCount > 0
            ? __('The clearest leakage point is :count checkout drops, with the largest handoff loss at :step.', ['count' => number_format($checkoutDropCount), 'step' => data_get($largestDrop, 'label', __('the funnel handoff'))])
            : __('Checkout leakage is currently limited, with no major drop spike detected in this range.'),
    ];

    $kpiCards = [
        ['label' => __('Gross revenue'), 'value' => 'EGP ' . number_format((float) ($totals['revenue_gross'] ?? 0), 2), 'delta' => $revenueDelta, 'help' => __('Gross revenue inside the selected reporting window.')],
        ['label' => __('Completed orders'), 'value' => number_format((int) ($totals['orders_count'] ?? 0)), 'delta' => $ordersDelta, 'help' => __('Completed orders captured in this range.')],
        ['label' => __('Store conversion rate'), 'value' => number_format(((float) ($totals['conversion_rate'] ?? 0)) * 100, 1) . '%', 'delta' => $conversionDelta, 'help' => __('Sessions that converted into purchases.')],
        ['label' => __('Average order value'), 'value' => 'EGP ' . number_format((float) ($totals['average_order_value'] ?? 0), 2), 'delta' => $aovDelta, 'help' => __('Average order value across purchased orders.')],
    ];

    $storySignals = [
        ['title' => __('Commercial pace'), 'value' => $revenueDelta['text'], 'tone' => $revenueDelta['rate'] >= 0 ? 'good' : 'risk', 'description' => $revenueDelta['rate'] >= 0 ? __('Revenue is building faster than the previous comparable window.') : __('Revenue is softer than the previous comparable window and needs recovery attention.')],
        ['title' => __('Order engine'), 'value' => $ordersDelta['text'], 'tone' => $ordersDelta['rate'] >= 0 ? 'good' : 'risk', 'description' => $ordersDelta['rate'] >= 0 ? __('Order throughput is supporting the current growth story.') : __('Order throughput is softer, so acquisition quality or offer fit may need review.')],
        ['title' => __('Checkout friction'), 'value' => number_format($checkoutDropCount), 'tone' => $checkoutDropCount > 0 ? 'warn' : 'good', 'description' => $checkoutDropCount > 0 ? __('Checkout exits are the main leakage point right now.') : __('Checkout flow looks stable inside the selected range.')],
    ];

    $operatorSummaryCards = [
        [
            'label' => __('Main pressure'),
            'value' => $checkoutDropCount > 0 ? __('Checkout leakage') : __('No major pressure yet'),
            'help' => $checkoutDropCount > 0
                ? __(':count checkout starts did not reach purchase in the selected range.', ['count' => number_format($checkoutDropCount)])
                : __('No major operational blockage is standing out in this range.'),
        ],
        [
            'label' => __('Best return'),
            'value' => data_get($bestDay, 'full_label', __('No data yet')),
            'help' => $bestDay
                ? __('Strongest revenue day at EGP :amount.', ['amount' => number_format((float) data_get($bestDay, 'revenue', 0), 0)])
                : __('The selected range does not yet have a standout day.'),
        ],
        [
            'label' => __('Next move'),
            'value' => $checkoutDropCount > 0 ? __('Reduce checkout friction') : __('Scale what is converting'),
            'help' => $checkoutDropCount > 0
                ? __('Review checkout drop-offs, payment failures, and handoff clarity first.')
                : __('Keep leaning into the channels, products, and offers that are already performing cleanly.'),
        ],
    ];

    $focusLaneItems = [
        ['label' => __('Scale'), 'value' => __('Best day: :day', ['day' => data_get($bestDay, 'full_label', '—')]), 'help' => __('Use the strongest day as a reference for campaign timing and merchandising rhythm.')],
        ['label' => __('Protect'), 'value' => __('Softest day: :day', ['day' => data_get($weakestDay, 'full_label', '—')]), 'help' => __('Review traffic quality, inventory pressure, or conversion issues around the weakest day.')],
        ['label' => __('Improve'), 'value' => __('Checkout drops: :count', ['count' => number_format($checkoutDropCount)]), 'help' => __('Tighten the handoff between checkout start and purchase completion.')],
    ];

    $topProductRevenue = max(1, (float) $topProducts->max(fn ($row) => (float) data_get($row, 'revenue_gross', 0)));
    $topCategoryRevenue = max(1, (float) $topCategories->max(fn ($row) => (float) data_get($row, 'revenue_gross', 0)));

    $exportRows = [
        ['label' => __('Gross revenue'), 'value' => 'EGP ' . number_format((float) ($totals['revenue_gross'] ?? 0), 2), 'context' => $revenueDelta['text']],
        ['label' => __('Completed orders'), 'value' => number_format((int) ($totals['orders_count'] ?? 0)), 'context' => $ordersDelta['text']],
        ['label' => __('Store conversion rate'), 'value' => number_format(((float) ($totals['conversion_rate'] ?? 0)) * 100, 1) . '%', 'context' => $conversionDelta['text']],
        ['label' => __('Average order value'), 'value' => 'EGP ' . number_format((float) ($totals['average_order_value'] ?? 0), 2), 'context' => $aovDelta['text']],
        ['label' => __('Returning buyer share'), 'value' => number_format($returningShare * 100, 1) . '%', 'context' => __('Repeat buyers inside the selected window.')],
        ['label' => __('Checkout drop count'), 'value' => number_format($checkoutDropCount), 'context' => data_get($largestDrop, 'label', __('Largest handoff loss not available'))],
    ];
@endphp

@php
    $depthSignals = [
        [
            'label' => __('Best day'),
            'value' => $bestDay ? $bestDay['label'] : __('No data yet'),
            'help' => $bestDay ? __('EGP :amount on the strongest tracked day.', ['amount' => number_format($bestDay['revenue'], 0)]) : __('No standout day is visible yet.'),
            'tone' => 'good',
        ],
        [
            'label' => __('Weakest day'),
            'value' => $weakestDay ? $weakestDay['label'] : __('No data yet'),
            'help' => $weakestDay ? __('EGP :amount on the softest tracked day.', ['amount' => number_format($weakestDay['revenue'], 0)]) : __('No weak day can be identified yet.'),
            'tone' => 'warn',
        ],
        [
            'label' => __('Average orders per day'),
            'value' => number_format($averageDailyOrders, 1),
            'help' => __('Daily order pace across the selected range.'),
            'tone' => 'neutral',
        ],
        [
            'label' => __('Returning mix'),
            'value' => number_format($returningShare * 100, 1) . '%',
            'help' => __('Share of tracked users coming back to buy again.'),
            'tone' => 'good',
        ],
    ];

    $patternRead = [
        [
            'title' => __('Revenue cadence'),
            'value' => ($revenueMomentum >= 0 ? '+' : '') . number_format($revenueMomentum * 100, 1) . '%',
            'help' => __('Second-half revenue versus the first half of the same period.'),
            'tone' => $revenueMomentum >= 0 ? 'good' : 'warn',
        ],
        [
            'title' => __('Order cadence'),
            'value' => ($ordersMomentum >= 0 ? '+' : '') . number_format($ordersMomentum * 100, 1) . '%',
            'help' => __('Second-half order pace versus the first half.'),
            'tone' => $ordersMomentum >= 0 ? 'good' : 'warn',
        ],
        [
            'title' => __('Checkout leakage'),
            'value' => number_format($checkoutDropCount),
            'help' => __('Estimated checkout starts that did not become purchases.'),
            'tone' => $checkoutDropCount > 0 ? 'warn' : 'good',
        ],
    ];
@endphp

@php

    $previous = $snapshot['previous'] ?? [];
    $previousTotals = $previous['totals'] ?? [];
    $currentRevenueValue = (float) ($totals['revenue_gross'] ?? 0);
    $previousRevenueValue = (float) ($previousTotals['revenue_gross'] ?? 0);
    $currentOrdersValue = (int) ($totals['orders_count'] ?? 0);
    $previousOrdersValue = (int) ($previousTotals['orders_count'] ?? 0);
    $currentAovValue = (float) ($totals['average_order_value'] ?? 0);
    $previousAovValue = (float) ($previousTotals['average_order_value'] ?? 0);
    $currentConversionValue = (float) ($totals['conversion_rate'] ?? 0);
    $previousConversionValue = (float) ($previousTotals['conversion_rate'] ?? 0);
    $currentAbandonmentValue = (float) ($totals['cart_abandonment_rate'] ?? 0);
    $previousAbandonmentValue = (float) ($previousTotals['cart_abandonment_rate'] ?? 0);
    $discountShare = $currentRevenueValue > 0 ? ((float) ($totals['discount_total'] ?? 0) / $currentRevenueValue) : 0.0;
    $couponLeader = $couponPerformance->sortByDesc(fn ($row) => (float) data_get($row, 'revenue_gross', 0))->first();
    $categoryLeader = $topCategories->sortByDesc(fn ($row) => (float) data_get($row, 'revenue_gross', 0))->first();
    $productLeader = $topProducts->sortByDesc(fn ($row) => (float) data_get($row, 'revenue_gross', 0))->first();

    $driverReason = $revenueDelta['rate'] >= 0
        ? ($ordersDelta['rate'] >= $aovDelta['rate']
            ? __('Growth is being driven more by order volume than basket expansion.')
            : __('Growth is being driven more by basket expansion than order volume.'))
        : ($conversionDelta['rate'] < 0
            ? __('The revenue slowdown is closely linked to weaker conversion quality.')
            : __('The revenue slowdown is more about softer demand than basket value.'));

    $pressureReason = $checkoutDropCount > 0
        ? __('The main pressure point is the handoff from :step, where too many shoppers exit before purchase.', ['step' => data_get($largestDrop, 'label', __('checkout'))])
        : __('No major checkout leakage spike is visible in the current range.');

    $discountReason = $discountShare > 0.12
        ? __('Discount dependency is elevated, so margin quality should be reviewed alongside revenue.')
        : __('Discount pressure looks contained, so revenue quality is not being heavily subsidized.');

    $reasonReads = [
        [
            'title' => __('What is driving the result'),
            'value' => $revenueDelta['text'],
            'help' => $driverReason,
            'tone' => $revenueDelta['rate'] >= 0 ? 'good' : 'warn',
        ],
        [
            'title' => __('What is slowing conversion'),
            'value' => number_format($checkoutDropCount),
            'help' => $pressureReason,
            'tone' => $checkoutDropCount > 0 ? 'warn' : 'good',
        ],
        [
            'title' => __('Revenue quality read'),
            'value' => number_format($discountShare * 100, 1) . '%',
            'help' => $discountReason,
            'tone' => $discountShare > 0.12 ? 'warn' : 'good',
        ],
    ];

    $periodCompareRows = [
        [
            'label' => __('Gross revenue'),
            'current' => 'EGP ' . number_format($currentRevenueValue, 2),
            'previous' => 'EGP ' . number_format($previousRevenueValue, 2),
            'delta' => $revenueDelta['text'],
            'help' => __('Shows whether commercial output expanded or softened against the previous matching window.'),
        ],
        [
            'label' => __('Completed orders'),
            'current' => number_format($currentOrdersValue),
            'previous' => number_format($previousOrdersValue),
            'delta' => $ordersDelta['text'],
            'help' => __('Use this to separate demand changes from basket or pricing effects.'),
        ],
        [
            'label' => __('Store conversion rate'),
            'current' => number_format($currentConversionValue * 100, 1) . '%',
            'previous' => number_format($previousConversionValue * 100, 1) . '%',
            'delta' => $conversionDelta['text'],
            'help' => __('A weaker rate usually points to traffic quality or checkout friction.'),
        ],
        [
            'label' => __('Average order value'),
            'current' => 'EGP ' . number_format($currentAovValue, 2),
            'previous' => 'EGP ' . number_format($previousAovValue, 2),
            'delta' => $aovDelta['text'],
            'help' => __('This helps explain whether basket size is lifting or compressing revenue.'),
        ],
        [
            'label' => __('Cart abandonment rate'),
            'current' => number_format($currentAbandonmentValue * 100, 1) . '%',
            'previous' => number_format($previousAbandonmentValue * 100, 1) . '%',
            'delta' => $abandonmentDelta['text'],
            'help' => __('Higher abandonment usually means the journey to purchase is leaking value.'),
        ],
    ];

    $leakageReads = $funnel->slice(1)->map(function ($step) {
        $dropRate = (float) data_get($step, 'drop_off_from_previous', 0);

        return [
            'title' => data_get($step, 'label', __('Journey step')),
            'value' => number_format($dropRate * 100, 1) . '%',
            'help' => __('Lost from the previous step with :count tracked completions remaining.', [
                'count' => number_format(max(0, ((int) data_get($step, 'count', 0)))),
            ]),
            'tone' => $dropRate >= 0.35 ? 'risk' : ($dropRate >= 0.15 ? 'warn' : 'good'),
            'count' => (int) data_get($step, 'count', 0),
            'drop_rate' => $dropRate,
        ];
    })->sortByDesc('drop_rate')->values();

    $highestReturnRows = collect([
        $productLeader ? [
            'label' => __('Top product return'),
            'title' => data_get($productLeader, 'product_name', __('Top product')),
            'value' => 'EGP ' . number_format((float) data_get($productLeader, 'revenue_gross', 0), 2),
            'help' => __('This is the strongest product by tracked revenue in the selected window.'),
        ] : null,
        $categoryLeader ? [
            'label' => __('Top category return'),
            'title' => data_get($categoryLeader, 'category_name', __('Top category')),
            'value' => 'EGP ' . number_format((float) data_get($categoryLeader, 'revenue_gross', 0), 2),
            'help' => __('This category is carrying the broadest revenue contribution right now.'),
        ] : null,
        $couponLeader ? [
            'label' => __('Top coupon return'),
            'title' => data_get($couponLeader, 'coupon_code', __('Top coupon')),
            'value' => 'EGP ' . number_format((float) data_get($couponLeader, 'revenue_gross', 0), 2),
            'help' => __('This offer is currently producing the highest tracked revenue contribution.'),
        ] : null,
    ])->filter()->values();

@endphp

@section('content')
<style>
.analytics-shell{display:grid;gap:20px}.analytics-card,.analytics-mini,.analytics-trend-card,.analytics-summary-card,.analytics-chart-card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:22px;box-shadow:0 18px 45px rgba(15,23,42,.06)}.analytics-card .card-body,.analytics-summary-card,.analytics-trend-card,.analytics-chart-card{padding:22px}.analytics-pills{display:flex;gap:10px;flex-wrap:wrap}.analytics-pill{display:inline-flex;align-items:center;padding:10px 14px;border-radius:999px;background:#fff;border:1px solid rgba(15,23,42,.08);font-weight:700;color:#0f172a;text-decoration:none}.analytics-pill.active{background:linear-gradient(135deg,#f97316,#fb923c);color:#fff;border-color:transparent}.analytics-form{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.analytics-form .form-control,.analytics-form .form-select{min-width:170px;border-radius:14px}.analytics-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.analytics-kpi{height:100%}.analytics-kpi .label{font-size:.92rem;color:#64748b;font-weight:700;margin-bottom:8px}.analytics-kpi .value{font-size:1.95rem;font-weight:800;color:#111827;line-height:1.1}.analytics-kpi .delta{margin-top:8px;font-weight:700;font-size:.9rem}.analytics-kpi .help{margin-top:6px;color:#64748b;font-size:.83rem;line-height:1.6}.analytics-block-title{font-weight:800;font-size:1.05rem;color:#0f172a;margin-bottom:6px}.analytics-block-subtitle{color:#64748b;font-size:.9rem;margin-bottom:16px}.analytics-summary-card{display:grid;gap:16px;background:linear-gradient(135deg,#fff7ed,#ffffff)}.analytics-summary-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}.analytics-summary-item{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:16px;background:rgba(255,255,255,.8);border:1px solid rgba(249,115,22,.12)}.analytics-summary-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:999px;background:#ffedd5;color:#c2410c;font-weight:800;flex:0 0 auto}.analytics-story-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.analytics-story-card{padding:18px;border-radius:20px;border:1px solid rgba(15,23,42,.06);background:#fff;box-shadow:0 16px 35px rgba(15,23,42,.05)}.analytics-story-card .kicker{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:#64748b}.analytics-story-card .metric{font-size:1.5rem;font-weight:800;margin-top:8px}.analytics-story-card .desc{font-size:.87rem;color:#64748b;margin-top:8px;line-height:1.7}.analytics-story-card.good .metric{color:#047857}.analytics-story-card.warn .metric{color:#c2410c}.analytics-story-card.risk .metric{color:#b91c1c}.analytics-storyboard{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}.analytics-comparison-card{padding:20px;border-radius:22px;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;border:1px solid rgba(15,23,42,.08);box-shadow:0 20px 45px rgba(15,23,42,.16)}.analytics-comparison-card .eyebrow{font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7)}.analytics-comparison-card .headline{font-size:1.55rem;font-weight:800;margin-top:8px;line-height:1.35}.analytics-comparison-card .subcopy{font-size:.92rem;line-height:1.8;color:rgba(255,255,255,.82);margin-top:10px}.analytics-compare-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}.analytics-compare-tile{padding:14px;border-radius:16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08)}.analytics-compare-tile .tile-label{font-size:.8rem;color:rgba(255,255,255,.72)}.analytics-compare-tile .tile-value{font-size:1.15rem;font-weight:800;margin-top:6px}.analytics-lane-card{padding:20px;border-radius:22px;border:1px solid rgba(15,23,42,.06);background:#fff;box-shadow:0 18px 45px rgba(15,23,42,.05)}.analytics-lane-list{display:grid;gap:12px}.analytics-lane-item{padding:14px 16px;border-radius:16px;background:#f8fafc;border:1px solid rgba(15,23,42,.05)}.analytics-lane-item .lane-label{font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;color:#64748b}.analytics-lane-item .lane-value{font-size:1rem;font-weight:800;color:#0f172a;margin-top:5px}.analytics-lane-item .lane-help{font-size:.86rem;color:#64748b;margin-top:7px;line-height:1.6}.analytics-insight-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.analytics-mini{padding:16px;height:100%;background:#f8fafc}.analytics-section{display:grid;gap:16px}.analytics-section-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.analytics-chip-row{display:flex;gap:10px;flex-wrap:wrap}.analytics-chip{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:#fff7ed;border:1px solid rgba(249,115,22,.14);color:#c2410c;font-weight:700;font-size:.84rem}.analytics-anchor-nav{display:flex;gap:10px;flex-wrap:wrap}.analytics-anchor{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid rgba(15,23,42,.08);font-weight:700;color:#334155;text-decoration:none}.analytics-chart-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(280px,.95fr);gap:16px;align-items:start}.analytics-svg-card{padding:18px;border-radius:20px;background:#f8fafc;border:1px solid rgba(15,23,42,.05)}.analytics-svg-wrap{position:relative;width:100%;overflow-x:auto;overflow-y:hidden}.analytics-svg-frame{min-width:520px;width:100%}.analytics-svg{width:100%;height:180px;display:block}.analytics-chart-grid>*{min-width:0}.analytics-shell,.analytics-section,.analytics-card,.analytics-summary-card,.analytics-story-card,.analytics-comparison-card,.analytics-lane-card,.analytics-svg-card{min-width:0}.analytics-gridlines{stroke:rgba(148,163,184,.25);stroke-width:1}.analytics-line.revenue{fill:none;stroke:#f97316;stroke-width:3}.analytics-line.orders{fill:none;stroke:#94a3b8;stroke-width:3}.analytics-line.aov{fill:none;stroke:#0f172a;stroke-width:2.5}.analytics-area.revenue{fill:rgba(249,115,22,.12)}.analytics-area.orders{fill:rgba(148,163,184,.12)}.analytics-axis{display:flex;justify-content:space-between;gap:8px;margin-top:8px;color:#64748b;font-size:.78rem;overflow-x:auto;padding-bottom:2px}.analytics-axis span{flex:0 0 auto;min-width:44px;text-align:center}.analytics-axis.compact span{min-width:64px;font-size:.74rem}.analytics-chart-legend{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px}.analytics-chart-legend span{display:inline-flex;align-items:center;gap:8px;color:#64748b;font-size:.83rem}.analytics-chart-legend i{display:inline-block;width:12px;height:12px;border-radius:999px}.analytics-chart-legend .rev{background:#f97316}.analytics-chart-legend .ord{background:#94a3b8}.analytics-chart-legend .aov{background:#0f172a}.analytics-list{display:grid;gap:14px}.analytics-row{display:flex;justify-content:space-between;gap:14px;padding:12px 0;border-bottom:1px solid rgba(15,23,42,.06)}.analytics-row:last-child{border-bottom:none}.analytics-bar-track{height:10px;border-radius:999px;background:#eef2f7;overflow:hidden}.analytics-bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#f97316,#fb923c)}.analytics-bar-fill.soft{background:linear-gradient(90deg,#cbd5e1,#94a3b8)}.analytics-bar-fill.dark{background:linear-gradient(90deg,#0f172a,#334155)}.analytics-table-wrap{overflow:auto}.analytics-table{width:100%;border-collapse:collapse}.analytics-table th,.analytics-table td{padding:12px 10px;border-bottom:1px solid rgba(15,23,42,.06);white-space:nowrap}.analytics-table th{font-size:.84rem;color:#64748b;text-transform:uppercase;letter-spacing:.03em}.analytics-funnel-step{padding:16px;border-radius:18px;background:#f8fafc;border:1px solid rgba(15,23,42,.05);margin-bottom:12px}.analytics-detail-stack{display:grid;gap:12px}.analytics-detail-card{padding:14px 16px;border-radius:18px;background:#fff;border:1px solid rgba(15,23,42,.06)}.analytics-leader-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media (max-width: 1400px){.analytics-chart-grid{grid-template-columns:1fr}.analytics-detail-stack{grid-template-columns:repeat(2,minmax(0,1fr));display:grid}}@media (max-width: 1200px){.analytics-grid-4,.analytics-insight-grid,.analytics-story-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.analytics-storyboard,.analytics-chart-grid,.analytics-leader-grid,.analytics-detail-stack{grid-template-columns:1fr}}@media (max-width: 768px){.analytics-grid-4,.analytics-insight-grid,.analytics-story-grid{grid-template-columns:1fr}}

.analytics-depth-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:18px 0}.analytics-depth-card,.analytics-pattern-card,.analytics-chart-compare-card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:22px;padding:18px 20px;box-shadow:0 14px 30px rgba(15,23,42,.04)}.analytics-depth-card.good,.analytics-pattern-card.good{background:linear-gradient(180deg,rgba(240,253,244,.9),#fff)}.analytics-depth-card.warn,.analytics-pattern-card.warn{background:linear-gradient(180deg,rgba(255,251,235,.92),#fff)}.analytics-depth-label{font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:700;margin-bottom:6px}.analytics-depth-value,.analytics-pattern-value{font-size:1.35rem;font-weight:800;color:#0f172a}.analytics-depth-help{color:#64748b;font-size:.86rem;margin-top:6px}.analytics-pattern-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:18px}.analytics-chart-compare-card{display:grid;grid-template-columns:1fr 1.2fr;gap:18px;margin-bottom:18px}.analytics-compare-stack{display:grid;gap:14px}.analytics-compare-row{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid rgba(15,23,42,.06)}.analytics-compare-row:last-child{border-bottom:none}.analytics-compare-label{font-weight:700;color:#0f172a}.analytics-compare-help{font-size:.85rem;color:#64748b;margin-top:4px}.analytics-compare-value{font-weight:800;color:#0f172a;white-space:nowrap}.analytics-deep-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:18px}.analytics-deep-card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:22px;padding:20px;box-shadow:0 14px 30px rgba(15,23,42,.04)}.analytics-deep-list{display:grid;gap:12px}.analytics-deep-item{padding:14px 16px;border-radius:18px;background:#f8fafc;border:1px solid rgba(15,23,42,.05)}.analytics-deep-item .eyebrow{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700}.analytics-deep-item .value{font-size:1.2rem;font-weight:800;color:#0f172a;margin-top:6px}.analytics-deep-item .help{color:#64748b;font-size:.86rem;line-height:1.65;margin-top:6px}.analytics-deep-item.good{background:linear-gradient(180deg,rgba(240,253,244,.9),#fff)}.analytics-deep-item.warn{background:linear-gradient(180deg,rgba(255,251,235,.92),#fff)}.analytics-deep-item.risk{background:linear-gradient(180deg,rgba(254,242,242,.95),#fff)}.analytics-period-table{display:grid;gap:10px}.analytics-period-row{display:grid;grid-template-columns:1.1fr .8fr .8fr .6fr;gap:12px;align-items:start;padding:14px 0;border-bottom:1px solid rgba(15,23,42,.06)}.analytics-period-row.header{padding-top:0;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b}.analytics-period-label{font-weight:800;color:#0f172a}.analytics-period-help{font-size:.84rem;color:#64748b;line-height:1.55;margin-top:4px}.analytics-period-delta{font-weight:800;text-align:end}.analytics-return-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.analytics-return-card{padding:16px;border-radius:18px;background:#fff7ed;border:1px solid rgba(249,115,22,.14)}.analytics-return-card .return-label{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:#9a3412;font-weight:700}.analytics-return-card .return-title{font-size:1rem;font-weight:800;color:#7c2d12;margin-top:6px}.analytics-return-card .return-value{font-size:1.2rem;font-weight:800;color:#0f172a;margin-top:6px}.analytics-return-card .return-help{font-size:.84rem;color:#7c2d12;line-height:1.6;margin-top:6px}@media (max-width:1200px){.analytics-depth-strip,.analytics-pattern-grid,.analytics-chart-compare-card,.analytics-deep-grid,.analytics-return-grid{grid-template-columns:1fr 1fr}}@media (max-width:768px){.analytics-depth-strip,.analytics-pattern-grid,.analytics-chart-compare-card,.analytics-deep-grid,.analytics-return-grid{grid-template-columns:1fr}}

</style>

<div class="analytics-shell">
    <x-admin.page-header
        :kicker="__('Revenue intelligence')"
        :title="__('Revenue Intelligence')"
        :description="__('Showing :from → :to', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')])"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Revenue Intelligence'), 'current' => true],
        ]"
    >
        <a href="{{ route('admin.analytics.offers', ['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]) }}" class="btn btn-outline-dark">
            <i class="bi bi-ticket-perforated"></i> {{ __('Offers drilldown') }}
        </a>
    </x-admin.page-header>

    @include('admin.analytics._nav')
@include('admin.analytics._trust_panel', ['trust' => $trust ?? [], 'uiState' => $uiState ?? []])

    @include('admin.analytics._report_toolbar', [
        'title' => __('Revenue Intelligence'),
        'subtitle' => __('Clean executive wording, export-ready summaries, and a print-friendly operator view for the selected period.'),
        'period' => $from->format('Y-m-d') . ' → ' . $to->format('Y-m-d'),
        'reportId' => 'overview-report',
        'exportRows' => $exportRows,
    ])

    <div class="analytics-card">
        <div class="card-body">
            <div class="analytics-pills mb-3">
                @foreach (['today' => __('Today'), '7d' => __('Last 7 days'), '30d' => __('Last 30 days'), '90d' => __('Last 90 days')] as $pillKey => $pillLabel)
                    <a href="{{ route('admin.analytics.index', ['range' => $pillKey]) }}" class="analytics-pill {{ $range === $pillKey ? 'active' : '' }}">{{ $pillLabel }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.analytics.index') }}" class="analytics-form">
                <input type="hidden" name="range" value="custom">
                <div>
                    <label class="form-label">{{ __('From') }}</label>
                    <input type="date" class="form-control" name="from_date" value="{{ request('from_date', $from->toDateString()) }}">
                </div>
                <div>
                    <label class="form-label">{{ __('To') }}</label>
                    <input type="date" class="form-control" name="to_date" value="{{ request('to_date', $to->toDateString()) }}">
                </div>
                <div>
                    <button class="btn btn-dark" type="submit">{{ __('Apply range') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="analytics-anchor-nav">
        <a href="#operator-summary" class="analytics-anchor">{{ __('Operator summary') }}</a>
        <a href="#deep-read" class="analytics-anchor">{{ __('Deep read') }}</a>
        <a href="#kpi-hierarchy" class="analytics-anchor">{{ __('KPI hierarchy') }}</a>
        <a href="#chart-suite" class="analytics-anchor">{{ __('Chart suite') }}</a>
        <a href="#drilldowns" class="analytics-anchor">{{ __('Drilldowns') }}</a>
        <a href="#funnel-health" class="analytics-anchor">{{ __('Funnel health') }}</a>
    </div>

    <div class="analytics-depth-strip" id="operator-summary">
        @foreach ($operatorSummaryCards as $item)
            <div class="analytics-depth-card neutral">
                <div class="analytics-depth-label">{{ $item['label'] }}</div>
                <div class="analytics-depth-value">{{ $item['value'] }}</div>
                <div class="analytics-depth-help">{{ $item['help'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="analytics-depth-strip">
        @foreach ($depthSignals as $signal)
            <div class="analytics-depth-card {{ $signal['tone'] }}">
                <div class="analytics-depth-label">{{ $signal['label'] }}</div>
                <div class="analytics-depth-value">{{ $signal['value'] }}</div>
                <div class="analytics-depth-help">{{ $signal['help'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="analytics-pattern-grid">
        @foreach ($patternRead as $item)
            <div class="analytics-pattern-card {{ $item['tone'] }}">
                <div class="analytics-block-title">{{ $item['title'] }}</div>
                <div class="analytics-pattern-value">{{ $item['value'] }}</div>
                <div class="analytics-block-subtitle">{{ $item['help'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="analytics-summary-card">
        <div>
            <div class="analytics-block-title">{{ __('Executive summary') }}</div>
            <div class="analytics-block-subtitle">{{ __('Fast executive read first, then open the section that explains pressure or return in more detail.') }}</div>
        </div>
        <ul class="analytics-summary-list">
            @foreach ($executiveSummary as $index => $item)
                <li class="analytics-summary-item">
                    <span class="analytics-summary-icon">{{ $index + 1 }}</span>
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="analytics-story-grid">
        @foreach ($storySignals as $signal)
            <div class="analytics-story-card {{ $signal['tone'] }}">
                <div class="kicker">{{ $signal['title'] }}</div>
                <div class="metric">{{ $signal['value'] }}</div>
                <div class="desc">{{ $signal['description'] }}</div>
            </div>
        @endforeach
    </div>


    <div class="analytics-deep-grid" id="deep-read">
        <div class="analytics-deep-card">
            <div class="analytics-block-title">{{ __('Reason read') }}</div>
            <div class="analytics-block-subtitle">{{ __('Direct reasons only: what is helping, what is slowing, and what it means right now.') }}</div>
            <div class="analytics-deep-list">
                @foreach ($reasonReads as $item)
                    <div class="analytics-deep-item {{ $item['tone'] }}">
                        <div class="eyebrow">{{ $item['title'] }}</div>
                        <div class="value">{{ $item['value'] }}</div>
                        <div class="help">{{ $item['help'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="analytics-deep-card">
            <div class="analytics-block-title">{{ __('Period comparison') }}</div>
            <div class="analytics-block-subtitle">{{ __('Current versus previous matching period, without extra reading overhead.') }}</div>
            <div class="analytics-period-table">
                <div class="analytics-period-row header">
                    <div>{{ __('Metric') }}</div>
                    <div>{{ __('Current') }}</div>
                    <div>{{ __('Previous') }}</div>
                    <div class="text-end">{{ __('Delta') }}</div>
                </div>
                @foreach ($periodCompareRows as $row)
                    <div class="analytics-period-row">
                        <div>
                            <div class="analytics-period-label">{{ $row['label'] }}</div>
                            <div class="analytics-period-help">{{ $row['help'] }}</div>
                        </div>
                        <div>{{ $row['current'] }}</div>
                        <div>{{ $row['previous'] }}</div>
                        <div class="analytics-period-delta">{{ $row['delta'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="analytics-deep-grid">
        <div class="analytics-deep-card">
            <div class="analytics-block-title">{{ __('Where leakage is happening') }}</div>
            <div class="analytics-block-subtitle">{{ __('The weakest journey handoffs ranked from highest to lowest pressure.') }}</div>
            <div class="analytics-deep-list">
                @forelse ($leakageReads->take(3) as $item)
                    <div class="analytics-deep-item {{ $item['tone'] }}">
                        <div class="eyebrow">{{ $item['title'] }}</div>
                        <div class="value">{{ $item['value'] }}</div>
                        <div class="help">{{ $item['help'] }}</div>
                    </div>
                @empty
                    <div class="text-muted">{{ __('No meaningful leakage points are visible for this range yet.') }}</div>
                @endforelse
            </div>
        </div>
        <div class="analytics-deep-card">
            <div class="analytics-block-title">{{ __('Where the highest return is') }}</div>
            <div class="analytics-block-subtitle">{{ __('The strongest tracked revenue contributors across products, categories, and offers.') }}</div>
            <div class="analytics-return-grid">
                @forelse ($highestReturnRows as $item)
                    <div class="analytics-return-card">
                        <div class="return-label">{{ $item['label'] }}</div>
                        <div class="return-title">{{ $item['title'] }}</div>
                        <div class="return-value">{{ $item['value'] }}</div>
                        <div class="return-help">{{ $item['help'] }}</div>
                    </div>
                @empty
                    <div class="text-muted">{{ __('No high-return entities are visible for this range yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="analytics-storyboard" id="kpi-hierarchy">
        <div class="analytics-comparison-card">
            <div class="eyebrow">{{ __('Comparison storytelling') }}</div>
            <div class="headline">{{ __('The current window is :direction versus the previous matching period.', ['direction' => $revenueDelta['rate'] >= 0 ? __('building') : __('under pressure')]) }}</div>
            <div class="subcopy">
                {{ __('Revenue pace is :rev, order pace is :orders, and cart abandonment is :abandonment. This lets the team see whether performance is being driven by demand, conversion quality, or leakage inside checkout.', ['rev' => $revenueDelta['text'], 'orders' => $ordersDelta['text'], 'abandonment' => $abandonmentDelta['text']]) }}
            </div>
            <div class="analytics-compare-grid">
                <div class="analytics-compare-tile"><div class="tile-label">{{ __('Revenue momentum') }}</div><div class="tile-value">{{ ($revenueMomentum >= 0 ? '+' : '') . number_format($revenueMomentum * 100, 1) }}%</div></div>
                <div class="analytics-compare-tile"><div class="tile-label">{{ __('Order momentum') }}</div><div class="tile-value">{{ ($ordersMomentum >= 0 ? '+' : '') . number_format($ordersMomentum * 100, 1) }}%</div></div>
                <div class="analytics-compare-tile"><div class="tile-label">{{ __('Returning buyer mix') }}</div><div class="tile-value">{{ number_format($returningShare * 100, 1) }}%</div></div>
                <div class="analytics-compare-tile"><div class="tile-label">{{ __('Checkout drops') }}</div><div class="tile-value">{{ number_format($checkoutDropCount) }}</div></div>
            </div>
        </div>
        <div class="analytics-lane-card">
            <div class="analytics-block-title">{{ __('Operating lanes') }}</div>
            <div class="analytics-block-subtitle">{{ __('Where to scale, protect, and improve next.') }}</div>
            <div class="analytics-lane-list">
                @foreach ($focusLaneItems as $item)
                    <div class="analytics-lane-item">
                        <div class="lane-label">{{ $item['label'] }}</div>
                        <div class="lane-value">{{ $item['value'] }}</div>
                        <div class="lane-help">{{ $item['help'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="analytics-grid-4">
        @foreach ($kpiCards as $item)
            <div class="analytics-trend-card analytics-kpi">
                <div class="label">{{ $item['label'] }}</div>
                <div class="value">{{ $item['value'] }}</div>
                <div class="delta {{ $item['delta']['class'] }}">{{ __('Vs previous') }}: {{ $item['delta']['text'] }}</div>
                <div class="help">{{ $item['help'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="analytics-chart-compare-card" id="chart-suite">
        <div>
            <div class="analytics-block-title">{{ __('Chart suite') }}</div>
            <div class="analytics-block-subtitle">{{ __('Revenue, orders, and average order value in one aligned comparison view.') }}</div>
        </div>
        <div class="analytics-compare-stack">
            @foreach ([
                ['label' => __('Revenue line'), 'value' => 'EGP ' . number_format((float) ($totals['revenue_gross'] ?? 0), 0), 'help' => __('Gross revenue over the selected period.')],
                ['label' => __('Orders line'), 'value' => number_format((int) ($totals['orders_count'] ?? 0)), 'help' => __('Confirmed order count in the same range.')],
                ['label' => __('AOV line'), 'value' => 'EGP ' . number_format((float) ($totals['average_order_value'] ?? 0), 2), 'help' => __('Average order value across tracked orders.')],
            ] as $item)
                <div class="analytics-compare-row">
                    <div>
                        <div class="analytics-compare-label">{{ $item['label'] }}</div>
                        <div class="analytics-compare-help">{{ $item['help'] }}</div>
                    </div>
                    <div class="analytics-compare-value">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="analytics-insight-grid">
        <div class="analytics-mini h-100">
            <div class="small text-muted mb-1">{{ __('Data source') }}</div>
            <div class="fw-bold fs-5">{{ $isAggregated ? __('Aggregated daily stats') : __('Live event fallback') }}</div>
            <div class="text-muted small mt-2">
                @if ($isAggregated && $lastAggregatedAt)
                    {{ __('Last aggregation: :time', ['time' => \Illuminate\Support\Carbon::parse($lastAggregatedAt)->format('Y-m-d H:i')]) }}
                @elseif ($lastEventAt)
                    {{ __('Last event seen: :time', ['time' => \Illuminate\Support\Carbon::parse($lastEventAt)->format('Y-m-d H:i')]) }}
                @else
                    {{ __('No analytics ingestion timestamps are available yet.') }}
                @endif
            </div>
        </div>
        <div class="analytics-mini h-100">
            <div class="small text-muted mb-1">{{ __('Average daily revenue') }}</div>
            <div class="fw-bold fs-5">EGP {{ number_format($averageDailyRevenue, 2) }}</div>
            <div class="text-muted small mt-2">{{ __('Average gross revenue per tracked day in the selected window.') }}</div>
        </div>
        <div class="analytics-mini h-100">
            <div class="small text-muted mb-1">{{ __('Average daily orders') }}</div>
            <div class="fw-bold fs-5">{{ number_format($averageDailyOrders, 1) }}</div>
            <div class="text-muted small mt-2">{{ __('Typical order rhythm inside the current reporting range.') }}</div>
        </div>
        <div class="analytics-mini h-100">
            <div class="small text-muted mb-1">{{ __('Performance pulse') }}</div>
            <div class="fw-bold fs-5">{{ __('Best day') }}: {{ data_get($bestDay, 'full_label', '—') }}</div>
            <div class="text-muted small mt-2">{{ __('Peak revenue: :value', ['value' => 'EGP ' . number_format((float) data_get($bestDay, 'revenue', 0), 2)]) }}<br>{{ __('Softest day: :day', ['day' => data_get($weakestDay, 'full_label', '—')]) }}</div>
        </div>
    </div>

    <div class="analytics-section" id="chart-suite">
        <div class="analytics-section-head">
            <div>
                <div class="analytics-block-title">{{ __('Chart suite') }}</div>
                <div class="analytics-block-subtitle">{{ __('Real visual reads for revenue, order pace, and basket quality using the same tracked data underneath the dashboard.') }}</div>
            </div>
            <div class="analytics-chip-row">
                <span class="analytics-chip">{{ __('Best day') }}: {{ data_get($bestDay, 'label', '—') }}</span>
                <span class="analytics-chip">{{ __('Softest day') }}: {{ data_get($weakestDay, 'label', '—') }}</span>
            </div>
        </div>

        <div class="analytics-chart-grid">
            <div class="analytics-chart-card">
                <div class="analytics-block-title">{{ __('Revenue and order trend') }}</div>
                <div class="analytics-block-subtitle">{{ __('A true line view for pace change across the selected days.') }}</div>
                <div class="analytics-chart-legend">
                    <span><i class="rev"></i>{{ __('Revenue') }}</span>
                    <span><i class="ord"></i>{{ __('Orders') }}</span>
                </div>
                <div class="analytics-svg-wrap">
                    <div class="analytics-svg-frame">
                    <svg viewBox="0 0 520 180" class="analytics-svg" role="img" aria-label="{{ __('Revenue and orders trend chart') }}">
                        <line x1="0" y1="30" x2="520" y2="30" class="analytics-gridlines"></line>
                        <line x1="0" y1="90" x2="520" y2="90" class="analytics-gridlines"></line>
                        <line x1="0" y1="150" x2="520" y2="150" class="analytics-gridlines"></line>
                        @if (! empty($revenueSvg['area']))<polygon points="{{ $revenueSvg['area'] }}" class="analytics-area revenue"></polygon>@endif
                        @if (! empty($ordersSvg['area']))<polygon points="{{ $ordersSvg['area'] }}" class="analytics-area orders"></polygon>@endif
                        @if (! empty($revenueSvg['line']))<polyline points="{{ $revenueSvg['line'] }}" class="analytics-line revenue"></polyline>@endif
                        @if (! empty($ordersSvg['line']))<polyline points="{{ $ordersSvg['line'] }}" class="analytics-line orders"></polyline>@endif
                    </svg>
                    </div>
                </div>
                <div class="analytics-axis {{ $chartLabelStep > 1 ? 'compact' : '' }}">
                    @forelse ($axisLabels as $row)
                        <span>{{ $row['label'] }}</span>
                    @empty
                        <span>{{ __('No daily labels yet') }}</span>
                    @endforelse
                </div>
            </div>
            <div class="analytics-detail-stack">
                <div class="analytics-svg-card">
                    <div class="analytics-block-title">{{ __('AOV trend') }}</div>
                    <div class="analytics-block-subtitle">{{ __('Basket quality trend across the same window.') }}</div>
                    <div class="analytics-chart-legend"><span><i class="aov"></i>{{ __('AOV') }}</span></div>
                    <div class="analytics-svg-wrap"><div class="analytics-svg-frame"><svg viewBox="0 0 520 180" class="analytics-svg" role="img" aria-label="{{ __('AOV trend chart') }}">
                        <line x1="0" y1="30" x2="520" y2="30" class="analytics-gridlines"></line>
                        <line x1="0" y1="90" x2="520" y2="90" class="analytics-gridlines"></line>
                        <line x1="0" y1="150" x2="520" y2="150" class="analytics-gridlines"></line>
                        @if (! empty($aovSvg['line']))<polyline points="{{ $aovSvg['line'] }}" class="analytics-line aov"></polyline>@endif
                    </svg></div></div>
                    <div class="analytics-axis {{ $chartLabelStep > 1 ? 'compact' : '' }}">
                        @forelse ($axisLabels as $row)
                            <span>{{ $row['label'] }}</span>
                        @empty
                            <span>{{ __('No daily labels yet') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="analytics-svg-card">
                    <div class="analytics-block-title">{{ __('Quick chart read') }}</div>
                    <div class="analytics-block-subtitle">{{ __('Use this hierarchy to decide where to click next.') }}</div>
                    <div class="analytics-lane-list">
                        <div class="analytics-lane-item"><div class="lane-label">{{ __('If revenue is weak') }}</div><div class="lane-value">{{ __('Check products and categories') }}</div><div class="lane-help">{{ __('Go to the product leaderboard and inspect the softest movers first.') }}</div></div>
                        <div class="analytics-lane-item"><div class="lane-label">{{ __('If orders are weak') }}</div><div class="lane-value">{{ __('Review growth and offer fit') }}</div><div class="lane-help">{{ __('Compare order pace against checkout drops and coupon pressure.') }}</div></div>
                        <div class="analytics-lane-item"><div class="lane-label">{{ __('If AOV is weak') }}</div><div class="lane-value">{{ __('Inspect offer quality') }}</div><div class="lane-help">{{ __('Use offers drilldown to see whether discounts are carrying or compressing value.') }}</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3" id="funnel-health">
        <div class="col-lg-7">
            <div class="analytics-card h-100">
                <div class="card-body">
                    <div class="analytics-block-title">{{ __('KPI trend board') }}</div>
                    <div class="analytics-block-subtitle">{{ __('Daily revenue, orders, and AOV bars for fast day-by-day diagnosis.') }}</div>
                    <div class="analytics-list">
                        @if ($dailyChartRows->isEmpty())
                            <div class="text-muted">{{ __('No analytics data yet for this period.') }}</div>
                        @else
                            @foreach ($dailyChartRows as $chartRow)
                                <div class="analytics-row">
                                    <div style="min-width:90px"><div class="fw-bold">{{ $chartRow['label'] }}</div><div class="small text-muted">{{ __('CVR') }} {{ number_format($chartRow['conversion_rate'] * 100, 1) }}%</div></div>
                                    <div class="flex-fill">
                                        <div class="analytics-bar-track mb-2"><div class="analytics-bar-fill" style="width: {{ max(4, $chartRow['revenue_width']) }}%"></div></div>
                                        <div class="analytics-bar-track mb-2"><div class="analytics-bar-fill soft" style="width: {{ max(4, $chartRow['orders_width']) }}%"></div></div>
                                        <div class="analytics-bar-track"><div class="analytics-bar-fill dark" style="width: {{ max(4, $chartRow['aov_width']) }}%"></div></div>
                                    </div>
                                    <div class="text-end"><div class="fw-bold">EGP {{ number_format($chartRow['revenue'], 2) }}</div><div class="text-muted small">{{ number_format($chartRow['orders_count']) }} {{ __('orders') }} · {{ __('AOV') }} {{ number_format($chartRow['aov'], 2) }}</div></div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="analytics-card h-100">
                <div class="card-body">
                    <div class="analytics-block-title">{{ __('Funnel tracking') }}</div>
                    <div class="analytics-block-subtitle">{{ __('View → cart → checkout → purchase with direct leakage context.') }}</div>
                    @foreach ($funnel as $step)
                        <div class="analytics-funnel-step">
                            <div class="d-flex justify-content-between align-items-center mb-1"><strong>{{ $step['label'] }}</strong><span>{{ number_format((int) $step['count']) }}</span></div>
                            <div class="small text-muted">{{ __('From previous: :rate', ['rate' => number_format(((float) ($step['conversion_from_previous'] ?? 0)) * 100, 1) . '%']) }} · {{ __('Drop-off: :rate', ['rate' => number_format(((float) ($step['drop_off_from_previous'] ?? 0)) * 100, 1) . '%']) }}</div>
                        </div>
                    @endforeach
                    <div class="analytics-mini mt-3">
                        <div class="small text-muted mb-1">{{ __('Largest drop-off') }}</div>
                        <div class="fw-bold">{{ data_get($largestDrop, 'label', __('No drop-off yet')) }}</div>
                        <div class="text-muted small">{{ number_format(((float) data_get($largestDrop, 'drop_off_from_previous', 0)) * 100, 1) }}%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (!($uiState['empty'] ?? false))
    <div class="analytics-section" id="drilldowns">
        <div class="analytics-section-head">
            <div>
                <div class="analytics-block-title">{{ __('Drilldowns') }}</div>
                <div class="analytics-block-subtitle">{{ __('Move from the headline KPIs into the entities carrying or dragging performance.') }}</div>
            </div>
            <div class="analytics-chip-row">
                <span class="analytics-chip">{{ __('Products') }}: {{ number_format($topProducts->count()) }}</span>
                <span class="analytics-chip">{{ __('Categories') }}: {{ number_format($topCategories->count()) }}</span>
                <span class="analytics-chip">{{ __('Coupons') }}: {{ number_format($couponPerformance->count()) }}</span>
            </div>
        </div>

        <div class="analytics-leader-grid">
            <div class="analytics-card">
                <div class="card-body">
                    <div class="analytics-block-title">{{ __('Top products leaderboard') }}</div>
                    <div class="analytics-block-subtitle">{{ __('Click straight into a product drilldown from the strongest or weakest commercial movers.') }}</div>
                    <div class="analytics-list">
                        @forelse ($topProducts as $row)
                            <div class="analytics-row">
                                <div class="flex-fill">
                                    <div class="fw-bold"><a href="{{ route('admin.analytics.products.show', ['product' => $row->product_id, 'range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]) }}" class="text-decoration-none">{{ $row->product_name ?? __('Product #:id', ['id' => $row->product_id]) }}</a></div>
                                    <div class="text-muted small">{{ number_format((int) data_get($row, 'purchases', 0)) }} {{ __('purchases') }} · {{ __('Qty') }} {{ number_format((int) data_get($row, 'purchased_quantity', 0)) }}</div>
                                    <div class="analytics-bar-track mt-2"><div class="analytics-bar-fill" style="width: {{ min(100, (((float) data_get($row, 'revenue_gross', 0)) / $topProductRevenue) * 100) }}%"></div></div>
                                </div>
                                <div class="text-end"><div class="fw-bold">EGP {{ number_format((float) data_get($row, 'revenue_gross', 0), 2) }}</div><div class="text-muted small">#{{ $row->product_id }}</div></div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No product performance data is available for this range yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="analytics-card">
                <div class="card-body">
                    <div class="analytics-block-title">{{ __('Category and coupon mix') }}</div>
                    <div class="analytics-block-subtitle">{{ __('Use this to understand whether performance concentration is broad or narrow.') }}</div>
                    <div class="analytics-list mb-3">
                        @forelse ($topCategories->take(4) as $row)
                            <div class="analytics-row">
                                <div class="flex-fill">
                                    <div class="fw-bold">{{ $row->category_name ?? __('Uncategorized') }}</div>
                                    <div class="analytics-bar-track mt-2"><div class="analytics-bar-fill soft" style="width: {{ min(100, (((float) data_get($row, 'revenue_gross', 0)) / $topCategoryRevenue) * 100) }}%"></div></div>
                                </div>
                                <div class="text-end"><div class="fw-bold">EGP {{ number_format((float) data_get($row, 'revenue_gross', 0), 2) }}</div></div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No category mix data is available yet.') }}</div>
                        @endforelse
                    </div>
                    <div class="analytics-block-title">{{ __('Coupon performance') }}</div>
                    <div class="analytics-list">
                        @forelse ($couponPerformance->take(4) as $row)
                            <div class="analytics-row">
                                <div><div class="fw-bold">{{ data_get($row, 'coupon_code', __('No coupon')) }}</div><div class="text-muted small">{{ number_format((int) data_get($row, 'orders_count', 0)) }} {{ __('orders') }}</div></div>
                                <div class="text-end"><div class="fw-bold">EGP {{ number_format((float) data_get($row, 'revenue_gross', 0), 2) }}</div></div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No coupon performance records for this range.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($topSessions->isNotEmpty())
        @if ($uiState['show_watchlist'] ?? false)
    <div class="analytics-card">
            <div class="card-body">
                <div class="analytics-block-title">{{ __('Session watchlist') }}</div>
                <div class="analytics-block-subtitle">{{ __('High-value sessions that can guide merchandising or retention review.') }}</div>
                <div class="analytics-table-wrap">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Orders') }}</th>
                                <th>{{ __('Revenue') }}</th>
                                <th>{{ __('Last order') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topSessions as $session)
                                <tr>
                                    <td>{{ data_get($session, 'customer_name', __('Guest')) }}</td>
                                    <td>{{ number_format((int) data_get($session, 'orders_count', 0)) }}</td>
                                    <td>EGP {{ number_format((float) data_get($session, 'revenue_gross', 0), 2) }}</td>
                                    <td>{{ data_get($session, 'last_order_at', '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    @endif
    @endif
</div>
@endsection
