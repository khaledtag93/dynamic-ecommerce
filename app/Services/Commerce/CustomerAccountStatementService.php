<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CustomerAccountStatementService
{
    public function build(User $customer, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();
        $type = (string) ($filters['type'] ?? '');

        $movements = collect();

        if ($type === '' || $type === 'order') {
            $movements = $movements->concat($this->orderMovements($customer, $from, $to));
        }

        if ($type === '' || $type === 'payment') {
            $movements = $movements->concat($this->paymentMovements($customer, $from, $to));
        }

        if ($type === '' || $type === 'refund') {
            $movements = $movements->concat($this->refundMovements($customer, $from, $to));
        }

        if ($type === '' || $type === 'return') {
            $movements = $movements->concat($this->returnMovements($customer, $from, $to));
        }

        $movements = $movements
            ->sortByDesc(fn (array $movement) => $movement['occurred_at']->getTimestamp())
            ->values();

        return [
            'movements' => $movements,
            'totals_by_currency' => $this->totalsByCurrency($movements),
            'counts' => [
                'orders' => $movements->where('type', 'order')->count(),
                'payments' => $movements->where('type', 'payment')->count(),
                'refunds' => $movements->where('type', 'refund')->count(),
                'returns' => $movements->where('type', 'return')->count(),
            ],
            'from' => $from,
            'to' => $to,
        ];
    }

    private function orderMovements(User $customer, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::query()
            ->where('user_id', $customer->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get([
                'id',
                'order_number',
                'status',
                'payment_status',
                'delivery_status',
                'grand_total',
                'currency',
                'placed_at',
                'created_at',
            ])
            ->map(fn (Order $order) => [
                'type' => 'order',
                'type_label' => __('Order placed'),
                'reference' => $order->order_number,
                'status_label' => $order->status_label,
                'amount' => (float) $order->grand_total,
                'currency' => $order->currency ?: 'EGP',
                'occurred_at' => $order->placed_at ?: $order->created_at,
                'details' => __('Payment: :payment · Delivery: :delivery', [
                    'payment' => $order->payment_status_label,
                    'delivery' => $order->delivery_status_label,
                ]),
                'url' => route('admin.orders.show', $order),
            ]);
    }

    private function paymentMovements(User $customer, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Payment::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $customer->id))
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->with('order:id,user_id,order_number,currency')
            ->orderBy('paid_at')
            ->get()
            ->map(fn (Payment $payment) => [
                'type' => 'payment',
                'type_label' => __('Payment captured'),
                'reference' => $payment->transaction_reference ?: ($payment->order?->order_number ?? '#'.$payment->id),
                'status_label' => $payment->status_label,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency ?: ($payment->order?->currency ?? 'EGP'),
                'occurred_at' => $payment->paid_at,
                'details' => trim(($payment->method_label ?: __('Payment')).($payment->order ? ' · '.$payment->order->order_number : '')),
                'url' => route('admin.payments.show', $payment),
            ]);
    }

    private function refundMovements(User $customer, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return OrderRefund::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $customer->id))
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('processed_at', [$from, $to])
                    ->orWhere(function ($fallback) use ($from, $to) {
                        $fallback->whereNull('processed_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->with([
                'order:id,user_id,order_number,currency',
                'returnRequest:id,reference',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (OrderRefund $refund) {
                $returnReference = $refund->returnRequest?->reference;

                return [
                    'type' => 'refund',
                    'type_label' => __('Refund processed'),
                    'reference' => $returnReference ?: ($refund->order?->order_number ?? '#'.$refund->id),
                    'status_label' => __('Processed'),
                    'amount' => (float) $refund->amount,
                    'currency' => $refund->order?->currency ?: 'EGP',
                    'occurred_at' => $refund->processed_at ?: $refund->created_at,
                    'details' => $refund->reason ?: __('Refund linked to :order', [
                        'order' => $refund->order?->order_number ?? '#'.$refund->order_id,
                    ]),
                    'url' => $refund->returnRequest
                        ? route('admin.returns.show', $refund->returnRequest)
                        : ($refund->order ? route('admin.orders.show', $refund->order) : null),
                ];
            });
    }

    private function returnMovements(User $customer, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return ReturnRequest::query()
            ->where('user_id', $customer->id)
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('requested_at', [$from, $to])
                    ->orWhere(function ($fallback) use ($from, $to) {
                        $fallback->whereNull('requested_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->with('order:id,user_id,order_number,currency')
            ->orderBy('id')
            ->get()
            ->map(fn (ReturnRequest $returnRequest) => [
                'type' => 'return',
                'type_label' => __('Return request'),
                'reference' => $returnRequest->reference,
                'status_label' => $returnRequest->status_label,
                'amount' => null,
                'currency' => $returnRequest->order?->currency,
                'occurred_at' => $returnRequest->requested_at ?: $returnRequest->created_at,
                'details' => $returnRequest->order
                    ? __('Linked order: :order', ['order' => $returnRequest->order->order_number])
                    : __('Return request'),
                'url' => route('admin.returns.show', $returnRequest),
            ]);
    }

    private function totalsByCurrency(Collection $movements): Collection
    {
        return $movements
            ->filter(fn (array $movement) => $movement['amount'] !== null && filled($movement['currency']))
            ->groupBy('currency')
            ->map(function (Collection $currencyMovements, string $currency) {
                return [
                    'currency' => $currency,
                    'order_value' => round((float) $currencyMovements->where('type', 'order')->sum('amount'), 2),
                    'payments_captured' => round((float) $currencyMovements->where('type', 'payment')->sum('amount'), 2),
                    'refunds_processed' => round((float) $currencyMovements->where('type', 'refund')->sum('amount'), 2),
                ];
            })
            ->values();
    }
}
