@php
    $trust = $trust ?? [];
    $uiState = $uiState ?? [];
    $tone = $trust['source_tone'] ?? 'neutral';
    $toneClass = match ($tone) {
        'good' => 'is-good',
        'warn' => 'is-warn',
        'risk' => 'is-risk',
        default => 'is-neutral',
    };
    $lastSyncAt = $trust['last_sync_at'] ?? null;
    $generatedAt = $trust['generated_at'] ?? null;
@endphp

<style>
.analytics-trust{display:grid;gap:14px;margin-top:16px}.analytics-trust-card{display:grid;gap:12px;padding:18px 20px;border-radius:20px;background:color-mix(in srgb, var(--admin-surface) 98%, white);border:1px solid color-mix(in srgb, var(--admin-border) 88%, white);box-shadow:0 18px 40px rgba(15,23,42,.05)}.analytics-trust-card.is-good{background:linear-gradient(135deg,#f8fff9,#ffffff)}.analytics-trust-card.is-warn{background:linear-gradient(135deg,#fffaf1,#ffffff)}.analytics-trust-card.is-risk{background:linear-gradient(135deg,#fff5f5,#ffffff)}.analytics-trust-card.is-neutral{background:linear-gradient(135deg,#f8fafc,#ffffff)}.analytics-trust-head{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}.analytics-trust-title{font-size:1.05rem;font-weight:800;color:var(--admin-text)}.analytics-trust-copy{font-size:.92rem;line-height:1.75;color:var(--admin-muted)}.analytics-trust-chips{display:flex;gap:10px;flex-wrap:wrap}.analytics-trust-chip{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:color-mix(in srgb, var(--admin-surface) 98%, white);border:1px solid color-mix(in srgb, var(--admin-border) 88%, white);font-size:.82rem;font-weight:700;color:var(--admin-text)}.analytics-empty{display:grid;gap:10px;padding:20px;border-radius:20px;background:color-mix(in srgb, var(--admin-surface) 98%, white);border:1px dashed rgba(148,163,184,.5)}.analytics-empty-title{font-weight:800;color:var(--admin-text)}.analytics-empty-copy{color:var(--admin-muted);line-height:1.75}.analytics-visibility-note{font-size:.86rem;color:var(--admin-muted)}
</style>

<div class="analytics-trust">
    <div class="analytics-trust-card {{ $toneClass }} page-break-avoid">
        <div class="analytics-trust-head">
            <div>
                <div class="analytics-trust-title">{{ __('Data trust and visibility') }}</div>
                <div class="analytics-trust-copy">{{ $trust['help'] ?? __('This dashboard is ready for operator review.') }}</div>
            </div>
            <div class="analytics-trust-chips">
                @if (! empty($trust['source_label']))
                    <span class="analytics-trust-chip">{{ __('Source') }}: {{ $trust['source_label'] }}</span>
                @endif
                @if (! empty($trust['coverage_days']))
                    <span class="analytics-trust-chip">{{ __('Coverage') }}: {{ number_format((int) $trust['coverage_days']) }} {{ __('days') }}</span>
                @endif
                @if ($lastSyncAt)
                    <span class="analytics-trust-chip">{{ __('Last sync') }}: {{ \Illuminate\Support\Carbon::parse($lastSyncAt)->format('Y-m-d H:i') }}</span>
                @endif
                @if ($generatedAt)
                    <span class="analytics-trust-chip">{{ __('Generated at') }}: {{ \Illuminate\Support\Carbon::parse($generatedAt)->format('Y-m-d H:i') }}</span>
                @endif
            </div>
        </div>
        <div class="analytics-visibility-note">{{ __('Sections automatically hide low-signal blocks when no meaningful data is available, so operators only see what is actionable.') }}</div>
    </div>

    @if (! empty($uiState['empty']))
        <div class="analytics-empty page-break-avoid">
            <div class="analytics-empty-title">{{ __('No meaningful analytics signal yet for this window') }}</div>
            <div class="analytics-empty-copy">{{ __('Try widening the date range, checking whether analytics jobs have run, or waiting for more orders and events before reviewing KPI movement.') }}</div>
        </div>
    @endif
</div>
