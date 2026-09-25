<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Order receipt') }} {{ $order->order_number }}</title>
    <style>
        :root {
            color-scheme: light;
            --receipt-ink: #172033;
            --receipt-muted: #667085;
            --receipt-border: #dfe5ec;
            --receipt-soft: #f7f9fc;
            --receipt-accent: #2563eb;
            --receipt-warning: #fff7ed;
            --receipt-warning-border: #fed7aa;
        }

        * { box-sizing: border-box; }

        @page {
            size: A4;
            margin: 12mm;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #eef2f6;
            color: var(--receipt-ink);
            font-family: Arial, Helvetica, sans-serif;
        }

        body { padding: 24px 12px 42px; }

        .receipt-toolbar {
            width: min(100%, 920px);
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .receipt-button {
            appearance: none;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #111827;
            border-radius: 10px;
            padding: 10px 14px;
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

        .receipt {
            width: min(100%, 190mm);
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--receipt-border);
            border-radius: 18px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, .12);
            overflow: hidden;
        }

        .receipt-header {
            padding: 22px 24px 18px;
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            border-bottom: 1px solid var(--receipt-border);
        }

        .receipt-store {
            margin: 0;
            font-size: 26px;
            line-height: 1.15;
        }

        .receipt-kicker {
            margin-top: 6px;
            color: var(--receipt-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .receipt-title {
            text-align: end;
        }

        .receipt-title strong {
            display: block;
            font-size: 18px;
        }

        .receipt-reference {
            margin-top: 5px;
            color: var(--receipt-accent);
            font-weight: 800;
            word-break: break-word;
        }

        .receipt-warning {
            margin: 18px 24px 0;
            padding: 11px 13px;
            border: 1px solid var(--receipt-warning-border);
            border-radius: 12px;
            background: var(--receipt-warning);
            font-size: 12px;
            line-height: 1.55;
        }

        .receipt-section {
            padding: 20px 24px;
            border-bottom: 1px solid var(--receipt-border);
        }

        .receipt-section:last-child {
            border-bottom: 0;
        }

        .receipt-section-title {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .035em;
            color: var(--receipt-muted);
        }

        .receipt-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 28px;
        }

        .receipt-meta {
            min-width: 0;
        }

        .receipt-meta span {
            display: block;
            color: var(--receipt-muted);
            font-size: 11px;
            margin-bottom: 4px;
        }

        .receipt-meta strong,
        .receipt-meta div {
            overflow-wrap: anywhere;
            line-height: 1.5;
        }

        .receipt-items {
            width: 100%;
            border-collapse: collapse;
        }

        .receipt-items th,
        .receipt-items td {
            padding: 11px 8px;
            border-bottom: 1px solid #edf0f4;
            text-align: start;
            vertical-align: top;
        }

        .receipt-items th {
            padding-top: 0;
            color: var(--receipt-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .035em;
        }

        .receipt-items th:nth-last-child(-n+3),
        .receipt-items td:nth-last-child(-n+3) {
            text-align: end;
            white-space: nowrap;
        }

        .receipt-items tbody tr:last-child td {
            border-bottom: 0;
        }

        .receipt-item-name {
            font-weight: 800;
        }

        .receipt-item-meta {
            color: var(--receipt-muted);
            font-size: 11px;
            margin-top: 3px;
        }

        .receipt-totals {
            margin-inline-start: auto;
            width: min(100%, 390px);
        }

        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 5px 0;
            line-height: 1.5;
        }

        .receipt-total-row span {
            color: var(--receipt-muted);
        }

        .receipt-total-row--grand {
            margin-top: 7px;
            padding-top: 11px;
            border-top: 1px solid var(--receipt-border);
            font-size: 18px;
            font-weight: 800;
        }

        .receipt-total-row--grand span {
            color: var(--receipt-ink);
        }

        .receipt-total-row--net {
            font-weight: 800;
        }

        .receipt-note {
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--receipt-soft);
            line-height: 1.6;
        }

        .receipt-footer {
            padding: 18px 24px 22px;
            text-align: center;
            color: var(--receipt-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 700px) {
            .receipt-header { flex-direction: column; }
            .receipt-title { text-align: start; }
            .receipt-grid { grid-template-columns: 1fr; }
            .receipt-section { padding-inline: 16px; }
            .receipt-header { padding-inline: 16px; }
            .receipt-warning { margin-inline: 16px; }
            .receipt-items th:nth-child(2),
            .receipt-items td:nth-child(2) { display: none; }
        }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .no-print { display: none !important; }
            .receipt {
                width: 100%;
                max-width: none;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .receipt-header,
            .receipt-section,
            .receipt-footer {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
@php
    $currency = $order->currency ?: 'EGP';
    $latestPayment = $order->payments->sortByDesc('id')->first();
    $placedAt = $order->placed_at ?: $order->created_at;
    $netAfterRefunds = max(0, (float) $order->grand_total - (float) $order->refund_total);
@endphp

<div class="receipt-toolbar no-print">
    <a class="receipt-button" href="{{ $backUrl }}">{{ $backLabel }}</a>
    <button type="button" class="receipt-button receipt-button--primary" onclick="window.print()">{{ __('Print receipt') }}</button>
</div>

<main class="receipt" data-order-receipt>
    <header class="receipt-header">
        <div>
            <h1 class="receipt-store">{{ $settings['store_name'] ?? config('app.name') }}</h1>
            <div class="receipt-kicker">
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
        </div>
        <div class="receipt-title">
            <strong>{{ __('Order receipt') }}</strong>
            <div class="receipt-reference">{{ $order->order_number }}</div>
        </div>
    </header>

    <div class="receipt-warning">
        {{ __('This document is an order receipt, not a tax or fiscal invoice.') }}
    </div>

    <section class="receipt-section">
        <h2 class="receipt-section-title">{{ __('Order information') }}</h2>
        <div class="receipt-grid">
            <div class="receipt-meta">
                <span>{{ __('Receipt reference') }}</span>
                <strong>{{ $order->order_number }}</strong>
            </div>
            <div class="receipt-meta">
                <span>{{ __('Placed at') }}</span>
                <strong>{{ optional($placedAt)->format('Y-m-d H:i') }}</strong>
            </div>
            <div class="receipt-meta">
                <span>{{ __('Order status') }}</span>
                <strong>{{ $order->status_label }}</strong>
            </div>
            <div class="receipt-meta">
                <span>{{ __('Payment status') }}</span>
                <strong>{{ $order->payment_status_label }}</strong>
            </div>
            <div class="receipt-meta">
                <span>{{ __('Payment method') }}</span>
                <strong>{{ $order->payment_method_label }}</strong>
            </div>
            <div class="receipt-meta">
                <span>{{ __('Delivery method') }}</span>
                <strong>{{ $order->delivery_method_label }}</strong>
            </div>
        </div>
    </section>

    <section class="receipt-section">
        <h2 class="receipt-section-title">{{ __('Customer') }}</h2>
        <div class="receipt-grid">
            <div class="receipt-meta">
                <span>{{ __('Customer name') }}</span>
                <strong>{{ $order->customer_name ?: '—' }}</strong>
            </div>
            <div class="receipt-meta">
                <span>{{ __('Email') }}</span>
                <strong>{{ $order->customer_email ?: '—' }}</strong>
            </div>
            <div class="receipt-meta">
                <span>{{ __('Phone') }}</span>
                <strong>{{ $order->customer_phone ?: '—' }}</strong>
            </div>
            @if($order->sales_channel === $order::SALES_CHANNEL_POS)
                <div class="receipt-meta">
                    <span>{{ __('Fulfillment') }}</span>
                    <strong>{{ __('Store pickup') }}</strong>
                </div>
            @else
                <div class="receipt-meta">
                    <span>{{ __('Shipping address') }}</span>
                    <div>
                        {{ $order->shipping_address_line_1 ?: '—' }}
                        @if($order->shipping_address_line_2), {{ $order->shipping_address_line_2 }}@endif
                        @if($order->shipping_city)<br>{{ $order->shipping_city }}@endif
                        @if($order->shipping_state), {{ $order->shipping_state }}@endif
                        @if($order->shipping_postal_code) {{ $order->shipping_postal_code }}@endif
                        @if($order->shipping_country)<br>{{ $order->shipping_country }}@endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section class="receipt-section">
        <h2 class="receipt-section-title">{{ __('Items') }}</h2>
        <table class="receipt-items">
            <thead>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('SKU') }}</th>
                    <th>{{ __('Qty') }}</th>
                    <th>{{ __('Unit price') }}</th>
                    <th>{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            <div class="receipt-item-name">{{ $item->product_name ?: __('Order item') }}</div>
                            @if($item->variant_name)
                                <div class="receipt-item-meta">{{ $item->variant_name }}</div>
                            @endif
                        </td>
                        <td>{{ $item->sku ?: '—' }}</td>
                        <td>{{ (int) $item->quantity }}</td>
                        <td>{{ $currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td><strong>{{ $currency }} {{ number_format((float) $item->line_total, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="receipt-section">
        <div class="receipt-totals">
            <div class="receipt-total-row"><span>{{ __('Subtotal') }}</span><strong>{{ $currency }} {{ number_format((float) $order->subtotal, 2) }}</strong></div>
            @if((float) $order->discount_total > 0)
                <div class="receipt-total-row"><span>{{ __('Discount') }}</span><strong>-{{ $currency }} {{ number_format((float) $order->discount_total, 2) }}</strong></div>
            @endif
            <div class="receipt-total-row"><span>{{ __('Shipping') }}</span><strong>{{ $currency }} {{ number_format((float) $order->shipping_total, 2) }}</strong></div>
            @if((float) $order->tax_total > 0)
                <div class="receipt-total-row"><span>{{ __('Tax') }}</span><strong>{{ $currency }} {{ number_format((float) $order->tax_total, 2) }}</strong></div>
            @endif
            @if($order->coupon_code)
                <div class="receipt-total-row"><span>{{ __('Coupon') }}</span><strong>{{ $order->coupon_code }}</strong></div>
            @endif
            @if((float) $order->refund_total > 0)
                <div class="receipt-total-row"><span>{{ __('Refunded') }}</span><strong>-{{ $currency }} {{ number_format((float) $order->refund_total, 2) }}</strong></div>
            @endif
            <div class="receipt-total-row receipt-total-row--grand"><span>{{ __('Grand total') }}</span><strong>{{ $currency }} {{ number_format((float) $order->grand_total, 2) }}</strong></div>
            @if((float) $order->refund_total > 0)
                <div class="receipt-total-row receipt-total-row--net"><span>{{ __('Net after refunds') }}</span><strong>{{ $currency }} {{ number_format($netAfterRefunds, 2) }}</strong></div>
            @endif
        </div>
    </section>

    <section class="receipt-section">
        <h2 class="receipt-section-title">{{ __('Payment & delivery') }}</h2>
        <div class="receipt-grid">
            <div class="receipt-meta">
                <span>{{ __('Payment status') }}</span>
                <strong>{{ $order->payment_status_label }}</strong>
            </div>
            @if($latestPayment?->transaction_reference)
                <div class="receipt-meta">
                    <span>{{ __('Payment reference') }}</span>
                    <strong>{{ $latestPayment->transaction_reference }}</strong>
                </div>
            @endif
            @if($latestPayment?->provider)
                <div class="receipt-meta">
                    <span>{{ __('Provider') }}</span>
                    <strong>{{ $latestPayment->provider }}</strong>
                </div>
            @endif
            @if($latestPayment?->paid_at)
                <div class="receipt-meta">
                    <span>{{ __('Paid at') }}</span>
                    <strong>{{ $latestPayment->paid_at->format('Y-m-d H:i') }}</strong>
                </div>
            @endif
            @if($order->shipping_provider)
                <div class="receipt-meta">
                    <span>{{ __('Courier') }}</span>
                    <strong>{{ $order->shipping_provider }}</strong>
                </div>
            @endif
            @if($order->tracking_number)
                <div class="receipt-meta">
                    <span>{{ __('Tracking number') }}</span>
                    <strong>{{ $order->tracking_number }}</strong>
                </div>
            @endif
            @if($order->estimated_delivery_date)
                <div class="receipt-meta">
                    <span>{{ __('Estimated delivery date') }}</span>
                    <strong>{{ $order->estimated_delivery_date->format('Y-m-d') }}</strong>
                </div>
            @endif
        </div>
    </section>

    @if(!blank($order->notes))
        <section class="receipt-section">
            <h2 class="receipt-section-title">{{ __('Notes') }}</h2>
            <div class="receipt-note">{{ $order->notes }}</div>
        </section>
    @endif

    <footer class="receipt-footer">
        <div>{{ __('Thank you for your purchase.') }}</div>
        <div>{{ __('This receipt reflects the order values currently recorded in the system.') }}</div>
    </footer>
</main>
</body>
</html>
