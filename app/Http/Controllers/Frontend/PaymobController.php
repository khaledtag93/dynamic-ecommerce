<?php

namespace App\Http\Controllers\Frontend;

use App\Exceptions\PaymobCheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\PaymentService;
use App\Services\Payments\PaymobGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymobController extends Controller
{
    protected string $logChannel = 'payments';
    protected PaymobGatewayService $gateway;
    protected PaymentService $paymentService;

    protected function logInfo(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->info($message, $context);
    }

    protected function logWarning(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->warning($message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->error($message, $context);
        Log::error($message, $context);
    }

    public function __construct(
        PaymobGatewayService $gateway,
        PaymentService $paymentService
    ) {
        $this->gateway = $gateway;
        $this->paymentService = $paymentService;
    }

    protected function storeCheckoutFailure(Payment $payment, \Throwable $e): void
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['checkout_error'] = $e->getMessage();
        $meta['checkout_error_at'] = now()->toDateTimeString();
        $meta['checkout_error_context'] = $e instanceof PaymobCheckoutException
            ? $e->diagnostics()
            : ['exception' => get_class($e)];

        $payment->update([
            'provider' => 'paymob',
            'provider_status' => 'initiation_failed',
            'notes' => __('The secure payment page could not be opened. Retry is allowed after configuration is corrected.'),
            'meta' => $meta,
        ]);
    }

    public function redirect(Order $order)
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);

        try {
            $this->logInfo('Paymob redirect flow started', [
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? null,
                'user_id' => auth()->id(),
                'gateway_diagnostics' => $this->gateway->configurationDiagnostics(),
            ]);

            $payment = $order->payments()->latest('id')->first();

            if (! $payment) {
                $this->logWarning('Paymob redirect aborted: no payment record found', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? null,
                ]);

                return redirect()
                    ->route('orders.success', $order)
                    ->with('error', __('No payment record was found for this order.'));
            }

            if (
                $payment->status === Payment::STATUS_PAID
                || $order->payment_status === Order::PAYMENT_STATUS_PAID
            ) {
                $this->logInfo('Paymob redirect skipped: order already paid', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'payment_status' => $payment->status,
                    'order_payment_status' => $order->payment_status,
                ]);

                return redirect()
                    ->route('payments.paymob.result', $order)
                    ->with('success', __('This order is already marked as paid.'));
            }

            $reusedUrl = $this->gateway->reuseCheckoutUrlIfAvailable($payment);

            if ($reusedUrl) {
                $this->logInfo('Paymob checkout URL reused', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ]);

                return redirect()->away($reusedUrl);
            }

            $url = $this->gateway->checkoutUrl(
                $order->loadMissing('items', 'payments'),
                $payment
            );

            $this->logInfo('Paymob redirect URL generated successfully', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);

            return redirect()->away($url);
        } catch (\Throwable $e) {
            $payment = $order->payments()->latest('id')->first();

            if ($payment) {
                $this->storeCheckoutFailure($payment, $e);
            }

            $this->logError('Paymob redirect flow failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? null,
                'user_id' => auth()->id(),
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'diagnostics' => $e instanceof PaymobCheckoutException ? $e->diagnostics() : null,
                'trace' => $e->getTraceAsString(),
            ]);

            report($e);

            return redirect()
                ->route('payments.paymob.result', $order)
                ->with('error', $e instanceof PaymobCheckoutException
                    ? $e->userMessage()
                    : __('Unable to start online payment right now. Please try again or choose another payment method.'));
        }
    }

    public function callback(Request $request)
    {
        $payload = $request->all();
        $isBrowserFlow = $request->isMethod('get') || $request->expectsHtml();

        try {
            $this->logInfo('Paymob callback route hit', [
                'method' => $request->method(),
                'query' => $request->query(),
                'body' => $payload,
                'full_url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            $result = $this->gateway->handleCallback($payload);

            $this->logInfo('Paymob callback resolved', [
                'success' => $result['success'] ?? null,
                'pending' => $result['pending'] ?? null,
                'provider_status' => $result['provider_status'] ?? null,
                'transaction_id' => $result['transaction_id'] ?? null,
                'paymob_order_id' => $result['paymob_order_id'] ?? null,
                'merchant_order_id' => $result['merchant_order_id'] ?? null,
                'message' => $result['message'] ?? null,
                'hmac_valid' => $result['hmac_valid'] ?? null,
            ]);

            $payment = $result['payment'] ?? null;
            $order = $result['order'] ?? optional($payment)->order;

            if ($payment && $order) {
                $context = [
                    'transaction_id' => $result['transaction_id'] ?? $request->input('id'),
                    'raw' => $result['data'] ?? $payload,
                    'provider_status' => $result['provider_status'] ?? 'pending',
                    'hmac_valid' => $result['hmac_valid'] ?? null,
                    'paymob_order_id' => $result['paymob_order_id'] ?? null,
                    'response_code' => $result['response_code'] ?? null,
                    'response_message' => $result['response_message'] ?? null,
                    'notes' => $result['message'] ?? __('Payment result received from gateway.'),
                ];

                if ($result['success'] ?? false) {
                    $this->paymentService->markAsPaid($payment, $context);
                } elseif ($result['pending'] ?? false) {
                    $this->paymentService->markAsPending($payment, $context);
                } else {
                    $this->paymentService->markAsFailed($payment, $context);
                }

                $freshPayment = $payment->fresh();
                $freshOrder = $order->fresh();

                $this->logInfo('Paymob payment sync applied', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'payment_status' => $freshPayment?->status,
                    'provider_status' => $freshPayment?->provider_status,
                    'order_payment_status' => $freshOrder?->payment_status,
                ]);

                if ($isBrowserFlow) {
                    return redirect()
                        ->route('payments.paymob.result', $order)
                        ->with(
                            ($result['success'] ?? false) ? 'success' : (($result['pending'] ?? false) ? 'info' : 'error'),
                            $result['message'] ?? __('Payment result received.')
                        );
                }

                return response()->json([
                    'status' => 'ok',
                    'payment_status' => $freshPayment?->status,
                    'order_payment_status' => $freshOrder?->payment_status,
                    'provider_status' => $freshPayment?->provider_status,
                    'message' => $result['message'] ?? 'Callback processed.',
                ]);
            }

            $this->logWarning('Paymob callback unresolved', [
                'message' => $result['message'] ?? null,
                'payload' => $payload,
            ]);

            if ($isBrowserFlow) {
                return redirect()
                    ->route('frontend.home')
                    ->with('error', $result['message'] ?? __('Unable to resolve payment result.'));
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'Unable to resolve payment result.',
            ], 400);
        } catch (\Throwable $e) {
            $this->logError('Paymob callback processing failed', [
                'method' => $request->method(),
                'full_url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            report($e);

            if ($isBrowserFlow) {
                return redirect()
                    ->route('frontend.home')
                    ->with('error', __('We could not verify the payment result right now. Please contact support if any amount was deducted.'));
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Callback processing failed.',
            ], 500);
        }
    }

    public function result(Order $order)
    {
        abort_unless((int) $order->user_id === (int) auth()->id(), 403);

        try {
            $order->load(['items', 'payments']);

            $payment = $order->payments()->latest('id')->first();

            if (! $payment) {
                $this->logWarning('Paymob result page opened without payment record', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? null,
                    'user_id' => auth()->id(),
                ]);

                return redirect()
                    ->route('orders.success', $order)
                    ->with('error', __('Payment record was not found.'));
            }

            $status = $payment->status;
            $checkoutDiagnostics = data_get($payment->meta, 'checkout_error_context', []);

            $this->logInfo('Paymob result page rendered', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'status' => $status,
                'provider_status' => $payment->provider_status,
                'checkout_diagnostics' => $checkoutDiagnostics,
            ]);

            return view('frontend.payments.paymob-result', compact('order', 'payment', 'status', 'checkoutDiagnostics'));
        } catch (\Throwable $e) {
            $this->logError('Paymob result page failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? null,
                'user_id' => auth()->id(),
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            report($e);

            return redirect()
                ->route('frontend.home')
                ->with('error', __('Unable to display the payment result right now.'));
        }
    }
}
