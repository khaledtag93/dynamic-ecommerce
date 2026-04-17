<?php

namespace App\Services\Commerce;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Mail\OrderUpdateMail;
use App\Models\NotificationDispatchLog;
use App\Models\Order;
use App\Models\WhatsAppLog;
use App\Notifications\DeliveryStatusUpdatedNotification;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrderNotificationService
{
    public const EVENT_STATUS_UPDATED = 'order_status_updated';
    public const EVENT_CANCELLED = 'order_cancelled';
    public const EVENT_DELIVERY_UPDATED = 'delivery_status_updated';
    public const EVENT_REFUND_RECORDED = 'refund_recorded';

    public function __construct(
        protected WhatsAppServiceInterface $whatsAppService,
        protected StoreSettingsService $storeSettingsService,
        protected NotificationTemplateService $notificationTemplateService,
        protected NotificationAutomationService $notificationAutomationService,
    ) {
    }

    /**
     * @return array<string,array{label:string,description:string}>
     */
    public static function supportedChannels(): array
    {
        return [
            'database' => [
                'label' => __('In-app / database'),
                'description' => __('Stores notification records inside the application notification inbox.'),
            ],
            'email' => [
                'label' => __('Email'),
                'description' => __('Uses the configured Laravel mail driver to email the customer.'),
            ],
            'whatsapp' => [
                'label' => __('WhatsApp'),
                'description' => __('Uses the production WhatsApp channel and templates for customer updates.'),
            ],
            'sms' => [
                'label' => __('SMS (ready later)'),
                'description' => __('Reserved for a future SMS provider without changing the event engine structure.'),
            ],
        ];
    }

    /**
     * @return array<string,array{label:string,description:string}>
     */
    public static function eventDefinitions(): array
    {
        return [
            self::EVENT_STATUS_UPDATED => [
                'label' => __('Order status updated'),
                'description' => __('Triggered when the order moves between pending, processing, completed, and similar states.'),
            ],
            self::EVENT_CANCELLED => [
                'label' => __('Order cancelled'),
                'description' => __('Triggered when an order is cancelled and stock is restored.'),
            ],
            self::EVENT_DELIVERY_UPDATED => [
                'label' => __('Delivery status updated'),
                'description' => __('Triggered when the delivery progress changes such as preparing or delivered.'),
            ],
            self::EVENT_REFUND_RECORDED => [
                'label' => __('Refund recorded'),
                'description' => __('Triggered after a refund entry updates payment status and refund totals.'),
            ],
        ];
    }

    public function notifyStatusUpdated(Order $order, ?string $message = null): void
    {
        $message ??= __('Order :order is now :status.', [
            'order' => $order->order_number,
            'status' => $order->status_label,
        ]);

        $this->dispatch(self::EVENT_STATUS_UPDATED, $order, [
            'title' => __('Order update'),
            'message' => $message,
        ]);
    }

    public function notifyCancelled(Order $order, ?string $message = null): void
    {
        $message ??= __('Your order :order was cancelled.', [
            'order' => $order->order_number,
        ]);

        $this->dispatch(self::EVENT_CANCELLED, $order, [
            'title' => __('Order cancelled'),
            'message' => $message,
        ]);
    }

    public function notifyDeliveryUpdated(Order $order, ?string $message = null): void
    {
        $message ??= __('Delivery for order :order is now :status.', [
            'order' => $order->order_number,
            'status' => $order->delivery_status_label,
        ]);

        $this->dispatch(self::EVENT_DELIVERY_UPDATED, $order, [
            'title' => __('Delivery update'),
            'message' => $message,
        ]);
    }

    public function notifyRefundRecorded(Order $order, ?string $message = null): void
    {
        $message ??= __('A refund was recorded on your order :order.', [
            'order' => $order->order_number,
        ]);

        $this->dispatch(self::EVENT_REFUND_RECORDED, $order, [
            'title' => __('Refund update'),
            'message' => $message,
        ]);
    }

    /**
     * @param  array{title?:string,message?:string,channels?:array<int,string>,meta?:array<string,mixed>}  $payload
     */
    public function dispatch(string $event, Order $order, array $payload = []): void
    {
        $channels = $payload['channels'] ?? $this->channelsForEvent($event);
        $message = (string) ($payload['message'] ?? '');
        $title = (string) ($payload['title'] ?? '');
        $meta = (array) ($payload['meta'] ?? []);
        $preferredLocale = isset($payload['locale']) ? (string) $payload['locale'] : null;

        foreach ($channels as $channel) {
            $rendered = $this->notificationTemplateService->renderForOrder($order, $event, $channel, $preferredLocale, [
                'custom_message' => $message,
            ]);

            $channelTitle = $title !== '' ? $title : (string) ($rendered['title'] ?? __('Order update'));
            $channelMessage = $message !== '' ? $message : (string) ($rendered['body'] ?? '');

            $logMeta = array_merge($meta, [
                'locale' => $rendered['locale'] ?? $preferredLocale,
                'template_id' => $rendered['template']?->id,
            ]);

            $log = $this->createDispatchLog($event, $channel, $order, $channelTitle, $channelMessage, $logMeta);

            if ($log && $log->status === NotificationDispatchLog::STATUS_SKIPPED && data_get($log->meta, 'duplicate_guard')) {
                continue;
            }

            try {
                $result = match ($channel) {
                    'database' => $this->sendDatabase($event, $order, $channelMessage),
                    'email' => $this->sendEmail($event, $order, (string) ($rendered['subject'] ?: $channelTitle), $channelMessage),
                    'whatsapp' => $this->sendWhatsApp($event, $order),
                    default => 'skipped',
                };

                if ($result === 'skipped') {
                    $this->markDispatchSkipped($log, __('Recipient is missing or the channel is reserved for later.'));

                    if ($log) {
                        $this->notificationAutomationService->executeForDispatch($log);
                    }

                    continue;
                }

                $this->markDispatchSent($log, [
                    'provider' => $channel === 'whatsapp' ? 'whatsapp' : $channel,
                ]);
            } catch (Throwable $e) {
                $this->markDispatchFailed($log, $e->getMessage());

                if ($log) {
                    $this->notificationAutomationService->executeForDispatch($log);
                }

                Log::warning('Order notification channel failed.', [
                    'event' => $event,
                    'channel' => $channel,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function retryDispatchLog(NotificationDispatchLog $log): array
    {
        $order = $log->order;

        if (! $order) {
            return [
                'ok' => false,
                'message' => __('This notification log cannot be retried because the related order is missing.'),
                'reason_code' => 'missing_order',
            ];
        }

        $lockKey = 'notification-dispatch-retry:'.$log->id;
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return [
                'ok' => false,
                'message' => __('A retry is already being processed for this notification log.'),
                'reason_code' => 'retry_locked',
            ];
        }

        try {
            $meta = (array) ($log->meta ?? []);
            $meta['retry_of_id'] = $log->id;
            $meta['retry_requested_at'] = now()->toDateTimeString();
            $meta['operator_retry'] = true;
            $meta['retry_source'] = 'admin_notification_center';
            $meta['idempotency_key'] = sha1(implode('|', [
                'dispatch_retry',
                $log->id,
                $log->event,
                $log->channel,
                now()->format('YmdHi'),
            ]));

            $this->dispatch($log->event, $order, [
                'channels' => [$log->channel],
                'title' => (string) ($log->title ?? __('Order update')),
                'message' => (string) ($log->message ?? ''),
                'meta' => $meta,
            ]);

            if (Schema::hasTable('notification_dispatch_logs')) {
                $currentMeta = (array) ($log->meta ?? []);
                $currentMeta['retry_count'] = ((int) ($currentMeta['retry_count'] ?? 0)) + 1;
                $currentMeta['last_retry_requested_at'] = now()->toDateTimeString();
                $currentMeta['last_retry_reason'] = 'operator_requested';

                $log->forceFill([
                    'retried_at' => now(),
                    'meta' => $currentMeta,
                ])->save();
            }

            Log::info('Notification dispatch retry executed.', [
                'notification_dispatch_log_id' => $log->id,
                'order_id' => $order->id,
                'event' => $log->event,
                'channel' => $log->channel,
                'reason_code' => 'operator_requested',
            ]);

            return [
                'ok' => true,
                'message' => __('Notification retry was executed safely.'),
                'reason_code' => 'retry_executed',
            ];
        } finally {
            optional($lock)->release();
        }
    }

    protected function sendDatabase(string $event, Order $order, string $message): bool
    {
        if (! $order->user) {
            return false;
        }

        match ($event) {
            self::EVENT_DELIVERY_UPDATED => $order->user->notify(new DeliveryStatusUpdatedNotification($order)),
            default => $order->user->notify(new OrderStatusChangedNotification($order, $message)),
        };

        return true;
    }

    protected function sendEmail(string $event, Order $order, string $title, string $message): bool
    {
        $email = trim((string) ($order->customer_email ?: $order->user?->email ?: ''));

        if ($email === '') {
            return false;
        }

        Mail::to($email)->send(new OrderUpdateMail($order, $event, $title, $message));

        return true;
    }

    protected function sendWhatsApp(string $event, Order $order): bool
    {
        if (blank($order->customer_phone) && blank($order->user?->phone ?? null)) {
            return false;
        }

        match ($event) {
            self::EVENT_STATUS_UPDATED,
            self::EVENT_CANCELLED => $this->whatsAppService->queueOrderStatusUpdate($order),
            self::EVENT_DELIVERY_UPDATED => $this->whatsAppService->queueDeliveryUpdate($order),
            default => null,
        };

        return true;
    }

    /**
     * @return array<int,string>
     */
    protected function channelsForEvent(string $event): array
    {
        $defaults = (array) config('commerce_notifications.default_channels', ['database', 'email']);
        $events = (array) config('commerce_notifications.events', []);
        $configuredChannels = array_values(array_unique((array) ($events[$event] ?? $defaults)));

        return array_values(array_filter($configuredChannels, fn (string $channel) => $this->isChannelEnabledForEvent($event, $channel)));
    }

    protected function isChannelEnabledForEvent(string $event, string $channel): bool
    {
        $settings = $this->storeSettingsService->all();

        if (($settings['notification_center_enabled'] ?? '1') !== '1') {
            return false;
        }

        if (($settings['notification_channel_'.$channel.'_enabled'] ?? '0') !== '1') {
            return false;
        }

        if (($settings['notification_event_'.$event.'_'.$channel] ?? '0') !== '1') {
            return false;
        }

        if ($channel === 'whatsapp') {
            if (($settings['whatsapp_enabled'] ?? '0') !== '1') {
                return false;
            }

            $featureKey = match ($event) {
                self::EVENT_STATUS_UPDATED, self::EVENT_CANCELLED => 'whatsapp_feature_order_status_update',
                self::EVENT_DELIVERY_UPDATED => 'whatsapp_feature_delivery_update',
                default => null,
            };

            if ($featureKey && ($settings[$featureKey] ?? '0') !== '1') {
                return false;
            }
        }

        if ($channel === 'sms') {
            return false;
        }

        return true;
    }

    protected function shouldPreventDuplicateDispatch(string $event, string $channel, Order $order, array $meta = []): ?NotificationDispatchLog
    {
        if (! Schema::hasTable('notification_dispatch_logs')) {
            return null;
        }

        if (($meta['force_retry'] ?? false) || ($meta['automation_run'] ?? false) || ($meta['test_send'] ?? false)) {
            return null;
        }

        $fingerprint = sha1(implode('|', [
            $order->id,
            $event,
            $channel,
            (string) ($meta['template_id'] ?? ''),
            (string) ($meta['locale'] ?? ''),
        ]));

        $windowStart = now()->subMinutes(3);

        return NotificationDispatchLog::query()
            ->where('order_id', $order->id)
            ->where('event', $event)
            ->where('channel', $channel)
            ->whereIn('status', [NotificationDispatchLog::STATUS_PENDING, NotificationDispatchLog::STATUS_SENT])
            ->where('created_at', '>=', $windowStart)
            ->where('meta->dispatch_fingerprint', $fingerprint)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    protected function createDispatchLog(string $event, string $channel, Order $order, string $title, string $message, array $meta = []): ?NotificationDispatchLog
    {
        if (! Schema::hasTable('notification_dispatch_logs')) {
            return null;
        }

        $meta['dispatch_fingerprint'] = $meta['dispatch_fingerprint'] ?? sha1(implode('|', [
            $order->id,
            $event,
            $channel,
            (string) ($meta['template_id'] ?? ''),
            (string) ($meta['locale'] ?? ''),
        ]));

        if ($duplicateLog = $this->shouldPreventDuplicateDispatch($event, $channel, $order, $meta)) {
            Log::info('Notification dispatch duplicate prevented.', [
                'order_id' => $order->id,
                'event' => $event,
                'channel' => $channel,
                'duplicate_of_log_id' => $duplicateLog->id,
            ]);

            return NotificationDispatchLog::query()->create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'event' => $event,
                'channel' => $channel,
                'status' => NotificationDispatchLog::STATUS_SKIPPED,
                'title' => $title,
                'message' => $message,
                'recipient' => $this->resolveRecipient($channel, $order),
                'provider' => $this->resolveProvider($channel),
                'payload' => [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'delivery_status' => $order->delivery_status,
                ],
                'attempted_at' => now(),
                'failed_at' => now(),
                'error_message' => __('Duplicate protection skipped a repeated dispatch within the current safety window.'),
                'retry_of_id' => $duplicateLog->id,
                'meta' => array_merge($meta, [
                    'duplicate_guard' => true,
                    'duplicate_of_log_id' => $duplicateLog->id,
                ]),
            ]);
        }

        return NotificationDispatchLog::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'event' => $event,
            'channel' => $channel,
            'status' => NotificationDispatchLog::STATUS_PENDING,
            'title' => $title,
            'message' => $message,
            'recipient' => $this->resolveRecipient($channel, $order),
            'provider' => $this->resolveProvider($channel),
            'payload' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'delivery_status' => $order->delivery_status,
            ],
            'attempted_at' => now(),
            'meta' => $meta,
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    protected function markDispatchSent(?NotificationDispatchLog $log, array $data = []): void
    {
        if (! $log) {
            return;
        }

        $log->forceFill([
            'status' => NotificationDispatchLog::STATUS_SENT,
            'provider' => $data['provider'] ?? $log->provider,
            'sent_at' => now(),
            'response_payload' => $data['response_payload'] ?? $log->response_payload,
        ])->save();
    }

    protected function markDispatchFailed(?NotificationDispatchLog $log, string $error): void
    {
        if (! $log) {
            return;
        }

        $log->forceFill([
            'status' => NotificationDispatchLog::STATUS_FAILED,
            'error_message' => $error,
            'failed_at' => now(),
        ])->save();
    }

    protected function markDispatchSkipped(?NotificationDispatchLog $log, string $reason): void
    {
        if (! $log) {
            return;
        }

        $log->forceFill([
            'status' => NotificationDispatchLog::STATUS_SKIPPED,
            'error_message' => $reason,
            'failed_at' => now(),
        ])->save();
    }

    protected function resolveRecipient(string $channel, Order $order): ?string
    {
        return match ($channel) {
            'email' => trim((string) ($order->customer_email ?: $order->user?->email ?: '')) ?: null,
            'whatsapp' => trim((string) ($order->customer_phone ?: $order->user?->phone ?? '')) ?: null,
            'database' => $order->user?->email,
            default => null,
        };
    }

    protected function resolveProvider(string $channel): ?string
    {
        return match ($channel) {
            'email' => config('mail.default'),
            'whatsapp' => $this->storeSettingsService->all()['whatsapp_default_provider'] ?? 'whatsapp',
            'database' => 'database',
            default => null,
        };
    }
}
