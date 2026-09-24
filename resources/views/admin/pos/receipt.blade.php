<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sales receipt') }} {{ $order->order_number }}</title>
    <style>
        :root {
            color-scheme: light;
            --receipt-border: #d7dce2;
            --receipt-muted: #667085;
            --receipt-ink: #111827;
            --receipt-soft: #f5f7fa;
        }

        * { box-sizing: border-box; }

        @page {
            size: {{ $paper === 'a4' ? 'A4' : $paper . 'mm auto' }};
            margin: {{ $paper === 'a4' ? '12mm' : '3mm' }};
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #eef1f4;
            color: var(--receipt-ink);
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            padding: 22px 12px 40px;
        }

        .receipt-toolbar {
            width: min(100%, 920px);
            margin: 0 auto 16px;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .receipt-toolbar__group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .receipt-button {
            appearance: none;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #111827;
            border-radius: 9px;
            padding: 9px 13px;
            text-decoration: none;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .receipt-button--primary {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }

        .receipt-button[aria-current="page"] {
            border-color: #111827;
            box-shadow: 0 0 0 2px rgba(17, 24, 39, .08);
        }

        .receipt {
            background: #fff;
            margin: 0 auto;
            box-shadow: 0 12px 35px rgba(15, 23, 42, .12);
            padding: 6mm 5mm;
            width: {{ $paper === '58' ? '58mm' : ($paper === 'a4' ? '190mm' : '80mm') }};
            max-width: 100%;
        }

        .receipt--58 {
            padding: 4mm 3mm;
            font-size: 11px;
        }

        .receipt--80 {
            font-size: 12px;
        }

        .receipt--a4 {
            font-size: 14px;
            padding: 14mm 15mm;
        }

        .receipt-header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 1px dashed #9ca3af;
        }

        .receipt-store {
            margin: 0;
            font-size: 1.45em;
            line-height: 1.15;
        }

        .receipt-type {
            margin: 5px 0 0;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .receipt-contact,
        .receipt-meta,
        .receipt-footer {
            color: var(--receipt-muted);
            line-height: 1.5;
        }

        .receipt-contact {
            margin-top: 8px;
            font-size: .88em;
        }

        .receipt-meta {
            display: grid;
            grid-template-columns: 1fr;
            gap: 4px;
            padding: 12px 0;
            border-bottom: 1px dashed #9ca3af;
        }

        .receipt-meta-row,
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .receipt-meta-row strong,
        .receipt-total-row strong {
            color: var(--receipt-ink);
            text-align: end;
        }

        .receipt-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .receipt-items th,
        .receipt-items td {
            padding: 7px 3px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .receipt-items th {
            font-size: .82em;
            color: var(--receipt-muted);
            text-align: start;
        }

        .receipt-items th:last-child,
        .receipt-items td:last-child {
            text-align: end;
        }

        .receipt-item-name {
            font-weight: 800;
        }

        .receipt-item-meta {
            color: var(--receipt-muted);
            font-size: .82em;
            margin-top: 2px;
        }

        .receipt-totals {
            margin-top: 10px;
            padding-top: 5px;
        }

        .receipt-total-row {
            padding: 4px 0;
        }

        .receipt-total-row--grand {
            margin-top: 6px;
            padding-top: 9px;
            border-top: 1px dashed #9ca3af;
            font-size: 1.18em;
            font-weight: 800;
        }

        .receipt-payment {
            margin-top: 12px;
            padding: 10px;
            border-radius: 8px;
            background: var(--receipt-soft);
        }

        .receipt-note {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px dashed #9ca3af;
        }

        .receipt-footer {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px dashed #9ca3af;
            text-align: center;
            font-size: .86em;
        }

        .receipt--58 .receipt-items th:nth-child(2),
        .receipt--58 .receipt-items td:nth-child(2) {
            display: none;
        }

        .receipt--58 .receipt-items th,
        .receipt--58 .receipt-items td {
            padding-inline: 1px;
        }

        .receipt--a4 .receipt-meta {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 24px;
        }

        @media print {
            html, body {
                background: #fff;
            }

            body {
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .receipt {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
@php
    $payment = $order->payments->first();
    $currency = $order->currency ?: 'EGP';
    $cashReceived = data_get($order->meta, 'pos.cash_received');
    $changeDue = (float) data_get($order->meta, 'pos.change_due', 0);
    $cashierUserId = data_get($order->meta, 'cashier_user_id');
@endphp

<div class="receipt-toolbar no-print">
    <div class="receipt-toolbar__group">
        <a class="receipt-button" href="{{ route('admin.pos.sales.show', $order) }}">{{ __('Back to sale') }}</a>
        <a class="receipt-button" href="{{ route('admin.pos.index') }}">{{ __('Start new sale') }}</a>
    </div>
    <div class="receipt-toolbar__group" aria-label="{{ __('Paper size') }}">
        @foreach(['58' => '58 mm', '80' => '80 mm', 'a4' => 'A4'] as $paperKey => $paperLabel)
            <a
                class="receipt-button"
                aria-current="{{ $paper === $paperKey ? 'page' : 'false' }}"
                href="{{ route('admin.pos.sales.show', $order) }}?receipt=1&paper={{ $paperKey }}"
            >{{ $paperLabel }}</a>
        @endforeach
        <button type="button" class="receipt-button receipt-button--primary" onclick="window.print()">{{ __('Print') }}</button>
    </div>
</div>

<main class="receipt receipt--{{ $paper }}" data-receipt-paper="{{ $paper }}">
    <header class="receipt-header">
        <h1 class="receipt-store">{{ $settings['store_name'] ?? config('app.name') }}</h1>
        <div class="receipt-type">{{ __('Sales receipt') }}</div>

        @if(!blank($settings['store_contact_address'] ?? null) || !blank($settings['store_support_phone'] ?? null) || !blank($settings['store_support_email'] ?? null))
            <div class="receipt-contact">
                @if(!blank($settings['store_contact_address'] ?? null))
                    <div>{{ $settings['store_contact_address'] }}</div>
                @endif
                @if(!blank($settings['store_support_phone'] ?? null))
                    <div>{{ __('Phone') }}: {{ $settings['store_support_phone'] }}</div>
                @endif
                @if(!blank($settings['store_support_email'] ?? null))
                    <div>{{ __('Email') }}: {{ $settings['store_support_email'] }}</div>
                @endif
            </div>
        @endif
    </header>

    <section class="receipt-meta">
        <div class="receipt-meta-row"><span>{{ __('Receipt reference') }}</span><strong>{{ $order->order_number }}</strong></div>
        <div class="receipt-meta-row"><span>{{ __('Date') }}</span><strong>{{ optional($order->placed_at)->format('Y-m-d H:i') }}</strong></div>
        <div class="receipt-meta-row"><span>{{ __('Cashier') }}</span><strong>#{{ $cashierUserId }}</strong></div>
        <div class="receipt-meta-row"><span>{{ __('Payment method') }}</span><strong>{{ $order->payment_method_label }}</strong></div>
        @if(!blank($order->customer_name))
            <div class="receipt-meta-row"><span>{{ __('Customer name') }}</span><strong>{{ $order->customer_name }}</strong></div>
        @endif
        @if($order->user_id && !blank($order->customer_email))
            <div class="receipt-meta-row"><span>{{ __('Customer account') }}</span><strong>{{ $order->customer_email }}</strong></div>
        @endif
        @if($payment?->transaction_reference)
            <div class="receipt-meta-row"><span>{{ __('Payment reference') }}</span><strong>{{ $payment->transaction_reference }}</strong></div>
        @endif
    </section>

    <table class="receipt-items">
        <thead>
            <tr>
                <th>{{ __('Item') }}</th>
                <th>{{ __('SKU') }}</th>
                <th>{{ __('Quantity') }}</th>
                <th>{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>
                        <div class="receipt-item-name">{{ $item->product_name }}</div>
                        @if($item->variant_name)
                            <div class="receipt-item-meta">{{ $item->variant_name }}</div>
                        @endif
                        <div class="receipt-item-meta">{{ $currency }} {{ number_format((float) $item->unit_price, 2) }} × {{ $item->quantity }}</div>
                        @php($itemDiscount = (float) data_get($item->meta, 'pos.discount.total_amount', 0))
                        @if($itemDiscount > 0)
                            <div class="receipt-item-meta">{{ __('Discount') }}: -{{ $currency }} {{ number_format($itemDiscount, 2) }}</div>
                        @endif
                    </td>
                    <td>{{ $item->sku ?: '—' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td><strong>{{ $currency }} {{ number_format((float) $item->line_total, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="receipt-totals">
        <div class="receipt-total-row"><span>{{ __('Subtotal') }}</span><strong>{{ $currency }} {{ number_format((float) $order->subtotal, 2) }}</strong></div>
        @if((float) $order->discount_total > 0)
            <div class="receipt-total-row"><span>{{ __('Discount') }}</span><strong>-{{ $currency }} {{ number_format((float) $order->discount_total, 2) }}</strong></div>
        @endif
        @if((float) $order->tax_total > 0)
            <div class="receipt-total-row"><span>{{ __('Tax') }}</span><strong>{{ $currency }} {{ number_format((float) $order->tax_total, 2) }}</strong></div>
        @endif
        <div class="receipt-total-row receipt-total-row--grand"><span>{{ __('Total') }}</span><strong>{{ $currency }} {{ number_format((float) $order->grand_total, 2) }}</strong></div>
    </section>

    <section class="receipt-payment">
        <div class="receipt-meta-row"><span>{{ __('Payment status') }}</span><strong>{{ $order->payment_status_label }}</strong></div>
        @if($order->payment_method === \App\Models\Order::PAYMENT_METHOD_POS_CASH && $cashReceived !== null)
            <div class="receipt-meta-row"><span>{{ __('Cash received') }}</span><strong>{{ $currency }} {{ number_format((float) $cashReceived, 2) }}</strong></div>
            <div class="receipt-meta-row"><span>{{ __('Change due') }}</span><strong>{{ $currency }} {{ number_format($changeDue, 2) }}</strong></div>
        @endif
    </section>

    @if(!blank($order->notes))
        <section class="receipt-note">
            <strong>{{ __('Notes') }}</strong>
            <div>{{ $order->notes }}</div>
        </section>
    @endif

    <footer class="receipt-footer">
        <div>{{ __('Thank you for your purchase.') }}</div>
        <div>{{ __('This receipt reflects the completed POS sale recorded in the system.') }}</div>
    </footer>
</main>
</body>
</html>
