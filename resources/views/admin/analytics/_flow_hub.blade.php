@php
    $flowTitle = $flowTitle ?? __('Cross-page flow');
    $flowSubtitle = $flowSubtitle ?? __('Move from the current signal into the next best page without losing the same reporting context.');
    $flowItems = collect($flowItems ?? [])->values();
@endphp

@if ($flowItems->isNotEmpty())
<style>
.analytics-flow-card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:22px;box-shadow:0 18px 45px rgba(15,23,42,.06);padding:18px}.analytics-flow-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.analytics-flow-title{font-weight:800;font-size:1.05rem;color:#0f172a}.analytics-flow-subtitle{color:#64748b;font-size:.9rem;margin-top:6px}.analytics-flow-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:16px}.analytics-flow-item{display:flex;gap:12px;align-items:flex-start;padding:16px;border-radius:18px;border:1px solid rgba(15,23,42,.07);background:#fff;text-decoration:none;color:#0f172a;transition:.2s ease;min-width:0}.analytics-flow-item:hover{transform:translateY(-1px);border-color:rgba(249,115,22,.26);box-shadow:0 16px 28px rgba(249,115,22,.10);color:#0f172a}.analytics-flow-item.active{background:linear-gradient(135deg,#fff7ed,#ffffff);border-color:rgba(249,115,22,.18)}.analytics-flow-item.disabled{opacity:.72;background:#f8fafc}.analytics-flow-icon{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:14px;background:#fff7ed;color:#c2410c;flex:0 0 auto}.analytics-flow-label{font-weight:800;line-height:1.35}.analytics-flow-copy{font-size:.82rem;color:#64748b;margin-top:5px;line-height:1.55}.analytics-flow-meta{display:inline-flex;align-items:center;gap:6px;margin-top:8px;font-size:.78rem;font-weight:700;color:#c2410c}.analytics-flow-item.active .analytics-flow-meta{color:#9a3412}.analytics-flow-item.disabled .analytics-flow-meta{color:#94a3b8}.analytics-flow-item.current-page{border-style:dashed}@media (max-width: 1200px){.analytics-flow-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width: 768px){.analytics-flow-grid{grid-template-columns:1fr}}
</style>

<div class="analytics-flow-card">
    <div class="analytics-flow-head">
        <div>
            <div class="analytics-flow-title">{{ $flowTitle }}</div>
            <div class="analytics-flow-subtitle">{{ $flowSubtitle }}</div>
        </div>
        <span class="analytics-chip">{{ __('Context preserved') }}</span>
    </div>

    <div class="analytics-flow-grid">
        @foreach ($flowItems as $item)
            @php
                $isActive = (bool) ($item['active'] ?? false);
                $isDisabled = empty($item['url']);
                $classes = trim('analytics-flow-item ' . ($isActive ? 'active current-page ' : '') . ($isDisabled ? 'disabled ' : ''));
            @endphp
            @if ($isDisabled)
                <div class="{{ $classes }}" aria-disabled="true">
                    <span class="analytics-flow-icon"><i class="mdi {{ $item['icon'] ?? 'mdi-arrow-top-right' }}"></i></span>
                    <div class="min-w-0">
                        <div class="analytics-flow-label">{{ $item['label'] }}</div>
                        <div class="analytics-flow-copy">{{ $item['description'] ?? '' }}</div>
                        <div class="analytics-flow-meta">{{ $item['meta'] ?? __('Waiting for more data') }}</div>
                    </div>
                </div>
            @else
                <a href="{{ $item['url'] }}" class="{{ $classes }}">
                    <span class="analytics-flow-icon"><i class="mdi {{ $item['icon'] ?? 'mdi-arrow-top-right' }}"></i></span>
                    <div class="min-w-0">
                        <div class="analytics-flow-label">{{ $item['label'] }}</div>
                        <div class="analytics-flow-copy">{{ $item['description'] ?? '' }}</div>
                        <div class="analytics-flow-meta">{{ $item['meta'] ?? __('Open now') }} <i class="mdi {{ $isActive ? 'mdi-check-circle-outline' : 'mdi-arrow-top-right' }}"></i></div>
                    </div>
                </a>
            @endif
        @endforeach
    </div>
</div>
@endif
