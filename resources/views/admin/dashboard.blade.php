@extends('layouts.admin')

@section('title', __('Admin Dashboard'))

@section('content')
@php($can = fn (string $permission): bool => auth()->user()?->hasPermission($permission) ?? false)
<div class="admin-page-shell admin-home">
    <x-admin.page-header :kicker="__('Store overview')" :title="__('Admin dashboard')" :description="__('A clear view of recent activity and the next actions for your store.')">
        @if($can('catalog.manage'))
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-plus"></i><span>{{ __('Add product') }}</span></a>
        @endif
        @if($can('orders.view'))
            <a href="{{ route('admin.orders.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-cart-outline"></i><span>{{ __('Review orders') }}</span></a>
        @endif
    </x-admin.page-header>

    <form action="{{ route('admin.dashboard') }}" method="GET" class="admin-home-mobile-search d-lg-none mb-3" role="search">
        <label for="admin-home-search" class="visually-hidden">{{ __('Search products, orders, customers, and coupons') }}</label>
        <input id="admin-home-search" type="search" name="q" class="form-control" value="{{ $q }}" placeholder="{{ __('Search products, orders, customers, and coupons') }}">
        <button type="submit" class="btn btn-primary" aria-label="{{ __('Search') }}"><i class="mdi mdi-magnify"></i></button>
    </form>

    <nav class="admin-home-jump" aria-label="{{ __('Page sections') }}">
        <a href="#home-overview">{{ __('Overview') }}</a>
        <a href="#home-priorities">{{ __('Needs attention') }}</a>
        <a href="#home-activity">{{ __('Recent activity') }}</a>
    </nav>

    @if($q !== '')
        <section class="admin-card mb-4" id="dashboard-search" aria-labelledby="dashboard-search-title">
            <div class="admin-card-body">
                <div class="admin-home-section-heading mb-3">
                    <div><div class="admin-kicker">{{ __('Quick lookup') }}</div><h2 id="dashboard-search-title">{{ __('Search results') }}</h2><p class="text-muted small mb-0">{{ __('Showing quick matches for') }} <strong>{{ $q }}</strong></p></div>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-light border btn-sm">{{ __('Clear search') }}</a>
                </div>
                <div class="row g-3">
                    @foreach([
                        'orders' => ['label' => __('Orders'), 'route' => 'admin.orders.show', 'field' => 'order_number', 'permission' => 'orders.view'],
                        'products' => ['label' => __('Products'), 'route' => 'admin.products.edit', 'field' => 'name', 'permission' => 'catalog.manage'],
                        'customers' => ['label' => __('Customers'), 'route' => 'admin.customers.show', 'field' => 'name', 'permission' => 'customers.manage'],
                        'coupons' => ['label' => __('Coupons'), 'route' => 'admin.coupons.edit', 'field' => 'code', 'permission' => 'promotions.manage'],
                        'categories' => ['label' => __('Categories'), 'route' => 'admin.categories.edit', 'field' => 'name', 'permission' => 'catalog.manage'],
                    ] as $key => $meta)
                        @if($can($meta['permission']))
                            <div class="col-md-6 col-xl-4">
                                <div class="admin-home-search-group h-100">
                                    <h3>{{ $meta['label'] }}</h3>
                                    @forelse($searchResults[$key] as $item)
                                        <a href="{{ route($meta['route'], $item) }}">{{ data_get($item, $meta['field']) }} <i class="mdi mdi-arrow-top-right"></i></a>
                                    @empty
                                        <p class="text-muted small mb-0">{{ __('No matches yet.') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="home-overview" aria-labelledby="home-overview-title" class="mb-4">
        <div class="admin-home-section-heading">
            <div><div class="admin-kicker">{{ __('Last 30 days') }}</div><h2 id="home-overview-title">{{ __('At a glance') }}</h2></div>
            <a href="{{ route('admin.analytics.index') }}" class="btn btn-light border btn-sm btn-text-icon"><span>{{ __('Explore analytics') }}</span><i class="mdi mdi-arrow-top-right"></i></a>
        </div>
        <div class="row g-3">
            @foreach($kpiCards as $kpi)
                <div class="col-sm-6 col-xl-3">
                    <div class="admin-card admin-home-metric h-100">
                        <div class="admin-home-metric-icon"><i class="mdi {{ $kpi['icon'] }}"></i></div>
                        <div class="admin-home-metric-label">{{ $kpi['label'] }}</div>
                        <div class="admin-home-metric-value">{{ $kpi['value'] }}</div>
                        <p class="mb-0 text-muted small">{{ $kpi['copy'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section id="home-priorities" aria-labelledby="home-priorities-title" class="mb-4">
        <div class="admin-home-section-heading">
            <div><div class="admin-kicker">{{ __('Daily workflow') }}</div><h2 id="home-priorities-title">{{ __('Needs attention') }}</h2></div>
        </div>
        <div class="row g-3">
            @if($can('orders.view'))
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('admin.orders.index') }}" class="admin-card admin-home-task d-flex h-100 text-decoration-none">
                        <span class="admin-home-task-icon"><i class="mdi mdi-cart-outline"></i></span>
                        <span class="admin-home-task-copy"><strong>{{ __('Pending orders') }}</strong><small>{{ __('Review the order queue') }}</small></span>
                        <span class="admin-home-task-count">{{ number_format($stats['orders_pending']) }}</span>
                    </a>
                </div>
            @endif
            @if($can('catalog.manage'))
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('admin.products.index') }}" class="admin-card admin-home-task d-flex h-100 text-decoration-none">
                        <span class="admin-home-task-icon"><i class="mdi mdi-package-variant-closed"></i></span>
                        <span class="admin-home-task-copy"><strong>{{ __('Low-stock products') }}</strong><small>{{ __('Review inventory') }}</small></span>
                        <span class="admin-home-task-count">{{ number_format($stats['products_low_stock']) }}</span>
                    </a>
                </div>
            @endif
            @if($can('payments.view'))
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('admin.payments.index') }}" class="admin-card admin-home-task d-flex h-100 text-decoration-none">
                        <span class="admin-home-task-icon"><i class="mdi mdi-credit-card-off-outline"></i></span>
                        <span class="admin-home-task-copy"><strong>{{ __('Failed payments') }}</strong><small>{{ __('Review payment records') }}</small></span>
                        <span class="admin-home-task-count">{{ number_format($failedPaymentsCount) }}</span>
                    </a>
                </div>
            @endif
        </div>
    </section>

    <section class="mb-4" aria-labelledby="home-workspaces-title">
        <div class="admin-home-section-heading"><div><div class="admin-kicker">{{ __('Quick access') }}</div><h2 id="home-workspaces-title">{{ __('Your workspaces') }}</h2></div></div>
        <div class="admin-card admin-home-links">
            @if($can('orders.view'))<a href="{{ route('admin.orders.index') }}"><i class="mdi mdi-cart-outline"></i><span>{{ __('Orders') }}</span><i class="mdi mdi-arrow-top-right"></i></a>@endif
            @if($can('catalog.manage'))<a href="{{ route('admin.products.index') }}"><i class="mdi mdi-package-variant-closed"></i><span>{{ __('Products') }}</span><i class="mdi mdi-arrow-top-right"></i></a>@endif
            @if($can('growth.view'))<a href="{{ route('admin.growth.index') }}"><i class="mdi mdi-chart-line"></i><span>{{ __('Growth Engine') }}</span><i class="mdi mdi-arrow-top-right"></i></a>@endif
            @if($can('settings.manage'))<a href="{{ route('admin.settings.branding') }}"><i class="mdi mdi-palette-outline"></i><span>{{ __('Brand & Identity') }}</span><i class="mdi mdi-arrow-top-right"></i></a>@endif
            @if($can('notifications.view'))<a href="{{ route('admin.notifications.index') }}"><i class="mdi mdi-bell-outline"></i><span>{{ __('Admin Inbox') }}</span><i class="mdi mdi-arrow-top-right"></i></a>@endif
        </div>
    </section>

    <section id="home-activity" aria-labelledby="home-activity-title">
        <div class="admin-home-section-heading"><div><div class="admin-kicker">{{ __('Daily workflow') }}</div><h2 id="home-activity-title">{{ __('Recent activity') }}</h2></div></div>
        <div class="row g-3">
            @if($can('orders.view'))
                <div class="col-xl-7">
                    <div class="admin-card h-100">
                        <div class="admin-home-card-head"><h3>{{ __('Recent orders') }}</h3><a href="{{ route('admin.orders.index') }}">{{ __('View all') }}</a></div>
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Status') }}</th><th>{{ __('Total') }}</th></tr></thead>
                                <tbody>
                                    @forelse($recentOrders as $order)
                                        <tr>
                                            <td><a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold">{{ $order->order_number }}</a></td>
                                            <td>{{ $order->customer_name }}</td>
                                            <td><span class="badge admin-status-badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span></td>
                                            <td class="fw-semibold text-nowrap">EGP {{ number_format($order->grand_total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No orders yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
            @if($can('catalog.manage'))
                <div class="col-xl-5">
                    <div class="admin-card h-100">
                        <div class="admin-home-card-head"><div><h3>{{ __('Stock snapshot') }}</h3><p class="text-muted small mb-0">{{ __('Products with the lowest available quantity.') }}</p></div><a href="{{ route('admin.products.index') }}">{{ __('View all') }}</a></div>
                        <div class="admin-home-stock-list">
                            @forelse($lowStockProducts as $product)
                                <a href="{{ route('admin.products.edit', $product) }}"><span>{{ $product->name }}</span><strong>{{ number_format($product->quantity) }}</strong></a>
                            @empty
                                <p class="text-muted mb-0">{{ __('No products yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

@push('styles')
<style>
.admin-home { max-width: 1540px; margin-inline: auto; }
.admin-home-mobile-search { display: flex; gap: .5rem; }
.admin-home-jump { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.5rem; }
.admin-home-jump a { padding: .55rem .85rem; border-radius: 999px; color: var(--admin-text); background: var(--admin-surface); border: 1px solid var(--admin-border); text-decoration: none; font-weight: 600; font-size: .88rem; }
.admin-home-jump a:hover, .admin-home-jump a:focus-visible { color: var(--admin-primary-dark); border-color: var(--admin-primary); }
.admin-home-section-heading { display: flex; align-items: end; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; }
.admin-home-section-heading h2 { margin: .25rem 0 0; font-size: clamp(1.25rem, 2vw, 1.55rem); font-weight: 800; color: var(--admin-text); }
.admin-home-metric { padding: 1.25rem; }
.admin-home-metric-icon, .admin-home-task-icon { width: 2.7rem; height: 2.7rem; border-radius: .85rem; display: inline-grid; place-items: center; background: var(--admin-primary-soft); color: var(--admin-primary-dark); font-size: 1.35rem; }
.admin-home-metric-label { margin-top: 1rem; color: var(--admin-muted); font-size: .9rem; font-weight: 700; }
.admin-home-metric-value { margin: .4rem 0; color: var(--admin-text); font-size: clamp(1.35rem, 2vw, 1.85rem); font-weight: 800; line-height: 1.2; overflow-wrap: anywhere; }
.admin-home-task { align-items: center; gap: .9rem; padding: 1rem; color: var(--admin-text); transition: border-color .15s, transform .15s; }
.admin-home-task:hover { border-color: var(--admin-primary); transform: translateY(-2px); color: var(--admin-text); }
.admin-home-task-copy { display: flex; flex-direction: column; gap: .2rem; flex: 1; min-width: 0; }
.admin-home-task-copy small { color: var(--admin-muted); }
.admin-home-task-count { font-size: 1.4rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.admin-home-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); padding: .5rem; }
.admin-home-links a { display: flex; align-items: center; gap: .7rem; padding: .9rem; border-radius: .8rem; color: var(--admin-text); text-decoration: none; font-weight: 700; }
.admin-home-links a:hover, .admin-home-links a:focus-visible { background: var(--admin-primary-soft); color: var(--admin-primary-dark); }
.admin-home-links a span { flex: 1; }.admin-home-links a i:first-child { font-size: 1.3rem; color: var(--admin-primary-dark); }
.admin-home-card-head { display: flex; align-items: start; justify-content: space-between; gap: 1rem; padding: 1.25rem; }
.admin-home-card-head h3, .admin-home-search-group h3 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--admin-text); }
.admin-home-card-head a { white-space: nowrap; font-weight: 700; }.admin-home-stock-list { padding: 0 1.25rem 1.25rem; }
.admin-home-stock-list a, .admin-home-search-group a { display: flex; justify-content: space-between; gap: 1rem; padding: .8rem 0; border-top: 1px solid var(--admin-border); color: var(--admin-text); text-decoration: none; }
.admin-home-stock-list a:hover, .admin-home-search-group a:hover { color: var(--admin-primary-dark); }
.admin-home-stock-list a strong { font-variant-numeric: tabular-nums; }.admin-home-search-group { padding: 1rem; border: 1px solid var(--admin-border); border-radius: 1rem; }
.admin-home-search-group h3 { margin-bottom: .5rem; }
@media (max-width: 575.98px) { .admin-home-task { padding: .85rem; }.admin-home-card-head { padding: 1rem; }.admin-home-stock-list { padding-inline: 1rem; } }
</style>
@endpush
