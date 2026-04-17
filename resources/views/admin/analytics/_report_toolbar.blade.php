@php
    $reportTitle = $title ?? __('Analytics report');
    $reportSubtitle = $subtitle ?? null;
    $reportPeriod = $period ?? null;
    $reportId = $reportId ?? 'analytics-report';
    $reportExportRows = collect($exportRows ?? [])->filter(fn ($row) => filled(data_get($row, 'label')));
@endphp

<style>
.analytics-toolbar{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;padding:16px 18px;border-radius:20px;background:linear-gradient(135deg,#fff,#fff7ed);border:1px solid rgba(15,23,42,.06);box-shadow:0 14px 30px rgba(15,23,42,.05)}.analytics-toolbar-meta{display:grid;gap:8px}.analytics-toolbar-kicker{font-size:.76rem;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800}.analytics-toolbar-title{font-size:1.08rem;font-weight:800;color:#0f172a}.analytics-toolbar-copy{font-size:.9rem;color:#64748b;line-height:1.7}.analytics-toolbar-chips{display:flex;gap:8px;flex-wrap:wrap}.analytics-toolbar-chip{display:inline-flex;align-items:center;padding:7px 11px;border-radius:999px;background:#fff;border:1px solid rgba(15,23,42,.08);font-size:.82rem;font-weight:700;color:#334155}.analytics-toolbar-actions{display:flex;gap:10px;flex-wrap:wrap}.analytics-toolbar-actions .btn{border-radius:999px}.analytics-export-card{display:none;margin-top:12px;padding:16px 18px;border-radius:18px;background:#fff;border:1px dashed rgba(148,163,184,.5)}.analytics-export-card.is-ready{display:block}.analytics-export-table{width:100%;border-collapse:collapse;margin-top:10px}.analytics-export-table th,.analytics-export-table td{padding:9px 8px;border-bottom:1px solid rgba(15,23,42,.08);font-size:.9rem}.analytics-export-table th{text-transform:uppercase;letter-spacing:.04em;color:#64748b;font-size:.76rem}.analytics-export-note{font-size:.82rem;color:#64748b;margin-top:10px;line-height:1.7}@media print{.analytics-toolbar-actions,.analytics-nav,.analytics-anchor-nav,.offers-anchor-nav,.growth-filter,.analytics-pills,form,.btn,.admin-sidebar,.admin-topbar,.sidebar,.navbar{display:none!important}.analytics-toolbar,.analytics-card,.analytics-chart-card,.offers-chart-card,.growth-panel,.growth-card,.growth-focus,.offers-hero,.offers-trend-card{box-shadow:none!important;border-color:#dbe3ee!important}.analytics-export-card{display:block!important;border-style:solid}.page-break-avoid{break-inside:avoid;page-break-inside:avoid}}</style>

<div class="analytics-toolbar page-break-avoid">
    <div class="analytics-toolbar-meta">
        <div class="analytics-toolbar-kicker">{{ __('Operator-ready analytics') }}</div>
        <div class="analytics-toolbar-title">{{ $reportTitle }}</div>
        @if ($reportSubtitle)
            <div class="analytics-toolbar-copy">{{ $reportSubtitle }}</div>
        @endif
        <div class="analytics-toolbar-chips">
            @if ($reportPeriod)
                <span class="analytics-toolbar-chip">{{ __('Reporting window') }}: {{ $reportPeriod }}</span>
            @endif
            <span class="analytics-toolbar-chip">{{ __('Generated at') }}: {{ now()->format('Y-m-d H:i') }}</span>
            <span class="analytics-toolbar-chip">{{ __('Export status') }}: {{ __('Ready for browser print / CSV summary') }}</span>
        </div>
    </div>
    <div class="analytics-toolbar-actions">
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> {{ __('Print view') }}
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-export-copy="{{ $reportId }}">
            <i class="bi bi-link-45deg"></i> {{ __('Copy report link') }}
        </button>
        <button type="button" class="btn btn-dark btn-sm" data-export-csv="{{ $reportId }}">
            <i class="bi bi-download"></i> {{ __('Export summary CSV') }}
        </button>
    </div>
</div>

<div class="analytics-export-card page-break-avoid is-ready" id="{{ $reportId }}-export-card" data-report-title="{{ $reportTitle }}">
    <div class="fw-bold">{{ __('Export-ready summary') }}</div>
    <div class="text-muted small mt-1">{{ __('A concise operator summary for sharing, printing, or attaching to reviews.') }}</div>
    <table class="analytics-export-table" id="{{ $reportId }}-export-table">
        <thead>
            <tr>
                <th>{{ __('Metric') }}</th>
                <th>{{ __('Value') }}</th>
                <th>{{ __('Context') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportExportRows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td>{{ $row['value'] }}</td>
                    <td>{{ $row['context'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="analytics-export-note">{{ __('Tip: use Print view for a clean PDF, or Export summary CSV for spreadsheet follow-up.') }}</div>
</div>

@push('scripts')
<script>
(function(){
    function toCsv(table) {
        const rows = Array.from(table.querySelectorAll('tr'));
        return rows.map((row) => Array.from(row.querySelectorAll('th,td')).map((cell) => {
            const text = (cell.innerText || '').replace(/\s+/g, ' ').trim().replace(/"/g, '""');
            return '"' + text + '"';
        }).join(',')).join('\n');
    }

    document.querySelectorAll('[data-export-csv]').forEach((button) => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-export-csv');
            const table = document.getElementById(id + '-export-table');
            const card = document.getElementById(id + '-export-card');
            if (!table || !card) return;
            const title = (card.getAttribute('data-report-title') || 'analytics-report').toLowerCase().replace(/[^a-z0-9]+/g, '-');
            const blob = new Blob([toCsv(table)], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = title + '.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        });
    });

    document.querySelectorAll('[data-export-copy]').forEach((button) => {
        button.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(window.location.href);
                const original = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check2"></i> {{ __('Copied') }}';
                setTimeout(() => this.innerHTML = original, 1600);
            } catch (error) {}
        });
    });
})();
</script>
@endpush
