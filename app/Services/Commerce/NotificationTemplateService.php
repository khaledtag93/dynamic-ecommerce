<?php

namespace App\Services\Commerce;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Mail\OrderUpdateMail;
use App\Models\NotificationDispatchLog;
use App\Models\NotificationMessageTemplate;
use App\Models\Order;
use App\Notifications\DeliveryStatusUpdatedNotification;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class NotificationTemplateService
{
    public function __construct(
        protected StoreSettingsService $storeSettingsService,
        protected WhatsAppServiceInterface $whatsAppService,
    ) {
    }

    public static function supportedLocales(): array
    {
        return [
            'ar' => __('Arabic'),
            'en' => __('English'),
        ];
    }

    public function ensureDefaults(): void
    {
        if (! Schema::hasTable('notification_message_templates')) {
            return;
        }

        foreach ($this->defaultTemplates() as $template) {
            NotificationMessageTemplate::query()->updateOrCreate(
                [
                    'event' => $template['event'],
                    'channel' => $template['channel'],
                    'locale' => $template['locale'],
                ],
                Arr::only($template, ['name', 'title', 'subject', 'body', 'tokens', 'is_active', 'sort_order'])
            );
        }
    }

    public function groupedTemplates()
    {
        if (! Schema::hasTable('notification_message_templates')) {
            return collect();
        }

        return NotificationMessageTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('event')
            ->orderBy('channel')
            ->orderBy('locale')
            ->get()
            ->groupBy(['event', 'channel', 'locale']);
    }

    public function resolveTemplate(string $event, string $channel, string $locale): ?NotificationMessageTemplate
    {
        if (! Schema::hasTable('notification_message_templates')) {
            return null;
        }

        $template = NotificationMessageTemplate::query()
            ->where('event', $event)
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->first();

        if ($template) {
            return $template;
        }

        $defaultLocale = (string) ($this->storeSettingsService->all()['default_locale'] ?? 'ar');

        return NotificationMessageTemplate::query()
            ->where('event', $event)
            ->where('channel', $channel)
            ->where('locale', $defaultLocale)
            ->first();
    }

    public function resolveLocaleForOrder(Order $order, ?string $preferredLocale = null): string
    {
        if ($preferredLocale && array_key_exists($preferredLocale, static::supportedLocales())) {
            return $preferredLocale;
        }

        $metaLocale = data_get($order->meta, 'locale');
        if (is_string($metaLocale) && array_key_exists($metaLocale, static::supportedLocales())) {
            return $metaLocale;
        }

        $settings = $this->storeSettingsService->all();
        $defaultLocale = (string) ($settings['default_locale'] ?? 'ar');

        return array_key_exists($defaultLocale, static::supportedLocales()) ? $defaultLocale : 'ar';
    }

    public function renderForOrder(Order $order, string $event, string $channel, ?string $locale = null, array $extraTokens = []): array
    {
        $locale = $this->resolveLocaleForOrder($order, $locale);
        $template = $this->resolveTemplate($event, $channel, $locale);
        $settings = $this->storeSettingsService->all();
        $tokens = $this->buildTokens($order, $locale, $extraTokens);

        if (! $template || ! $template->is_active) {
            return [
                'template' => $template,
                'locale' => $locale,
                'tokens' => $tokens,
                'title' => $this->fallbackTitle($event),
                'subject' => $this->fallbackSubject($event, $order, $locale, $settings),
                'body' => $this->fallbackBody($event, $order, $locale, $settings),
            ];
        }

        return [
            'template' => $template,
            'locale' => $locale,
            'tokens' => $tokens,
            'title' => $this->replaceTokens((string) ($template->title ?: $this->fallbackTitle($event)), $tokens),
            'subject' => $this->replaceTokens((string) ($template->subject ?: $this->fallbackSubject($event, $order, $locale, $settings)), $tokens),
            'body' => $this->replaceTokens((string) $template->body, $tokens),
        ];
    }

    public function saveTemplate(string $event, string $channel, string $locale, array $data): NotificationMessageTemplate
    {
        if (! Schema::hasTable('notification_message_templates')) {
            throw new RuntimeException('Notification templates table is missing.');
        }

        return NotificationMessageTemplate::query()->updateOrCreate(
            [
                'event' => $event,
                'channel' => $channel,
                'locale' => $locale,
            ],
            [
                'name' => $data['name'] ?: Str::headline($event.' '.$channel.' '.$locale),
                'title' => $data['title'] ?: null,
                'subject' => $data['subject'] ?: null,
                'body' => $data['body'],
                'tokens' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['tokens_text'] ?? ''))))),
                'is_active' => ! empty($data['is_active']),
                'sort_order' => (int) ($data['sort_order'] ?? 100),
            ]
        );
    }

    public function sendTest(Order $order, string $event, string $channel, ?string $locale = null, ?string $testEmail = null): void
    {
        $rendered = $this->renderForOrder($order, $event, $channel, $locale);

        if ($channel === 'database') {
            if (! $order->user) {
                throw new RuntimeException(__('The selected order has no linked customer account for in-app testing.'));
            }

            match ($event) {
                OrderNotificationService::EVENT_DELIVERY_UPDATED => $order->user->notify(new DeliveryStatusUpdatedNotification($order)),
                default => $order->user->notify(new OrderStatusChangedNotification($order, $rendered['body'])),
            };

            $this->createDispatchLog($order, $event, $channel, $rendered, $order->user->email ?: $order->customer_email, __('Manual admin test send.'));

            return;
        }

        if ($channel === 'email') {
            $email = trim((string) ($testEmail ?: $order->customer_email ?: $order->user?->email ?: ''));
            if ($email === '') {
                throw new RuntimeException(__('No email recipient is available for this test send.'));
            }

            Mail::to($email)->send(new OrderUpdateMail($order, $event, $rendered['subject'] ?: $rendered['title'], $rendered['body']));
            $this->createDispatchLog($order, $event, $channel, $rendered, $email, __('Manual admin test send.'));

            return;
        }

        if ($channel === 'whatsapp') {
            match ($event) {
                OrderNotificationService::EVENT_STATUS_UPDATED,
                OrderNotificationService::EVENT_CANCELLED => $this->whatsAppService->sendOrderStatusUpdate($order),
                OrderNotificationService::EVENT_DELIVERY_UPDATED => $this->whatsAppService->sendDeliveryUpdate($order),
                default => throw new RuntimeException(__('WhatsApp test send is not available for this event yet.')),
            };

            return;
        }

        throw new RuntimeException(__('Test send is not available for this channel.'));
    }

    protected function createDispatchLog(Order $order, string $event, string $channel, array $rendered, ?string $recipient, ?string $note = null): void
    {
        if (! Schema::hasTable('notification_dispatch_logs')) {
            return;
        }

        NotificationDispatchLog::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'event' => $event,
            'channel' => $channel,
            'status' => NotificationDispatchLog::STATUS_SENT,
            'title' => $rendered['title'] ?: $rendered['subject'],
            'message' => $rendered['body'],
            'recipient' => $recipient,
            'provider' => $channel,
            'payload' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'delivery_status' => $order->delivery_status,
                'test_send' => true,
            ],
            'response_payload' => ['note' => $note],
            'attempted_at' => now(),
            'sent_at' => now(),
            'meta' => [
                'test_send' => true,
                'locale' => $rendered['locale'] ?? null,
                'template_id' => $rendered['template']?->id,
            ],
        ]);
    }

    protected function replaceTokens(string $content, array $tokens): string
    {
        return str_replace(array_keys($tokens), array_values($tokens), $content);
    }

    protected function buildTokens(Order $order, string $locale, array $extraTokens = []): array
    {
        $settings = $this->storeSettingsService->all();
        $storeName = (string) ($settings['store_name_'.$locale] ?? $settings['store_name'] ?? config('app.name'));

        $tokens = [
            ':store_name' => $storeName,
            ':customer_name' => (string) ($order->customer_name ?: $order->user?->name ?: __('Customer')),
            ':order_number' => (string) $order->order_number,
            ':order_status' => (string) $order->status_label,
            ':delivery_status' => (string) $order->delivery_status_label,
            ':payment_status' => (string) $order->payment_status_label,
            ':payment_method' => (string) $order->payment_method_label,
            ':grand_total' => number_format((float) $order->grand_total, 2),
            ':currency' => (string) ($order->currency ?: 'EGP'),
            ':tracking_number' => (string) ($order->tracking_number ?: '—'),
            ':shipping_provider' => (string) ($order->shipping_provider ?: '—'),
            ':refund_total' => number_format((float) $order->refund_total, 2),
            ':estimated_delivery_date' => $order->estimated_delivery_date?->format('Y-m-d') ?: '—',
            ':customer_phone' => (string) ($order->customer_phone ?: $order->user?->phone ?: '—'),
            ':customer_email' => (string) ($order->customer_email ?: $order->user?->email ?: '—'),
        ];

        foreach ($extraTokens as $key => $value) {
            $tokens[Str::startsWith($key, ':') ? $key : ':'.$key] = (string) $value;
        }

        return $tokens;
    }

    protected function fallbackTitle(string $event): string
    {
        return match ($event) {
            OrderNotificationService::EVENT_STATUS_UPDATED => __('Order update'),
            OrderNotificationService::EVENT_CANCELLED => __('Order cancelled'),
            OrderNotificationService::EVENT_DELIVERY_UPDATED => __('Delivery update'),
            OrderNotificationService::EVENT_REFUND_RECORDED => __('Refund update'),
            default => __('Notification update'),
        };
    }

    protected function fallbackSubject(string $event, Order $order, string $locale, array $settings): string
    {
        $storeName = (string) ($settings['store_name_'.$locale] ?? $settings['store_name'] ?? config('app.name'));

        return $this->fallbackTitle($event).' - '.$order->order_number.' - '.$storeName;
    }

    protected function fallbackBody(string $event, Order $order, string $locale, array $settings): string
    {
        return match ($event) {
            OrderNotificationService::EVENT_STATUS_UPDATED => $locale === 'ar'
                ? "مرحبًا :customer_name، تم تحديث حالة طلبك :order_number إلى :order_status."
                : 'Hello :customer_name, your order :order_number is now :order_status.',
            OrderNotificationService::EVENT_CANCELLED => $locale === 'ar'
                ? "مرحبًا :customer_name، تم إلغاء طلبك :order_number."
                : 'Hello :customer_name, your order :order_number has been cancelled.',
            OrderNotificationService::EVENT_DELIVERY_UPDATED => $locale === 'ar'
                ? "مرحبًا :customer_name، حالة التوصيل لطلبك :order_number أصبحت :delivery_status."
                : 'Hello :customer_name, delivery for your order :order_number is now :delivery_status.',
            OrderNotificationService::EVENT_REFUND_RECORDED => $locale === 'ar'
                ? "مرحبًا :customer_name، تم تسجيل استرداد بقيمة :refund_total :currency على الطلب :order_number."
                : 'Hello :customer_name, a refund of :refund_total :currency was recorded on order :order_number.',
            default => $locale === 'ar'
                ? 'لديك تحديث جديد مرتبط بطلبك :order_number.'
                : 'You have a new update related to your order :order_number.',
        };
    }

    protected function defaultTemplates(): array
    {
        $defaults = [];
        $events = OrderNotificationService::eventDefinitions();
        $channels = ['database', 'email', 'whatsapp'];
        $sort = 10;

        foreach (array_keys($events) as $event) {
            foreach ($channels as $channel) {
                foreach (array_keys(static::supportedLocales()) as $locale) {
                    $isWhatsAppRefund = $channel === 'whatsapp' && $event === OrderNotificationService::EVENT_REFUND_RECORDED;
                    $defaults[] = [
                        'event' => $event,
                        'channel' => $channel,
                        'locale' => $locale,
                        'name' => Str::headline($event.' '.$channel.' '.$locale),
                        'title' => $this->fallbackTitle($event),
                        'subject' => $channel === 'email' ? $this->fallbackTitle($event).' - :order_number' : null,
                        'body' => $this->fallbackBody($event, new Order([
                            'order_number' => ':order_number',
                            'customer_name' => ':customer_name',
                            'status' => Order::STATUS_PENDING,
                            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
                            'payment_status' => Order::PAYMENT_STATUS_PENDING,
                            'payment_method' => Order::PAYMENT_METHOD_COD,
                            'grand_total' => 0,
                            'currency' => 'EGP',
                            'refund_total' => 0,
                        ]), $locale, $this->storeSettingsService->all()),
                        'tokens' => [':store_name', ':customer_name', ':order_number', ':order_status', ':delivery_status', ':payment_status', ':payment_method', ':grand_total', ':currency', ':tracking_number', ':shipping_provider', ':refund_total', ':estimated_delivery_date'],
                        'is_active' => ! $isWhatsAppRefund,
                        'sort_order' => $sort++,
                    ];
                }
            }
        }

        return $defaults;
    }
}
