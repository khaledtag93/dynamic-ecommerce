@php
    $analyticsNavRange = $range ?? request('range', '7d');
    $analyticsNavFrom = request('from_date');
    $analyticsNavTo = request('to_date');

    $analyticsNavItems = [
        [
            'label' => __('Overview'),
            'icon' => 'mdi-chart-areaspline',
            'hint' => __('Core revenue view'),
            'url' => route('admin.analytics.index', array_filter([
                'range' => $analyticsNavRange,
                'from_date' => $analyticsNavFrom,
                'to_date' => $analyticsNavTo,
            ])),
            'active' => request()->routeIs('admin.analytics.index'),
        ],
        [
            'label' => __('Growth'),
            'icon' => 'mdi-rocket-launch-outline',
            'hint' => __('Demand and retention'),
            'url' => route('admin.analytics.growth', array_filter([
                'range' => $analyticsNavRange,
                'from_date' => $analyticsNavFrom,
                'to_date' => $analyticsNavTo,
            ])),
            'active' => request()->routeIs('admin.analytics.growth'),
        ],
        [
            'label' => __('Offers'),
            'icon' => 'mdi-ticket-percent-outline',
            'hint' => __('Discount quality'),
            'url' => route('admin.analytics.offers', array_filter([
                'range' => $analyticsNavRange,
                'from_date' => $analyticsNavFrom,
                'to_date' => $analyticsNavTo,
            ])),
            'active' => request()->routeIs('admin.analytics.offers'),
        ],
    ];
@endphp

<style>
.analytics-nav{display:flex;gap:10px;flex-wrap:wrap}.analytics-nav-link{display:inline-flex;align-items:center;gap:10px;padding:10px 13px;border-radius:16px;background:var(--admin-surface);border:1px solid var(--admin-border);font-weight:700;color:var(--admin-text);text-decoration:none;transition:.18s ease;min-width:0}.analytics-nav-link:hover{border-color:color-mix(in srgb,var(--admin-primary) 35%,var(--admin-border));color:var(--admin-primary);transform:translateY(-1px)}.analytics-nav-link.active{background:var(--admin-primary);color:#fff;border-color:var(--admin-primary);box-shadow:0 10px 24px color-mix(in srgb,var(--admin-primary) 22%,transparent)}.analytics-nav-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:10px;background:color-mix(in srgb,var(--admin-primary) 9%,var(--admin-surface));color:var(--admin-primary);flex:0 0 auto}.analytics-nav-link.active .analytics-nav-icon{background:rgba(255,255,255,.16);color:#fff}.analytics-nav-copy{display:grid;min-width:0}.analytics-nav-label{line-height:1.2}.analytics-nav-hint{font-size:.76rem;font-weight:600;color:var(--admin-muted);line-height:1.3;margin-top:2px}.analytics-nav-link.active .analytics-nav-hint{color:rgba(255,255,255,.82)}@media(max-width:640px){.analytics-nav{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px;scrollbar-width:thin;scroll-snap-type:x proximity}.analytics-nav-link{flex:0 0 min(82vw,260px);scroll-snap-align:start}}
</style>

<nav class="analytics-nav" aria-label="{{ __('Analytics navigation') }}">
    @foreach ($analyticsNavItems as $item)
        <a href="{{ $item['url'] }}" class="analytics-nav-link {{ $item['active'] ? 'active' : '' }}">
            <span class="analytics-nav-icon"><i class="mdi {{ $item['icon'] }}"></i></span>
            <span class="analytics-nav-copy">
                <span class="analytics-nav-label">{{ $item['label'] }}</span>
                <span class="analytics-nav-hint">{{ $item['hint'] }}</span>
            </span>
        </a>
    @endforeach
</nav>
