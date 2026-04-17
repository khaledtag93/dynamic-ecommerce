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
.analytics-nav-wrap{display:grid;gap:12px}.analytics-nav{display:flex;gap:10px;flex-wrap:wrap}.analytics-nav-link{display:inline-flex;align-items:center;gap:10px;padding:11px 14px;border-radius:18px;background:#fff;border:1px solid rgba(15,23,42,.08);font-weight:700;color:#0f172a;text-decoration:none;transition:.2s ease;min-width:0}.analytics-nav-link:hover{border-color:rgba(249,115,22,.35);color:#ea580c;transform:translateY(-1px)}.analytics-nav-link.active{background:linear-gradient(135deg,#f97316,#fb923c);color:#fff;border-color:transparent;box-shadow:0 12px 24px rgba(249,115,22,.22)}.analytics-nav-icon{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:12px;background:#fff7ed;color:#c2410c;flex:0 0 auto}.analytics-nav-link.active .analytics-nav-icon{background:rgba(255,255,255,.18);color:#fff}.analytics-nav-copy{display:grid;min-width:0}.analytics-nav-label{line-height:1.2}.analytics-nav-hint{font-size:.78rem;font-weight:600;color:#64748b;line-height:1.3;margin-top:3px}.analytics-nav-link.active .analytics-nav-hint{color:rgba(255,255,255,.82)}.analytics-read-strip{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:12px 14px;border-radius:18px;background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:0 14px 30px rgba(15,23,42,.05)}.analytics-read-strip .title{font-size:.9rem;font-weight:800;color:#0f172a}.analytics-read-strip .help{font-size:.82rem;color:#64748b;line-height:1.55}.analytics-read-strip .chips{display:flex;gap:8px;flex-wrap:wrap}.analytics-read-strip .chip{display:inline-flex;align-items:center;padding:8px 11px;border-radius:999px;background:#f8fafc;border:1px solid rgba(15,23,42,.06);font-size:.78rem;font-weight:700;color:#334155}
</style>

<div class="analytics-nav-wrap">
    <div class="analytics-nav">
        @foreach ($analyticsNavItems as $item)
            <a href="{{ $item['url'] }}" class="analytics-nav-link {{ $item['active'] ? 'active' : '' }}">
                <span class="analytics-nav-icon"><i class="mdi {{ $item['icon'] }}"></i></span>
                <span class="analytics-nav-copy">
                    <span class="analytics-nav-label">{{ $item['label'] }}</span>
                    <span class="analytics-nav-hint">{{ $item['hint'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="analytics-read-strip">
        <div>
            <div class="title">{{ __('Operator reading mode') }}</div>
            <div class="help">{{ __('Read top to bottom: summary first, pressure second, then open the drilldown that explains the next action.') }}</div>
        </div>
        <div class="chips">
            <span class="chip">{{ __('Faster scan') }}</span>
            <span class="chip">{{ __('Less repetition') }}</span>
            <span class="chip">{{ __('Context preserved') }}</span>
        </div>
    </div>
</div>

@if (! empty($analyticsFlowItems ?? []))
    <div class="mt-3">
        @include('admin.analytics._flow_hub', [
            'flowTitle' => $analyticsFlowTitle ?? __('Cross-page flow'),
            'flowSubtitle' => $analyticsFlowSubtitle ?? __('Open the next page directly from the current insight without resetting your reporting window.'),
            'flowItems' => $analyticsFlowItems,
        ])
    </div>
@endif
