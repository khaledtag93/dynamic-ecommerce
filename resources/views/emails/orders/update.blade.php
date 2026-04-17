<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:640px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.06);">
            <div style="padding:24px 24px 8px;background:linear-gradient(135deg,#f97316,#fb923c);color:#ffffff;">
                <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.92;">{{ config('app.name') }}</div>
                <h1 style="margin:10px 0 6px;font-size:24px;line-height:1.3;">{{ $title }}</h1>
                <p style="margin:0 0 8px;font-size:14px;opacity:.95;">{{ __('Order number') }}: <strong>{{ $order->order_number }}</strong></p>
            </div>

            <div style="padding:24px;">
                <p style="margin:0 0 18px;font-size:15px;line-height:1.7;">{{ $message }}</p>

                <table style="width:100%;border-collapse:collapse;margin:0 0 18px;">
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:700;width:38%;">{{ __('Order status') }}</td>
                        <td style="padding:10px 12px;border:1px solid #e2e8f0;">{{ $order->status_label }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:700;">{{ __('Delivery status') }}</td>
                        <td style="padding:10px 12px;border:1px solid #e2e8f0;">{{ $order->delivery_status_label }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:700;">{{ __('Payment status') }}</td>
                        <td style="padding:10px 12px;border:1px solid #e2e8f0;">{{ $order->payment_status_label }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:700;">{{ __('Total') }}</td>
                        <td style="padding:10px 12px;border:1px solid #e2e8f0;">{{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</td>
                    </tr>
                </table>

                @php
                    $customerOrderUrl = route('orders.show', $order);
                @endphp

                <a href="{{ $customerOrderUrl }}" style="display:inline-block;padding:12px 18px;background:#f97316;color:#ffffff;text-decoration:none;border-radius:12px;font-weight:700;">
                    {{ __('View order') }}
                </a>
            </div>
        </div>
    </div>
</body>
</html>
