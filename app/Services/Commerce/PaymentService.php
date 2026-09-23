<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderPaymentStatusUpdatedNotification;
use App\Services\Payments\PaymobGatewayService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        protected StoreSettingsService $storeSettingsService,
        protected PaymobGatewayService $paymobGatewayService,
    ) {
    }

    public function paymentOptionsForCheckout(): array
    {
        $settings = $this->storeSettingsService->all();
        $options = [];

        if (($settings['payment_cod_enabled'] ?? '1') === '1') {
            $options[Order::PAYMENT_METHOD_COD] = [
                'label' => __('Cash on Delivery'),
                'description' => __('Pay when the order is delivered to you.'),
                'provider' => null,
            ];
        }

        if (($settings['payment_bank_transfer_enabled'] ?? '1') === '1') {
            $options[Order::PAYMENT_METHOD_BANK_TRANSFER] = [
                'label' => __('Bank Transfer'),
                'description' => __('Place the order now and complete the transfer manually using your bank instructions.'),
                'provider' => null,
            ];
        }

        if (($settings['payment_online_enabled'] ?? '1') === '1') {
            $options[Order::PAYMENT_METHOD_ONLINE] = [
                'label' => __('Online Payment'),
                'description' => __('Continue to a secure hosted payment page after placing the order.'),
                'provider' => $settings['payment_gateway_provider'] ?? 'custom_gateway',
            ];
        }

        if ($options === []) {
            $options[Order::PAYMENT_METHOD_COD] = [
                'label' => __('Cash on Delivery'),
                'description' => __('Fallback method kept active to avoid blocking checkout.'),
                'provider' => null,
            ];
        }

        return $options;
    }

    public function enabledMethods(): array
    {
        return array_keys($this->paymentOptionsForCheckout());
    }

    public function isMethodEnabled(string $method): bool
    {
        return in_array($method, $this->enabledMethods(), true);
    }

    public function gatewayProvider(): string
    {
        $settings = $this->storeSettingsService->all();

        return strtolower((string) ($settings['payment_gateway_provider'] ?? 'paymob'));
    }

    public function initialOrderPaymentStatus(string $method): string
    {
        return match ($method) {
            Order::PAYMENT_METHOD_COD => Order::PAYMENT_STATUS_UNPAID,
            Order::PAYMENT_METHOD_BANK_TRANSFER, Order::PAYMENT_METHOD_ONLINE => Order::PAYMENT_STATUS_PENDING,
            default => Order::PAYMENT_STATUS_PENDING,
        };
    }

    public function createForOrder(Order $order): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'method' => $order->payment_method,
            'provider' => $order->payment_method === Order::PAYMENT_METHOD_ONLINE ? ($this->gatewayProvider() ?? 'gateway_placeholder') : null,
            'status' => $this->initialStatus($order->payment_method),
            'transaction_reference' => 'PAY-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
            'amount' => $order->grand_total,
            'currency' => $order->currency ?? 'EGP',
            'notes' => $order->payment_method === Order::PAYMENT_METHOD_ONLINE
                ? 'Gateway payment record created and ready for hosted checkout.'
                : 'Manual/offline-ready payment record created.',
            'meta' => [
                'foundation_only' => false,
                'gateway_provider' => $this->gatewayProvider(),
                'payment_method_label' => $order->payment_method_label,
                'events' => [[
                    'event' => 'payment_record_created',
                    'message' => __('Payment record was created automatically for this order.'),
                    'at' => now()->toDateTimeString(),
                ]],
            ],
        ]);
    }

    public function updateStatus(Payment $payment, string $status, array $context = []): Payment
    {
        $allowedStatuses = array_keys(Payment::statusOptions());
        abort_unless(in_array($status, $allowedStatuses, true), 422);

        if ($status === Payment::STATUS_REFUNDED) {
            throw ValidationException::withMessages([
                'status' => 'Record refunds from the order refund action so the financial ledger stays consistent.',
            ]);
        }

        return DB::transaction(function () use ($payment, $status, $context) {
            // Keep financial mutations on one lock order (Order -> Payment).
            // Refund/cancel flows already lock the order first, so callbacks and
            // manual payment changes must do the same to avoid lock inversion.
            $lockedOrder = $this->lockParentOrderForPayment($payment);

            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status === Payment::STATUS_REFUNDED) {
                throw ValidationException::withMessages([
                    'status' => 'A refunded payment is terminal and cannot be changed manually.',
                ]);
            }

            if ($lockedPayment->status === Payment::STATUS_PAID && $status !== Payment::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'status' => __('A paid payment cannot be downgraded manually. Use the order refund action when money is returned.'),
                ]);
            }

            if (! $this->manualTransitionAllowed($lockedPayment->status, $status)) {
                throw ValidationException::withMessages([
                    'status' => __('This manual payment status transition is not allowed. Verify the payment evidence and use the appropriate order or gateway flow.'),
                ]);
            }

            if ($lockedPayment->status === $status) {
                return $lockedPayment;
            }

            $meta = array_merge($lockedPayment->meta ?? [], Arr::except($context, ['notes', 'provider_status']));
            $meta = $this->pushPaymentEvent($meta, 'manual_status_update', __('Payment status was updated manually from the admin panel.'));

            $updates = [
                'status' => $status,
                'notes' => $context['notes'] ?? $lockedPayment->notes,
                'provider_status' => $context['provider_status'] ?? $lockedPayment->provider_status,
                'meta' => $meta,
            ];

            if ($status === Payment::STATUS_AUTHORIZED) {
                $updates['authorized_at'] = now();
            }

            if ($status === Payment::STATUS_PAID) {
                $updates['paid_at'] = $lockedPayment->paid_at ?? now();
                $updates['failed_at'] = null;
            }

            if ($status === Payment::STATUS_FAILED) {
                $updates['failed_at'] = now();
            }

            if ($status === Payment::STATUS_PENDING) {
                $updates['failed_at'] = null;
            }

            if ($status !== Payment::STATUS_AUTHORIZED) {
                $updates['authorized_at'] = $status === Payment::STATUS_PENDING
                    ? null
                    : $lockedPayment->authorized_at;
            }

            $lockedPayment->update($updates);
            $lockedPayment->refresh();

            $order = $lockedOrder ?? $lockedPayment->order()->first();
            if ($order) {
                $this->syncOrderPaymentStatus($order);
                $this->notifyPaymentStatusChanged($order->fresh(), $lockedPayment);
            }

            return $lockedPayment;
        });
    }

    public function markAsPaid(Payment $payment, array $context = []): Payment
    {
        return $this->transitionGatewayPayment(
            $payment,
            Payment::STATUS_PAID,
            'gateway_paid',
            __('Payment was confirmed by the payment gateway.'),
            $context
        );
    }

    public function markAsFailed(Payment $payment, array $context = []): Payment
    {
        return $this->transitionGatewayPayment(
            $payment,
            Payment::STATUS_FAILED,
            'gateway_failed',
            __('Payment was declined or failed at the payment gateway.'),
            $context
        );
    }

    public function markAsPending(Payment $payment, array $context = []): Payment
    {
        return $this->transitionGatewayPayment(
            $payment,
            Payment::STATUS_PENDING,
            'gateway_pending',
            __('Payment is still pending confirmation from the payment gateway.'),
            $context
        );
    }

    protected function transitionGatewayPayment(Payment $payment, string $status, string $event, string $message, array $context = []): Payment
    {
        return DB::transaction(function () use ($payment, $status, $event, $message, $context) {
            // Match refund/cancel lock ordering so a gateway callback cannot
            // deadlock against an order mutation that subsequently touches its payment.
            $lockedOrder = $this->lockParentOrderForPayment($payment);

            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Paid/refunded are terminal. Replayed callbacks and stale gateway
            // downgrades must not mutate financial state or duplicate timeline events.
            if (in_array($lockedPayment->status, [Payment::STATUS_PAID, Payment::STATUS_REFUNDED], true)) {
                return $lockedPayment;
            }

            $incomingTransactionId = ! empty($context['transaction_id'])
                ? (string) $context['transaction_id']
                : null;

            $lastGatewayTransition = data_get($lockedPayment->meta, 'last_gateway_transition');

            if (
                $lockedPayment->status === $status
                && $lastGatewayTransition
                && (string) data_get($lastGatewayTransition, 'status') === $status
                && (string) data_get($lastGatewayTransition, 'transaction_id') === (string) $incomingTransactionId
            ) {
                return $lockedPayment;
            }

            $meta = array_merge($lockedPayment->meta ?? [], Arr::except($context, ['notes', 'provider_status', 'transaction_id']));
            $meta = $this->pushPaymentEvent($meta, $event, $message);

            if (array_key_exists('raw', $context)) {
                $meta['gateway_callback_payload'] = $context['raw'];
            }

            if (array_key_exists('hmac_valid', $context)) {
                $meta['paymob_hmac_valid'] = $context['hmac_valid'];
            }

            if (! empty($context['paymob_order_id'])) {
                $meta['paymob_order_id'] = (string) $context['paymob_order_id'];
            }

            if (! empty($context['response_code'])) {
                $meta['gateway_response_code'] = (string) $context['response_code'];
            }

            if (! empty($context['response_message'])) {
                $meta['gateway_response_message'] = (string) $context['response_message'];
            }

            $meta['last_gateway_transition'] = [
                'status' => $status,
                'transaction_id' => $incomingTransactionId,
                'provider_status' => $context['provider_status'] ?? null,
                'at' => now()->toDateTimeString(),
            ];

            $updates = [
                'status' => $status,
                'provider_status' => $context['provider_status'] ?? $lockedPayment->provider_status,
                'notes' => $context['notes'] ?? $lockedPayment->notes,
                'meta' => $meta,
            ];

            if (! empty($context['transaction_id'])) {
                $updates['transaction_reference'] = (string) $context['transaction_id'];
            }

            if ($status === Payment::STATUS_PAID) {
                $updates['paid_at'] = $lockedPayment->paid_at ?? now();
                $updates['failed_at'] = null;
            }

            if ($status === Payment::STATUS_FAILED) {
                $updates['failed_at'] = $lockedPayment->failed_at ?? now();
            }

            if ($status === Payment::STATUS_PENDING) {
                $updates['failed_at'] = null;
            }

            $lockedPayment->update($updates);
            $lockedPayment->refresh();

            $order = $lockedOrder ?? $lockedPayment->order()->first();
            if ($order) {
                $this->syncOrderPaymentStatus($order);
                $this->notifyPaymentStatusChanged($order->fresh(), $lockedPayment);
            }

            return $lockedPayment;
        });
    }

    public function syncOrderPaymentStatus(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Refund records are the authoritative refund ledger. Never allow a
            // later payment sync to erase partially-refunded/refunded order state.
            $refundTotal = round((float) $lockedOrder->refunds()->sum('amount'), 2);

            if ($refundTotal > 0) {
                $status = $refundTotal >= (float) $lockedOrder->grand_total
                    ? Order::PAYMENT_STATUS_REFUNDED
                    : Order::PAYMENT_STATUS_PARTIALLY_REFUNDED;

                $lockedOrder->update([
                    'refund_total' => $refundTotal,
                    'payment_status' => $status,
                ]);

                return;
            }

            $payments = $lockedOrder->payments()->get();

            if ($payments->isEmpty()) {
                return;
            }

            $status = match (true) {
                $payments->contains(fn (Payment $payment) => $payment->status === Payment::STATUS_PAID) => Order::PAYMENT_STATUS_PAID,
                $payments->contains(fn (Payment $payment) => $payment->status === Payment::STATUS_REFUNDED) => Order::PAYMENT_STATUS_REFUNDED,
                $payments->contains(fn (Payment $payment) => in_array($payment->status, [Payment::STATUS_PENDING, Payment::STATUS_AUTHORIZED], true)) => Order::PAYMENT_STATUS_PENDING,
                $payments->contains(fn (Payment $payment) => $payment->status === Payment::STATUS_FAILED) => Order::PAYMENT_STATUS_FAILED,
                default => Order::PAYMENT_STATUS_UNPAID,
            };

            $lockedOrder->update([
                'payment_status' => $status,
            ]);
        });
    }

    public function onlineGatewayUrlForOrder(Order $order, Payment $payment): string
    {
        $provider = $this->gatewayProvider();

        if ($provider === 'paymob') {
            return $this->paymobGatewayService->checkoutUrl($order->loadMissing('items'), $payment);
        }

        throw new \RuntimeException(__('The selected online gateway is not configured yet.'));
    }

    public function onlineGatewayConfigured(): bool
    {
        return match ($this->gatewayProvider()) {
            'paymob' => $this->paymobGatewayService->isConfigured(),
            default => false,
        };
    }

    public function checkoutInstructionsFor(Order $order): ?string
    {
        $settings = $this->storeSettingsService->all();

        if ($order->payment_method === Order::PAYMENT_METHOD_BANK_TRANSFER) {
            return app()->getLocale() === 'ar'
                ? ($settings['bank_transfer_instructions_ar'] ?? __('Please transfer the amount using your bank details and keep the transfer reference for support.'))
                : ($settings['bank_transfer_instructions_en'] ?? __('Please transfer the amount using your bank details and keep the transfer reference for support.'));
        }

        if ($order->payment_method === Order::PAYMENT_METHOD_ONLINE) {
            return $this->onlineGatewayConfigured()
                ? __('Your online payment is ready. Tap the payment button to continue to the secure gateway. If you return before finishing, you can safely retry from your order details.')
                : __('Online payment is enabled, but the gateway credentials are still incomplete in the admin settings.');
        }

        return __('You can pay the courier when the shipment reaches you.');
    }

    public function notifyPaymentStatusChanged(Order $order, Payment $payment): void
    {
        if ($order->user && ! $this->hasRecentPaymentStatusNotification($order->user, $payment)) {
            $order->user->notify(new OrderPaymentStatusUpdatedNotification($order, $payment));
        }

        User::query()
            ->where('role_as', 1)
            ->whereKeyNot(optional($order->user)->id)
            ->get()
            ->each(function (User $admin) use ($order, $payment) {
                if (! $this->hasRecentPaymentStatusNotification($admin, $payment)) {
                    $admin->notify(new OrderPaymentStatusUpdatedNotification($order, $payment));
                }
            });
    }

    protected function hasRecentPaymentStatusNotification(User $user, Payment $payment): bool
    {
        return $user->notifications()
            ->where('type', OrderPaymentStatusUpdatedNotification::class)
            ->latest()
            ->take(10)
            ->get()
            ->contains(function ($notification) use ($payment) {
                return (int) data_get($notification->data, 'payment_id') === (int) $payment->id
                    && (string) data_get($notification->data, 'payment_status') === (string) $payment->status;
            });
    }

    protected function lockParentOrderForPayment(Payment $payment): ?Order
    {
        $orderId = Payment::query()
            ->whereKey($payment->getKey())
            ->value('order_id');

        if (! $orderId) {
            return null;
        }

        return Order::query()
            ->whereKey($orderId)
            ->lockForUpdate()
            ->first();
    }

    public function allowedManualStatuses(Payment $payment): array
    {
        return array_values(array_filter(
            array_keys(Payment::statusOptions()),
            fn (string $status) => $status !== Payment::STATUS_REFUNDED
                && $this->manualTransitionAllowed($payment->status, $status)
        ));
    }

    protected function manualTransitionAllowed(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return match ($from) {
            Payment::STATUS_PENDING => in_array($to, [Payment::STATUS_AUTHORIZED, Payment::STATUS_PAID, Payment::STATUS_FAILED], true),
            Payment::STATUS_AUTHORIZED => in_array($to, [Payment::STATUS_PAID, Payment::STATUS_FAILED], true),
            Payment::STATUS_FAILED => in_array($to, [Payment::STATUS_PENDING, Payment::STATUS_PAID], true),
            Payment::STATUS_PAID, Payment::STATUS_REFUNDED => false,
            default => false,
        };
    }

    protected function pushPaymentEvent(array $meta, string $event, string $message): array
    {
        $events = $meta['events'] ?? [];
        $events[] = [
            'event' => $event,
            'message' => $message,
            'at' => now()->toDateTimeString(),
        ];

        $meta['events'] = array_slice($events, -20);

        return $meta;
    }

    protected function initialStatus(string $method): string
    {
        return match ($method) {
            Order::PAYMENT_METHOD_COD => Payment::STATUS_PENDING,
            Order::PAYMENT_METHOD_BANK_TRANSFER, Order::PAYMENT_METHOD_ONLINE => Payment::STATUS_PENDING,
            default => Payment::STATUS_PENDING,
        };
    }
}