<?php

namespace App\Services\Channels\WhatsApp;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Order;
use App\Models\WhatsAppLog;
use App\Services\Channels\WhatsApp\Providers\MetaWhatsAppProvider;
use App\Services\Channels\WhatsApp\Support\TemplateRenderer;
use App\Services\Channels\WhatsApp\Support\WhatsAppConfig;
use App\Services\Channels\WhatsApp\Support\WhatsAppPhoneNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppManager implements WhatsAppServiceInterface
{
    public function __construct(
        protected WhatsAppConfig $config,
        protected MetaWhatsAppProvider $metaProvider,
        protected WhatsAppPhoneNormalizer $phoneNormalizer,
        protected TemplateRenderer $templateRenderer,
    ) {
    }

    public function queueOrderConfirmation(Order $order): void
    {
        $this->queueMessage($order, WhatsAppMessageType::ORDER_CONFIRMATION);
    }

    public function queueOrderStatusUpdate(Order $order): void
    {
        $this->queueMessage($order, WhatsAppMessageType::ORDER_STATUS_UPDATE);
    }

    public function queueDeliveryUpdate(Order $order): void
    {
        $this->queueMessage($order, WhatsAppMessageType::DELIVERY_UPDATE);
    }

    public function sendOrderConfirmation(Order|int $order): ?WhatsAppLog
    {
        $order = $order instanceof Order ? $order : Order::query()->find($order);

        return $order ? $this->sendForOrder($order, WhatsAppMessageType::ORDER_CONFIRMATION) : null;
    }

    public function sendOrderStatusUpdate(Order|int $order): ?WhatsAppLog
    {
        $order = $order instanceof Order ? $order : Order::query()->find($order);

        return $order ? $this->sendForOrder($order, WhatsAppMessageType::ORDER_STATUS_UPDATE) : null;
    }

    public function sendDeliveryUpdate(Order|int $order): ?WhatsAppLog
    {
        $order = $order instanceof Order ? $order : Order::query()->find($order);

        return $order ? $this->sendForOrder($order, WhatsAppMessageType::DELIVERY_UPDATE) : null;
    }

    public function retry(WhatsAppLog|int $log): ?WhatsAppLog
    {
        $log = $log instanceof WhatsAppLog ? $log : WhatsAppLog::query()->find($log);

        if (! $log || ! $log->order_id) {
            return null;
        }

        $messageType = WhatsAppMessageType::tryFrom((string) $log->message_type);

        if (! $messageType || ! $log->order) {
            return null;
        }

        $lock = Cache::lock('whatsapp-retry:'.$log->id, 10);

        if (! $lock->get()) {
            Log::info('WhatsApp retry skipped because another retry lock is active.', [
                'whatsapp_log_id' => $log->id,
                'order_id' => $log->order_id,
                'message_type' => $log->message_type,
            ]);

            return null;
        }

        try {
            $meta = (array) ($log->meta ?? []);
            $meta['last_retry_requested_at'] = now()->toDateTimeString();
            $meta['operator_retry'] = true;
            $log->forceFill([
                'meta' => $meta,
            ])->save();

            return $this->sendForOrder($log->order, $messageType, $log->attempts, true, $log);
        } finally {
            optional($lock)->release();
        }
    }

    protected function queueMessage(Order $order, WhatsAppMessageType $messageType): void
    {
        if (! $this->config->enabled() || ! $this->config->featureEnabled($messageType->value)) {
            return;
        }

        DB::afterCommit(function () use ($order, $messageType): void {
            if ($this->config->queueEnabled()) {
                $job = SendWhatsAppMessageJob::dispatch($order->id, $messageType->value);

                if ($connection = $this->config->queueConnection()) {
                    $job->onConnection($connection);
                }

                if ($queue = $this->config->queueName()) {
                    $job->onQueue($queue);
                }

                return;
            }

            SendWhatsAppMessageJob::dispatchSync($order->id, $messageType->value);
        });
    }

    protected function sendForOrder(Order $order, WhatsAppMessageType $messageType, int $previousAttempts = 0, bool $force = false, ?WhatsAppLog $retrySourceLog = null): WhatsAppLog
    {
        $normalizedPhone = $this->phoneNormalizer->normalize($order->customer_phone);
        $locale = $this->resolveLocale($order);
        $template = $this->config->template($messageType->value, $locale);

        if (! $force && ($duplicateLog = $this->recentDuplicateLog($order, $messageType))) {
            return WhatsAppLog::create([
                'order_id' => $order->id,
                'provider' => $this->config->provider(),
                'message_type' => $messageType->value,
                'status' => WhatsAppLog::STATUS_SKIPPED,
                'phone' => $order->customer_phone,
                'normalized_phone' => $normalizedPhone,
                'locale' => $locale,
                'template_name' => $template['name'] ?: null,
                'attempts' => $previousAttempts + 1,
                'error_message' => 'Duplicate guard skipped this WhatsApp message because a recent pending/sent message already exists for the same order and type.',
                'failed_at' => now(),
                'meta' => [
                    'order_number' => $order->order_number,
                    'order_status' => $order->status,
                    'delivery_status' => $order->delivery_status,
                    'duplicate_guard' => true,
                    'duplicate_of_log_id' => $duplicateLog->id,
                    'queue_enabled' => $this->config->queueEnabled(),
                    'queue_connection' => $this->config->queueConnection(),
                    'queue_name' => $this->config->queueName(),
                    'sample_preview' => $this->samplePreview($messageType->value, $locale, $order),
                ],
            ]);
        }

        $log = WhatsAppLog::create([
            'order_id' => $order->id,
            'provider' => $this->config->provider(),
            'message_type' => $messageType->value,
            'status' => WhatsAppLog::STATUS_PENDING,
            'phone' => $order->customer_phone,
            'normalized_phone' => $normalizedPhone,
            'locale' => $locale,
            'template_name' => $template['name'] ?: null,
            'attempts' => $previousAttempts + 1,
            'meta' => [
                'order_number' => $order->order_number,
                'order_status' => $order->status,
                'delivery_status' => $order->delivery_status,
                'queue_enabled' => $this->config->queueEnabled(),
                'queue_connection' => $this->config->queueConnection(),
                'queue_name' => $this->config->queueName(),
                'sample_preview' => $this->samplePreview($messageType->value, $locale, $order),
                'is_retry' => $retrySourceLog !== null,
                'retry_of_log_id' => $retrySourceLog?->id,
                'operator_retry' => $retrySourceLog !== null,
                'idempotency_key' => sha1(implode('|', [
                    'whatsapp',
                    $order->id,
                    $messageType->value,
                    $normalizedPhone,
                    $retrySourceLog?->id ?: 'fresh',
                    now()->format('YmdHi'),
                ])),
            ],
        ]);

        if (! $this->config->enabled()) {
            return tap($log)->update([
                'status' => WhatsAppLog::STATUS_SKIPPED,
                'error_message' => 'WhatsApp channel is disabled.',
                'failed_at' => now(),
            ]);
        }

        if (! $this->config->featureEnabled($messageType->value)) {
            return tap($log)->update([
                'status' => WhatsAppLog::STATUS_SKIPPED,
                'error_message' => 'WhatsApp feature is disabled for this message type.',
                'failed_at' => now(),
            ]);
        }

        if (! $normalizedPhone) {
            return tap($log)->update([
                'status' => WhatsAppLog::STATUS_FAILED,
                'error_message' => 'Customer phone is missing or invalid for WhatsApp.',
                'failed_at' => now(),
            ]);
        }

        if (blank($template['name']) || blank($template['language'])) {
            return tap($log)->update([
                'status' => WhatsAppLog::STATUS_FAILED,
                'error_message' => 'WhatsApp template name or language is not configured.',
                'failed_at' => now(),
            ]);
        }

        $requestPayload = null;
        $responsePayload = null;
        $result = [];

        try {
            $templateName = (string) $template['name'];
            $parameters = $templateName === 'hello_world'
                ? []
                : $this->templateParameters($messageType, $order, $locale);

            $result = $this->provider()->sendTemplate(
                $normalizedPhone,
                $templateName,
                (string) $template['language'],
                $parameters,
                $this->providerConfig([
                    'message_type' => $messageType->value,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'locale' => $locale,
                ])
            );

            $requestPayload = $result['request_payload'] ?? $result['request'] ?? null;
            $responsePayload = $result['body'] ?? $result['response'] ?? null;

            if (! (bool) ($result['success'] ?? false)) {
                $errorMessage = (string) data_get($responsePayload, 'error.message', data_get($responsePayload, 'message', 'WhatsApp provider rejected the request.'));
                throw new RuntimeException($errorMessage);
            }

            $meta = (array) ($log->meta ?? []);
            $meta['provider_status_code'] = $result['status'] ?? null;
            $meta['request_url'] = $result['request_url'] ?? null;

            $log->update([
                'status' => WhatsAppLog::STATUS_SENT,
                'request_payload' => $requestPayload,
                'response_payload' => $responsePayload,
                'provider_message_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
                'error_message' => null,
                'meta' => $meta,
            ]);
        } catch (Throwable $e) {
            report($e);

            $meta = (array) ($log->meta ?? []);
            $meta['request_url'] = $result['request_url'] ?? null;
            $meta['provider_status_code'] = $result['status'] ?? null;

            $log->update([
                'status' => WhatsAppLog::STATUS_FAILED,
                'request_payload' => $requestPayload,
                'response_payload' => $responsePayload,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
                'meta' => $meta,
            ]);
        }

        Log::info('WhatsApp send attempt finished.', [
            'whatsapp_log_id' => $log->id,
            'order_id' => $order->id,
            'message_type' => $messageType->value,
            'status' => $log->status,
            'attempts' => $log->attempts,
            'is_retry' => $retrySourceLog !== null,
            'retry_of_log_id' => $retrySourceLog?->id,
        ]);

        return $log->fresh();
    }

    protected function recentDuplicateLog(Order $order, WhatsAppMessageType $messageType): ?WhatsAppLog
    {
        $windowStart = Carbon::now()->subMinutes($this->config->duplicateWindowMinutes());

        return WhatsAppLog::query()
            ->forOrder((int) $order->id)
            ->forMessageType($messageType->value)
            ->activeForDuplicateGuard()
            ->where(function ($query) use ($windowStart) {
                $query->where('created_at', '>=', $windowStart)
                    ->orWhere('sent_at', '>=', $windowStart);
            })
            ->latest('id')
            ->first();
    }

    protected function provider(): MetaWhatsAppProvider
    {
        return $this->metaProvider;
    }

    protected function resolveLocale(Order $order): string
    {
        $metaLocale = data_get($order->meta, 'locale');
        $locale = $metaLocale ?: $this->config->fallbackLocale();

        return $locale === 'ar' ? 'ar' : 'en';
    }

    protected function templateParameters(WhatsAppMessageType $messageType, Order $order, string $locale): array
    {
        return match ($messageType) {
            WhatsAppMessageType::ORDER_CONFIRMATION => [
                (string) $order->customer_name,
                (string) $order->order_number,
                number_format((float) $order->grand_total, 2).' '.(string) $order->currency,
                (string) $order->payment_method_label,
                (string) $order->delivery_method_label,
            ],
            WhatsAppMessageType::ORDER_STATUS_UPDATE => [
                (string) $order->customer_name,
                (string) $order->order_number,
                (string) $order->status_label,
                number_format((float) $order->grand_total, 2).' '.(string) $order->currency,
            ],
            WhatsAppMessageType::DELIVERY_UPDATE => [
                (string) $order->customer_name,
                (string) $order->order_number,
                (string) $order->delivery_status_label,
                (string) ($order->tracking_number ?: ($locale === 'ar' ? 'سيتم إضافته قريبًا' : 'Will be shared soon')),
            ],
        };
    }

    protected function samplePreview(string $messageType, string $locale, Order $order): string
    {
        $template = $this->config->template($messageType, $locale);
        $sample = (string) ($template['sample_body'] ?? '');

        if ($sample === '') {
            return '';
        }

        $messageTypeEnum = WhatsAppMessageType::tryFrom($messageType);

        if (! $messageTypeEnum) {
            return $sample;
        }

        $parameters = $this->templateParameters($messageTypeEnum, $order, $locale);

        foreach ($parameters as $index => $value) {
            $sample = str_replace('{{'.($index + 1).'}}', (string) $value, $sample);
        }

        return $sample;
    }

    protected function providerConfig(array $overrides = []): array
    {
        return array_merge([
            'base_url' => (string) $this->config->meta('base_url', 'https://graph.facebook.com'),
            'graph_version' => (string) $this->config->meta('graph_version', 'v23.0'),
            'access_token' => (string) $this->config->meta('access_token', ''),
            'phone_number_id' => (string) $this->config->meta('phone_number_id', ''),
            'business_account_id' => (string) $this->config->meta('business_account_id', ''),
            'timeout' => (int) $this->config->meta('timeout', 20),
        ], $overrides);
    }
}
