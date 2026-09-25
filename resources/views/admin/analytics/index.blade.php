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
.analytics-shell{display:grid;gap:18px}.analytics-toolbar-card,.analytics-panel,.analytics-chart-card,.analytics-detail-card{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:20px;box-shadow:0 14px 34px rgba(15,23,42,.05)}.analytics-toolbar-card,.analytics-panel{padding:20px}.analytics-range-row{display:flex;justify-content:space-between;gap:16px;align-items:end;flex-wrap:wrap}.analytics-pills{display:flex;gap:8px;flex-wrap:wrap}.analytics-pill{display:inline-flex;align-items:center;padding:9px 13px;border-radius:999px;background:color-mix(in srgb,var(--admin-surface) 92%,var(--admin-primary) 8%);border:1px solid var(--admin-border);font-weight:700;color:var(--admin-text);text-decoration:none}.analytics-pill:hover{border-color:color-mix(in srgb,var(--admin-primary) 35%,var(--admin-border));color:var(--admin-primary)}.analytics-pill.active{background:var(--admin-primary);color:#fff;border-color:var(--admin-primary)}.analytics-form{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.analytics-form .form-control{min-width:160px}.analytics-section-nav{display:flex;gap:8px;flex-wrap:wrap}.analytics-section-nav a{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:var(--admin-surface);border:1px solid var(--admin-border);font-size:.84rem;font-weight:700;color:var(--admin-muted);text-decoration:none}.analytics-section-nav a:hover{color:var(--admin-primary);border-color:color-mix(in srgb,var(--admin-primary) 35%,var(--admin-border))}.analytics-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.analytics-kpi-card{min-height:150px}.analytics-section-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px}.analytics-title{font-size:1.05rem;font-weight:800;color:var(--admin-text)}.analytics-subtitle{font-size:.88rem;color:var(--admin-muted);line-height:1.65;margin-top:4px}.analytics-decision-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}.analytics-summary-list,.analytics-lane-list,.analytics-list{display:grid;gap:10px}.analytics-summary-item,.analytics-lane-item{padding:13px 14px;border-radius:15px;background:color-mix(in srgb,var(--admin-surface) 92%,var(--admin-bg) 8%);border:1px solid var(--admin-border)}.analytics-summary-item{display:flex;gap:10px;align-items:flex-start}.analytics-summary-index{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:color-mix(in srgb,var(--admin-primary) 12%,var(--admin-surface));color:var(--admin-primary);font-weight:800;flex:0 0 auto}.analytics-lane-label{font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);font-weight:800}.analytics-lane-value{font-weight:800;color:var(--admin-text);margin-top:4px}.analytics-lane-help{font-size:.84rem;color:var(--admin-muted);line-height:1.55;margin-top:5px}.analytics-chart-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(280px,.8fr);gap:16px}.analytics-chart-card{padding:18px;min-width:0}.analytics-svg-wrap{position:relative;width:100%;overflow-x:auto;overflow-y:hidden}.analytics-svg-frame{min-width:520px;width:100%}.analytics-svg{width:100%;height:180px;display:block}.analytics-gridlines{stroke:color-mix(in srgb,var(--admin-border) 70%,transparent);stroke-width:1}.analytics-line.revenue{fill:none;stroke:var(--admin-primary);stroke-width:3}.analytics-line.orders{fill:none;stroke:var(--admin-muted);stroke-width:3}.analytics-line.aov{fill:none;stroke:var(--admin-text);stroke-width:2.5}.analytics-area.revenue{fill:color-mix(in srgb,var(--admin-primary) 12%,transparent)}.analytics-area.orders{fill:color-mix(in srgb,var(--admin-muted) 10%,transparent)}.analytics-chart-legend{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:12px}.analytics-chart-legend span{display:inline-flex;align-items:center;gap:7px;color:var(--admin-muted);font-size:.82rem}.analytics-chart-legend i{display:inline-block;width:10px;height:10px;border-radius:999px}.analytics-chart-legend .rev{background:var(--admin-primary)}.analytics-chart-legend .ord{background:var(--admin-muted)}.analytics-chart-legend .aov{background:var(--admin-text)}.analytics-axis{display:flex;justify-content:space-between;gap:8px;margin-top:8px;color:var(--admin-muted);font-size:.74rem;overflow-x:auto}.analytics-axis span{flex:0 0 auto;min-width:44px;text-align:center}.analytics-secondary-stack{display:grid;gap:14px}.analytics-pulse-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.analytics-pulse{padding:13px;border-radius:15px;background:color-mix(in srgb,var(--admin-surface) 92%,var(--admin-bg) 8%);border:1px solid var(--admin-border)}.analytics-pulse-label{font-size:.74rem;color:var(--admin-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:800}.analytics-pulse-value{font-weight:800;color:var(--admin-text);margin-top:5px}.analytics-compare-funnel{display:grid;grid-template-columns:1.05fr .95fr;gap:16px}.analytics-period-table{display:grid}.analytics-period-row{display:grid;grid-template-columns:1.2fr .75fr .75fr .55fr;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--admin-border)}.analytics-period-row:last-child{border-bottom:none}.analytics-period-row.header{font-size:.74rem;font-weight:800;text-transform:uppercase;color:var(--admin-muted)}.analytics-period-label{font-weight:700;color:var(--admin-text)}.analytics-period-help{font-size:.8rem;color:var(--admin-muted);margin-top:3px}.analytics-period-delta{text-align:end;font-weight:800}.analytics-funnel-step{padding:13px 14px;border-radius:15px;background:color-mix(in srgb,var(--admin-surface) 92%,var(--admin-bg) 8%);border:1px solid var(--admin-border)}.analytics-funnel-step+.analytics-funnel-step{margin-top:9px}.analytics-leader-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}.analytics-row{display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid var(--admin-border)}.analytics-row:last-child{border-bottom:none}.analytics-bar-track{height:8px;border-radius:999px;background:color-mix(in srgb,var(--admin-border) 70%,transparent);overflow:hidden}.analytics-bar-fill{height:100%;border-radius:999px;background:var(--admin-primary)}.analytics-bar-fill.soft{background:var(--admin-muted)}.analytics-chip-row{display:flex;gap:8px;flex-wrap:wrap}.analytics-chip{display:inline-flex;align-items:center;padding:7px 10px;border-radius:999px;background:color-mix(in srgb,var(--admin-primary) 8%,var(--admin-surface));border:1px solid color-mix(in srgb,var(--admin-primary) 18%,var(--admin-border));color:var(--admin-text);font-size:.8rem;font-weight:700}.analytics-table-wrap{overflow:auto}.analytics-table{width:100%;border-collapse:collapse}.analytics-table th,.analytics-table td{padding:11px 9px;border-bottom:1px solid var(--admin-border);white-space:nowrap}.analytics-table th{font-size:.76rem;text-transform:uppercase;letter-spacing:.04em;color:var(--admin-muted)}.analytics-more{border:1px solid var(--admin-border);border-radius:18px;background:var(--admin-surface);overflow:hidden}.analytics-more summary{cursor:pointer;padding:16px 18px;font-weight:800;color:var(--admin-text);list-style:none}.analytics-more summary::-webkit-details-marker{display:none}.analytics-more summary:after{content:"+";float:inline-end;color:var(--admin-primary);font-size:1.2rem}.analytics-more[open] summary:after{content:"−"}.analytics-more-body{padding:0 18px 18px}.analytics-daily-row{display:grid;grid-template-columns:80px 1fr auto;gap:12px;align-items:center;padding:10px 0;border-top:1px solid var(--admin-border)}.analytics-daily-bars{display:grid;gap:5px}.analytics-daily-meta{text-align:end}.analytics-bar-fill.orders{background:var(--admin-muted)}.analytics-bar-fill.aov{background:var(--admin-text)}@media(max-width:1200px){.analytics-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.analytics-decision-grid,.analytics-chart-grid,.analytics-compare-funnel,.analytics-leader-grid{grid-template-columns:1fr}}@media(max-width:768px){.analytics-kpi-grid,.analytics-pulse-grid{grid-template-columns:1fr}.analytics-period-row{grid-template-columns:1fr 1fr}.analytics-period-row.header{display:none}.analytics-period-delta{text-align:start}.analytics-daily-row{grid-template-columns:1fr}.analytics-daily-meta{text-align:start}.analytics-toolbar-card,.analytics-panel{padding:16px}.analytics-range-row{align-items:stretch}.analytics-pills{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px;scrollbar-width:thin}.analytics-pill{flex:0 0 auto}.analytics-form{display:grid;grid-template-columns:1fr 1fr;width:100%}.analytics-form .form-control{min-width:0;width:100%}.analytics-form button{grid-column:1/-1;width:100%}.analytics-svg-frame{min-width:460px}}@media(max-width:480px){.analytics-form{grid-template-columns:1fr}.analytics-form button{grid-column:auto}}
</style>

<div class="analytics-shell" data-admin-section-tabs="analytics" data-admin-section-history="true" data-live-list>
    <x-admin.page-header
        :kicker="__('Revenue intelligence')"
        :title="__('Revenue Intelligence')"
        :description="__('Showing :from → :to', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')])"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Revenue Intelligence'), 'current' => true],
        ]"
    >
        <a href="{{ route('admin.analytics.offers', ['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]) }}" class="btn btn-light border">
            <i class="bi bi-ticket-perforated"></i> {{ __('Offers drilldown') }}
        </a>
    </x-admin.page-header>

    @include('admin.analytics._nav')

    <div class="analytics-toolbar-card">
        <div class="analytics-range-row">
            <div>
                <div class="analytics-title">{{ __('Reporting window') }}</div>
                <div class="analytics-subtitle">{{ __('Choose a quick range or set exact dates. Every section below uses the same window.') }}</div>
                <div class="analytics-pills mt-3">
                    @foreach (['today' => __('Today'), '7d' => __('Last 7 days'), '30d' => __('Last 30 days'), '90d' => __('Last 90 days')] as $pillKey => $pillLabel)
                        <a href="{{ route('admin.analytics.index', ['range' => $pillKey]) }}" data-live-link class="analytics-pill {{ $range === $pillKey ? 'active' : '' }}">{{ $pillLabel }}</a>
                    @endforeach
                </div>
            </div>
            <form method="GET" action="{{ route('admin.analytics.index') }}" class="analytics-form" data-live-filter>
                <input type="hidden" name="range" value="custom">
                <div>
                    <label class="form-label">{{ __('From') }}</label>
                    <input type="date" class="form-control" name="from_date" value="{{ request('from_date', $from->toDateString()) }}" data-live-filter-control>
                </div>
                <div>
                    <label class="form-label">{{ __('To') }}</label>
                    <input type="date" class="form-control" name="to_date" value="{{ request('to_date', $to->toDateString()) }}" data-live-filter-control>
                </div>
                <button class="btn btn-primary" type="submit">{{ __('Apply range') }}</button>
            </form>
        </div>
    </div>

    <div class="visually-hidden" aria-live="polite" data-live-status data-loading="{{ __('Loading...') }}" data-updated="{{ __('Updated') }}" data-error="{{ __('Could not refresh this view. Use the fallback link to reload.') }}"></div>
    <a class="visually-hidden" href="{{ request()->fullUrl() }}" data-live-fallback>{{ __('Reload results') }}</a>

    <div data-live-results>
    @include('admin.analytics._trust_panel', ['trust' => $trust ?? [], 'uiState' => $uiState ?? []])

    @include('admin.analytics._report_toolbar', [
        'title' => __('Revenue Intelligence'),
        'subtitle' => __('A concise commercial summary for the selected period.'),
        'period' => $from->format('Y-m-d') . ' → ' . $to->format('Y-m-d'),
        'reportId' => 'overview-report',
        'exportRows' => $exportRows,
    ])

    @php
        $analyticsSections = ($uiState['empty'] ?? false)
            ? ['performance' => __('Performance')]
            : [
                'performance' => __('Performance'),
                'decision' => __('Decision read'),
                'trends' => __('Trends'),
                'funnel' => __('Funnel & comparison'),
                'drilldowns' => __('Drilldowns'),
            ];
    @endphp

    <x-admin.section-tabs id="analytics" :sections="$analyticsSections" />

    <section id="analytics-panel-performance" role="tabpanel" aria-labelledby="analytics-tab-performance" data-admin-section-panel="performance">
        <div class="analytics-kpi-grid">
            @foreach ($kpiCards as $item)
                <x-admin.stat-card
                    :label="$item['label']"
                    :value="$item['value']"
                    :help="$item['help']"
                    class="analytics-kpi-card"
                >
                    <span class="{{ $item['delta']['class'] }}">{{ __('Vs previous') }}: {{ $item['delta']['text'] }}</span>
                </x-admin.stat-card>
            @endforeach
        </div>
    </section>

    @if (!($uiState['empty'] ?? false))
        <section class="analytics-decision-grid" id="analytics-panel-decision" role="tabpanel" aria-labelledby="analytics-tab-decision" data-admin-section-panel="decision">
            <div class="analytics-panel">
                <div class="analytics-section-head">
                    <div>
                        <div class="analytics-title">{{ __('Decision read') }}</div>
                        <div class="analytics-subtitle">{{ __('Three signals to understand before opening deeper diagnostics.') }}</div>
                    </div>
                </div>
                <div class="analytics-summary-list">
                    @foreach ($executiveSummary as $index => $item)
                        <div class="analytics-summary-item">
                            <span class="analytics-summary-index">{{ $index + 1 }}</span>
                            <span>{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="analytics-panel">
                <div class="analytics-section-head">
                    <div>
                        <div class="analytics-title">{{ __('Operating lanes') }}</div>
                        <div class="analytics-subtitle">{{ __('Where to scale, protect, and improve next.') }}</div>
                    </div>
                </div>
                <div class="analytics-lane-list">
                    @foreach ($focusLaneItems as $item)
                        <div class="analytics-lane-item">
                            <div class="analytics-lane-label">{{ $item['label'] }}</div>
                            <div class="analytics-lane-value">{{ $item['value'] }}</div>
                            <div class="analytics-lane-help">{{ $item['help'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="analytics-panel" id="analytics-panel-trends" role="tabpanel" aria-labelledby="analytics-tab-trends" data-admin-section-panel="trends">
            <div class="analytics-section-head">
                <div>
                    <div class="analytics-title">{{ __('Performance trends') }}</div>
                    <div class="analytics-subtitle">{{ __('Revenue, order pace, and basket quality across the same reporting window.') }}</div>
                </div>
                <div class="analytics-chip-row">
                    <span class="analytics-chip">{{ __('Best day') }}: {{ data_get($bestDay, 'full_label', '—') }}</span>
                    <span class="analytics-chip">{{ __('Softest day') }}: {{ data_get($weakestDay, 'full_label', '—') }}</span>
                </div>
            </div>
            <div class="analytics-chart-grid">
                <div class="analytics-chart-card">
                    <div class="analytics-title">{{ __('Revenue and order trend') }}</div>
                    <div class="analytics-subtitle">{{ __('Use pace changes here before jumping into products or offers.') }}</div>
                    <div class="analytics-chart-legend mt-3">
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
                    <div class="analytics-axis">
                        @forelse ($axisLabels as $row)<span>{{ $row['label'] }}</span>@empty<span>{{ __('No daily labels yet') }}</span>@endforelse
                    </div>
                </div>
                <div class="analytics-secondary-stack">
                    <div class="analytics-chart-card">
                        <div class="analytics-title">{{ __('AOV trend') }}</div>
                        <div class="analytics-subtitle">{{ __('Average basket quality across the selected days.') }}</div>
                        <div class="analytics-chart-legend mt-3"><span><i class="aov"></i>{{ __('AOV') }}</span></div>
                        <div class="analytics-svg-wrap"><div class="analytics-svg-frame">
                            <svg viewBox="0 0 520 180" class="analytics-svg" role="img" aria-label="{{ __('AOV trend chart') }}">
                                <line x1="0" y1="30" x2="520" y2="30" class="analytics-gridlines"></line>
                                <line x1="0" y1="90" x2="520" y2="90" class="analytics-gridlines"></line>
                                <line x1="0" y1="150" x2="520" y2="150" class="analytics-gridlines"></line>
                                @if (! empty($aovSvg['line']))<polyline points="{{ $aovSvg['line'] }}" class="analytics-line aov"></polyline>@endif
                            </svg>
                        </div></div>
                    </div>
                    <div class="analytics-pulse-grid">
                        <div class="analytics-pulse"><div class="analytics-pulse-label">{{ __('Average daily revenue') }}</div><div class="analytics-pulse-value">EGP {{ number_format($averageDailyRevenue, 2) }}</div></div>
                        <div class="analytics-pulse"><div class="analytics-pulse-label">{{ __('Average daily orders') }}</div><div class="analytics-pulse-value">{{ number_format($averageDailyOrders, 1) }}</div></div>
                        <div class="analytics-pulse"><div class="analytics-pulse-label">{{ __('Returning buyer share') }}</div><div class="analytics-pulse-value">{{ number_format($returningShare * 100, 1) }}%</div></div>
                        <div class="analytics-pulse"><div class="analytics-pulse-label">{{ __('Checkout drops') }}</div><div class="analytics-pulse-value">{{ number_format($checkoutDropCount) }}</div></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="analytics-compare-funnel" id="analytics-panel-funnel" role="tabpanel" aria-labelledby="analytics-tab-funnel" data-admin-section-panel="funnel">
            <div class="analytics-panel">
                <div class="analytics-section-head"><div><div class="analytics-title">{{ __('Period comparison') }}</div><div class="analytics-subtitle">{{ __('Current versus the previous matching period.') }}</div></div></div>
                <div class="analytics-period-table">
                    <div class="analytics-period-row header"><div>{{ __('Metric') }}</div><div>{{ __('Current') }}</div><div>{{ __('Previous') }}</div><div>{{ __('Delta') }}</div></div>
                    @foreach ($periodCompareRows as $row)
                        <div class="analytics-period-row">
                            <div><div class="analytics-period-label">{{ $row['label'] }}</div><div class="analytics-period-help">{{ $row['help'] }}</div></div>
                            <div>{{ $row['current'] }}</div>
                            <div>{{ $row['previous'] }}</div>
                            <div class="analytics-period-delta">{{ $row['delta'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="analytics-panel">
                <div class="analytics-section-head"><div><div class="analytics-title">{{ __('Funnel health') }}</div><div class="analytics-subtitle">{{ __('View → cart → checkout → purchase, with direct leakage context.') }}</div></div></div>
                @foreach ($funnel as $step)
                    <div class="analytics-funnel-step">
                        <div class="d-flex justify-content-between align-items-center gap-2"><strong>{{ $step['label'] }}</strong><span>{{ number_format((int) $step['count']) }}</span></div>
                        <div class="small text-muted mt-1">{{ __('From previous: :rate', ['rate' => number_format(((float) ($step['conversion_from_previous'] ?? 0)) * 100, 1) . '%']) }} · {{ __('Drop-off: :rate', ['rate' => number_format(((float) ($step['drop_off_from_previous'] ?? 0)) * 100, 1) . '%']) }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="analytics-panel" id="analytics-panel-drilldowns" role="tabpanel" aria-labelledby="analytics-tab-drilldowns" data-admin-section-panel="drilldowns">
            <div class="analytics-section-head">
                <div><div class="analytics-title">{{ __('Commercial drilldowns') }}</div><div class="analytics-subtitle">{{ __('Open the entities carrying revenue instead of scanning more summary cards.') }}</div></div>
                <div class="analytics-chip-row">
                    <span class="analytics-chip">{{ __('Products') }}: {{ number_format($topProducts->count()) }}</span>
                    <span class="analytics-chip">{{ __('Categories') }}: {{ number_format($topCategories->count()) }}</span>
                    <span class="analytics-chip">{{ __('Coupons') }}: {{ number_format($couponPerformance->count()) }}</span>
                </div>
            </div>
            <div class="analytics-leader-grid">
                <div class="analytics-detail-card">
                    <div class="analytics-title">{{ __('Top products leaderboard') }}</div>
                    <div class="analytics-subtitle">{{ __('Click a product to continue into its focused performance view.') }}</div>
                    <div class="analytics-list mt-3">
                        @forelse ($topProducts as $row)
                            <div class="analytics-row">
                                <div class="flex-fill">
                                    <div class="fw-bold"><a href="{{ route('admin.analytics.products.show', ['product' => $row->product_id, 'range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]) }}" class="text-decoration-none">{{ $row->product_name ?? __('Product #:id', ['id' => $row->product_id]) }}</a></div>
                                    <div class="text-muted small">{{ number_format((int) data_get($row, 'purchases', 0)) }} {{ __('purchases') }} · {{ __('Qty') }} {{ number_format((int) data_get($row, 'purchased_quantity', 0)) }}</div>
                                    <div class="analytics-bar-track mt-2"><div class="analytics-bar-fill" style="width: {{ min(100, (((float) data_get($row, 'revenue_gross', 0)) / $topProductRevenue) * 100) }}%"></div></div>
                                </div>
                                <div class="text-end fw-bold">EGP {{ number_format((float) data_get($row, 'revenue_gross', 0), 2) }}</div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No product performance data is available for this range yet.') }}</div>
                        @endforelse
                    </div>
                </div>
                <div class="analytics-detail-card">
                    <div class="analytics-title">{{ __('Category and coupon mix') }}</div>
                    <div class="analytics-subtitle">{{ __('A compact concentration view for merchandising and discount quality.') }}</div>
                    <div class="analytics-list mt-3">
                        @forelse ($topCategories->take(4) as $row)
                            <div class="analytics-row">
                                <div class="flex-fill">
                                    <div class="fw-bold">{{ $row->category_name ?? __('Uncategorized') }}</div>
                                    <div class="analytics-bar-track mt-2"><div class="analytics-bar-fill soft" style="width: {{ min(100, (((float) data_get($row, 'revenue_gross', 0)) / $topCategoryRevenue) * 100) }}%"></div></div>
                                </div>
                                <div class="text-end fw-bold">EGP {{ number_format((float) data_get($row, 'revenue_gross', 0), 2) }}</div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No category mix data is available yet.') }}</div>
                        @endforelse
                    </div>
                    <hr>
                    <div class="analytics-list">
                        @forelse ($couponPerformance->take(4) as $row)
                            <div class="analytics-row">
                                <div><div class="fw-bold">{{ data_get($row, 'coupon_code', __('No coupon')) }}</div><div class="text-muted small">{{ number_format((int) data_get($row, 'orders_count', 0)) }} {{ __('orders') }}</div></div>
                                <div class="text-end fw-bold">EGP {{ number_format((float) data_get($row, 'revenue_gross', 0), 2) }}</div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No coupon performance records for this range.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <details class="analytics-more" data-admin-section-panel="trends">
            <summary>{{ __('More diagnostics') }}</summary>
            <div class="analytics-more-body">
                <div class="analytics-subtitle mb-2">{{ __('Daily revenue, orders, conversion, and AOV are available here when a deeper operational check is needed.') }}</div>
                @forelse ($dailyChartRows as $chartRow)
                    <div class="analytics-daily-row">
                        <div><div class="fw-bold">{{ $chartRow['label'] }}</div><div class="small text-muted">{{ __('CVR') }} {{ number_format($chartRow['conversion_rate'] * 100, 1) }}%</div></div>
                        <div class="analytics-daily-bars">
                            <div class="analytics-bar-track"><div class="analytics-bar-fill" style="width: {{ max(4, $chartRow['revenue_width']) }}%"></div></div>
                            <div class="analytics-bar-track"><div class="analytics-bar-fill orders" style="width: {{ max(4, $chartRow['orders_width']) }}%"></div></div>
                            <div class="analytics-bar-track"><div class="analytics-bar-fill aov" style="width: {{ max(4, $chartRow['aov_width']) }}%"></div></div>
                        </div>
                        <div class="analytics-daily-meta"><div class="fw-bold">EGP {{ number_format($chartRow['revenue'], 2) }}</div><div class="small text-muted">{{ number_format($chartRow['orders_count']) }} {{ __('orders') }} · {{ __('AOV') }} {{ number_format($chartRow['aov'], 2) }}</div></div>
                    </div>
                @empty
                    <div class="text-muted">{{ __('No analytics data yet for this period.') }}</div>
                @endforelse
            </div>
        </details>

        @if ($topSessions->isNotEmpty() && ($uiState['show_watchlist'] ?? false))
            <div class="analytics-panel" data-admin-section-panel="drilldowns">
                <div class="analytics-section-head"><div><div class="analytics-title">{{ __('Session watchlist') }}</div><div class="analytics-subtitle">{{ __('High-value sessions for focused retention review.') }}</div></div></div>
                <div class="analytics-table-wrap">
                    <table class="analytics-table">
                        <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Orders') }}</th><th>{{ __('Revenue') }}</th><th>{{ __('Last order') }}</th></tr></thead>
                        <tbody>
                            @foreach ($topSessions as $session)
                                <tr><td>{{ data_get($session, 'customer_name', __('Guest')) }}</td><td>{{ number_format((int) data_get($session, 'orders_count', 0)) }}</td><td>EGP {{ number_format((float) data_get($session, 'revenue_gross', 0), 2) }}</td><td>{{ data_get($session, 'last_order_at', '—') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
    </div>
</div>

@push('scripts')
    <script defer src="{{ asset('admin/js/live-list.js') }}"></script>
@endpush
@endsection
