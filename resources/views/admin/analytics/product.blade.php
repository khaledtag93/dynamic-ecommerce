@extends('layouts.admin')

@section('title', __('Product Drilldown'))

@php
    $totals = $drilldown['totals'] ?? [];
    $dailyRows = collect($drilldown['daily'] ?? [])->values();
    $topVariants = collect($drilldown['top_variants'] ?? [])->values();

    $maxViews = max(1, (int) $dailyRows->max('views'));
    $maxRevenue = max(1, (float) $dailyRows->max('revenue_gross'));
    $maxPurchases = max(1, (int) $dailyRows->max('purchases'));
    $maxVariantRevenue = max(1, (float) $topVariants->max('revenue_gross'));

    $chartRows = $dailyRows->map(function ($row) use ($maxViews, $maxRevenue, $maxPurchases) {
        $dateValue = $row->stat_date instanceof \Carbon\CarbonInterface ? $row->stat_date : \Illuminate\Support\Carbon::parse((string) $row->stat_date);
        return [
            'label' => $dateValue->format('m-d'),
            'full_label' => $dateValue->toDateString(),
            'views' => (int) $row->views,
            'adds' => (int) $row->add_to_cart_count,
            'purchases' => (int) $row->purchases,
            'quantity' => (int) $row->purchased_quantity,
            'revenue' => (float) $row->revenue_gross,
            'view_width' => min(100, (((int) $row->views) / $maxViews) * 100),
            'purchase_width' => min(100, (((int) $row->purchases) / $maxPurchases) * 100),
            'revenue_width' => min(100, (((float) $row->revenue_gross) / $maxRevenue) * 100),
        ];
    });


    $chartLabelStep = $chartRows->count() > 60 ? 7 : ($chartRows->count() > 30 ? 5 : ($chartRows->count() > 14 ? 3 : 1));
    $axisLabels = $chartRows->values()->filter(function ($row, $index) use ($chartLabelStep, $chartRows) {
        return $index === 0 || $index === ($chartRows->count() - 1) || ($index % $chartLabelStep) === 0;
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
        return ['line' => $points, 'area' => $points . ' ' . $lastX . ',' . $height . ' ' . $firstX . ',' . $height];
    };

    $viewsSvg = $buildSvgPath($chartRows, 'views', $maxViews);
    $revenueSvg = $buildSvgPath($chartRows, 'revenue', $maxRevenue);
    $purchasesSvg = $buildSvgPath($chartRows, 'purchases', $maxPurchases);

    $bestRevenueDay = $chartRows->sortByDesc('revenue')->first();
    $bestViewsDay = $chartRows->sortByDesc('views')->first();
    $bestPurchaseDay = $chartRows->sortByDesc('purchases')->first();

    $productOperatorReads = [
        [
            'label' => __('Best signal'),
            'value' => data_get($bestRevenueDay, 'full_label', __('No signal yet')),
            'help' => __('Strongest revenue day in the selected window.'),
        ],
        [
            'label' => __('Primary pressure'),
            'value' => ((float) ($totals['views'] ?? 0)) > 0 && ((float) ($totals['conversion_rate'] ?? 0)) < 0.02 ? __('Weak purchase conversion') : __('No major pressure yet'),
            'help' => __('Read this first when product traffic is present but monetization is soft.'),
        ],
        [
            'label' => __('Next action'),
            'value' => ((float) ($totals['cart_to_purchase_rate'] ?? 0)) < 0.35 ? __('Review checkout friction or stock clarity') : __('Scale what is already converting cleanly'),
            'help' => __('First operator move from the current product read.'),
        ],
    ];

    $analyticsFlowTitle = __('Cross-page flow');
    $analyticsFlowSubtitle = __('Use the same reporting range to move from this product into the broader revenue picture or offer context.');
    $analyticsFlowItems = [
        [
            'label' => __('Dashboard'),
            'description' => __('Return to the executive board when you need the fastest operating picture and next actions.'),
            'meta' => __('Executive summary'),
            'icon' => 'mdi-view-dashboard-outline',
            'url' => route('admin.dashboard'),
        ],
        [
            'label' => __('Revenue Intelligence'),
            'description' => __('Step back into the overview to compare this product against the wider business trend and funnel.'),
            'meta' => __('Compare against the whole store'),
            'icon' => 'mdi-chart-areaspline',
            'url' => route('admin.analytics.index', array_filter(['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])),
        ],
        [
            'label' => __('Offers Drilldown'),
            'description' => __('Open offer performance next when revenue is present but value quality may be shaped by discounts.'),
            'meta' => __('Check offer pressure'),
            'icon' => 'mdi-ticket-percent-outline',
            'url' => route('admin.analytics.offers', array_filter(['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])),
        ],
        [
            'label' => __('Product Drilldown'),
            'description' => __('Stay on this product to inspect discovery, conversion, and variant mix in one place.'),
            'meta' => __('You are here'),
            'icon' => 'mdi-cube-outline',
            'url' => route('admin.analytics.products.show', array_filter(['product' => $product->id, 'range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])),
            'active' => true,
        ],
    ];

    $exportRows = [
        ['label' => __('Product views'), 'value' => number_format((int) ($totals['views'] ?? 0)), 'context' => __('Discovery volume inside the selected window.')],
        ['label' => __('Add-to-cart actions'), 'value' => number_format((int) ($totals['add_to_cart_count'] ?? 0)), 'context' => __('Intent captured before purchase.')],
        ['label' => __('Purchased orders'), 'value' => number_format((int) ($totals['purchases'] ?? 0)), 'context' => __('Completed purchases for this product.')],
        ['label' => __('Gross product revenue'), 'value' => 'EGP ' . number_format((float) ($totals['revenue_gross'] ?? 0), 2), 'context' => __('Revenue attributed to this product.')],
        ['label' => __('View-to-purchase rate'), 'value' => number_format(((float) ($totals['conversion_rate'] ?? 0)) * 100, 1) . '%', 'context' => __('End-to-end product conversion.')],
        ['label' => __('Average revenue per purchase'), 'value' => 'EGP ' . number_format((float) ($totals['average_revenue_per_purchase'] ?? 0), 2), 'context' => __('Revenue depth after conversion.')],
    ];
@endphp

@section('content')
<style>
.analytics-shell{display:grid;gap:20px}.analytics-card,.analytics-mini,.analytics-chart-card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:22px;box-shadow:0 18px 45px rgba(15,23,42,.06)}.analytics-card .card-body,.analytics-chart-card{padding:22px}.analytics-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.analytics-mini-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.analytics-mini{padding:16px;border-radius:18px;background:#f8fafc;border:1px solid rgba(15,23,42,.05);height:100%}.analytics-anchor-nav{display:flex;gap:10px;flex-wrap:wrap}.analytics-anchor{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid rgba(15,23,42,.08);font-weight:700;color:#334155;text-decoration:none}.analytics-table{width:100%;border-collapse:collapse}.analytics-table th,.analytics-table td{padding:12px 10px;border-bottom:1px solid rgba(15,23,42,.06)}.analytics-table th{font-size:.84rem;color:#64748b;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}.analytics-chart-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:16px;align-items:start}.analytics-svg-wrap{position:relative;width:100%;overflow-x:auto;overflow-y:hidden}.analytics-svg-frame{min-width:520px;width:100%}.analytics-svg{width:100%;height:180px;display:block}.analytics-chart-grid>*{min-width:0}.analytics-gridlines{stroke:rgba(148,163,184,.25);stroke-width:1}.analytics-area.rev{fill:rgba(249,115,22,.12)}.analytics-area.views{fill:rgba(148,163,184,.12)}.analytics-area.pur{fill:rgba(15,23,42,.08)}.analytics-line.rev{fill:none;stroke:#f97316;stroke-width:3}.analytics-line.views{fill:none;stroke:#94a3b8;stroke-width:3}.analytics-line.pur{fill:none;stroke:#0f172a;stroke-width:3}.analytics-axis{display:flex;justify-content:space-between;gap:8px;margin-top:8px;color:#64748b;font-size:.78rem;overflow-x:auto;padding-bottom:2px}.analytics-axis span{flex:0 0 auto;min-width:44px;text-align:center}.analytics-axis.compact span{min-width:64px;font-size:.74rem}.analytics-chart-legend{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px}.analytics-chart-legend span{display:inline-flex;align-items:center;gap:8px;color:#64748b;font-size:.83rem}.analytics-chart-legend i{display:inline-block;width:12px;height:12px;border-radius:999px}.analytics-chart-legend .rev{background:#f97316}.analytics-chart-legend .views{background:#94a3b8}.analytics-chart-legend .pur{background:#0f172a}.analytics-list{display:grid;gap:14px}.analytics-row{display:flex;justify-content:space-between;gap:14px;padding:12px 0;border-bottom:1px solid rgba(15,23,42,.06)}.analytics-row:last-child{border-bottom:none}.analytics-bar-track{height:10px;border-radius:999px;background:#eef2f7;overflow:hidden}.analytics-bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#f97316,#fb923c)}.analytics-bar-fill.soft{background:linear-gradient(90deg,#cbd5e1,#94a3b8)}.analytics-bar-fill.dark{background:linear-gradient(90deg,#0f172a,#334155)}.analytics-detail-stack{display:grid;gap:12px}@media (max-width: 1400px){.analytics-chart-grid{grid-template-columns:1fr}.analytics-detail-stack{grid-template-columns:repeat(2,minmax(0,1fr));display:grid}}@media (max-width: 1200px){.analytics-grid-4,.analytics-mini-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.analytics-chart-grid,.analytics-detail-stack{grid-template-columns:1fr}}@media (max-width: 768px){.analytics-grid-4,.analytics-mini-grid{grid-template-columns:1fr}}
</style>
<x-admin.page-header
    :kicker="__('Revenue intelligence')"
    :title="$product->name"
    :description="__('Product drilldown for :from → :to', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')])"
    :breadcrumbs="[
        ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('Revenue Intelligence'), 'url' => route('admin.analytics.index', ['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')])],
        ['label' => __('Product drilldown'), 'current' => true],
    ]"
>
    <a href="{{ route('admin.analytics.index', ['range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]) }}" class="btn btn-outline-dark">{{ __('Back to revenue intelligence') }}</a>
</x-admin.page-header>

@include('admin.analytics._nav')
@include('admin.analytics._trust_panel', ['trust' => $trust ?? [], 'uiState' => $uiState ?? []])

@include('admin.analytics._report_toolbar', [
    'title' => __('Product drilldown'),
    'subtitle' => __('Use this view for cleaner KPI wording, a shareable product summary, and export-ready follow-up.'),
    'period' => $from->format('Y-m-d') . ' → ' . $to->format('Y-m-d'),
    'reportId' => 'product-report',
    'exportRows' => $exportRows,
])

<div class="analytics-shell">
    <div class="analytics-anchor-nav">
        <a href="#product-operator-summary" class="analytics-anchor">{{ __('Operator summary') }}</a>
        <a href="#product-kpis" class="analytics-anchor">{{ __('KPI hierarchy') }}</a>
        <a href="#product-charts" class="analytics-anchor">{{ __('Chart suite') }}</a>
        <a href="#product-variants" class="analytics-anchor">{{ __('Variant mix') }}</a>
    </div>

    <div class="analytics-mini-grid" id="product-operator-summary">
        @foreach ($productOperatorReads as $item)
            <div class="analytics-mini">
                <div class="text-muted small">{{ $item['label'] }}</div>
                <div class="fs-5 fw-bold mt-1">{{ $item['value'] }}</div>
                <div class="text-muted small mt-2">{{ $item['help'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="analytics-grid-4" id="product-kpis">
        <div class="analytics-card"><div class="card-body"><div class="text-muted small">{{ __('Product views') }}</div><div class="fs-3 fw-bold">{{ number_format((int) ($totals['views'] ?? 0)) }}</div></div></div>
        <div class="analytics-card"><div class="card-body"><div class="text-muted small">{{ __('Add-to-cart actions') }}</div><div class="fs-3 fw-bold">{{ number_format((int) ($totals['add_to_cart_count'] ?? 0)) }}</div></div></div>
        <div class="analytics-card"><div class="card-body"><div class="text-muted small">{{ __('Purchased orders') }}</div><div class="fs-3 fw-bold">{{ number_format((int) ($totals['purchases'] ?? 0)) }}</div></div></div>
        <div class="analytics-card"><div class="card-body"><div class="text-muted small">{{ __('Gross product revenue') }}</div><div class="fs-3 fw-bold">EGP {{ number_format((float) ($totals['revenue_gross'] ?? 0), 2) }}</div></div></div>
    </div>

    <div class="analytics-mini-grid">
        <div class="analytics-mini"><div class="text-muted small">{{ __('View → cart') }}</div><div class="fs-4 fw-bold">{{ number_format(((float) ($totals['add_to_cart_rate'] ?? 0)) * 100, 1) }}%</div><div class="text-muted small mt-2">{{ __('Share of product views that progressed into cart activity.') }}</div></div>
        <div class="analytics-mini"><div class="text-muted small">{{ __('View → purchase') }}</div><div class="fs-4 fw-bold">{{ number_format(((float) ($totals['conversion_rate'] ?? 0)) * 100, 1) }}%</div><div class="text-muted small mt-2">{{ __('End-to-end product conversion from view to purchase.') }}</div></div>
        <div class="analytics-mini"><div class="text-muted small">{{ __('Cart → purchase') }}</div><div class="fs-4 fw-bold">{{ number_format(((float) ($totals['cart_to_purchase_rate'] ?? 0)) * 100, 1) }}%</div><div class="text-muted small mt-2">{{ __('Whether this product closes cleanly after intent exists.') }}</div></div>
    </div>

    @if ($uiState['show_charts'] ?? false)
    <div class="analytics-chart-grid" id="product-charts">
        <div class="analytics-chart-card">
            <div class="fw-bold fs-5">{{ __('Product chart suite') }}</div>
            <div class="text-muted small mt-1 mb-3">{{ __('Track discovery, monetization, and purchase completion in one visual view.') }}</div>
            <div class="analytics-chart-legend">
                <span><i class="views"></i>{{ __('Product views') }}</span>
                <span><i class="rev"></i>{{ __('Gross product revenue') }}</span>
                <span><i class="pur"></i>{{ __('Purchased orders') }}</span>
            </div>
            <div class="analytics-svg-wrap"><div class="analytics-svg-frame"><svg viewBox="0 0 520 180" class="analytics-svg" role="img" aria-label="{{ __('Product trend chart') }}">
                <line x1="0" y1="30" x2="520" y2="30" class="analytics-gridlines"></line>
                <line x1="0" y1="90" x2="520" y2="90" class="analytics-gridlines"></line>
                <line x1="0" y1="150" x2="520" y2="150" class="analytics-gridlines"></line>
                @if (! empty($viewsSvg['area']))<polygon points="{{ $viewsSvg['area'] }}" class="analytics-area views"></polygon>@endif
                @if (! empty($revenueSvg['area']))<polygon points="{{ $revenueSvg['area'] }}" class="analytics-area rev"></polygon>@endif
                @if (! empty($viewsSvg['line']))<polyline points="{{ $viewsSvg['line'] }}" class="analytics-line views"></polyline>@endif
                @if (! empty($revenueSvg['line']))<polyline points="{{ $revenueSvg['line'] }}" class="analytics-line rev"></polyline>@endif
                @if (! empty($purchasesSvg['line']))<polyline points="{{ $purchasesSvg['line'] }}" class="analytics-line pur"></polyline>@endif
            </svg></div></div>
            <div class="analytics-axis {{ $chartLabelStep > 1 ? 'compact' : '' }}">
                @forelse ($axisLabels as $row)
                    <span>{{ $row['label'] }}</span>
                @empty
                    <span>{{ __('No daily labels yet') }}</span>
                @endforelse
            </div>
        </div>
        <div class="analytics-detail-stack">
            <div class="analytics-chart-card">
                <div class="fw-bold fs-5">{{ __('Drilldown read') }}</div>
                <div class="text-muted small mt-1">{{ __('Use the strongest daily signal to understand what this product is best at.') }}</div>
                <div class="analytics-list mt-3">
                    <div class="analytics-row"><div><div class="fw-bold">{{ __('Discovery peak') }}</div><div class="text-muted small">{{ data_get($bestViewsDay, 'full_label', '—') }}</div></div><div class="text-end"><div class="fw-bold">{{ number_format((int) data_get($bestViewsDay, 'views', 0)) }}</div><div class="text-muted small">{{ __('views') }}</div></div></div>
                    <div class="analytics-row"><div><div class="fw-bold">{{ __('Revenue peak') }}</div><div class="text-muted small">{{ data_get($bestRevenueDay, 'full_label', '—') }}</div></div><div class="text-end"><div class="fw-bold">EGP {{ number_format((float) data_get($bestRevenueDay, 'revenue', 0), 2) }}</div></div></div>
                    <div class="analytics-row"><div><div class="fw-bold">{{ __('Purchase peak') }}</div><div class="text-muted small">{{ data_get($bestPurchaseDay, 'full_label', '—') }}</div></div><div class="text-end"><div class="fw-bold">{{ number_format((int) data_get($bestPurchaseDay, 'purchases', 0)) }}</div><div class="text-muted small">{{ __('purchases') }}</div></div></div>
                </div>
            </div>
            <div class="analytics-chart-card">
                <div class="fw-bold fs-5">{{ __('What to inspect next') }}</div>
                <div class="text-muted small mt-1">{{ __('A hierarchy for moving from headline product performance into action.') }}</div>
                <div class="analytics-list mt-3">
                    <div class="analytics-row"><div class="fw-bold">{{ __('High views, weak purchases') }}</div><div class="text-muted small">{{ __('Review PDP clarity or price resistance') }}</div></div>
                    <div class="analytics-row"><div class="fw-bold">{{ __('High adds, weak purchases') }}</div><div class="text-muted small">{{ __('Review stock, checkout friction, or shipping perception') }}</div></div>
                    <div class="analytics-row"><div class="fw-bold">{{ __('Healthy purchases, weak revenue') }}</div><div class="text-muted small">{{ __('Inspect variant mix, quantity depth, or offer quality') }}</div></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="analytics-card mb-3">
        <div class="card-body">
            <h4 class="mb-1">{{ __('Daily performance board') }}</h4>
            <div class="text-muted small mb-3">{{ __('Compact daily read for discovery, revenue, and purchase completion in one place.') }}</div>
            <div class="analytics-list">
                @forelse ($chartRows as $row)
                    <div class="analytics-row">
                        <div style="min-width:88px"><div class="fw-bold">{{ $row['label'] }}</div><div class="small text-muted">{{ __('Qty') }} {{ number_format($row['quantity']) }}</div></div>
                        <div class="flex-fill">
                            <div class="analytics-bar-track mb-2"><div class="analytics-bar-fill soft" style="width: {{ max(4, $row['view_width']) }}%"></div></div>
                            <div class="analytics-bar-track mb-2"><div class="analytics-bar-fill" style="width: {{ max(4, $row['revenue_width']) }}%"></div></div>
                            <div class="analytics-bar-track"><div class="analytics-bar-fill dark" style="width: {{ max(4, $row['purchase_width']) }}%"></div></div>
                        </div>
                        <div class="text-end"><div class="fw-bold">EGP {{ number_format($row['revenue'], 2) }}</div><div class="text-muted small">{{ number_format($row['views']) }} {{ __('views') }} · {{ number_format($row['purchases']) }} {{ __('purchases') }}</div></div>
                    </div>
                @empty
                    <div class="text-muted">{{ __('No daily stats yet for this product.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($uiState['show_variants'] ?? false)
    <div class="analytics-card" id="product-variants">
        <div class="card-body">
            <h4 class="mb-1">{{ __('Top variants / order mix') }}</h4>
            <div class="text-muted small mb-3">{{ __('Use this section to see whether the main product result is coming from one variant or a healthier mix.') }}</div>
            <div class="analytics-list mb-4">
                @forelse ($topVariants as $row)
                    <div class="analytics-row">
                        <div class="flex-fill">
                            <div class="fw-bold">{{ $row->variant_name ?: __('Default / simple product') }}</div>
                            <div class="analytics-bar-track mt-2"><div class="analytics-bar-fill" style="width: {{ min(100, (((float) $row->revenue_gross) / $maxVariantRevenue) * 100) }}%"></div></div>
                        </div>
                        <div class="text-end"><div class="fw-bold">EGP {{ number_format((float) $row->revenue_gross, 2) }}</div><div class="text-muted small">{{ __('Qty') }} {{ number_format((int) $row->quantity) }}</div></div>
                    </div>
                @empty
                    <div class="text-muted">{{ __('No order mix data yet.') }}</div>
                @endforelse
            </div>
            <div class="table-responsive">
                <table class="analytics-table">
                    <thead><tr><th>{{ __('Variant') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Gross product revenue') }}</th></tr></thead>
                    <tbody>
                        @forelse ($topVariants as $row)
                            <tr><td>{{ $row->variant_name ?: __('Default / simple product') }}</td><td>{{ number_format((int) $row->quantity) }}</td><td>EGP {{ number_format((float) $row->revenue_gross, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">{{ __('No order mix data yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
