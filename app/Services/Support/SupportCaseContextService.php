<?php

namespace App\Services\Support;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\SupportCase;
use App\Models\User;

class SupportCaseContextService
{
    public function build(SupportCase $case, User $viewer): array
    {
        $canViewCustomers = $viewer->hasPermission('customers.manage');
        $canViewOrders = $viewer->hasPermission('orders.view');
        $canViewPayments = $viewer->hasPermission('payments.view');
        $canViewDelivery = $viewer->hasPermission('delivery.view');

        $customer = $case->customer()->first(['id', 'name', 'email']);

        $context = [
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'statement_url' => $canViewCustomers
                    ? route('admin.customers.statement', $customer)
                    : null,
            ] : null,
            'order' => null,
            'delivery' => null,
            'payment_visible' => $canViewPayments,
            'payment' => null,
            'returns_visible' => $canViewOrders,
            'returns' => collect(),
            'return_total' => 0,
        ];

        if (! $case->order_id) {
            return $context;
        }

        $order = Order::query()
            ->select([
                'id',
                'user_id',
                'order_number',
                'status',
                'payment_status',
                'payment_method',
                'delivery_status',
                'delivery_method',
                'grand_total',
                'currency',
                'shipping_provider',
                'tracking_number',
                'estimated_delivery_date',
            ])
            ->find($case->order_id);

        if (! $order) {
            return $context;
        }

        $context['order'] = [
            'id' => $order->id,
            'number' => $order->order_number,
            'status' => $order->status,
            'status_label' => $order->status_label,
            'payment_status' => $order->payment_status,
            'payment_status_label' => $order->payment_status_label,
            'delivery_status' => $order->delivery_status,
            'delivery_status_label' => $order->delivery_status_label,
            'grand_total' => (float) $order->grand_total,
            'currency' => $order->currency ?: 'EGP',
            'url' => $canViewOrders ? route('admin.orders.show', $order) : null,
        ];

        $context['delivery'] = [
            'status_label' => $order->delivery_status_label,
            'method_label' => $order->delivery_method_label,
            'provider' => $order->shipping_provider,
            'tracking_number' => $order->tracking_number,
            'estimated_delivery_date' => $order->estimated_delivery_date?->format('Y-m-d'),
            'url' => $canViewDelivery
                ? route('admin.deliveries.index', ['search' => $order->order_number])
                : null,
        ];

        if ($canViewPayments) {
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->latest('id')
                ->first([
                    'id',
                    'order_id',
                    'method',
                    'provider',
                    'status',
                    'transaction_reference',
                    'amount',
                    'currency',
                    'paid_at',
                ]);

            if ($payment) {
                $context['payment'] = [
                    'id' => $payment->id,
                    'status_label' => $payment->status_label,
                    'status_badge_class' => $payment->status_badge_class,
                    'method_label' => $payment->method_label,
                    'provider' => $payment->provider,
                    'reference' => $payment->transaction_reference,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency ?: ($order->currency ?: 'EGP'),
                    'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                    'url' => route('admin.payments.show', $payment),
                ];
            }
        }

        if ($canViewOrders) {
            $returnQuery = ReturnRequest::query()
                ->where('order_id', $order->id);

            $context['return_total'] = (clone $returnQuery)->count();
            $context['returns'] = $returnQuery
                ->latest('id')
                ->limit(5)
                ->get(['id', 'order_id', 'reference', 'status', 'requested_at', 'created_at'])
                ->map(fn (ReturnRequest $returnRequest) => [
                    'id' => $returnRequest->id,
                    'reference' => $returnRequest->reference,
                    'status_label' => $returnRequest->status_label,
                    'status_badge_class' => $returnRequest->status_badge_class,
                    'requested_at' => ($returnRequest->requested_at ?: $returnRequest->created_at)?->format('Y-m-d H:i'),
                    'url' => route('admin.returns.show', $returnRequest),
                ]);
        }

        return $context;
    }
}
