<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderPaymentStatusUpdatedNotification;
use App\Services\Payments\PaymobGatewayService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

        $meta = array_merge($payment->meta ?? [], Arr::except($context, ['notes', 'provider_status']));
        $meta = $this->pushPaymentEvent($meta, 'manual_status_update', __('Payment status was updated manually from the admin panel.'));

        $updates = [
            'status' => $status,
            'notes' => $context['notes'] ?? $payment->notes,
            'provider_status' => $context['provider_status'] ?? $payment->provider_status,
            'meta' => $meta,
        ];

        if ($status === Payment::STATUS_AUTHORIZED) {
            $updates['authorized_at'] = now();
        }

        if ($status === Payment::STATUS_PAID) {
            $updates['paid_at'] = now();
            $updates['failed_at'] = null;
        }

        if ($status === Payment::STATUS_FAILED) {
            $updates['failed_at'] = now();
        }

        if ($status === Payment::STATUS_REFUNDED) {
            $updates['refunded_at'] = now();
        }

        if ($status === Payment::STATUS_PENDING) {
            $updates['failed_at'] = null;
        }

        $payment->update($updates);
        $payment->refresh();

        $order = $payment->order()->first();
        if ($order) {
            $this->syncOrderPaymentStatus($order);
            $this->notifyPaymentStatusChanged($order, $payment);
        }

        return $payment;
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
        $meta = array_merge($payment->meta ?? [], Arr::except($context, ['notes', 'provider_status', 'transaction_id']));
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

        $updates = [
            'status' => $status,
            'provider_status' => $context['provider_status'] ?? $payment->provider_status,
            'notes' => $context['notes'] ?? $payment->notes,
            'meta' => $meta,
        ];

        if (! empty($context['transaction_id'])) {
            $updates['transaction_reference'] = (string) $context['transaction_id'];
        }

        if ($status === Payment::STATUS_PAID) {
            $updates['paid_at'] = $payment->paid_at ?? now();
            $updates['failed_at'] = null;
        }

        if ($status === Payment::STATUS_FAILED) {
            $updates['failed_at'] = $payment->failed_at ?? now();
        }

        if ($status === Payment::STATUS_PENDING) {
            $updates['failed_at'] = null;
        }

        $payment->update($updates);
        $payment->refresh();

        $order = $payment->order()->first();
        if ($order) {
            $this->syncOrderPaymentStatus($order);
            $this->notifyPaymentStatusChanged($order, $payment);
        }

        return $payment;
    }

    public function syncOrderPaymentStatus(Order $order): void
    {
        $payments = $order->payments()->get();

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

        $order->update([
            'payment_status' => $status,
        ]);
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