<?php

namespace App\Services\Payments;

use App\Exceptions\PaymobCheckoutException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PaymobGatewayService
{
    protected string $logChannel = 'payments';
    protected string $baseUrl;
    protected string $apiKey;
    protected string $hmacSecret;
    protected string $integrationId;
    protected string $iframeId;
    protected string $currency;
    protected bool $verifySsl;
    protected array $settingsSource = [];

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

    public function __construct(?StoreSettingsService $storeSettingsService = null)
    {
        $settings = [];

        try {
            if ($storeSettingsService && Schema::hasTable('website_settings')) {
                $settings = $storeSettingsService->all();
            }
        } catch (\Throwable $e) {
            // Fall back to env/config based credentials when database access is not ready yet.
            $this->logWarning('Paymob settings fallback to config/env because website settings could not be loaded.', [
                'message' => $e->getMessage(),
            ]);
        }

        $this->baseUrl = rtrim((string) $this->setting($settings, 'paymob_base_url', config('services.paymob.base_url')), '/');
        $this->apiKey = (string) $this->setting($settings, 'paymob_api_key', config('services.paymob.api_key'));
        $this->hmacSecret = (string) $this->setting($settings, 'paymob_hmac_secret', config('services.paymob.hmac_secret'));
        $this->integrationId = (string) $this->setting($settings, 'paymob_integration_id', config('services.paymob.integration_id'));
        $this->iframeId = (string) $this->setting($settings, 'paymob_iframe_id', config('services.paymob.iframe_id'));
        $this->currency = (string) $this->setting($settings, 'paymob_currency', config('services.paymob.currency', 'EGP'));
        $this->verifySsl = (bool) config('services.paymob.verify_ssl', true);
        $this->settingsSource = [
            'base_url' => (! array_key_exists('paymob_base_url', $settings) || blank($settings['paymob_base_url'] ?? null)) ? 'config/env' : 'website_settings',
            'api_key' => (! array_key_exists('paymob_api_key', $settings) || blank($settings['paymob_api_key'] ?? null)) ? 'config/env' : 'website_settings',
            'hmac_secret' => (! array_key_exists('paymob_hmac_secret', $settings) || blank($settings['paymob_hmac_secret'] ?? null)) ? 'config/env' : 'website_settings',
            'integration_id' => (! array_key_exists('paymob_integration_id', $settings) || blank($settings['paymob_integration_id'] ?? null)) ? 'config/env' : 'website_settings',
            'iframe_id' => (! array_key_exists('paymob_iframe_id', $settings) || blank($settings['paymob_iframe_id'] ?? null)) ? 'config/env' : 'website_settings',
            'currency' => (! array_key_exists('paymob_currency', $settings) || blank($settings['paymob_currency'] ?? null)) ? 'config/env' : 'website_settings',
        ];

        $this->logInfo('Paymob runtime config resolved', [
            'base_url' => $this->baseUrl,
            'api_key_prefix' => $this->apiKey !== '' ? substr($this->apiKey, 0, 10) . '***' : null,
            'hmac_prefix' => $this->hmacSecret !== '' ? substr($this->hmacSecret, 0, 8) . '***' : null,
            'integration_id' => $this->integrationId,
            'iframe_id' => $this->iframeId,
            'currency' => $this->currency,
            'verify_ssl' => $this->verifySsl,
            'settings_keys_present' => array_values(array_intersect(array_keys($settings), [
                'paymob_base_url',
                'paymob_api_key',
                'paymob_hmac_secret',
                'paymob_integration_id',
                'paymob_iframe_id',
                'paymob_currency',
            ])),
            'settings_source' => $this->settingsSource,
        ]);
    }

    protected function setting(array $settings, string $key, mixed $fallback = null): mixed
    {
        $value = $settings[$key] ?? null;

        if ($value === null) {
            return $fallback;
        }

        if (is_string($value) && trim($value) === '') {
            return $fallback;
        }

        return $value;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== ''
            && $this->apiKey !== ''
            && $this->integrationId !== ''
            && $this->iframeId !== '';
    }

    public function configurationDiagnostics(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'integration_id' => $this->integrationId,
            'iframe_id' => $this->iframeId,
            'currency' => $this->currency,
            'verify_ssl' => $this->verifySsl,
            'settings_source' => $this->settingsSource,
            'api_key_present' => $this->apiKey !== '',
            'hmac_secret_present' => $this->hmacSecret !== '',
            'is_configured' => $this->isConfigured(),
        ];
    }

    protected function checkoutUrlFromToken(string $paymentToken): string
    {
        return $this->checkoutUrlFromToken($paymentToken);
    }

    protected function client()
    {
        return Http::acceptJson()
            ->timeout(60)
            ->withOptions([
                'verify' => $this->verifySsl,
            ]);
    }

    protected function sanitizePayloadForLogs(array $payload): array
    {
        $sanitized = $payload;

        if (isset($sanitized['auth_token'])) {
            $sanitized['auth_token'] = substr((string) $sanitized['auth_token'], 0, 10) . '***';
        }

        if (isset($sanitized['api_key'])) {
            $sanitized['api_key'] = substr((string) $sanitized['api_key'], 0, 10) . '***';
        }

        return $sanitized;
    }

    protected function formatApiFailureMessage(string $endpoint, int $status, mixed $body): string
    {
        if ($endpoint === 'acceptance/payment_keys' && $status === 403) {
            return 'Paymob rejected the payment key request. This usually means the integration is not active for this account, the iframe is not linked to the same integration, or the app is reading stale credentials from website settings instead of the current .env values.';
        }

        return 'Paymob request failed.';
    }

    protected function buildFailureDiagnostics(string $endpoint, int $status, mixed $body, array $payload): array
    {
        return [
            'endpoint' => $endpoint,
            'status' => $status,
            'body' => $body,
            'integration_id' => $payload['integration_id'] ?? $this->integrationId,
            'iframe_id' => $this->iframeId,
            'currency' => $payload['currency'] ?? $this->currency,
            'order_id' => $payload['order_id'] ?? null,
            'merchant_order_id' => $payload['merchant_order_id'] ?? null,
            'amount_cents' => $payload['amount_cents'] ?? null,
            'settings_source' => $this->settingsSource,
        ];
    }

    protected function post(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $this->logInfo('Paymob API request started', [
            'endpoint' => $endpoint,
            'url' => $url,
            'payload' => $this->sanitizePayloadForLogs($payload),
            'payload_meta' => [
                'integration_id' => $payload['integration_id'] ?? null,
                'amount_cents' => $payload['amount_cents'] ?? null,
                'currency' => $payload['currency'] ?? null,
                'order_id' => $payload['order_id'] ?? null,
                'merchant_order_id' => $payload['merchant_order_id'] ?? null,
                'has_billing_data' => isset($payload['billing_data']),
            ],
        ]);

        $response = $this->client()->post($url, $payload);

        if (! $response->successful()) {
            $this->logError('Paymob API request failed', [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
                'payload' => $this->sanitizePayloadForLogs($payload),
                'payload_meta' => [
                    'integration_id' => $payload['integration_id'] ?? null,
                    'amount_cents' => $payload['amount_cents'] ?? null,
                    'currency' => $payload['currency'] ?? null,
                    'order_id' => $payload['order_id'] ?? null,
                    'merchant_order_id' => $payload['merchant_order_id'] ?? null,
                    'has_billing_data' => isset($payload['billing_data']),
                ],
            ]);

            $diagnostics = $this->buildFailureDiagnostics($endpoint, $response->status(), $response->json() ?: $response->body(), $payload);

            throw new PaymobCheckoutException(
                $this->formatApiFailureMessage($endpoint, $response->status(), $response->json() ?: $response->body()),
                $diagnostics,
                $endpoint === 'acceptance/payment_keys' && $response->status() === 403
                    ? __('We could not open the secure payment page because the Paymob integration is rejecting payment key creation. Please try again later or contact support.')
                    : __('Unable to contact the payment gateway right now. Please try again shortly.')
            );
        }

        $this->logInfo('Paymob API request succeeded', [
            'endpoint' => $endpoint,
            'url' => $url,
            'status' => $response->status(),
            'response' => $response->json() ?: $response->body(),
        ]);

        return $response->json();
    }

    protected function resolveOrderAmount(Order $order): float
    {
        $candidates = [
            $order->grand_total ?? null,
            $order->total ?? null,
            $order->subtotal ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (float) $candidate > 0) {
                return (float) $candidate;
            }
        }

        throw new RuntimeException(
            "Unable to resolve payable amount for order #{$order->id}."
        );
    }

    protected function amountCentsFromOrder(Order $order): int
    {
        $resolvedAmount = $this->resolveOrderAmount($order);
        $amountCents = (int) round($resolvedAmount * 100);

        $this->logInfo('Paymob amount conversion', [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?? null,
            'grand_total' => $order->grand_total ?? null,
            'total' => $order->total ?? null,
            'subtotal' => $order->subtotal ?? null,
            'resolved_amount' => $resolvedAmount,
            'amount_cents' => $amountCents,
        ]);

        if ($amountCents < 10) {
            throw new RuntimeException(
                "Invalid Paymob amount. Resolved order amount [{$resolvedAmount}] produced [{$amountCents}] cents."
            );
        }

        return $amountCents;
    }

    protected function splitCustomerName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return ['Customer', 'User'];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = $parts[0] ?? 'Customer';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'User';

        return [$firstName, $lastName];
    }

    protected function paymentMeta(Payment $payment): array
    {
        return is_array($payment->meta) ? $payment->meta : [];
    }

    protected function appendEvent(array $meta, string $event, string $message, array $extra = []): array
    {
        $events = $meta['events'] ?? [];
        $events[] = array_filter(array_merge([
            'event' => $event,
            'message' => $message,
            'at' => now()->toDateTimeString(),
        ], $extra), static fn ($value) => $value !== null);

        $meta['events'] = array_slice($events, -20);

        return $meta;
    }

    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'on'], true);
    }

    protected function normalizeStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    protected function normalizeCountry(?string $country): string
    {
        $country = strtolower(trim((string) $country));

        return match ($country) {
            '', 'egypt', 'eg' => 'EG',
            'saudi arabia', 'sa', 'ksa' => 'SA',
            'united arab emirates', 'uae', 'ae' => 'AE',
            default => strlen($country) === 2 ? strtoupper($country) : 'EG',
        };
    }

    public function authenticate(): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Paymob is not fully configured yet.');
        }

        $this->logInfo('Paymob authentication started');

        $response = $this->post('auth/tokens', [
            'api_key' => $this->apiKey,
        ]);

        $token = $response['token'] ?? null;

        if (! $token) {
            throw new RuntimeException('Paymob auth token missing.');
        }

        $this->logInfo('Paymob authentication succeeded', [
            'token_prefix' => substr((string) $token, 0, 10) . '***',
        ]);

        return $token;
    }

    public function registerOrder(Order $order, string $authToken): int
    {
        $amountCents = $this->amountCentsFromOrder($order);

        $this->logInfo('Paymob register order started', [
            'local_order_id' => $order->id,
            'order_number' => $order->order_number ?? null,
            'amount_cents' => $amountCents,
            'currency' => $this->currency,
        ]);

        $response = $this->post('ecommerce/orders', [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => $this->currency,
            'merchant_order_id' => (string) $order->id,
            'items' => [],
        ]);

        $paymobOrderId = $response['id'] ?? null;

        if (! $paymobOrderId) {
            throw new RuntimeException('Paymob order id missing.');
        }

        $this->logInfo('Paymob register order succeeded', [
            'local_order_id' => $order->id,
            'order_number' => $order->order_number ?? null,
            'paymob_order_id' => $paymobOrderId,
            'amount_cents' => $amountCents,
        ]);

        return (int) $paymobOrderId;
    }

    public function generatePaymentKey(Order $order, string $authToken, int $paymobOrderId): string
    {
        $amountCents = $this->amountCentsFromOrder($order);

        [$firstName, $lastName] = $this->splitCustomerName($order->customer_name);

        $billingData = [
            'apartment' => 'NA',
            'email' => $order->customer_email ?: 'no-reply@example.com',
            'floor' => 'NA',
            'first_name' => $firstName,
            'street' => $order->shipping_address_line_1 ?: 'NA',
            'building' => 'NA',
            'phone_number' => $order->customer_phone ?: 'NA',
            'shipping_method' => 'NA',
            'postal_code' => $order->shipping_postal_code ?: 'NA',
            'city' => $order->shipping_city ?: 'NA',
            'country' => $this->normalizeCountry($order->shipping_country),
            'last_name' => $lastName,
            'state' => $order->shipping_state ?: 'NA',
        ];

        $this->logInfo('Paymob generate payment key started', [
            'local_order_id' => $order->id,
            'order_number' => $order->order_number ?? null,
            'paymob_order_id' => $paymobOrderId,
            'integration_id' => (int) $this->integrationId,
            'iframe_id' => $this->iframeId,
            'amount_cents' => $amountCents,
            'currency' => $this->currency,
            'billing_data' => $billingData,
        ]);

        $response = $this->post('acceptance/payment_keys', [
            'auth_token' => $authToken,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $paymobOrderId,
            'billing_data' => $billingData,
            'currency' => $this->currency,
            'integration_id' => (int) $this->integrationId,
            'lock_order_when_paid' => true,
        ]);

        $paymentToken = $response['token'] ?? null;

        if (! $paymentToken) {
            throw new RuntimeException('Paymob payment token missing.');
        }

        $this->logInfo('Paymob generate payment key succeeded', [
            'local_order_id' => $order->id,
            'paymob_order_id' => $paymobOrderId,
            'payment_token_prefix' => substr((string) $paymentToken, 0, 10) . '***',
        ]);

        return $paymentToken;
    }

    public function reuseCheckoutUrlIfAvailable(?Payment $payment): ?string
    {
        if (! $payment) {
            return null;
        }

        if (in_array($payment->status, [
            Payment::STATUS_PAID,
            Payment::STATUS_FAILED,
            Payment::STATUS_REFUNDED,
        ], true)) {
            return null;
        }

        $meta = $this->paymentMeta($payment);
        $paymentToken = data_get($meta, 'payment_token');
        $lastInitiatedAt = data_get($meta, 'last_initiated_at');

        if (! $paymentToken || ! $lastInitiatedAt) {
            return null;
        }

        try {
            $lastInitiated = \Carbon\Carbon::parse($lastInitiatedAt);
        } catch (\Throwable $e) {
            return null;
        }

        if ($lastInitiated->diffInMinutes(now()) > 15) {
            return null;
        }

        return $this->checkoutUrlFromToken($paymentToken);
    }

    public function checkoutUrl(Order $order, ?Payment $payment = null): string
    {
        $this->logInfo('Paymob checkout flow started', [
            'local_order_id' => $order->id,
            'order_number' => $order->order_number ?? null,
            'payment_id' => $payment?->id,
        ]);

        $reusedUrl = $this->reuseCheckoutUrlIfAvailable($payment);
        if ($reusedUrl) {
            $this->logInfo('Paymob checkout URL reused from existing payment token', [
                'local_order_id' => $order->id,
                'payment_id' => $payment?->id,
            ]);

            return $reusedUrl;
        }

        $authToken = $this->authenticate();

        $existingPaymobOrderId = null;
        $meta = [];

        if ($payment) {
            $meta = $this->paymentMeta($payment);
            $existingPaymobOrderId = data_get($meta, 'paymob_order_id');
        }

        $paymobOrderId = $existingPaymobOrderId
            ? (int) $existingPaymobOrderId
            : $this->registerOrder($order, $authToken);

        if ($payment && ! $existingPaymobOrderId) {
            $meta['paymob_order_id'] = $paymobOrderId;
            $meta = $this->appendEvent($meta, 'paymob_order_registered', 'Paymob order registration completed.', [
                'paymob_order_id' => $paymobOrderId,
            ]);

            $payment->update([
                'provider' => 'paymob',
                'provider_status' => $payment->provider_status ?: 'initiated',
                'transaction_reference' => (string) $paymobOrderId,
                'meta' => $meta,
            ]);

            $payment->refresh();
            $meta = $this->paymentMeta($payment);
        }

        $paymentToken = $this->generatePaymentKey($order, $authToken, $paymobOrderId);

        if ($payment) {
            $meta = $this->paymentMeta($payment);
            $meta['payment_token'] = $paymentToken;
            $meta['last_initiated_at'] = now()->toDateTimeString();
            $meta['paymob_payment_key_created_at'] = now()->toDateTimeString();
            unset($meta['checkout_error']);
            unset($meta['checkout_error_at']);
            unset($meta['checkout_error_context']);
            $meta = $this->appendEvent($meta, 'paymob_checkout_started', 'Customer was redirected to the Paymob hosted checkout.', [
                'paymob_order_id' => $paymobOrderId,
            ]);

            $payment->update([
                'provider' => 'paymob',
                'provider_status' => 'initiated',
                'transaction_reference' => (string) $paymobOrderId,
                'meta' => $meta,
            ]);
        }

        $this->logInfo('Paymob checkout flow completed', [
            'local_order_id' => $order->id,
            'payment_id' => $payment?->id,
            'paymob_order_id' => $paymobOrderId,
            'iframe_id' => $this->iframeId,
        ]);

        return $this->checkoutUrlFromToken($paymentToken);
    }

    protected function extractOrderIdentifiers(array $payload): array
    {
        $obj = is_array($payload['obj'] ?? null) ? $payload['obj'] : [];

        $merchantOrderId = data_get($obj, 'order.merchant_order_id')
            ?? data_get($obj, 'merchant_order_id')
            ?? data_get($payload, 'merchant_order_id');

        $paymobOrderId = data_get($obj, 'order.id')
            ?? data_get($obj, 'order')
            ?? data_get($payload, 'order')
            ?? data_get($payload, 'paymob_order_id');

        $transactionId = data_get($obj, 'id')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'txn_id');

        return [
            'merchant_order_id' => $merchantOrderId ? (string) $merchantOrderId : null,
            'paymob_order_id' => $paymobOrderId ? (string) $paymobOrderId : null,
            'transaction_id' => $transactionId ? (string) $transactionId : null,
            'obj' => $obj,
        ];
    }

    protected function resolveOrderFromPayload(array $payload): ?Order
    {
        $identifiers = $this->extractOrderIdentifiers($payload);

        if ($identifiers['merchant_order_id']) {
            $order = Order::with('payments')->find($identifiers['merchant_order_id']);
            if ($order) {
                return $order;
            }
        }

        if ($identifiers['paymob_order_id']) {
            $payment = Payment::query()
                ->where('provider', 'paymob')
                ->where(function ($query) use ($identifiers) {
                    $query->where('transaction_reference', $identifiers['paymob_order_id'])
                        ->orWhere('meta->paymob_order_id', $identifiers['paymob_order_id']);
                })
                ->latest('id')
                ->first();

            if ($payment?->order) {
                return $payment->order->loadMissing('payments');
            }
        }

        if ($identifiers['transaction_id']) {
            $payment = Payment::query()
                ->where('provider', 'paymob')
                ->where('transaction_reference', $identifiers['transaction_id'])
                ->latest('id')
                ->first();

            if ($payment?->order) {
                return $payment->order->loadMissing('payments');
            }
        }

        return null;
    }

    protected function validateHmac(array $payload): ?bool
    {
        if ($this->hmacSecret === '') {
            return null;
        }

        $providedHmac = data_get($payload, 'hmac');
        if (! $providedHmac) {
            return null;
        }

        $obj = is_array($payload['obj'] ?? null) ? $payload['obj'] : [];
        if ($obj === []) {
            return null;
        }

        $fields = [
            data_get($obj, 'amount_cents'),
            data_get($obj, 'created_at'),
            data_get($obj, 'currency'),
            data_get($obj, 'error_occured') ? 'true' : 'false',
            data_get($obj, 'has_parent_transaction') ? 'true' : 'false',
            data_get($obj, 'id'),
            data_get($obj, 'integration_id'),
            data_get($obj, 'is_3d_secure') ? 'true' : 'false',
            data_get($obj, 'is_auth') ? 'true' : 'false',
            data_get($obj, 'is_capture') ? 'true' : 'false',
            data_get($obj, 'is_refunded') ? 'true' : 'false',
            data_get($obj, 'is_standalone_payment') ? 'true' : 'false',
            data_get($obj, 'is_voided') ? 'true' : 'false',
            data_get($obj, 'order.id'),
            data_get($obj, 'owner'),
            data_get($obj, 'pending') ? 'true' : 'false',
            data_get($obj, 'source_data.pan'),
            data_get($obj, 'source_data.sub_type'),
            data_get($obj, 'source_data.type'),
            data_get($obj, 'success') ? 'true' : 'false',
        ];

        $data = implode('', array_map(static fn ($value) => $value === null ? '' : (string) $value, $fields));
        $calculated = hash_hmac('sha512', $data, $this->hmacSecret);

        return hash_equals(strtolower((string) $providedHmac), strtolower($calculated));
    }

    public function handleCallback(array $payload): array
    {
        $this->logInfo('Paymob callback payload', [
            'payload' => $payload,
        ]);

        $identifiers = $this->extractOrderIdentifiers($payload);
        $obj = $identifiers['obj'];
        $order = $this->resolveOrderFromPayload($payload);
        $payment = $order?->payments()->latest('id')->first();

        if (! $order || ! $payment) {
            return [
                'valid' => false,
                'success' => false,
                'pending' => false,
                'message' => 'Order or payment could not be resolved from callback data.',
                'order' => $order,
                'payment' => $payment,
                'provider_status' => 'unresolved',
                'transaction_id' => $identifiers['transaction_id'],
                'paymob_order_id' => $identifiers['paymob_order_id'],
                'merchant_order_id' => $identifiers['merchant_order_id'],
                'hmac_valid' => $this->validateHmac($payload),
                'data' => $payload,
            ];
        }

        $success = $this->truthy(data_get($obj, 'success') ?? data_get($payload, 'success'))
            || $this->truthy(data_get($obj, 'is_paid') ?? data_get($payload, 'is_paid'));

        $pending = $this->truthy(data_get($obj, 'pending') ?? data_get($payload, 'pending'));
        $isVoided = $this->truthy(data_get($obj, 'is_voided') ?? data_get($payload, 'is_voided'));
        $isRefunded = $this->truthy(data_get($obj, 'is_refunded') ?? data_get($payload, 'is_refunded'));
        $errorOccured = $this->truthy(data_get($obj, 'error_occured') ?? data_get($payload, 'error_occured'));

        $responseCode = $this->normalizeStatusValue(
            data_get($obj, 'txn_response_code') ?? data_get($payload, 'txn_response_code')
        );

        $responseMessage = data_get($obj, 'txn_response_message') ?? data_get($payload, 'txn_response_message');

        $declined = ! $success
            && (
                $errorOccured
                || $isVoided
                || $isRefunded
                || ($pending === false && $responseCode !== null && ! in_array($responseCode, ['approved', 'success', '00'], true))
            );

        $providerStatus = $success
            ? 'paid'
            : ($pending
                ? 'pending'
                : ($declined ? 'declined' : 'failed'));

        $message = $success
            ? 'Callback processed successfully.'
            : ($responseMessage ?: 'Callback processed with non-successful status.');

        return [
            'valid' => true,
            'success' => $success,
            'pending' => ! $success && $pending,
            'message' => $message,
            'order' => $order,
            'payment' => $payment,
            'provider_status' => $providerStatus,
            'transaction_id' => $identifiers['transaction_id'],
            'paymob_order_id' => $identifiers['paymob_order_id'],
            'merchant_order_id' => $identifiers['merchant_order_id'],
            'response_code' => $responseCode,
            'response_message' => $responseMessage,
            'hmac_valid' => $this->validateHmac($payload),
            'data' => $payload,
        ];
    }
}