@extends('layouts.admin')

@section('title', $pageMeta['title'] ?? __('Growth'))

@section('content')
@php
    $growthHelp = match ($pageMeta['key'] ?? 'overview') {
        'content' => [
            'title' => __('How to use Content & Journeys'),
            'intro' => __('Build growth journeys one layer at a time. Start with the campaign, then add targeting, message content, and experiments only when they add value.'),
            'items' => [
                ['title' => __('Campaigns'), 'body' => __('A campaign is the customer journey you want to run, such as cart recovery or a VIP offer.'), 'example' => __('Example: send a recovery message to customers who left checkout without paying.')],
                ['title' => __('Automation rules'), 'body' => __('Rules decide when a campaign is allowed to run, including trigger, timing, priority, and cooldown behavior.')],
                ['title' => __('Templates'), 'body' => __('Templates keep reusable Arabic and English message copy consistent across campaigns and channels.')],
                ['title' => __('Audience segments'), 'body' => __('Segments group customers using reusable targeting conditions so the same audience logic can be shared safely.')],
                ['title' => __('Experiments'), 'body' => __('Use experiments only when you want to compare two controlled variants and measure which performs better.')],
            ],
        ],
        'operations' => [
            'title' => __('How to use Growth Operations'),
            'intro' => __('Use this page to watch delivery health and investigate what the engine did. Test-data tools only appear outside Production.'),
            'items' => [
                ['title' => __('Deliveries'), 'body' => __('Check pending, sent, delivered, failed, or simulated messages. Retry only failed deliveries after reviewing the cause.')],
                ['title' => __('Trigger log'), 'body' => __('This shows why and when automation rules fired for a customer or guest session.')],
                ['title' => __('Message log'), 'body' => __('Use this history to confirm the channel, experiment participation, and send timing without opening campaign setup.')],
            ],
        ],
        'insights' => [
            'title' => __('How to read Growth Insights'),
            'intro' => __('Read the top metrics first, then use the detailed sections to understand campaign impact, retention, experiments, and customer risk.'),
            'items' => [
                ['title' => __('Attributed revenue'), 'body' => __('Revenue linked back to tracked growth touches inside the configured attribution window.')],
                ['title' => __('Retention'), 'body' => __('Cohort retention shows whether customers return after their first purchase over 30, 60, and 90 days.')],
                ['title' => __('Churn risk'), 'body' => __('Higher churn-risk scores indicate customers who may need a win-back journey sooner.')],
                ['title' => __('Experiments'), 'body' => __('Compare messages, conversions, and revenue before deciding whether a variant is worth keeping.')],
            ],
        ],
        default => [
            'title' => __('How to use Growth Overview'),
            'intro' => __('Use Overview as the control room: check health, confirm what is enabled, and choose the next action without opening every growth tool at once.'),
            'items' => [
                ['title' => __('Engine'), 'body' => __('Turns growth detection and automation processing on or off.')],
                ['title' => __('Messaging'), 'body' => __('Controls whether campaigns may create customer-facing messages. Keep it off while validating new journeys.')],
                ['title' => __('Real email'), 'body' => __('When disabled, email behavior can be validated without sending a real customer email.')],
                ['title' => __('Experiments'), 'body' => __('Allows active A/B variants to influence campaign messaging and offers.')],
            ],
        ],
    };
@endphp

<style>
.gm-shell{display:grid;gap:20px}.gm-hero,.gm-panel{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:22px;box-shadow:0 12px 30px rgba(15,23,42,.06)}
.gm-hero{padding:1.2rem 1.25rem}.gm-hero-top{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.gm-eyebrow{display:inline-flex;align-items:center;gap:.45rem;color:var(--admin-primary-dark);font-size:.78rem;font-weight:800}
.gm-title{font-size:30px;font-weight:800;line-height:1.15;color:var(--admin-text);margin:.45rem 0 .4rem}.gm-subtitle{color:var(--admin-muted);max-width:880px;font-size:14px;line-height:1.7}.gm-statusbar{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem;margin-top:1rem}
.gm-status{display:flex;align-items:center;justify-content:space-between;gap:.65rem;padding:.75rem .85rem;border:1px solid var(--admin-border);border-radius:14px;background:var(--admin-surface-alt)}.gm-status strong{font-size:.84rem;color:var(--admin-text)}.gm-status span{font-size:.75rem;font-weight:800;padding:.28rem .5rem;border-radius:999px}
.gm-status .is-on{background:var(--admin-success-bg);color:var(--admin-success-text)}.gm-status .is-off{background:var(--admin-danger-bg);color:var(--admin-danger-text)}
.gm-nav{display:flex;gap:.55rem;position:sticky;top:74px;z-index:8;overflow-x:auto;overscroll-behavior-inline:contain;padding:.25rem;background:color-mix(in srgb,var(--admin-body-bg, #f8fafc) 88%,transparent);scrollbar-width:thin}.gm-nav a{display:inline-flex;align-items:center;gap:.4rem;flex:0 0 auto;padding:.68rem .9rem;border-radius:12px;border:1px solid var(--admin-border);background:var(--admin-surface);color:var(--admin-text);font-weight:800;font-size:13px;text-decoration:none;white-space:nowrap}
.gm-nav a.active{background:var(--admin-primary-soft);border-color:color-mix(in srgb,var(--admin-primary) 28%,var(--admin-border));color:var(--admin-primary-dark)}.gm-nav a:hover{text-decoration:none;background:var(--admin-surface-alt);color:var(--admin-text)}
.gm-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.gm-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}.gm-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.gm-panel{padding:1rem 1.1rem}.gm-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.gm-section-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;margin:.1rem 0 -.25rem}.gm-section-heading h3{margin:0;font-size:1.08rem;font-weight:800;color:var(--admin-text)}.gm-section-heading p{margin:.3rem 0 0;color:var(--admin-muted);font-size:.86rem;line-height:1.6}
.gm-section{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:14px}.gm-section h4{margin:0;font-weight:800;color:var(--admin-text)}.gm-mini{font-size:12px;color:var(--admin-muted);line-height:1.6}.gm-help{font-size:13px;color:var(--admin-muted);line-height:1.6;margin-top:8px}.gm-value{font-size:28px;font-weight:800;color:var(--admin-text);line-height:1}
.gm-table{width:100%;margin:0}.gm-table th,.gm-table td{padding:11px 12px;border-bottom:1px solid var(--admin-border);vertical-align:top}.gm-table th{font-size:12px;text-transform:uppercase;color:var(--admin-muted);font-weight:800;letter-spacing:.04em;white-space:nowrap}.gm-table tbody tr:last-child td{border-bottom:none}
.gm-empty{padding:16px;border:1px dashed var(--admin-border);border-radius:16px;background:var(--admin-surface-alt);color:var(--admin-muted);font-size:14px;line-height:1.7}.gm-box{padding:14px;border:1px solid var(--admin-border);border-radius:16px;background:var(--admin-surface-alt)}.gm-stack{display:grid;gap:12px}.gm-alert{padding:14px 16px;border-radius:16px;background:var(--admin-primary-soft);color:var(--admin-primary-dark);font-size:13px;line-height:1.7;border:1px solid color-mix(in srgb,var(--admin-primary) 18%,var(--admin-border))}.gm-alert--warning{background:color-mix(in srgb,#f59e0b 12%,var(--admin-surface));border-color:color-mix(in srgb,#f59e0b 28%,var(--admin-border));color:var(--admin-text)}
.gm-toggle-list{display:grid;gap:.75rem}.gm-setting-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:1rem;padding:.9rem 1rem;border:1px solid var(--admin-border);border-radius:14px;background:var(--admin-surface-alt)}.gm-setting-copy{min-width:0}.gm-setting-title{display:block;margin:0 0 .18rem;color:var(--admin-text);font-weight:800;cursor:pointer}.gm-setting-switch{padding:0!important;margin:0!important;min-height:0}.gm-setting-switch .form-check-input{float:none!important;margin:0!important;cursor:pointer}.gm-module{padding:0;overflow:hidden}.gm-module>summary{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;cursor:pointer}.gm-module>summary::-webkit-details-marker{display:none}.gm-module>summary:hover{background:var(--admin-surface-alt)}
.gm-module-summary{display:flex;align-items:center;gap:.75rem;min-width:0}.gm-module-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:12px;background:var(--admin-primary-soft);color:var(--admin-primary-dark);flex:0 0 auto}.gm-module-title{font-weight:800;color:var(--admin-text)}.gm-count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;padding:.2rem .48rem;border-radius:999px;background:var(--admin-surface-alt);border:1px solid var(--admin-border);font-size:.75rem;font-weight:800;color:var(--admin-text)}.gm-module-body{padding:0 1.1rem 1.1rem;border-top:1px solid var(--admin-border)}
@media (max-width:1200px){.gm-grid,.gm-grid--three,.gm-two{grid-template-columns:repeat(2,minmax(0,1fr))}.gm-statusbar{grid-template-columns:repeat(2,minmax(0,1fr))}}
html[dir="rtl"] .gm-setting-row{text-align:right}
@media (max-width:768px){.gm-grid,.gm-grid--three,.gm-two,.gm-statusbar{grid-template-columns:1fr}.gm-title{font-size:25px}.gm-hero{padding:1rem}.gm-hero-top{align-items:flex-start}.gm-actions{align-items:stretch}.gm-actions .btn{width:100%;justify-content:center}.gm-nav{top:64px;margin-inline:-.2rem}.gm-section-heading{align-items:flex-start;flex-direction:column}.gm-setting-row{gap:.75rem}}
</style>

<div class="gm-shell">
    <header class="gm-hero">
        <nav class="admin-breadcrumbs mb-2" aria-label="{{ __('Breadcrumb') }}">
            <ol class="admin-breadcrumb-list">
                <li class="admin-breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a></li>
                <li class="admin-breadcrumb-item is-current"><span>{{ __('Growth') }}</span></li>
            </ol>
        </nav>

        <div class="gm-hero-top">
            <div>
                <div class="gm-eyebrow"><i class="mdi mdi-chart-timeline-variant"></i>{{ __('Growth center') }}</div>
                <h2 class="gm-title">{{ $pageMeta['heading'] ?? $pageMeta['title'] ?? __('Growth') }}</h2>
                <div class="gm-subtitle">{{ $pageMeta['description'] ?? __('Manage growth activity from focused workspaces with clear controls and simple explanations.') }}</div>
            </div>
            <x-admin.page-help :title="$growthHelp['title']" :intro="$growthHelp['intro']" :items="$growthHelp['items']" />
        </div>

        <div class="gm-statusbar" aria-label="{{ __('Growth status') }}">
            @foreach([
                [__('Engine'), $engineOn],
                [__('Messaging'), $messagingOn],
                [__('Real email'), $realEmailOn],
                [__('Experiments'), $experimentsOn],
            ] as [$label, $enabled])
                <div class="gm-status">
                    <strong>{{ $label }}</strong>
                    <span class="{{ $enabled ? 'is-on' : 'is-off' }}">{{ $enabled ? __('Enabled') : __('Disabled') }}</span>
                </div>
            @endforeach
        </div>
    </header>

    <nav class="gm-nav" aria-label="{{ __('Growth workspace navigation') }}">
        <a href="{{ $quickLinks['overview'] }}" class="{{ ($pageMeta['key'] ?? 'overview') === 'overview' ? 'active' : '' }}"><i class="mdi mdi-view-dashboard-outline"></i>{{ __('Overview') }}</a>
        <a href="{{ $quickLinks['content'] }}" class="{{ ($pageMeta['key'] ?? 'overview') === 'content' ? 'active' : '' }}"><i class="mdi mdi-source-branch"></i>{{ __('Content & Journeys') }}</a>
        <a href="{{ $quickLinks['operations'] }}" class="{{ ($pageMeta['key'] ?? 'overview') === 'operations' ? 'active' : '' }}"><i class="mdi mdi-cog-sync-outline"></i>{{ __('Operations') }}</a>
        <a href="{{ $quickLinks['insights'] }}" class="{{ ($pageMeta['key'] ?? 'overview') === 'insights' ? 'active' : '' }}"><i class="mdi mdi-chart-box-outline"></i>{{ __('Insights') }}</a>
    </nav>

    @yield('growth-module-content')
</div>
@endsection
