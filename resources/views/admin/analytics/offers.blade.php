@extends('layouts.admin')

@section('title', __('Offers Drilldown'))

@php
    $couponRows = collect(data_get($drilldown, 'coupon_rows', collect()))->values();
    $discountedOrders = data_get($drilldown, 'discounted_orders');
    $activePromotions = collect(data_get($drilldown, 'active_promotions', collect()))->values();

    $discountedOrdersCount = (int) data_get($discountedOrders, 'orders_count', 0);
    $discountTotal = (float) data_get($discountedOrders, 'discount_total', 0);
    $discountRevenue = (float) data_get($discountedOrders, 'revenue_gross', 0);
    $effectiveDiscountRate = $discountRevenue > 0 ? ($discountTotal / $discountRevenue) : 0;
    $bestCoupon = $couponRows->sortByDesc('revenue_gross')->first();
    $heaviestDiscountCoupon = $couponRows->sortByDesc('discount_total')->first();
    $couponMaxRevenue = max(1, (float) $couponRows->max('revenue_gross'));
    $couponMaxOrders = max(1, (int) $couponRows->max('orders_count'));
    $unusedPromotions = $activePromotions->where('is_active', true)->count();
    $couponChartRows = $couponRows->take(6)->values();
    $topDiscountedProduct = data_get($drilldown, 'top_discounted_product');

    $analyticsFlowTitle = __('Cross-page flow');
    $analyticsFlowSubtitle = __('Move between the executive board, core revenue view, and product detail without dropping the same reporting range.');
    $analyticsFlowItems = [
        [
            'label' => __('Dashboard'),
            'description' => __('Return to the executive board when you need the fastest operational summary and action queue.'),
            'meta' => __('Executive summary'),
            'icon' => 'mdi-view-dashboard-outline',
            'url' => route('admin.dashboard'),
        ],
        [
            'label' => __('Revenue Intelligence'),
            'description' => __('Go back to the overview when you want the broader picture before judging offer quality.'),
            'meta' => __('Compare periods and leakage'),
            'icon' => 'mdi-chart-areaspline',
            'url' => route('admin.analytics.index', array_filter(['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])),
        ],
        [
            'label' => __('Offers Drilldown'),
            'description' => __('Stay here to inspect discount pressure, coupon revenue, and promotion efficiency.'),
            'meta' => __('You are here'),
            'icon' => 'mdi-ticket-percent-outline',
            'url' => route('admin.analytics.offers', array_filter(['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])),
            'active' => true,
        ],
        [
            'label' => __('Product Drilldown'),
            'description' => __('Open the most discount-influenced product next when you want to see whether the offer is lifting or masking product demand.'),
            'meta' => $topDiscountedProduct
                ? __('Top discounted product: :name', ['name' => $topDiscountedProduct->product_name ?? __('Product #:id', ['id' => $topDiscountedProduct->product_id])])
                : __('Product flow opens here once discounted order mix is available.'),
            'icon' => 'mdi-cube-outline',
            'url' => $topDiscountedProduct
                ? route('admin.analytics.products.show', array_filter(['product' => $topDiscountedProduct->product_id, 'range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]))
                : null,
        ],
    ];

    $promoNarrative = $effectiveDiscountRate <= 0.12
        ? __('Discounts are creating support for growth without putting major pressure on realized revenue quality.')
        : __('Discount pressure is elevated, so the team should verify whether offers are buying demand efficiently or compressing value.') ;

    $promoLaneItems = [
        ['label' => __('Scale'), 'value' => $bestCoupon ? __('Lean into :code while it is converting cleanly.', ['code' => $bestCoupon->coupon_code]) : __('No scale signal yet.')],
        ['label' => __('Protect'), 'value' => __('Keep effective discount rate near a disciplined operating range.')],
        ['label' => __('Refine'), 'value' => $heaviestDiscountCoupon ? __('Review :code for margin drag or over-discounting.', ['code' => $heaviestDiscountCoupon->coupon_code]) : __('No refinement signal yet.')],
    ];

    $exportRows = [
        ['label' => __('Discounted orders'), 'value' => number_format($discountedOrdersCount), 'context' => __('Orders influenced by pricing incentives in this window.')],
        ['label' => __('Discount value'), 'value' => 'EGP ' . number_format($discountTotal, 2), 'context' => __('Total promotional cost absorbed across discounted orders.')],
        ['label' => __('Discounted revenue'), 'value' => 'EGP ' . number_format($discountRevenue, 2), 'context' => __('Revenue created while a discount was present.')],
        ['label' => __('Effective discount rate'), 'value' => number_format($effectiveDiscountRate * 100, 1) . '%', 'context' => __('Discount total divided by discounted-order revenue.')],
        ['label' => __('Top revenue coupon'), 'value' => $bestCoupon?->coupon_code ?? '—', 'context' => $bestCoupon ? ('EGP ' . number_format((float) $bestCoupon->revenue_gross, 2)) : __('No coupon leader yet')],
        ['label' => __('Active promotions'), 'value' => number_format($activePromotions->count()), 'context' => __('Configured live promotion rules.')],
    ];
    $offerOperatorReads = [
        [
            'label' => __('Best lever'),
            'value' => $bestCoupon ? $bestCoupon->coupon_code : __('No coupon leader yet'),
            'help' => $bestCoupon ? __('Highest revenue coupon in the selected range.') : __('No clean scale signal is visible yet.'),
        ],
        [
            'label' => __('Biggest risk'),
            'value' => $heaviestDiscountCoupon ? $heaviestDiscountCoupon->coupon_code : __('No discount burden yet'),
            'help' => $heaviestDiscountCoupon ? __('Highest discount load across tracked coupons.') : __('No coupon is creating heavy pressure right now.'),
        ],
        [
            'label' => __('Next action'),
            'value' => $effectiveDiscountRate <= 0.12 ? __('Scale the leading coupon carefully') : __('Review discount pressure before scaling'),
            'help' => __('Use this as the first operator move from the current offer mix.'),
        ],
    ];
@endphp


@section('content')
<style>
.analytics-shell{display:grid;gap:20px}.analytics-card,.analytics-mini,.offers-hero,.offers-trend-card,.offers-chart-card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:22px;box-shadow:0 18px 45px rgba(15,23,42,.06)}.analytics-card .card-body,.offers-hero,.offers-trend-card,.offers-chart-card{padding:22px}.analytics-table{width:100%;border-collapse:collapse}.analytics-table th,.analytics-table td{padding:12px 10px;border-bottom:1px solid rgba(15,23,42,.06)}.analytics-table th{font-size:.84rem;color:#64748b;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}.analytics-mini{padding:16px;border-radius:18px;background:#f8fafc;border:1px solid rgba(15,23,42,.05);height:100%}.offers-hero{background:linear-gradient(135deg,#fff7ed,#ffffff);display:grid;gap:16px}.offers-hero-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}.offers-hero-item{padding:12px 14px;border-radius:16px;background:rgba(255,255,255,.85);border:1px solid rgba(249,115,22,.12)}.offers-anchor-nav{display:flex;gap:10px;flex-wrap:wrap}.offers-anchor{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid rgba(15,23,42,.08);font-weight:700;color:#334155;text-decoration:none}.offers-trend-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.offers-kicker{font-size:.84rem;color:#64748b;font-weight:700}.offers-value{font-size:1.55rem;font-weight:800;color:#0f172a;margin-top:6px}.offers-help{font-size:.82rem;color:#64748b;margin-top:6px}.offers-list{display:grid;gap:14px}.offers-row{display:flex;justify-content:space-between;gap:14px;padding:12px 0;border-bottom:1px solid rgba(15,23,42,.06)}.offers-row:last-child{border-bottom:none}.offers-bar{height:10px;border-radius:999px;background:#eef2f7;overflow:hidden;margin-top:8px}.offers-bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#f97316,#fb923c)}.offers-storyboard{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}.offers-story-card{padding:20px;border-radius:22px;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;border:1px solid rgba(15,23,42,.08);box-shadow:0 20px 45px rgba(15,23,42,.16)}.offers-story-card .eyebrow{font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7)}.offers-story-card .headline{font-size:1.55rem;font-weight:800;margin-top:8px;line-height:1.35}.offers-story-card .subcopy{font-size:.92rem;line-height:1.8;color:rgba(255,255,255,.82);margin-top:10px}.offers-story-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}.offers-story-tile{padding:14px;border-radius:16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08)}.offers-lane-card{padding:20px;border-radius:22px;border:1px solid rgba(15,23,42,.06);background:#fff;box-shadow:0 18px 45px rgba(15,23,42,.05)}.offers-lane-list{display:grid;gap:12px}.offers-lane-item{padding:14px 16px;border-radius:16px;background:#f8fafc;border:1px solid rgba(15,23,42,.05)}.offers-lane-item .lane-label{font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;color:#64748b}.offers-lane-item .lane-value{font-size:1rem;font-weight:800;color:#0f172a;margin-top:5px}.offers-spark-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.offers-spark-card{padding:16px;border-radius:20px;background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:0 16px 35px rgba(15,23,42,.05)}.offers-chart-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:16px;align-items:start}.offers-chart-legend{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px}.offers-chart-legend span{display:inline-flex;align-items:center;gap:8px;color:#64748b;font-size:.83rem}.offers-chart-legend i{display:inline-block;width:12px;height:12px;border-radius:999px}.offers-chart-legend .rev{background:#f97316}.offers-chart-legend .ord{background:#94a3b8}.offers-svg-wrap{position:relative;width:100%;overflow-x:auto;overflow-y:hidden}.offers-svg-frame{min-width:520px;width:100%}.offers-svg{width:100%;height:220px;display:block}.offers-chart-grid>*{min-width:0}.offers-gridline{stroke:rgba(148,163,184,.25);stroke-width:1}.offers-axis{display:flex;justify-content:space-between;gap:8px;margin-top:8px;color:#64748b;font-size:.78rem;overflow-x:auto;padding-bottom:2px}.offers-axis span{flex:0 0 auto;min-width:60px;text-align:center}.offers-detail-stack{display:grid;gap:12px}@media (max-width: 1400px){.offers-chart-grid{grid-template-columns:1fr}.offers-detail-stack{grid-template-columns:repeat(2,minmax(0,1fr));display:grid}}@media (max-width: 1200px){.offers-trend-grid,.offers-spark-grid,.offers-story-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.offers-storyboard,.offers-chart-grid,.offers-detail-stack{grid-template-columns:1fr}}@media (max-width: 768px){.offers-trend-grid,.offers-spark-grid,.offers-story-grid{grid-template-columns:1fr}}
</style>
<x-admin.page-header
    :kicker="__('Revenue intelligence')"
    :title="__('Offers drilldown')"
    :description="__('Review coupon performance and active promotions for the selected reporting window.')"
    :breadcrumbs="[
        ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('Revenue Intelligence'), 'url' => route('admin.analytics.index', ['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])],
        ['label' => __('Offers drilldown'), 'current' => true],
    ]"
>
    <span class="admin-topbar-chip">{{ __('Selected range') }}: {{ $from->format('Y-m-d') }} → {{ $to->format('Y-m-d') }}</span>
    <a href="{{ route('admin.analytics.index', ['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]) }}" class="btn btn-outline-dark">{{ __('Back to dashboard') }}</a>
</x-admin.page-header>

@include('admin.analytics._nav')
@include('admin.analytics._trust_panel', ['trust' => $trust ?? [], 'uiState' => $uiState ?? []])

@include('admin.analytics._report_toolbar', [
    'title' => __('Offers drilldown'),
    'subtitle' => __('Promotion efficiency wording is now cleaner for admins, with a concise export summary and print-safe review block.'),
    'period' => $from->format('Y-m-d') . ' → ' . $to->format('Y-m-d'),
    'reportId' => 'offers-report',
    'exportRows' => $exportRows,
])

<div class="analytics-shell">
    <div class="offers-anchor-nav">
        <a href="#offers-chart-suite" class="offers-anchor">{{ __('Chart suite') }}</a>
        <a href="#offers-leaderboard" class="offers-anchor">{{ __('Leaderboard') }}</a>
        <a href="#offers-posture" class="offers-anchor">{{ __('Promotion posture') }}</a>
    </div>

    <div class="offers-hero">
        <div>
            <div class="fw-bold fs-5">{{ __('Offer performance summary') }}</div>
            <div class="text-muted small mt-1">{{ __('Understand whether discounts are creating efficient revenue or simply increasing promotional pressure.') }}</div>
        </div>
        <ul class="offers-hero-list">
            <li class="offers-hero-item">{{ $bestCoupon ? __('Top revenue coupon is :code, generating :revenue across :orders orders.', ['code' => $bestCoupon->coupon_code, 'revenue' => 'EGP ' . number_format((float) $bestCoupon->revenue_gross, 2), 'orders' => number_format((int) $bestCoupon->orders_count)]) : __('No coupon-driven revenue has been recorded in this range yet.') }}</li>
            <li class="offers-hero-item">{{ $heaviestDiscountCoupon ? __('Highest discount burden is currently on :code with :value discounted.', ['code' => $heaviestDiscountCoupon->coupon_code, 'value' => 'EGP ' . number_format((float) $heaviestDiscountCoupon->discount_total, 2)]) : __('No discount burden insight is available yet.') }}</li>
            <li class="offers-hero-item">{{ __('Effective discount rate across discounted orders is :rate, while :count active promotions are currently configured.', ['rate' => number_format($effectiveDiscountRate * 100, 1) . '%', 'count' => number_format($unusedPromotions)]) }}</li>
        </ul>
    </div>

    <div class="offers-storyboard">
        <div class="offers-story-card">
            <div class="eyebrow">{{ __('Offer storytelling') }}</div>
            <div class="headline">{{ __('Promotions are currently :mode across :period.', ['mode' => $effectiveDiscountRate <= 0.12 ? __('supporting margin-aware growth') : __('putting pressure on margin quality'), 'period' => $from->format('Y-m-d') . ' → ' . $to->format('Y-m-d')]) }}</div>
            <div class="subcopy">{{ $promoNarrative }}</div>
            <div class="offers-story-grid">
                <div class="offers-story-tile"><div class="small text-white-50">{{ __('Discounted orders') }}</div><div class="fw-bold fs-5 mt-1">{{ number_format($discountedOrdersCount) }}</div></div>
                <div class="offers-story-tile"><div class="small text-white-50">{{ __('Effective discount rate') }}</div><div class="fw-bold fs-5 mt-1">{{ number_format($effectiveDiscountRate * 100, 1) }}%</div></div>
                <div class="offers-story-tile"><div class="small text-white-50">{{ __('Tracked coupons') }}</div><div class="fw-bold fs-5 mt-1">{{ number_format($couponRows->count()) }}</div></div>
            </div>
        </div>
        <div class="offers-lane-card">
            <div class="fw-bold fs-5">{{ __('Offer operating lanes') }}</div>
            <div class="text-muted small mt-1 mb-3">{{ __('A fast SaaS-style read on where to scale, protect, and refine promotional activity.') }}</div>
            <div class="offers-lane-list">
                @foreach ($promoLaneItems as $item)
                    <div class="offers-lane-item">
                        <div class="lane-label">{{ $item['label'] }}</div>
                        <div class="lane-value">{{ $item['value'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="offers-spark-grid" id="offers-operator-summary">
        @foreach ($offerOperatorReads as $item)
            <div class="offers-spark-card">
                <div class="small text-muted">{{ $item['label'] }}</div>
                <div class="fw-bold fs-5 mt-1">{{ $item['value'] }}</div>
                <div class="text-muted small mt-2">{{ $item['help'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="offers-spark-grid">
        <div class="offers-spark-card"><div class="small text-muted">{{ __('Top revenue coupon') }}</div><div class="fw-bold fs-5 mt-1">{{ $bestCoupon?->coupon_code ?? '—' }}</div></div>
        <div class="offers-spark-card"><div class="small text-muted">{{ __('Highest discount burden') }}</div><div class="fw-bold fs-5 mt-1">{{ $heaviestDiscountCoupon?->coupon_code ?? '—' }}</div></div>
        <div class="offers-spark-card"><div class="small text-muted">{{ __('Active promotions') }}</div><div class="fw-bold fs-5 mt-1">{{ number_format($activePromotions->count()) }}</div></div>
    </div>

    <div class="offers-trend-grid">
        <div class="offers-trend-card"><div class="offers-kicker">{{ __('Discounted orders') }}</div><div class="offers-value">{{ number_format($discountedOrdersCount) }}</div><div class="offers-help">{{ __('Orders influenced by coupon or discount logic in this window.') }}</div></div>
        <div class="offers-trend-card"><div class="offers-kicker">{{ __('Discount value') }}</div><div class="offers-value">EGP {{ number_format($discountTotal, 2) }}</div><div class="offers-help">{{ __('Total promotional cost absorbed across discounted orders.') }}</div></div>
        <div class="offers-trend-card"><div class="offers-kicker">{{ __('Discounted revenue') }}</div><div class="offers-value">EGP {{ number_format($discountRevenue, 2) }}</div><div class="offers-help">{{ __('Revenue created while a discount was present.') }}</div></div>
        <div class="offers-trend-card"><div class="offers-kicker">{{ __('Effective discount rate') }}</div><div class="offers-value">{{ number_format($effectiveDiscountRate * 100, 1) }}%</div><div class="offers-help">{{ __('Discount total divided by discounted-order revenue.') }}</div></div>
    </div>

    <div class="offers-chart-grid" id="offers-chart-suite">
        <div class="offers-chart-card">
            <div class="fw-bold fs-5">{{ __('Coupon chart suite') }}</div>
            <div class="text-muted small mt-1 mb-3">{{ __('A real visual read for the top coupon set by revenue and order pace.') }}</div>
            <div class="offers-chart-legend">
                <span><i class="rev"></i>{{ __('Revenue') }}</span>
                <span><i class="ord"></i>{{ __('Orders') }}</span>
            </div>
            <div class="offers-svg-wrap"><div class="offers-svg-frame"><svg viewBox="0 0 520 220" class="offers-svg" role="img" aria-label="{{ __('Coupon revenue chart') }}">
                <line x1="0" y1="40" x2="520" y2="40" class="offers-gridline"></line>
                <line x1="0" y1="110" x2="520" y2="110" class="offers-gridline"></line>
                <line x1="0" y1="180" x2="520" y2="180" class="offers-gridline"></line>
                @foreach ($couponChartRows as $index => $row)
                    @php
                        $x = 25 + ($index * 82);
                        $revenueHeight = min(140, (((float) $row->revenue_gross) / $couponMaxRevenue) * 140);
                        $ordersHeight = min(140, (((int) $row->orders_count) / $couponMaxOrders) * 140);
                    @endphp
                    <rect x="{{ $x }}" y="{{ 180 - $revenueHeight }}" width="28" height="{{ $revenueHeight }}" rx="8" fill="#f97316"></rect>
                    <rect x="{{ $x + 34 }}" y="{{ 180 - $ordersHeight }}" width="18" height="{{ $ordersHeight }}" rx="8" fill="#94a3b8"></rect>
                @endforeach
            </svg></div></div>
            <div class="offers-axis">
                @forelse ($couponChartRows as $row)
                    <span>{{ \Illuminate\Support\Str::limit($row->coupon_code, 8, '') }}</span>
                @empty
                    <span>{{ __('No coupon labels yet') }}</span>
                @endforelse
            </div>
        </div>
        <div class="offers-detail-stack">
            <div class="offers-chart-card">
                <div class="fw-bold fs-5">{{ __('Drilldown guidance') }}</div>
                <div class="text-muted small mt-1">{{ __('Use this hierarchy to move from coupon pressure to operational action.') }}</div>
                <div class="offers-lane-list mt-3">
                    <div class="offers-lane-item"><div class="lane-label">{{ __('If revenue is strong') }}</div><div class="lane-value">{{ __('Scale the best coupon carefully') }}</div></div>
                    <div class="offers-lane-item"><div class="lane-label">{{ __('If discount burden is strong') }}</div><div class="lane-value">{{ __('Review promotion settings and margin quality') }}</div></div>
                    <div class="offers-lane-item"><div class="lane-label">{{ __('If both are weak') }}</div><div class="lane-value">{{ __('Rethink offer fit and campaign placement') }}</div></div>
                </div>
            </div>
            <div class="offers-chart-card">
                <div class="fw-bold fs-5">{{ __('Top signals') }}</div>
                <div class="text-muted small mt-1">{{ __('Fast reads from the current coupon mix.') }}</div>
                <div class="offers-list mt-3">
                    @forelse ($couponRows->take(3) as $row)
                        <div class="offers-row">
                            <div>
                                <div class="fw-bold">{{ $row->coupon_code }}</div>
                                <div class="text-muted small">{{ number_format((int) $row->orders_count) }} {{ __('orders') }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">EGP {{ number_format((float) $row->revenue_gross, 2) }}</div>
                                <div class="text-muted small">{{ __('Discount') }} EGP {{ number_format((float) $row->discount_total, 2) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No coupon analytics yet for this period.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3" id="offers-leaderboard">
        <div class="col-lg-6">
            <div class="analytics-card h-100">
                <div class="card-body">
                    <h4 class="mb-1">{{ __('Coupon leaderboard') }}</h4>
                    <div class="text-muted small">{{ __('Fast ranking for the coupons carrying the most commercial weight right now.') }}</div>
                    <div class="text-muted small mb-3">{{ __('A visual view of which coupon codes are carrying commercial weight.') }}</div>
                    <div class="offers-list">
                        @forelse ($couponRows as $row)
                            <div class="offers-row">
                                <div class="flex-fill">
                                    <div class="fw-bold">{{ $row->coupon_code }}</div>
                                    <div class="text-muted small">{{ number_format((int) $row->orders_count) }} {{ __('orders') }} · {{ __('AOV') }} EGP {{ number_format((float) $row->average_order_value, 2) }}</div>
                                    <div class="offers-bar"><div class="offers-bar-fill" style="width: {{ min(100, ((float) $row->revenue_gross / $couponMaxRevenue) * 100) }}%"></div></div>
                                </div>
                                <div class="text-end"><div class="fw-bold">EGP {{ number_format((float) $row->revenue_gross, 2) }}</div><div class="text-muted small">{{ __('Discount') }}: EGP {{ number_format((float) $row->discount_total, 2) }}</div></div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No coupon analytics yet for this period.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6" id="offers-posture">
            <div class="analytics-card h-100">
                <div class="card-body">
                    <h4 class="mb-1">{{ __('Promotion posture') }}</h4>
                    <div class="text-muted small">{{ __('Use this block to review live rule pressure before widening promotion exposure.') }}</div>
                    <div class="text-muted small mb-3">{{ __('A fast view of active promotion rules and current promotional inventory.') }}</div>
                    @if (!($uiState['show_promotions'] ?? false))
                        <div class="alert alert-light border mb-3">{{ __('No active promotions are visible right now, so this section focuses on tracked coupon coverage only.') }}</div>
                    @endif
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6"><div class="analytics-mini"><div class="small text-muted">{{ __('Tracked coupons') }}</div><div class="fw-bold fs-4">{{ number_format($couponRows->count()) }}</div></div></div>
                        <div class="col-sm-6"><div class="analytics-mini"><div class="small text-muted">{{ __('Active promotions') }}</div><div class="fw-bold fs-4">{{ number_format($activePromotions->count()) }}</div></div></div>
                    </div>
                    <div class="offers-list">
                        @forelse ($activePromotions as $promotion)
                            <div class="offers-row">
                                <div><div class="fw-bold">{{ $promotion->name }}</div><div class="text-muted small">{{ __('Type') }}: {{ $promotion->type }} · {{ __('Priority') }} #{{ $promotion->priority }}</div></div>
                                <div class="text-end"><div class="fw-bold">{{ number_format((float) $promotion->discount_value, 2) }}</div><div class="text-muted small">{{ $promotion->is_active ? __('Active') : __('Inactive') }}</div></div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No active promotions configured yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="analytics-card">
        <div class="card-body">
            <h4 class="mb-3">{{ __('Coupon performance table') }}</h4>
            <div class="table-responsive">
                <table class="analytics-table">
                    <thead>
                        <tr><th>{{ __('Coupon') }}</th><th>{{ __('Orders') }}</th><th>{{ __('Revenue') }}</th><th>{{ __('Discount') }}</th><th>{{ __('AOV') }}</th><th>{{ __('Status') }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($couponRows as $row)
                            <tr>
                                <td><div class="fw-bold">{{ $row->coupon_code }}</div><div class="text-muted small">{{ __('Used') }}: {{ $row->used_count === null ? '—' : number_format((int) $row->used_count) }}</div></td>
                                <td>{{ number_format((int) $row->orders_count) }}</td>
                                <td>EGP {{ number_format((float) $row->revenue_gross, 2) }}</td>
                                <td>EGP {{ number_format((float) $row->discount_total, 2) }}</td>
                                <td>EGP {{ number_format((float) $row->average_order_value, 2) }}</td>
                                <td>
                                    @if ($row->is_active)
                                        <span class="badge bg-success-subtle text-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">{{ __('Inactive') }}</span>
                                    @endif
                                    @if ($row->remaining_usage !== null)
                                        <div class="text-muted small mt-1">{{ __('Remaining') }}: {{ number_format((int) $row->remaining_usage) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">{{ __('No coupon performance records for this range.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
