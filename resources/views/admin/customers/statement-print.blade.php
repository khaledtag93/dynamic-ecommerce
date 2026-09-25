<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Account statement') }} · {{ $user->name }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111827;margin:32px;font-size:13px}
        h1{font-size:24px;margin:0 0 6px}.muted{color:#6b7280}.meta{margin:18px 0;padding:12px;border:1px solid #d1d5db;border-radius:10px}
        table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border-bottom:1px solid #e5e7eb;padding:9px;text-align:start;vertical-align:top}
        th{background:#f9fafb;font-size:11px;text-transform:uppercase}.amount{text-align:end;white-space:nowrap}.no-balance{margin-top:16px;padding:10px;background:#f3f4f6;border-radius:8px}
        .totals{display:flex;gap:12px;flex-wrap:wrap;margin-top:16px}.total{border:1px solid #d1d5db;border-radius:8px;padding:10px;min-width:180px}
        @media print{.print-action{display:none}body{margin:12mm}}
    </style>
</head>
<body>
    <button class="print-action" onclick="window.print()">{{ __('Print') }}</button>
    <h1>{{ __('Account statement') }}</h1>
    <div class="muted">{{ $user->name }} · {{ $user->email }}</div>
    <div class="meta">
        <strong>{{ __('Period') }}:</strong> {{ $statement['from']->format('Y-m-d') }} → {{ $statement['to']->format('Y-m-d') }}
        @if($filters['type']) · <strong>{{ __('Movement type') }}:</strong> {{ ucfirst($filters['type']) }} @endif
    </div>
    <div class="no-balance">{{ __('This statement lists recorded commercial activity. It does not calculate a running customer balance.') }}</div>

    @if($statement['totals_by_currency']->isNotEmpty())
        <div class="totals">
            @foreach($statement['totals_by_currency'] as $currencyTotal)
                <div class="total">
                    <strong>{{ $currencyTotal['currency'] }}</strong><br>
                    {{ __('Order value') }}: {{ number_format($currencyTotal['order_value'], 2) }}<br>
                    {{ __('Captured') }}: {{ number_format($currencyTotal['payments_captured'], 2) }}<br>
                    {{ __('Refunded') }}: {{ number_format($currencyTotal['refunds_processed'], 2) }}
                </div>
            @endforeach
        </div>
    @endif

    <table>
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Status') }}</th><th>{{ __('Details') }}</th><th class="amount">{{ __('Amount') }}</th></tr></thead>
        <tbody>
            @forelse($statement['movements'] as $movement)
                <tr>
                    <td>{{ $movement['occurred_at']->format('Y-m-d H:i') }}</td>
                    <td>{{ $movement['type_label'] }}</td>
                    <td>{{ $movement['reference'] }}</td>
                    <td>{{ $movement['status_label'] }}</td>
                    <td>{{ $movement['details'] }}</td>
                    <td class="amount">{{ $movement['amount'] === null ? '—' : (($movement['currency'] ?? '').' '.number_format($movement['amount'], 2)) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">{{ __('No customer movements match this period and filter.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
