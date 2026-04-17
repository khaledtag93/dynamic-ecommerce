@extends('layouts.admin')

@section('title', __('Admin Dashboard'))

@section('content')
@php
    $statusTotal = max(1, array_sum($statusBreakdown));
    $pendingShare = round(($statusBreakdown['pending'] / $statusTotal) * 100);
    $completedShare = round(($statusBreakdown['completed'] / $statusTotal) * 100);
    $processingShare = round(($statusBreakdown['processing'] / $statusTotal) * 100);
    $cancelledShare = round(($statusBreakdown['cancelled'] / $statusTotal) * 100);
    $latestOrder = $recentOrders->first();
    $topProduct = $topProducts->first();
    $latestCustomer = $recentCustomers->first();
    $maxRevenue = max(1, (float) ($monthlyRevenue->max('total') ?? 0));
    $dashboardFlowItems = [
        [
            'label' => __('Dashboard'),
            'description' => __('Stay on the executive board for the fastest operating summary, watchlist, and first-action guidance.'),
            'meta' => __('You are here'),
            'icon' => 'mdi-view-dashboard-outline',
            'url' => route('admin.dashboard'),
            'active' => true,
        ],
        [
            'label' => __('Revenue Intelligence'),
            'description' => __('Move into the core analytics overview when you need the full period comparison and leakage read.'),
            'meta' => __('Best next step for trend reading'),
            'icon' => 'mdi-chart-areaspline',
            'url' => route('admin.analytics.index', ['range' => '30d']),
        ],
        [
            'label' => __('Offers Drilldown'),
            'description' => __('Open discount and coupon performance directly when demand looks assisted by pricing pressure.'),
            'meta' => __('Review promotion efficiency'),
            'icon' => 'mdi-ticket-percent-outline',
            'url' => route('admin.analytics.offers', ['range' => '30d']),
        ],
        [
            'label' => __('Product Drilldown'),
            'description' => __('Jump straight into the strongest product when you want to explain momentum at item level.'),
            'meta' => $topProduct ? __('Top product: :name', ['name' => $topProduct->product_name ?: __('Product #:id', ['id' => $topProduct->product_id])]) : __('Product drilldown opens here once product movement is available.'),
            'icon' => 'mdi-cube-outline',
            'url' => $topProduct ? route('admin.analytics.products.show', ['product' => $topProduct->product_id, 'range' => '30d']) : null,
        ],
    ];
@endphp
<div class="admin-page-shell dashboard-overview-page">
    <div class="admin-page-header">
        <div>
            <div class="admin-kicker">{{ __('Store overview') }}</div>
            <h1 class="admin-page-title">{{ __('Executive dashboard') }}</h1>
            <p class="admin-page-description">{{ __('Track business momentum, focus on the next priority, and move into the right workflow without losing time.') }}</p>
        </div>
        <div class="admin-page-actions">
            @can('catalog.manage')
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus-circle-outline"></i><span>{{ __('Add product') }}</span></a>
            @endcan
            @can('orders.view')
                <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-cart-outline"></i><span>{{ __('Review orders') }}</span></a>
            @endcan
        </div>
    </div>

    <div class="admin-dashboard-jumpbar mb-4">
        <a href="#dashboard-kpis" class="admin-jump-chip"><i class="mdi mdi-view-dashboard-outline"></i><span>{{ __('Core KPIs') }}</span></a>
        <a href="#dashboard-insights" class="admin-jump-chip"><i class="mdi mdi-lightning-bolt-outline"></i><span>{{ __('Action center') }}</span></a>
        <a href="#dashboard-analytics" class="admin-jump-chip"><i class="mdi mdi-chart-areaspline"></i><span>{{ __('Revenue trend') }}</span></a>
        <a href="#dashboard-operations" class="admin-jump-chip"><i class="mdi mdi-format-list-bulleted-square"></i><span>{{ __('Recent orders') }}</span></a>
    </div>

    <div class="admin-dashboard-command-center row g-4 mb-4">
        @foreach($commandCenterCards as $card)
            <div class="col-md-6 col-xxl-3">
                <a href="{{ $card['route'] }}" class="admin-card admin-command-card h-100 tone-{{ $card['tone'] }} text-decoration-none">
                    <div class="admin-card-body d-flex flex-column h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="admin-inline-label mb-2">{{ $card['label'] }}</div>
                                <h4 class="mb-1 text-dark">{{ $card['title'] }}</h4>
                                <p class="text-muted small mb-0">{{ $card['description'] }}</p>
                            </div>
                            <span class="admin-command-card__icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                        </div>
                        <div class="admin-command-card__value">{{ $card['value'] }}</div>
                        <div class="admin-command-card__meta">{{ $card['meta'] }}</div>
                        <div class="admin-command-card__cta mt-auto">{{ $card['cta'] }} <i class="mdi mdi-arrow-top-left"></i></div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="admin-dashboard-workspaces row g-4 mb-4">
        @foreach($workspaceLanes as $lane)
            <div class="col-xl-4">
                <div class="admin-card admin-workspace-card h-100 tone-{{ $lane['tone'] }}">
                    <div class="admin-card-body">
                        <div class="admin-inline-label mb-2">{{ __('Workspace') }}</div>
                        <h4 class="mb-1">{{ $lane['title'] }}</h4>
                        <p class="text-muted small mb-3">{{ $lane['description'] }}</p>
                        <div class="admin-workspace-list">
                            @foreach($lane['items'] as $item)
                                <a href="{{ $item['route'] }}" class="admin-workspace-item text-decoration-none">
                                    <span>{{ $item['label'] }}</span>
                                    <strong>{{ $item['value'] }}</strong>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="admin-dashboard-stage mb-4">
        <div class="admin-card admin-dashboard-hero h-100">
            <div class="admin-card-body">
                <div class="row g-4 align-items-stretch">
                    <div class="col-xl-7">
                        <div class="admin-chip mb-3">{{ $decisionSummary['period'] }}</div>
                        <h3 class="admin-hero-title mb-2">{{ $decisionSummary['title'] }}</h3>
                        <p class="admin-hero-copy mb-4">{{ $decisionSummary['message'] }}</p>

                        <div class="admin-focus-strip">
                            <div class="admin-focus-pill">
                                <span>{{ __('Pending now') }}</span>
                                <strong>{{ number_format($stats['orders_pending']) }}</strong>
                            </div>
                            <div class="admin-focus-pill">
                                <span>{{ __('Low stock') }}</span>
                                <strong>{{ number_format($stats['products_low_stock']) }}</strong>
                            </div>
                            <div class="admin-focus-pill">
                                <span>{{ __('Net revenue') }}</span>
                                <strong>EGP {{ number_format(max(0, $stats['revenue_net']), 0) }}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="admin-exec-panel h-100">
                            <div class="admin-exec-panel-head">
                                <div>
                                    <div class="admin-inline-label mb-2">{{ __('Today at a glance') }}</div>
                                    <h4 class="mb-1">{{ __('What needs attention first?') }}</h4>
                                    <p class="text-muted small mb-0">{{ __('A quick operational read for the current dashboard opening.') }}</p>
                                </div>
                            </div>

                            <div class="admin-exec-stat-grid">
                                <div class="admin-exec-stat">
                                    <span>{{ __('Completed share') }}</span>
                                    <strong>{{ $completedShare }}%</strong>
                                    <small>{{ __('Share of total order volume') }}</small>
                                </div>
                                <div class="admin-exec-stat">
                                    <span>{{ __('Pending share') }}</span>
                                    <strong>{{ $pendingShare }}%</strong>
                                    <small>{{ __('Orders still waiting for action') }}</small>
                                </div>
                                <div class="admin-exec-stat">
                                    <span>{{ __('Processing') }}</span>
                                    <strong>{{ number_format($statusBreakdown['processing']) }}</strong>
                                    <small>{{ __('Live orders currently moving') }}</small>
                                </div>
                                <div class="admin-exec-stat">
                                    <span>{{ __('Cancelled') }}</span>
                                    <strong>{{ number_format($statusBreakdown['cancelled']) }}</strong>
                                    <small>{{ __('Orders lost before completion') }}</small>
                                </div>
                            </div>

                            <div class="admin-context-card mt-3">
                                <div class="admin-context-icon"><i class="mdi mdi-flash-outline"></i></div>
                                <div>
                                    <div class="fw-semibold">{{ __('Fastest next move') }}</div>
                                    <div class="text-muted small">{{ $stats['orders_pending'] > 0 ? __('Review pending orders first, then check low-stock products that may slow new sales.') : __('No urgent blocker is dominating right now. Use analytics or growth tools to push stronger performance.') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="admin-card admin-navigation-card h-100">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="admin-inline-label mb-2">{{ __('Navigation map') }}</div>
                            <h4 class="mb-1">{{ __('Workspaces that matter right now') }}</h4>
                            <p class="text-muted small mb-0">{{ __('Use this strip to move across operations, analytics, reliability, and catalog without scanning the whole sidebar.') }}</p>
                        </div>
                        <span class="admin-navigation-card__icon"><i class="mdi mdi-compass-outline"></i></span>
                    </div>
                    <div class="admin-navigation-grid">
                        @foreach($navigationSections as $section)
                            <a href="{{ $section['route'] }}" class="admin-navigation-item tone-{{ $section['tone'] }} text-decoration-none">
                                <span class="admin-navigation-item__icon"><i class="mdi {{ $section['icon'] }}"></i></span>
                                <div class="admin-navigation-item__body">
                                    <div class="admin-navigation-item__head">
                                        <strong>{{ $section['label'] }}</strong>
                                        <span>{{ $section['value'] }}</span>
                                    </div>
                                    <div class="text-muted small">{{ $section['description'] }}</div>
                                    <div class="admin-navigation-item__meta">{{ $section['meta'] }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="admin-card admin-route-card h-100">
                <div class="admin-card-body">
                    <div class="admin-inline-label mb-2">{{ __('Route guidance') }}</div>
                    <h4 class="mb-1">{{ __('Best places to open next') }}</h4>
                    <p class="text-muted small mb-3">{{ __('The command center is stronger now, so these routes surface the most natural follow-up pages with less hunting.') }}</p>
                    <div class="admin-route-list">
                        @foreach($navigationHighlights as $highlight)
                            <a href="{{ $highlight['route'] }}" class="admin-route-item text-decoration-none">
                                <span class="admin-route-item__icon"><i class="mdi {{ $highlight['icon'] }}"></i></span>
                                <div>
                                    <strong>{{ $highlight['label'] }}</strong>
                                    <div class="text-muted small">{{ $highlight['description'] }}</div>
                                </div>
                                <i class="mdi mdi-arrow-top-left"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            @include('admin.analytics._flow_hub', [
                'flowTitle' => __('Analytics flow'),
                'flowSubtitle' => __('Move from the executive board into the exact analytics page you need next, with the most natural entry point already chosen.'),
                'flowItems' => $dashboardFlowItems,
            ])
        </div>
        <div class="col-xl-4">
            <div class="admin-card admin-reading-card h-100">
                <div class="admin-card-body">
                    <div class="admin-inline-label mb-2">{{ __('Reading order') }}</div>
                    <h4 class="mb-2">{{ __('How to read this dashboard quickly') }}</h4>
                    <p class="text-muted small mb-3">{{ __('Start with the board summary, confirm the top pressure, then move into the analytics page that explains the reason behind it.') }}</p>
                    <div class="admin-reading-steps">
                        <div class="admin-reading-step">
                            <strong>01</strong>
                            <div>
                                <div class="fw-semibold">{{ __('Check the headline') }}</div>
                                <div class="text-muted small">{{ __('Use the hero and action-now cards to see the current business posture in seconds.') }}</div>
                            </div>
                        </div>
                        <div class="admin-reading-step">
                            <strong>02</strong>
                            <div>
                                <div class="fw-semibold">{{ __('Confirm the pressure') }}</div>
                                <div class="text-muted small">{{ __('Review pressure points, watchlist items, and payment health before acting.') }}</div>
                            </div>
                        </div>
                        <div class="admin-reading-step">
                            <strong>03</strong>
                            <div>
                                <div class="fw-semibold">{{ __('Open the next page') }}</div>
                                <div class="text-muted small">{{ __('Use the analytics flow to jump directly to revenue, offers, or product drill-down without losing context.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-overview-strip mb-4">
        <div class="admin-overview-item">
            <span>{{ __('Board focus') }}</span>
            <strong>{{ $smartBoard['first_action']['title'] ?? __('Keep monitoring the dashboard') }}</strong>
        </div>
        <div class="admin-overview-item">
            <span>{{ __('Main pressure') }}</span>
            <strong>{{ $smartBoard['priority_queue'][0]['title'] ?? __('No major pressure right now') }}</strong>
        </div>
        <div class="admin-overview-item">
            <span>{{ __('Best opportunity') }}</span>
            <strong>{{ $smartBoard['opportunity_queue'][0]['title'] ?? __('No strong opportunity signal yet') }}</strong>
        </div>
        <div class="admin-overview-item">
            <span>{{ __('Next route') }}</span>
            <strong>{{ $quickActions[0]['label'] ?? __('Stay on the dashboard') }}</strong>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.dashboard') }}" class="admin-card mb-4" data-submit-loading>
        <div class="admin-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold">{{ __('Quick admin search') }}</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('Search products, orders, customers, coupons, categories') }}">
                </div>
                <div class="col-lg-4 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Searching...') }}">{{ __('Search') }}</button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-light border">{{ __('Reset') }}</a>
                </div>
            </div>
        </div>
    </form>

    <div id="dashboard-kpis" class="admin-dashboard-section mb-4">
        <div class="admin-section-heading mb-3">
            <div>
                <div class="admin-inline-label mb-2">{{ __('Performance snapshot') }}</div>
                <h3 class="mb-1">{{ __('Core KPIs') }}</h3>
                <p class="text-muted small mb-0">{{ __('Your most important 30-day metrics in one clean strip.') }}</p>
            </div>
        </div>
        <div class="row g-3">
            @foreach($kpiCards as $card)
                <div class="col-md-6 col-xl-4">
                    <div class="admin-card admin-stat-card admin-dashboard-kpi h-100 tone-{{ $card['tone'] }}">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <span class="admin-stat-icon"><i class="mdi {{ $card['icon'] }}"></i></span>
                            <span class="admin-delta-pill {{ $card['delta']['rate'] >= 0 ? 'positive' : 'negative' }}">{{ $card['delta']['text'] }}</span>
                        </div>
                        <div class="admin-stat-label mt-3">{{ $card['label'] }}</div>
                        <div class="admin-stat-value">{{ $card['value'] }}</div>
                        <div class="text-muted small mt-2">{{ $card['copy'] }}</div>
                        <div class="admin-kpi-footnote">{{ $card['delta']['comparison'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div id="dashboard-insights" class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="admin-card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Operations') }}</div>
                        <h4 class="mb-1">{{ __('Watchlist') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Start here when you want the quickest read on risk and follow-up.') }}</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-watchlist-stack">
                        @foreach($alerts as $alert)
                            <div class="admin-watch-item tone-{{ $alert['tone'] }}">
                                <div class="admin-watch-icon"><i class="mdi {{ $alert['icon'] }}"></i></div>
                                <div>
                                    <div class="fw-semibold">{{ $alert['title'] }}</div>
                                    <div class="text-muted small">{{ $alert['description'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="admin-card h-100">
                <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Priority actions') }}</div>
                        <h4 class="mb-1">{{ __('Action center') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Move into the busiest workflows without digging through the sidebar.') }}</p>
                    </div>
                    <span class="admin-chip">{{ __('Work faster') }}</span>
                </div>
                <div class="admin-card-body">
                    <div class="admin-quick-grid">
                        @foreach($quickActions as $action)
                            @can($action['permission'])
                                <a href="{{ $action['route'] }}" class="admin-quick-action h-100">
                                    <span class="admin-quick-action-icon"><i class="mdi {{ $action['icon'] }}"></i></span>
                                    <div>
                                        <div class="fw-semibold mb-1">{{ $action['label'] }}</div>
                                        <div class="text-muted small">{{ $action['description'] }}</div>
                                    </div>
                                    <i class="mdi mdi-arrow-right ms-auto"></i>
                                </a>
                            @endcan
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="admin-card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Trend read') }}</div>
                        <h4 class="mb-1">{{ __('Performance pulse') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('A compact read across winners, pipeline movement, and customer freshness.') }}</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-pulse-stack">
                        <div class="admin-pulse-card success">
                            <span>{{ __('Top product') }}</span>
                            <strong>{{ $topProduct?->product_name ?: __('No product movement yet') }}</strong>
                            <small>
                                @if($topProduct)
                                    {{ number_format((float) $topProduct->units_sold) }} {{ __('units sold') }} · EGP {{ number_format((float) $topProduct->revenue_total, 0) }}
                                @else
                                    {{ __('The recent sales window is still quiet.') }}
                                @endif
                            </small>
                        </div>
                        <div class="admin-pulse-card warning">
                            <span>{{ __('Latest order') }}</span>
                            <strong>{{ $latestOrder?->order_number ?: __('No orders yet') }}</strong>
                            <small>
                                @if($latestOrder)
                                    {{ $latestOrder->customer_name }} · {{ $latestOrder->status_label }}
                                @else
                                    {{ __('New orders will appear here once available.') }}
                                @endif
                            </small>
                        </div>
                        <div class="admin-pulse-card info">
                            <span>{{ __('Newest customer') }}</span>
                            <strong>{{ $latestCustomer?->name ?: __('No customers yet') }}</strong>
                            <small>
                                @if($latestCustomer)
                                    {{ $latestCustomer->created_at?->format('d M Y') }} · {{ __('Recently joined') }}
                                @else
                                    {{ __('New customer signups will appear here once available.') }}
                                @endif
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="admin-card admin-priority-card h-100 tone-{{ $smartBoard['first_action']['tone'] ?? 'neutral' }}">
                <div class="card-header border-0 pb-0">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Action now') }}</div>
                        <h4 class="mb-1">{{ __('First move to make now') }}</h4>
                        <p class="text-muted small mb-0">{{ __('The dashboard picks the clearest next step based on current pressure and momentum.') }}</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-priority-head">
                        <div class="admin-priority-icon"><i class="mdi {{ $smartBoard['first_action']['icon'] ?? 'mdi-flash-outline' }}"></i></div>
                        <div>
                            <span>{{ __('Recommended move') }}</span>
                            <strong>{{ $smartBoard['first_action']['title'] ?? __('Keep monitoring the dashboard') }}</strong>
                            <small>{{ $smartBoard['first_action']['metric'] ?? __('No critical metric is leading the board right now.') }}</small>
                        </div>
                    </div>
                    <p class="admin-priority-copy">{{ $smartBoard['first_action']['description'] ?? __('No urgent action is leading the board right now.') }}</p>
                    @if(!empty($smartBoard['first_action']['action_route']))
                        <a href="{{ $smartBoard['first_action']['action_route'] }}" class="btn btn-dark btn-sm btn-text-icon">
                            <i class="mdi mdi-arrow-top-right"></i>
                            <span>{{ $smartBoard['first_action']['action_label'] ?? __('Open next step') }}</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="row g-4 h-100">
                <div class="col-md-6">
                    <div class="admin-card h-100">
                        <div class="card-header border-0 pb-0">
                            <div>
                                <div class="admin-inline-label mb-2">{{ __('Where the problem is') }}</div>
                                <h4 class="mb-1">{{ __('Pressure points') }}</h4>
                                <p class="text-muted small mb-0">{{ __('The sharpest issues that can slow sales, operations, or customer experience.') }}</p>
                            </div>
                        </div>
                        <div class="admin-card-body">
                            <div class="admin-smart-stack">
                                @foreach($smartBoard['priority_queue'] as $signal)
                                    <div class="admin-smart-item tone-{{ $signal['tone'] }}">
                                        <div class="admin-smart-item-icon"><i class="mdi {{ $signal['icon'] }}"></i></div>
                                        <div>
                                            <div class="fw-semibold">{{ $signal['title'] }}</div>
                                            <div class="text-muted small">{{ $signal['description'] }}</div>
                                            <div class="admin-smart-metric">{{ $signal['metric'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="admin-card h-100">
                        <div class="card-header border-0 pb-0">
                            <div>
                                <div class="admin-inline-label mb-2">{{ __('Where the opportunity is') }}</div>
                                <h4 class="mb-1">{{ __('Momentum worth pushing') }}</h4>
                                <p class="text-muted small mb-0">{{ __('The clearest positive signals that are ready for a stronger commercial move.') }}</p>
                            </div>
                        </div>
                        <div class="admin-card-body">
                            <div class="admin-smart-stack">
                                @foreach($smartBoard['opportunity_queue'] as $signal)
                                    <div class="admin-smart-item tone-{{ $signal['tone'] }}">
                                        <div class="admin-smart-item-icon"><i class="mdi {{ $signal['icon'] }}"></i></div>
                                        <div>
                                            <div class="fw-semibold">{{ $signal['title'] }}</div>
                                            <div class="text-muted small">{{ $signal['description'] }}</div>
                                            <div class="admin-smart-metric">{{ $signal['metric'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="dashboard-analytics" class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="admin-card admin-chart-card h-100">
                <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Revenue') }}</div>
                        <h4 class="mb-1">{{ __('Revenue trend') }}</h4>
                        <p class="text-muted small mb-0">{{ __('A six-month view to spot momentum without leaving the dashboard.') }}</p>
                    </div>
                    <span class="admin-chip">{{ __('Last 6 months') }}</span>
                </div>
                <div class="admin-card-body">
                    <div class="admin-mini-chart admin-mini-chart-compact">
                        @forelse($monthlyRevenue as $month)
                            <div class="admin-bar-row">
                                <div class="fw-semibold">{{ $month->month_label }}</div>
                                <div class="admin-bar-track"><div class="admin-bar-fill" style="width: {{ max(8, min(100, ((float) $month->total / $maxRevenue) * 100)) }}%"></div></div>
                                <div class="fw-bold">EGP {{ number_format((float) $month->total, 0) }}</div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No monthly revenue yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="admin-card admin-chart-card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Pipeline') }}</div>
                        <h4 class="mb-1">{{ __('Order mix') }}</h4>
                        <p class="text-muted small mb-0">{{ __('Current distribution by order status for a faster operational read.') }}</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-donut-legend mb-3">
                        @foreach([
                            'pending' => __('Pending'),
                            'processing' => __('Processing'),
                            'completed' => __('Completed'),
                            'cancelled' => __('Cancelled'),
                        ] as $key => $label)
                            <div class="admin-donut-item">
                                <span class="admin-donut-label"><span class="admin-dot {{ $key }}"></span>{{ $label }}</span>
                                <span class="fw-bold">{{ $statusBreakdown[$key] }} <span class="text-muted small">({{ round(($statusBreakdown[$key] / $statusTotal) * 100) }}%)</span></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="admin-status-note-grid admin-status-note-grid-extended">
                        <div class="admin-status-note">
                            <span>{{ __('Completed') }}</span>
                            <strong>{{ number_format($statusBreakdown['completed']) }}</strong>
                        </div>
                        <div class="admin-status-note">
                            <span>{{ __('Pending') }}</span>
                            <strong>{{ number_format($statusBreakdown['pending']) }}</strong>
                        </div>
                        <div class="admin-status-note">
                            <span>{{ __('Processing') }}</span>
                            <strong>{{ $processingShare }}%</strong>
                        </div>
                        <div class="admin-status-note">
                            <span>{{ __('Cancelled') }}</span>
                            <strong>{{ $cancelledShare }}%</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="admin-card admin-chart-card h-100">
                <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Depth read') }}</div>
                        <h4 class="mb-1">{{ __('14-day commercial rhythm') }}</h4>
                        <p class="text-muted small mb-0">{{ __('A tighter daily read across revenue and order flow so shifts are visible before they become problems.') }}</p>
                    </div>
                    <span class="admin-chip">{{ __('Last 14 days') }}</span>
                </div>
                <div class="admin-card-body">
                    <div class="admin-depth-grid mb-4">
                        @foreach($depthHighlights as $highlight)
                            <div class="admin-depth-highlight tone-{{ $highlight['tone'] }}">
                                <div class="admin-depth-highlight-icon"><i class="mdi {{ $highlight['icon'] }}"></i></div>
                                <div>
                                    <span>{{ $highlight['label'] }}</span>
                                    <strong>{{ $highlight['value'] }}</strong>
                                    <small>{{ $highlight['meta'] }}</small>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="admin-depth-chart">
                        @forelse($dailyDepth as $day)
                            <div class="admin-depth-row">
                                <div class="admin-depth-day">
                                    <strong>{{ $day['label'] }}</strong>
                                    <small>{{ number_format($day['orders']) }} {{ __('orders') }}</small>
                                </div>
                                <div class="admin-depth-track-wrap">
                                    <div class="admin-depth-metric">
                                        <span>{{ __('Revenue') }}</span>
                                        <div class="admin-depth-track"><div class="admin-depth-fill revenue" style="width: {{ max(6, $day['revenue_width']) }}%"></div></div>
                                        <strong>EGP {{ number_format($day['revenue'], 0) }}</strong>
                                    </div>
                                    <div class="admin-depth-metric compact">
                                        <span>{{ __('Orders') }}</span>
                                        <div class="admin-depth-track thin"><div class="admin-depth-fill orders" style="width: {{ max(6, $day['orders_width']) }}%"></div></div>
                                        <strong>{{ number_format($day['orders']) }}</strong>
                                    </div>
                                </div>
                                <div class="admin-depth-conversion">
                                    <span>{{ __('Paid share') }}</span>
                                    <strong>{{ number_format($day['conversion'], 0) }}%</strong>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted">{{ __('No daily analytics yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="admin-card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Health mix') }}</div>
                        <h4 class="mb-1">{{ __('Payment outcome split') }}</h4>
                        <p class="text-muted small mb-0">{{ __('A fast quality read of payment outcomes across all tracked orders.') }}</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-donut-legend mb-4">
                        @foreach([
                            'paid' => __('Paid'),
                            'pending' => __('Pending'),
                            'failed' => __('Failed'),
                            'refunded' => __('Refunded'),
                        ] as $key => $label)
                            <div class="admin-donut-item">
                                <span class="admin-donut-label"><span class="admin-dot {{ $key }}"></span>{{ $label }}</span>
                                <span class="fw-bold">{{ number_format($paymentBreakdown[$key] ?? 0) }} <span class="text-muted small">({{ $paymentShare[$key] ?? 0 }}%)</span></span>
                            </div>
                        @endforeach
                    </div>

                    <div class="admin-signal-stack">
                        @foreach($analyticsSignals as $signal)
                            <div class="admin-signal-card tone-{{ $signal['tone'] }}">
                                <span>{{ $signal['title'] }}</span>
                                <strong>{{ $signal['value'] }}</strong>
                                <small>{{ $signal['description'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($q !== '')
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Quick lookup') }}</div>
                        <h4 class="mb-1">{{ __('Search results') }}</h4>
                        <p class="text-muted mb-0 small">{{ __('Showing quick matches for') }} <strong>{{ $q }}</strong>.</p>
                    </div>
                </div>
                <div class="row g-3">
                    @foreach([
                        'orders' => ['label' => __('Orders'), 'route' => 'admin.orders.show', 'field' => 'order_number'],
                        'products' => ['label' => __('Products'), 'route' => 'admin.products.edit', 'field' => 'name'],
                        'customers' => ['label' => __('Customers'), 'route' => 'admin.customers.show', 'field' => 'name'],
                        'coupons' => ['label' => __('Coupons'), 'route' => 'admin.coupons.edit', 'field' => 'code'],
                        'categories' => ['label' => __('Categories'), 'route' => 'admin.categories.edit', 'field' => 'name'],
                    ] as $key => $meta)
                        <div class="col-lg-6 col-xl-4">
                            <div class="admin-search-panel h-100">
                                <div class="admin-inline-label mb-2">{{ $meta['label'] }}</div>
                                @forelse($searchResults[$key] as $item)
                                    <a href="{{ route($meta['route'], $item) }}" class="admin-search-item">
                                        <span>{{ data_get($item, $meta['field']) }}</span>
                                        <i class="mdi mdi-arrow-right"></i>
                                    </a>
                                @empty
                                    <div class="text-muted small">{{ __('No matches yet.') }}</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Risk review') }}</div>
                        <h4 class="mb-1">{{ __('Top problems') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Issues that can slow conversion, operations, or fulfillment.') }}</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-insight-stack">
                        @foreach($problems as $problem)
                            <div class="admin-insight-item danger">
                                <div class="admin-insight-icon"><i class="mdi {{ $problem['icon'] }}"></i></div>
                                <div>
                                    <div class="fw-semibold">{{ $problem['title'] }}</div>
                                    <div class="text-muted small">{{ $problem['description'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Opportunity read') }}</div>
                        <h4 class="mb-1">{{ __('Top opportunities') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Positive signals worth turning into faster action.') }}</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-insight-stack">
                        @foreach($opportunities as $opportunity)
                            <div class="admin-insight-item success">
                                <div class="admin-insight-icon"><i class="mdi {{ $opportunity['icon'] }}"></i></div>
                                <div>
                                    <div class="fw-semibold">{{ $opportunity['title'] }}</div>
                                    <div class="text-muted small">{{ $opportunity['description'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="dashboard-operations" class="row g-4">
        <div class="col-xl-7">
            <div class="admin-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Daily workflow') }}</div>
                        <h4 class="mb-1">{{ __('Recent orders') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Latest customer orders and their current payment status.') }}</p>
                    </div>
                    @can('orders.view')
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-open-in-new"></i><span>{{ __('Open orders') }}</span></a>
                    @endcan
                </div>
                <div class="admin-card-body pt-0">
                    <div class="table-responsive">
                        <table class="table admin-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Order') }}</th>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                    <tr>
                                        <td>
                                            @can('orders.view')
                                                <a href="{{ route('admin.orders.show', $order) }}" class="fw-bold text-decoration-none">{{ $order->order_number }}</a>
                                            @else
                                                <span class="fw-bold">{{ $order->order_number }}</span>
                                            @endcan
                                            <div class="text-muted small">{{ $order->payment_method_label }}</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $order->customer_name }}</div>
                                            <div class="text-muted small">{{ $order->customer_email }}</div>
                                        </td>
                                        <td>
                                            <span class="badge admin-status-badge {{ $order->status_badge_class }} mb-2">{{ $order->status_label }}</span>
                                            <div><span class="badge admin-status-badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span></div>
                                        </td>
                                        <td class="fw-bold">EGP {{ number_format($order->grand_total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-4 text-muted">{{ __('No orders yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="admin-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Winners') }}</div>
                        <h4 class="mb-1">{{ __('Top products this month') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Quick winners by units sold in the last 30 days.') }}</p>
                    </div>
                    @can('dashboard.view')
                        <a href="{{ route('admin.analytics.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-chart-areaspline"></i><span>{{ __('Analytics') }}</span></a>
                    @endcan
                </div>
                <div class="admin-card-body">
                    @forelse($topProducts as $row)
                        <div class="admin-list-row">
                            <div>
                                <div class="fw-semibold">{{ $row->product_name ?: __('Unnamed product') }}</div>
                                <div class="text-muted small">{{ number_format((float) $row->units_sold) }} {{ __('units sold') }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">EGP {{ number_format((float) $row->revenue_total, 0) }}</div>
                                <div class="text-muted small">{{ __('Revenue') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No product movement yet in the recent window.') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="admin-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Inventory') }}</div>
                        <h4 class="mb-1">{{ __('Low-stock watchlist') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Products that may need replenishment soon.') }}</p>
                    </div>
                    @can('catalog.manage')
                        <a href="{{ route('admin.products.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-package-variant"></i><span>{{ __('Products') }}</span></a>
                    @endcan
                </div>
                <div class="admin-card-body">
                    @forelse($lowStockProducts as $product)
                        <div class="admin-list-row">
                            <div>
                                <div class="fw-semibold">{{ $product->name }}</div>
                                <div class="text-muted small">{{ optional($product->category)->name ?: __('No category') }} · {{ optional($product->brand)->name ?: __('No brand') }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">{{ $product->quantity_value }}</div>
                                <div class="text-muted small">{{ __('Current stock') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No low-stock products right now.') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="admin-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="admin-inline-label mb-2">{{ __('Customers') }}</div>
                        <h4 class="mb-1">{{ __('Recent customers') }}</h4>
                        <p class="mb-0 text-muted small">{{ __('Newest accounts added to the platform.') }}</p>
                    </div>
                    @can('customers.manage')
                        <a href="{{ route('admin.customers.index') }}" class="btn btn-light border btn-sm btn-text-icon"><i class="mdi mdi-account-group-outline"></i><span>{{ __('Customers') }}</span></a>
                    @endcan
                </div>
                <div class="admin-card-body">
                    @forelse($recentCustomers as $customer)
                        <div class="admin-list-row">
                            <div>
                                @can('customers.manage')
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="fw-semibold text-decoration-none">{{ $customer->name }}</a>
                                @else
                                    <span class="fw-semibold">{{ $customer->name }}</span>
                                @endcan
                                <div class="text-muted small">{{ $customer->email }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">{{ $customer->created_at?->format('d M') }}</div>
                                <div class="text-muted small">{{ __('Joined') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No customers yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<style>

.dashboard-overview-page .admin-dashboard-jumpbar {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
}
.dashboard-overview-page .admin-jump-chip {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .72rem 1rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--admin-surface) 98%, white);
    border: 1px solid rgba(148,163,184,.18);
    color: var(--admin-text);
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 10px 25px rgba(15,23,42,.04);
}
.dashboard-overview-page .admin-jump-chip:hover {
    border-color: rgba(249,115,22,.28);
    color: var(--admin-primary-dark);
}
.dashboard-overview-page .admin-reading-card,
.dashboard-overview-page .admin-overview-strip,
.dashboard-overview-page .admin-overview-item {
    border-radius: 20px;
}
.dashboard-overview-page .admin-reading-card {
    border: 1px solid rgba(148,163,184,.16);
}
.dashboard-overview-page .admin-reading-steps {
    display: grid;
    gap: .85rem;
}
.dashboard-overview-page .admin-reading-step {
    display: flex;
    align-items: flex-start;
    gap: .85rem;
    padding: .9rem 0;
    border-top: 1px dashed rgba(148,163,184,.22);
}
.dashboard-overview-page .admin-reading-step:first-child {
    border-top: 0;
    padding-top: 0;
}
.dashboard-overview-page .admin-reading-step strong {
    min-width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(249,115,22,.12);
    color: var(--admin-primary-dark);
    font-size: .9rem;
}
.dashboard-overview-page .admin-overview-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .85rem;
}
.dashboard-overview-page .admin-overview-item {
    padding: 1rem 1.05rem;
    border: 1px solid rgba(148,163,184,.14);
    background: color-mix(in srgb, var(--admin-surface) 96%, white);
}
.dashboard-overview-page .admin-overview-item span {
    display: block;
    margin-bottom: .3rem;
    color: var(--admin-muted);
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
}
.dashboard-overview-page .admin-overview-item strong {
    display: block;
    color: var(--admin-text);
}
.dashboard-overview-page .admin-dashboard-stage {
    display: grid;
}
.dashboard-overview-page .admin-command-card,
.dashboard-overview-page .admin-workspace-card {
    border: 1px solid var(--admin-border);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.dashboard-overview-page .admin-command-card:hover,
.dashboard-overview-page .admin-workspace-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}
.dashboard-overview-page .admin-command-card__icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--admin-primary-soft) 55%, white);
    color: var(--admin-primary-dark);
    font-size: 1.5rem;
    flex: 0 0 auto;
}
.dashboard-overview-page .admin-command-card__value {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--admin-text);
    line-height: 1.1;
}
.dashboard-overview-page .admin-command-card__meta {
    color: var(--admin-text-soft);
    font-size: .85rem;
    margin-top: .35rem;
}
.dashboard-overview-page .admin-command-card__cta {
    margin-top: 1rem;
    font-weight: 700;
    color: var(--admin-primary-dark);
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.dashboard-overview-page .admin-command-card.tone-danger,
.dashboard-overview-page .admin-workspace-card.tone-danger {
    border-color: color-mix(in srgb, #dc3545 20%, var(--admin-border));
}
.dashboard-overview-page .admin-command-card.tone-warning,
.dashboard-overview-page .admin-workspace-card.tone-warning {
    border-color: color-mix(in srgb, #f59e0b 28%, var(--admin-border));
}
.dashboard-overview-page .admin-command-card.tone-success,
.dashboard-overview-page .admin-workspace-card.tone-success {
    border-color: color-mix(in srgb, #22c55e 20%, var(--admin-border));
}
.dashboard-overview-page .admin-command-card.tone-info,
.dashboard-overview-page .admin-workspace-card.tone-info {
    border-color: color-mix(in srgb, #0ea5e9 20%, var(--admin-border));
}
.dashboard-overview-page .admin-workspace-list {
    display: grid;
    gap: .8rem;
}
.dashboard-overview-page .admin-workspace-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .95rem 1rem;
    border-radius: 16px;
    border: 1px solid var(--admin-border);
    background: color-mix(in srgb, var(--admin-surface-alt) 82%, white);
    color: var(--admin-text);
}
.dashboard-overview-page .admin-workspace-item:hover {
    border-color: color-mix(in srgb, var(--admin-primary) 30%, var(--admin-border));
    text-decoration: none;
}
.dashboard-overview-page .admin-workspace-item strong {
    font-size: .95rem;
}
.dashboard-overview-page .admin-dashboard-hero {
    border: 1px solid rgba(249, 115, 22, 0.16);
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.12), rgba(255, 255, 255, 0.98));
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.06);
}
.dashboard-overview-page .admin-hero-title {
    font-size: 1.45rem;
    font-weight: 800;
}
.dashboard-overview-page .admin-hero-copy {
    max-width: 820px;
    font-size: .98rem;
    color: var(--admin-muted);
}
.dashboard-overview-page .admin-focus-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .85rem;
}
.dashboard-overview-page .admin-focus-pill,
.dashboard-overview-page .admin-exec-stat,
.dashboard-overview-page .admin-context-card,
.dashboard-overview-page .admin-pulse-card,
.dashboard-overview-page .admin-status-note {
    border-radius: 18px;
    border: 1px solid rgba(148, 163, 184, .14);
    background: rgba(255, 255, 255, .92);
}
.dashboard-overview-page .admin-focus-pill {
    padding: .95rem 1rem;
}
.dashboard-overview-page .admin-focus-pill span,
.dashboard-overview-page .admin-exec-stat span,
.dashboard-overview-page .admin-pulse-card span,
.dashboard-overview-page .admin-status-note span {
    display: block;
    color: var(--admin-muted);
    font-size: .76rem;
    margin-bottom: .3rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
}
.dashboard-overview-page .admin-focus-pill strong,
.dashboard-overview-page .admin-exec-stat strong,
.dashboard-overview-page .admin-pulse-card strong {
    display: block;
    color: var(--admin-text);
    font-size: 1.08rem;
}
.dashboard-overview-page .admin-focus-pill strong {
    font-size: 1.15rem;
}
.dashboard-overview-page .admin-focus-pill:last-child strong {
    font-size: 1.05rem;
}
.dashboard-overview-page .admin-exec-panel {
    height: 100%;
    padding: 1.15rem;
    border-radius: 24px;
    background: color-mix(in srgb, var(--admin-surface) 92%, white);
    border: 1px solid rgba(148, 163, 184, .18);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
}
.dashboard-overview-page .admin-exec-stat-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .85rem;
    margin-top: 1rem;
}
.dashboard-overview-page .admin-exec-stat {
    padding: .95rem 1rem;
}
.dashboard-overview-page .admin-exec-stat small,
.dashboard-overview-page .admin-focus-pill small,
.dashboard-overview-page .admin-pulse-card small,
.dashboard-overview-page .admin-kpi-footnote {
    color: var(--admin-muted);
}
.dashboard-overview-page .admin-context-card {
    display: flex;
    gap: .85rem;
    padding: 1rem 1.05rem;
}
.dashboard-overview-page .admin-context-icon,
.dashboard-overview-page .admin-watch-icon,
.dashboard-overview-page .admin-insight-icon,
.dashboard-overview-page .admin-quick-action-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    background: color-mix(in srgb, var(--admin-surface-alt) 68%, white);
    color: var(--admin-text);
    flex-shrink: 0;
}
.dashboard-overview-page .admin-section-heading h3 {
    font-size: 1.15rem;
    font-weight: 800;
}
.dashboard-overview-page .admin-dashboard-kpi {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, .12);
}
.dashboard-overview-page .admin-dashboard-kpi.tone-success { border-color: rgba(34, 197, 94, .18); }
.dashboard-overview-page .admin-dashboard-kpi.tone-danger { border-color: rgba(239, 68, 68, .18); }
.dashboard-overview-page .admin-delta-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 74px;
    padding: .32rem .62rem;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 700;
}
.dashboard-overview-page .admin-delta-pill.positive {
    background: rgba(34, 197, 94, .12);
    color: var(--admin-success);
}
.dashboard-overview-page .admin-delta-pill.negative {
    background: rgba(239, 68, 68, .12);
    color: var(--admin-danger);
}
.dashboard-overview-page .admin-watchlist-stack,
.dashboard-overview-page .admin-insight-stack,
.dashboard-overview-page .admin-pulse-stack,
.dashboard-overview-page .admin-quick-grid {
    display: grid;
    gap: .9rem;
}
.dashboard-overview-page .admin-watch-item,
.dashboard-overview-page .admin-insight-item,
.dashboard-overview-page .admin-quick-action {
    display: flex;
    align-items: flex-start;
    gap: .9rem;
    padding: 1rem 1.05rem;
    border-radius: 18px;
    border: 1px solid rgba(148, 163, 184, .14);
    background: color-mix(in srgb, var(--admin-surface) 98%, white);
}
.dashboard-overview-page .admin-watch-item.tone-warning { background: color-mix(in srgb, var(--admin-warning-soft) 86%, white); }
.dashboard-overview-page .admin-watch-item.tone-danger,
.dashboard-overview-page .admin-insight-item.danger { background: color-mix(in srgb, var(--admin-danger-soft) 86%, white); }
.dashboard-overview-page .admin-watch-item.tone-success,
.dashboard-overview-page .admin-insight-item.success { background: color-mix(in srgb, var(--admin-success-soft) 88%, white); }
.dashboard-overview-page .admin-quick-action {
    text-decoration: none;
    color: inherit;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.dashboard-overview-page .admin-quick-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 32px rgba(15, 23, 42, .08);
    border-color: rgba(249, 115, 22, .22);
}
.dashboard-overview-page .admin-pulse-card {
    padding: 1rem 1.05rem;
}
.dashboard-overview-page .admin-pulse-card.success { background: color-mix(in srgb, var(--admin-success-soft) 88%, white); }
.dashboard-overview-page .admin-pulse-card.warning { background: rgba(245, 158, 11, .07); }
.dashboard-overview-page .admin-pulse-card.info { background: color-mix(in srgb, var(--admin-info-soft) 88%, white); }
.dashboard-overview-page .admin-mini-chart-compact .admin-bar-row + .admin-bar-row {
    margin-top: .85rem;
}
.dashboard-overview-page .admin-status-note-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
}
.dashboard-overview-page .admin-status-note-grid-extended {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.dashboard-overview-page .admin-status-note {
    padding: .9rem .95rem;
}
@media (max-width: 1199.98px) {
    .dashboard-overview-page .admin-focus-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 767.98px) {
    .dashboard-overview-page .admin-focus-strip,
    .dashboard-overview-page .admin-exec-stat-grid,
    .dashboard-overview-page .admin-status-note-grid {
        grid-template-columns: 1fr;
    }
    .dashboard-overview-page .admin-hero-copy {
        max-width: 100%;
    }
}

.dashboard-overview-page .admin-depth-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .9rem;
}
.dashboard-overview-page .admin-depth-highlight,
.dashboard-overview-page .admin-signal-card {
    display: flex;
    gap: .9rem;
    align-items: flex-start;
    padding: 1rem;
    border-radius: 18px;
    border: 1px solid rgba(148, 163, 184, .16);
    background: color-mix(in srgb, var(--admin-surface) 98%, white);
}
.dashboard-overview-page .admin-depth-highlight-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(249, 115, 22, .12);
    color: var(--admin-primary);
    font-size: 1.1rem;
}
.dashboard-overview-page .admin-depth-highlight span,
.dashboard-overview-page .admin-signal-card span {
    display: block;
    font-size: .76rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--admin-muted);
    margin-bottom: .25rem;
    font-weight: 700;
}
.dashboard-overview-page .admin-depth-highlight strong,
.dashboard-overview-page .admin-signal-card strong {
    display: block;
    font-size: 1.1rem;
    color: var(--admin-text);
}
.dashboard-overview-page .admin-depth-highlight small,
.dashboard-overview-page .admin-signal-card small {
    display: block;
    color: var(--admin-muted);
    margin-top: .2rem;
}
.dashboard-overview-page .admin-depth-chart {
    display: grid;
    gap: .9rem;
}
.dashboard-overview-page .admin-depth-row {
    display: grid;
    grid-template-columns: 92px minmax(0, 1fr) 72px;
    gap: .9rem;
    align-items: center;
}
.dashboard-overview-page .admin-depth-day strong,
.dashboard-overview-page .admin-depth-conversion strong {
    display: block;
    color: var(--admin-text);
}
.dashboard-overview-page .admin-depth-day small,
.dashboard-overview-page .admin-depth-conversion span {
    color: var(--admin-muted);
    font-size: .78rem;
}
.dashboard-overview-page .admin-depth-track-wrap {
    display: grid;
    gap: .45rem;
}
.dashboard-overview-page .admin-depth-metric {
    display: grid;
    grid-template-columns: 58px minmax(0, 1fr) 88px;
    gap: .65rem;
    align-items: center;
}
.dashboard-overview-page .admin-depth-metric.compact {
    grid-template-columns: 58px minmax(0, 1fr) 54px;
}
.dashboard-overview-page .admin-depth-metric span {
    color: var(--admin-muted);
    font-size: .76rem;
    font-weight: 700;
}
.dashboard-overview-page .admin-depth-track {
    height: 12px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--admin-surface-alt) 72%, white);
    overflow: hidden;
}
.dashboard-overview-page .admin-depth-track.thin {
    height: 8px;
}
.dashboard-overview-page .admin-depth-fill {
    height: 100%;
    border-radius: 999px;
}
.dashboard-overview-page .admin-depth-fill.revenue {
    background: linear-gradient(90deg, var(--admin-primary), color-mix(in srgb, var(--admin-primary) 55%, white));
}
.dashboard-overview-page .admin-depth-fill.orders {
    background: linear-gradient(90deg, var(--admin-sidebar), color-mix(in srgb, var(--admin-sidebar) 72%, var(--admin-primary)));
}
.dashboard-overview-page .admin-signal-stack {
    display: grid;
    gap: .85rem;
}

.dashboard-overview-page .admin-priority-card {
    border-width: 1px;
    border-style: solid;
}
.dashboard-overview-page .admin-priority-card.tone-danger {
    border-color: rgba(239, 68, 68, .24);
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-danger-soft) 74%, white), color-mix(in srgb, var(--admin-surface) 98%, white));
}
.dashboard-overview-page .admin-priority-card.tone-warning {
    border-color: rgba(245, 158, 11, .22);
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-warning-soft) 80%, white), color-mix(in srgb, var(--admin-surface) 98%, white));
}
.dashboard-overview-page .admin-priority-card.tone-info {
    border-color: rgba(59, 130, 246, .20);
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-info-soft) 82%, white), color-mix(in srgb, var(--admin-surface) 98%, white));
}
.dashboard-overview-page .admin-priority-card.tone-success,
.dashboard-overview-page .admin-priority-card.tone-neutral {
    border-color: rgba(148, 163, 184, .18);
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface-alt) 64%, white), color-mix(in srgb, var(--admin-surface) 98%, white));
}
.dashboard-overview-page .admin-priority-head {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr);
    gap: 1rem;
    align-items: center;
    margin-bottom: 1rem;
}
.dashboard-overview-page .admin-priority-icon,
.dashboard-overview-page .admin-smart-item-icon {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--admin-surface-alt) 68%, white);
    color: var(--admin-text);
    font-size: 1.3rem;
}
.dashboard-overview-page .admin-priority-head span {
    display: block;
    color: var(--admin-muted);
    text-transform: uppercase;
    font-size: .74rem;
    letter-spacing: .05em;
    font-weight: 700;
    margin-bottom: .25rem;
}
.dashboard-overview-page .admin-priority-head strong {
    display: block;
    color: var(--admin-text);
    font-size: 1.2rem;
    line-height: 1.45;
}
.dashboard-overview-page .admin-priority-head small,
.dashboard-overview-page .admin-priority-copy,
.dashboard-overview-page .admin-smart-metric {
    color: var(--admin-muted);
}
.dashboard-overview-page .admin-priority-copy {
    font-size: .95rem;
    line-height: 1.75;
    margin-bottom: 1rem;
}
.dashboard-overview-page .admin-smart-stack {
    display: grid;
    gap: .9rem;
}
.dashboard-overview-page .admin-smart-item {
    display: grid;
    grid-template-columns: 48px minmax(0, 1fr);
    gap: .85rem;
    align-items: start;
    border-radius: 18px;
    padding: .9rem 1rem;
    border: 1px solid rgba(226, 232, 240, .95);
    background: color-mix(in srgb, var(--admin-surface) 98%, white);
}
.dashboard-overview-page .admin-smart-item.tone-danger { background: color-mix(in srgb, var(--admin-danger-soft) 84%, white); border-color: rgba(239, 68, 68, .14); }
.dashboard-overview-page .admin-smart-item.tone-warning { background: color-mix(in srgb, var(--admin-warning-soft) 86%, white); border-color: rgba(245, 158, 11, .16); }
.dashboard-overview-page .admin-smart-item.tone-info { background: color-mix(in srgb, var(--admin-info-soft) 86%, white); border-color: rgba(59, 130, 246, .14); }
.dashboard-overview-page .admin-smart-item.tone-success { background: color-mix(in srgb, var(--admin-success-soft) 88%, white); border-color: rgba(34, 197, 94, .14); }
.dashboard-overview-page .admin-smart-item.tone-neutral { background: color-mix(in srgb, var(--admin-surface-alt) 76%, white); border-color: rgba(148, 163, 184, .16); }
.dashboard-overview-page .admin-smart-item .fw-semibold { color: var(--admin-text); margin-bottom: .2rem; }
.dashboard-overview-page .admin-smart-metric { font-size: .8rem; margin-top: .35rem; font-weight: 700; }
.dashboard-overview-page .admin-dot.paid { background: var(--admin-success); }
.dashboard-overview-page .admin-dot.failed { background: var(--admin-danger); }
.dashboard-overview-page .admin-dot.refunded { background: var(--admin-muted); }
.dashboard-overview-page .admin-signal-card.tone-success,
.dashboard-overview-page .admin-depth-highlight.tone-success { border-color: rgba(34, 197, 94, .22); background: color-mix(in srgb, var(--admin-success-soft) 88%, white); }
.dashboard-overview-page .admin-signal-card.tone-warning,
.dashboard-overview-page .admin-depth-highlight.tone-warning { border-color: rgba(245, 158, 11, .22); background: color-mix(in srgb, var(--admin-warning-soft) 88%, white); }
.dashboard-overview-page .admin-signal-card.tone-info,
.dashboard-overview-page .admin-depth-highlight.tone-info { border-color: rgba(59, 130, 246, .2); background: color-mix(in srgb, var(--admin-info-soft) 88%, white); }
.dashboard-overview-page .admin-signal-card.tone-neutral,
.dashboard-overview-page .admin-depth-highlight.tone-neutral { border-color: rgba(148, 163, 184, .18); background: color-mix(in srgb, var(--admin-surface-alt) 72%, white); }
@media (max-width: 991.98px) {
    .dashboard-overview-page .admin-command-card__value { font-size: 1.55rem; }
    .dashboard-overview-page .admin-workspace-item { padding: .85rem .9rem; }

    .dashboard-overview-page .admin-depth-grid,
    .dashboard-overview-page .admin-depth-row {
        grid-template-columns: 1fr;
    }
    .dashboard-overview-page .admin-depth-conversion {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
}
@media (max-width: 767.98px) {
    .dashboard-overview-page .admin-depth-metric,
    .dashboard-overview-page .admin-depth-metric.compact {
        grid-template-columns: 1fr;
    }
}


@media (max-width: 991.98px) {
    .dashboard-overview-page .admin-overview-strip,
    .dashboard-overview-page .admin-focus-strip,
    .dashboard-overview-page .admin-exec-stat-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 767.98px) {
    .dashboard-overview-page .admin-dashboard-jumpbar {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: .25rem;
    }
    .dashboard-overview-page .admin-jump-chip {
        white-space: nowrap;
    }
}
[dir="rtl"] .dashboard-overview-page .admin-jump-chip,
[dir="rtl"] .dashboard-overview-page .admin-reading-step,
[dir="rtl"] .dashboard-overview-page .admin-overview-item,
[dir="rtl"] .dashboard-overview-page .admin-context-card,
[dir="rtl"] .dashboard-overview-page .admin-watch-item,
[dir="rtl"] .dashboard-overview-page .admin-insight-item,
[dir="rtl"] .dashboard-overview-page .admin-quick-action,
[dir="rtl"] .dashboard-overview-page .admin-smart-item {
    text-align: right;
}



/* Theme sync pass for dashboard cards */
.dashboard-overview-page .admin-jump-chip,
.dashboard-overview-page .admin-overview-item,
.dashboard-overview-page .admin-focus-pill,
.dashboard-overview-page .admin-exec-stat,
.dashboard-overview-page .admin-context-card,
.dashboard-overview-page .admin-pulse-card,
.dashboard-overview-page .admin-status-note,
.dashboard-overview-page .admin-smart-item {
    background: color-mix(in srgb, var(--admin-surface) 94%, white);
    border-color: color-mix(in srgb, var(--admin-border) 88%, white);
    color: var(--admin-text);
}
.dashboard-overview-page .admin-jump-chip,
.dashboard-overview-page .admin-overview-item strong,
.dashboard-overview-page .admin-focus-pill strong,
.dashboard-overview-page .admin-exec-stat strong,
.dashboard-overview-page .admin-pulse-card strong,
.dashboard-overview-page .admin-smart-item .fw-semibold,
.dashboard-overview-page .admin-priority-head strong,
.dashboard-overview-page .admin-priority-icon,
.dashboard-overview-page .admin-smart-item-icon {
    color: var(--admin-text);
}
.dashboard-overview-page .admin-overview-item span,
.dashboard-overview-page .admin-focus-pill span,
.dashboard-overview-page .admin-exec-stat span,
.dashboard-overview-page .admin-pulse-card span,
.dashboard-overview-page .admin-status-note span,
.dashboard-overview-page .admin-priority-head span,
.dashboard-overview-page .admin-priority-copy,
.dashboard-overview-page .admin-priority-head small,
.dashboard-overview-page .admin-smart-metric,
.dashboard-overview-page .admin-hero-copy,
.dashboard-overview-page .admin-exec-stat small,
.dashboard-overview-page .admin-focus-pill small,
.dashboard-overview-page .admin-pulse-card small,
.dashboard-overview-page .admin-kpi-footnote {
    color: var(--admin-muted);
}
.dashboard-overview-page .admin-reading-card,
.dashboard-overview-page .admin-exec-panel {
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-surface-alt) 34%, white) 0%, var(--admin-surface) 100%);
    border-color: color-mix(in srgb, var(--admin-border) 88%, white);
}
.dashboard-overview-page .admin-dashboard-hero {
    border-color: color-mix(in srgb, var(--admin-primary) 18%, var(--admin-border));
    background: linear-gradient(135deg, color-mix(in srgb, var(--admin-primary-soft) 74%, white), color-mix(in srgb, var(--admin-accent-soft) 52%, white));
    box-shadow: 0 20px 40px color-mix(in srgb, var(--admin-primary) 12%, transparent);
}
.dashboard-overview-page .admin-reading-step strong,
.dashboard-overview-page .admin-context-icon,
.dashboard-overview-page .admin-watch-icon,
.dashboard-overview-page .admin-insight-icon,
.dashboard-overview-page .admin-quick-action-icon,
.dashboard-overview-page .admin-priority-icon,
.dashboard-overview-page .admin-smart-item-icon {
    background: linear-gradient(135deg, color-mix(in srgb, var(--admin-primary-soft) 70%, white), color-mix(in srgb, var(--admin-accent-soft) 55%, white));
    color: var(--admin-primary-dark);
}
.dashboard-overview-page .admin-priority-card.tone-danger,
.dashboard-overview-page .admin-smart-item.tone-danger,
.dashboard-overview-page .admin-signal-card.tone-danger,
.dashboard-overview-page .admin-depth-highlight.tone-danger {
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-accent) 10%, white), var(--admin-surface));
    border-color: color-mix(in srgb, var(--admin-accent) 22%, var(--admin-border));
}
.dashboard-overview-page .admin-priority-card.tone-warning,
.dashboard-overview-page .admin-smart-item.tone-warning,
.dashboard-overview-page .admin-signal-card.tone-warning,
.dashboard-overview-page .admin-depth-highlight.tone-warning {
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-primary-soft) 82%, white), var(--admin-surface));
    border-color: color-mix(in srgb, var(--admin-primary) 20%, var(--admin-border));
}
.dashboard-overview-page .admin-priority-card.tone-info,
.dashboard-overview-page .admin-smart-item.tone-info,
.dashboard-overview-page .admin-signal-card.tone-info,
.dashboard-overview-page .admin-depth-highlight.tone-info {
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-primary) 8%, white), color-mix(in srgb, var(--admin-accent-soft) 34%, white));
    border-color: color-mix(in srgb, var(--admin-primary) 18%, var(--admin-border));
}
.dashboard-overview-page .admin-priority-card.tone-success,
.dashboard-overview-page .admin-priority-card.tone-neutral,
.dashboard-overview-page .admin-smart-item.tone-success,
.dashboard-overview-page .admin-smart-item.tone-neutral,
.dashboard-overview-page .admin-signal-card.tone-success,
.dashboard-overview-page .admin-depth-highlight.tone-success,
.dashboard-overview-page .admin-signal-card.tone-neutral,
.dashboard-overview-page .admin-depth-highlight.tone-neutral {
    background: linear-gradient(180deg, color-mix(in srgb, var(--admin-primary-soft) 46%, white), var(--admin-surface));
    border-color: color-mix(in srgb, var(--admin-border) 88%, white);
}
.dashboard-overview-page .admin-dot.paid { background: color-mix(in srgb, var(--admin-primary) 76%, #16a34a); }
.dashboard-overview-page .admin-dot.failed { background: color-mix(in srgb, var(--admin-accent) 82%, #dc2626); }
.dashboard-overview-page .admin-dot.refunded { background: color-mix(in srgb, var(--admin-sidebar) 42%, white); }

</style>
@endsection
