@extends('layouts.admin')

@section('title', __('Growth Automation'))

@php
    $summary = $snapshot['summary'] ?? [];
    $campaigns = collect($snapshot['campaigns'] ?? []);
    $productOpportunities = collect($snapshot['product_opportunities'] ?? []);
    $couponCandidates = collect($snapshot['coupon_candidates'] ?? []);
    $activeRules = collect($snapshot['active_rules'] ?? []);
    $activePromotions = collect($snapshot['active_promotions'] ?? []);

    $signalCards = [
        [
            'label' => __('Warm cart sessions'),
            'value' => (int) ($summary['warm_cart_sessions'] ?? 0),
            'help' => __('High-intent sessions with cart activity but no purchase yet.'),
            'theme' => 'warn',
        ],
        [
            'label' => __('Checkout drop sessions'),
            'value' => (int) ($summary['checkout_drop_sessions'] ?? 0),
            'help' => __('Users who reached checkout and need reassurance or urgency.'),
            'theme' => 'danger',
        ],
        [
            'label' => __('Repeat buyer candidates'),
            'value' => (int) ($summary['repeat_customer_candidates'] ?? 0),
            'help' => __('Customers with momentum for loyalty, bundle, or replenishment campaigns.'),
            'theme' => 'success',
        ],
        [
            'label' => __('Discounted orders'),
            'value' => (int) ($summary['discounted_orders'] ?? 0),
            'help' => __('Orders already influenced by pricing incentives in the selected range.'),
            'theme' => 'neutral',
        ],
    ];

    $signalMax = max(1, collect($signalCards)->max('value'));
    $prioritySignal = collect($signalCards)->sortByDesc('value')->first();
    $focusHeadline = __('Audience pressure is centered on :label.', ['label' => data_get($prioritySignal, 'label', __('No active signal'))]);

    $recommendedActions = [
        __('Move the strongest signal into a concrete campaign or rule adjustment.'),
        __('Keep messaging tight and relevant so urgency does not become noise.'),
        __('Review channel choice, coupon support, and product fit together rather than in isolation.'),
    ];

    $recommendedFocus = match (data_get($prioritySignal, 'label')) {
        __('Checkout drop sessions') => __('Focus on checkout reassurance, payment trust, and urgency reminders.'),
        __('Warm cart sessions') => __('Focus on cart recovery, nudges, and low-friction reminders.'),
        __('Repeat buyer candidates') => __('Focus on loyalty, bundles, and replenishment campaigns.'),
        default => __('Focus on maintaining healthy promotional efficiency and message relevance.'),
    };

    $exportRows = [
        ['label' => __('Warm cart sessions'), 'value' => number_format((int) ($summary['warm_cart_sessions'] ?? 0)), 'context' => __('Recoverable purchase intent still sitting in carts.')],
        ['label' => __('Checkout drop sessions'), 'value' => number_format((int) ($summary['checkout_drop_sessions'] ?? 0)), 'context' => __('Sessions that reached checkout then exited.')],
        ['label' => __('Repeat buyer candidates'), 'value' => number_format((int) ($summary['repeat_customer_candidates'] ?? 0)), 'context' => __('Customers ready for loyalty or replenishment plays.')],
        ['label' => __('Discounted orders'), 'value' => number_format((int) ($summary['discounted_orders'] ?? 0)), 'context' => __('Orders already influenced by pricing incentives in the selected range.')],
        ['label' => __('Active automation rules'), 'value' => number_format($activeRules->count()), 'context' => __('Live rule coverage in the current setup.')],
        ['label' => __('Campaign blueprints'), 'value' => number_format($campaigns->count()), 'context' => __('Available campaign concepts inside this dashboard.')],
    ];
@endphp

@section('content')
<style>
.growth-shell{display:grid;gap:20px}.growth-grid,.growth-signal-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.growth-card,.growth-panel,.growth-focus{background:color-mix(in srgb, var(--admin-surface) 98%, white);border:1px solid color-mix(in srgb, var(--admin-border) 88%, white);border-radius:22px;box-shadow:0 18px 45px rgba(15,23,42,.06)}.growth-card,.growth-focus{padding:20px}.growth-kicker{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--admin-muted)}.growth-value{font-size:28px;font-weight:800;color:var(--admin-text);margin-top:6px}.growth-help{color:var(--admin-muted);font-size:13px;margin-top:6px;line-height:1.6}.growth-panel{padding:22px}.growth-panel h4{margin:0 0 14px;font-weight:800}.growth-table{width:100%;min-width:720px}.growth-table th,.growth-table td{padding:10px 12px;border-bottom:1px solid color-mix(in srgb, var(--admin-border) 72%, white);vertical-align:top}.growth-table th{font-size:12px;text-transform:uppercase;color:var(--admin-muted);white-space:nowrap}.pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:color-mix(in srgb, var(--admin-info-soft) 86%, white);color:var(--admin-info)}.pill-success{background:color-mix(in srgb, var(--admin-success-soft) 86%, white);color:var(--admin-success)}.pill-warn{background:color-mix(in srgb, var(--admin-primary-soft) 70%, white);color:var(--admin-primary-dark)}.pill-danger{background:color-mix(in srgb, var(--admin-danger-soft) 86%, white);color:var(--admin-danger)}.growth-two{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}.growth-list{display:grid;gap:12px}.growth-item{padding:14px;border:1px solid color-mix(in srgb, var(--admin-border) 84%, white);border-radius:16px;background:color-mix(in srgb, var(--admin-surface-alt) 70%, white)}.growth-item h5{margin:0 0 8px;font-size:16px;font-weight:800}.growth-meta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}.growth-empty{padding:20px;border:1px dashed color-mix(in srgb, var(--admin-border) 76%, white);border-radius:16px;background:color-mix(in srgb, var(--admin-surface-alt) 70%, white);color:var(--admin-muted)}.growth-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.growth-filter .form-control,.growth-filter .form-select{min-width:170px;border-radius:14px}.growth-card,.growth-panel,.growth-item,.growth-two>*,.growth-grid>*,.growth-signal-grid>*{min-width:0}.growth-panel .table-responsive{-webkit-overflow-scrolling:touch}.growth-meta .pill,.growth-panel .text-muted{overflow-wrap:anywhere}.growth-focus{display:grid;gap:14px;background:linear-gradient(135deg, color-mix(in srgb, var(--admin-primary-soft) 70%, white), color-mix(in srgb, var(--admin-surface) 98%, white))}.growth-focus-list{display:grid;gap:10px}.growth-focus-item{padding:12px 14px;border-radius:16px;background:rgba(255,255,255,.8);border:1px solid color-mix(in srgb, var(--admin-primary) 16%, var(--admin-border))}.growth-signal-card{padding:18px;border-radius:20px;border:1px solid color-mix(in srgb, var(--admin-border) 88%, white);background:color-mix(in srgb, var(--admin-surface) 98%, white);box-shadow:0 16px 35px rgba(15,23,42,.05)}.growth-meter{height:8px;border-radius:999px;background:color-mix(in srgb, var(--admin-surface-alt) 70%, white);overflow:hidden;margin-top:10px}.growth-meter-fill{height:100%;border-radius:999px;background:linear-gradient(90deg, var(--admin-primary), color-mix(in srgb, var(--admin-primary) 55%, white))}.growth-signal-card.success .growth-meter-fill{background:linear-gradient(90deg, var(--admin-success), color-mix(in srgb, var(--admin-success) 55%, white))}.growth-signal-card.danger .growth-meter-fill{background:linear-gradient(90deg, var(--admin-danger), color-mix(in srgb, var(--admin-danger) 55%, white))}.growth-signal-card.warn .growth-meter-fill{background:linear-gradient(90deg, var(--admin-primary-dark), color-mix(in srgb, var(--admin-primary) 55%, white))}.growth-story-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}.growth-story-card{padding:22px;border-radius:22px;background:linear-gradient(135deg, var(--admin-sidebar), color-mix(in srgb, var(--admin-sidebar) 76%, var(--admin-primary)));color:#fff;border:1px solid color-mix(in srgb, var(--admin-border) 88%, white);box-shadow:0 20px 45px rgba(15,23,42,.16)}.growth-story-card .eyebrow{font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.72)}.growth-story-card .headline{font-size:1.5rem;font-weight:800;line-height:1.4;margin-top:8px}.growth-story-card .subcopy{font-size:.92rem;line-height:1.75;color:rgba(255,255,255,.82);margin-top:10px}.growth-story-bullets{display:grid;gap:10px;margin-top:16px}.growth-story-bullets div{padding:12px 14px;border-radius:16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08)}.growth-lane-card{padding:20px;border-radius:22px;background:color-mix(in srgb, var(--admin-surface) 98%, white);border:1px solid color-mix(in srgb, var(--admin-border) 88%, white);box-shadow:0 18px 45px rgba(15,23,42,.05)}.growth-lane-list{display:grid;gap:12px}.growth-lane-item{padding:14px 16px;border-radius:16px;background:color-mix(in srgb, var(--admin-surface-alt) 70%, white);border:1px solid color-mix(in srgb, var(--admin-border) 84%, white)}.growth-lane-label{font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;color:var(--admin-muted)}.growth-lane-value{font-weight:800;color:var(--admin-text);margin-top:5px}.growth-lane-help{font-size:.86rem;color:var(--admin-muted);margin-top:7px;line-height:1.6}.growth-signal-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}.growth-story-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.growth-story-mini{padding:14px;border-radius:16px;background:color-mix(in srgb, var(--admin-surface) 98%, white);border:1px solid color-mix(in srgb, var(--admin-border) 88%, white)}@media (max-width: 1200px){.growth-grid,.growth-two,.growth-signal-grid,.growth-story-kpis{grid-template-columns:1fr 1fr}.growth-story-grid{grid-template-columns:1fr}}@media (max-width: 768px){.growth-grid,.growth-two,.growth-signal-grid,.growth-story-kpis{grid-template-columns:1fr}.growth-panel{padding:16px}.growth-table{min-width:680px}}
</style>

<div class="growth-shell">
    <x-admin.page-header
        :kicker="__('Revenue intelligence')"
        :title="__('Growth Automation')"
        :description="__('Audience signals, conversion gaps, and campaign opportunities for :from → :to', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')])"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Growth Automation'), 'current' => true],
        ]"
    >
        <form method="GET" action="{{ route('admin.analytics.growth') }}" class="growth-filter">
            <select class="form-select" name="range">
                @foreach(['today' => __('Today'), '7d' => __('Last 7 days'), '30d' => __('Last 30 days'), '90d' => __('Last 90 days')] as $value => $label)
                    <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-dark" type="submit">{{ __('Apply') }}</button>
        </form>
    </x-admin.page-header>

    @include('admin.analytics._nav')
@include('admin.analytics._trust_panel', ['trust' => $trust ?? [], 'uiState' => $uiState ?? []])

    @include('admin.analytics._report_toolbar', [
        'title' => __('Growth Automation'),
        'subtitle' => __('Operator-friendly wording plus a compact summary block for reviews, exports, and stakeholder updates.'),
        'period' => $from->format('Y-m-d') . ' → ' . $to->format('Y-m-d'),
        'reportId' => 'growth-report',
        'exportRows' => $exportRows,
    ])

    <div class="growth-story-grid">
        <div class="growth-story-card">
            <div class="eyebrow">{{ __('Growth storytelling') }}</div>
            <div class="headline">{{ $focusHeadline }}</div>
            <div class="subcopy">{{ __('This view translates audience signals into a simple operating brief: where demand is warming, where friction is highest, and what the team should activate next.') }}</div>
            <div class="growth-story-bullets">
                @foreach ($recommendedActions as $line)
                    <div>{{ $line }}</div>
                @endforeach
            </div>
        </div>
        <div class="growth-lane-card">
            <div class="fw-bold fs-5">{{ __('Action lanes') }}</div>
            <div class="text-muted small mt-1 mb-3">{{ __('A cleaner read on what to activate, protect, and refine next.') }}</div>
            <div class="growth-lane-list">
                <div class="growth-lane-item">
                    <div class="growth-lane-label">{{ __('Activate') }}</div>
                    <div class="growth-lane-value">{{ __('Turn the strongest audience pressure into a live campaign path.') }}</div>
                    <div class="growth-lane-help">{{ __('Use the dominant signal as your first operating priority instead of spreading effort too thin.') }}</div>
                </div>
                <div class="growth-lane-item">
                    <div class="growth-lane-label">{{ __('Protect') }}</div>
                    <div class="growth-lane-value">{{ __('Avoid over-messaging high-intent users with weak or generic content.') }}</div>
                    <div class="growth-lane-help">{{ __('Pair urgency with trust, relevance, and channel discipline.') }}</div>
                </div>
                <div class="growth-lane-item">
                    <div class="growth-lane-label">{{ __('Refine') }}</div>
                    <div class="growth-lane-value">{{ __('Tighten product fit, offer support, and automation rule coverage.') }}</div>
                    <div class="growth-lane-help">{{ __('Use the opportunity and coupon tables below as the source of the next iteration.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="growth-focus">
        <div>
            <div class="fw-bold fs-5">{{ __('Executive focus') }}</div>
            <div class="text-muted small mt-1">{{ __('The dashboard highlights the most actionable audience pressure inside the selected reporting window.') }}</div>
        </div>
        <div class="growth-focus-list">
            <div class="growth-focus-item">{{ __('Primary pressure point: :label', ['label' => data_get($prioritySignal, 'label', __('No active signal'))]) }}</div>
            <div class="growth-focus-item">{{ $recommendedFocus }}</div>
            <div class="growth-focus-item">{{ __('Campaign blueprints available: :count · Active rules: :rules · Product signals: :signals', ['count' => number_format($campaigns->count()), 'rules' => number_format($activeRules->count()), 'signals' => number_format($productOpportunities->count())]) }}</div>
        </div>
    </div>

    @if (!($uiState['empty'] ?? false))
    <div class="growth-grid">
        @foreach ($signalCards as $signal)
            <div class="growth-card">
                <div class="growth-kicker">{{ $signal['label'] }}</div>
                <div class="growth-value">{{ number_format($signal['value']) }}</div>
                <div class="growth-help">{{ $signal['help'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="growth-signal-grid">
        @foreach ($signalCards as $signal)
            <div class="growth-signal-card {{ $signal['theme'] }}">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <div class="fw-bold">{{ $signal['label'] }}</div>
                    <span class="pill {{ $signal['theme'] === 'success' ? 'pill-success' : ($signal['theme'] === 'danger' ? 'pill-danger' : ($signal['theme'] === 'warn' ? 'pill-warn' : '')) }}">{{ number_format($signal['value']) }}</span>
                </div>
                <div class="growth-meter"><div class="growth-meter-fill" style="width: {{ min(100, ($signal['value'] / $signalMax) * 100) }}%"></div></div>
                <div class="growth-help">{{ __('Relative pressure compared with the strongest signal in this window.') }}</div>
            </div>
        @endforeach
    </div>

    <div class="growth-story-kpis">
        <div class="growth-story-mini">
            <div class="small text-muted">{{ __('Campaign blueprints') }}</div>
            <div class="fw-bold fs-4 mt-1">{{ number_format($campaigns->count()) }}</div>
        </div>
        <div class="growth-story-mini">
            <div class="small text-muted">{{ __('Active rules') }}</div>
            <div class="fw-bold fs-4 mt-1">{{ number_format($activeRules->count()) }}</div>
        </div>
        <div class="growth-story-mini">
            <div class="small text-muted">{{ __('Product signals') }}</div>
            <div class="fw-bold fs-4 mt-1">{{ number_format($productOpportunities->count()) }}</div>
        </div>
    </div>

    <div class="growth-two">
        <div class="growth-panel">
            <h4>{{ __('Recommended campaigns') }}</h4>
            <div class="growth-list">
                @forelse($campaigns as $campaign)
                    <div class="growth-item">
                        <div class="d-flex justify-content-between align-items-start" style="gap:12px;">
                            <div>
                                <h5>{{ __($campaign['title']) }}</h5>
                                <div class="growth-meta">
                                    <span class="pill">{{ __($campaign['channel']) }}</span>
                                    <span class="pill pill-success">{{ __('Audience') }}: {{ number_format((int) ($campaign['audience_size'] ?? 0)) }}</span>
                                    @if(!empty($campaign['coupon_code']))
                                        <span class="pill pill-warn">{{ __('Coupon') }}: {{ $campaign['coupon_code'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="mb-2"><strong>{{ __('Goal:') }}</strong> {{ __($campaign['objective']) }}</div>
                        <div class="text-muted">{{ __($campaign['message']) }}</div>
                    </div>
                @empty
                    <div class="growth-empty">{{ __('No campaign blueprints are available yet.') }}</div>
                @endforelse
            </div>
        </div>

        <div class="growth-panel">
            <h4>{{ __('Automation readiness') }}</h4>
            @if($activeRules->isEmpty())
                <div class="growth-empty">{{ __('No saved automation rules yet. The engine page is ready, and you can start by adding rule records in the new growth_automation_rules table.') }}</div>
            @else
                <div class="growth-list">
                    @foreach($activeRules as $rule)
                        <div class="growth-item">
                            <h5>{{ __($rule->name) }}</h5>
                            <div class="growth-meta">
                                <span class="pill">{{ __($rule->channel) }}</span>
                                <span class="pill pill-success">{{ __($rule->trigger_type) }}</span>
                                <span class="pill pill-warn">{{ __('Priority') }} #{{ $rule->priority }}</span>
                            </div>
                            @if($rule->message)
                                <div class="text-muted">{{ __($rule->message) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="growth-panel">
        <div class="d-flex justify-content-between align-items-center mb-3" style="gap:12px;">
            <h4 class="mb-0">{{ __('Product opportunity signals') }}</h4>
            <small class="text-muted">{{ __('Focus on products getting attention without enough purchases.') }}</small>
        </div>
        <div class="table-responsive">
            <table class="growth-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Views') }}</th>
                        <th>{{ __('Adds') }}</th>
                        <th>{{ __('Purchases') }}</th>
                        <th>{{ __('View → Cart') }}</th>
                        <th>{{ __('View → Purchase') }}</th>
                        <th>{{ __('Signal') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productOpportunities as $row)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $row->product_name }}</div>
                                <a href="{{ route('admin.analytics.products.show', ['product' => $row->product_id, 'range' => $range, 'from_date' => request('from_date'), 'to_date' => request('to_date')]) }}" class="text-muted small">#{{ $row->product_id }}</a>
                            </td>
                            <td>{{ number_format((int) $row->views) }}</td>
                            <td>{{ number_format((int) $row->add_to_cart_count) }}</td>
                            <td>{{ number_format((int) $row->purchases) }}</td>
                            <td>{{ number_format((float) $row->view_to_cart_rate * 100, 1) }}%</td>
                            <td>{{ number_format((float) $row->view_to_purchase_rate * 100, 1) }}%</td>
                            <td>{{ $row->opportunity }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">{{ __('No product opportunity signals yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="growth-two">
        <div class="growth-panel">
            <h4>{{ __('Coupon candidates') }}</h4>
            <div class="table-responsive">
                <table class="growth-table">
                    <thead>
                        <tr>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Used') }}</th>
                            <th>{{ __('Remaining') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($couponCandidates as $coupon)
                            <tr>
                                <td><strong>{{ $coupon->code }}</strong><div class="text-muted small">{{ $coupon->name }}</div></td>
                                <td>{{ $coupon->type_label }}</td>
                                <td>{{ number_format((int) $coupon->used_count) }}</td>
                                <td>{{ $coupon->remaining_usage === null ? __('Unlimited') : number_format((int) $coupon->remaining_usage) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">{{ __('No active coupons found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="growth-panel">
        @if ($uiState['show_promotions'] ?? false)
            <h4>{{ __('Active promotions snapshot') }}</h4>
            <div class="growth-list">
                @forelse($activePromotions as $promotion)
                    <div class="growth-item">
                        <h5>{{ $promotion->name }}</h5>
                        <div class="growth-meta">
                            <span class="pill">{{ $promotion->type }}</span>
                            <span class="pill pill-success">{{ __('Priority') }} #{{ $promotion->priority }}</span>
                        </div>
                        <div class="text-muted">{{ __('Discount value') }}: {{ number_format((float) $promotion->discount_value, 2) }}</div>
                    </div>
                @empty
                    <div class="growth-empty">{{ __('No active promotions are available.') }}</div>
                @endforelse
            </div>
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
