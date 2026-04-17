@extends('layouts.admin')

@section('title', $pageMeta['title'] ?? __('Growth'))

@section('content')
<style>
.gm-shell{display:grid;gap:18px}.gm-card,.gm-panel,.gm-soft{background:color-mix(in srgb, var(--admin-surface) 98%, white);border:1px solid color-mix(in srgb, var(--admin-border) 88%, white);border-radius:22px;box-shadow:0 14px 34px rgba(15,23,42,.06)}
.gm-card,.gm-panel,.gm-soft{padding:1rem 1.1rem}.gm-hero{padding:1.2rem 1.25rem;border-radius:26px;background:linear-gradient(135deg, color-mix(in srgb, var(--admin-surface) 98%, white) 0%, color-mix(in srgb, var(--admin-primary-soft) 70%, white) 100%);border:1px solid color-mix(in srgb, var(--admin-primary) 18%, var(--admin-border));box-shadow:0 16px 38px rgba(15,23,42,.08)}
.gm-kicker{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:color-mix(in srgb, var(--admin-info-soft) 86%, white);color:var(--admin-info);font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.gm-title{font-size:30px;font-weight:800;line-height:1.15;color:var(--admin-text);margin:12px 0 8px}.gm-subtitle{color:var(--admin-muted);max-width:960px;font-size:14px;line-height:1.7}
.gm-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.gm-two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.gm-three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.gm-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.gm-value{font-size:28px;font-weight:800;color:var(--admin-text);line-height:1}.gm-help{font-size:13px;color:var(--admin-muted);line-height:1.6;margin-top:8px}.gm-section{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:14px}.gm-section h4{margin:0;font-weight:800;color:var(--admin-text)}.gm-mini{font-size:12px;color:var(--admin-muted);line-height:1.6}.gm-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:800}.gm-chip.on{background:color-mix(in srgb, var(--admin-success-soft) 86%, white);color:var(--admin-success);border:1px solid #a7f3d0}.gm-chip.off{background:color-mix(in srgb, var(--admin-danger-soft) 86%, white);color:var(--admin-danger);border:1px solid #fecaca}.gm-chip.soft{background:color-mix(in srgb, var(--admin-surface-alt) 70%, white);color:var(--admin-text);border:1px solid color-mix(in srgb, var(--admin-border) 86%, white)}
.gm-nav{display:flex;gap:10px;flex-wrap:wrap;position:sticky;top:74px;z-index:8}.gm-nav a{display:inline-flex;align-items:center;padding:10px 14px;border-radius:999px;border:1px solid color-mix(in srgb, var(--admin-border) 86%, white);background:color-mix(in srgb, var(--admin-surface) 98%, white);color:var(--admin-text);font-weight:700;font-size:13px;text-decoration:none}.gm-nav a.active{background:color-mix(in srgb, var(--admin-primary-soft) 70%, white);border-color:rgba(249,115,22,.26);color:var(--admin-primary-dark)}.gm-nav a:hover{text-decoration:none;background:color-mix(in srgb, var(--admin-surface-alt) 70%, white);color:var(--admin-text)}.gm-table{width:100%;margin:0}.gm-table th,.gm-table td{padding:11px 12px;border-bottom:1px solid color-mix(in srgb, var(--admin-border) 72%, white);vertical-align:top}.gm-table th{font-size:12px;text-transform:uppercase;color:var(--admin-muted);font-weight:800;letter-spacing:.05em;white-space:nowrap}.gm-table tbody tr:last-child td{border-bottom:none}.gm-list{display:grid;gap:12px}.gm-item{display:flex;justify-content:space-between;gap:12px;padding:13px 14px;border:1px dashed color-mix(in srgb, var(--admin-border) 80%, white);border-radius:14px;background:color-mix(in srgb, var(--admin-surface) 98%, white)}.gm-item strong{font-size:15px;color:var(--admin-text)}.gm-empty{padding:16px;border:1px dashed color-mix(in srgb, var(--admin-border) 76%, white);border-radius:16px;background:color-mix(in srgb, var(--admin-surface-alt) 70%, white);color:var(--admin-muted);font-size:14px;line-height:1.7}.gm-box{padding:14px;border:1px solid color-mix(in srgb, var(--admin-border) 86%, white);border-radius:16px;background:color-mix(in srgb, var(--admin-surface-alt) 70%, white)}.gm-stack{display:grid;gap:12px}.gm-alert{padding:14px 16px;border-radius:16px;background:color-mix(in srgb, var(--admin-info-soft) 86%, white);color:var(--admin-info);font-size:13px;line-height:1.7;border:1px solid rgba(59,130,246,.15)}
@media (max-width:1200px){.gm-grid,.gm-three,.gm-two{grid-template-columns:1fr 1fr}}@media (max-width:768px){.gm-grid,.gm-three,.gm-two{grid-template-columns:1fr}.gm-title{font-size:26px}.gm-actions{flex-direction:column;align-items:stretch}.gm-actions .btn,.gm-nav a{width:100%;justify-content:center}}
</style>

<div class="gm-shell">
    <div class="gm-hero">
        <nav class="admin-breadcrumbs mb-2" aria-label="{{ __('Breadcrumb') }}">
            <ol class="admin-breadcrumb-list">
                <li class="admin-breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a></li>
                <li class="admin-breadcrumb-item is-current"><span>{{ __('Growth') }}</span></li>
            </ol>
        </nav>
        <span class="gm-kicker">{{ __('Growth module') }}</span>
        <h2 class="gm-title">{{ $pageMeta['title'] ?? __('Growth') }}</h2>
        <div class="gm-subtitle">{{ $pageMeta['description'] ?? __('Organize the growth workspace into lighter focused pages so the team can move faster.') }}</div>
        <div class="gm-actions mt-3">
            <span class="gm-chip {{ $engineOn ? 'on' : 'off' }}">{{ __('Engine') }}: {{ $engineOn ? __('Enabled') : __('Disabled') }}</span>
            <span class="gm-chip {{ $messagingOn ? 'on' : 'off' }}">{{ __('Messaging') }}: {{ $messagingOn ? __('Enabled') : __('Disabled') }}</span>
            <span class="gm-chip {{ $realEmailOn ? 'on' : 'off' }}">{{ __('Real email') }}: {{ $realEmailOn ? __('Enabled') : __('Disabled') }}</span>
            <span class="gm-chip {{ $experimentsOn ? 'on' : 'off' }}">{{ __('Experiments') }}: {{ $experimentsOn ? __('Enabled') : __('Disabled') }}</span>
        </div>
    </div>

    <div class="gm-nav">
        <a href="{{ $quickLinks['overview'] }}" class="{{ ($pageMeta['key'] ?? 'overview') === 'overview' ? 'active' : '' }}">{{ __('Overview') }}</a>
        <a href="{{ $quickLinks['content'] }}" class="{{ ($pageMeta['key'] ?? 'overview') === 'content' ? 'active' : '' }}">{{ __('Content & Journeys') }}</a>
        <a href="{{ $quickLinks['operations'] }}" class="{{ ($pageMeta['key'] ?? 'overview') === 'operations' ? 'active' : '' }}">{{ __('Operations') }}</a>
        <a href="{{ $quickLinks['insights'] }}" class="{{ ($pageMeta['key'] ?? 'overview') === 'insights' ? 'active' : '' }}">{{ __('Insights') }}</a>
    </div>

    @yield('growth-module-content')
</div>
@endsection
