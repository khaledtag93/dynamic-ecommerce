<?php

namespace App\Services\Channels\WhatsApp\Support;

use App\Models\WebsiteSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class WhatsAppConfig
{
    public function enabled(): bool
    {
        return $this->bool('whatsapp_enabled', (bool) config('whatsapp.enabled', false));
    }

    public function featureEnabled(string $feature): bool
    {
        return $this->bool('whatsapp_feature_'.$feature, (bool) config('whatsapp.features.'.$feature, false));
    }

    public function provider(): string
    {
        return (string) $this->value('whatsapp_default_provider', (string) config('whatsapp.default_provider', 'meta'));
    }

    public function queueEnabled(): bool
    {
        return $this->bool('whatsapp_queue_enabled', (bool) config('whatsapp.queue.enabled', false));
    }

    public function queueConnection(): ?string
    {
        $value = (string) $this->value('whatsapp_queue_connection', (string) config('whatsapp.queue.connection', ''));

        return $value !== '' ? $value : null;
    }

    public function queueName(): ?string
    {
        $value = (string) $this->value('whatsapp_queue_queue', (string) config('whatsapp.queue.queue', 'default'));

        return $value !== '' ? $value : null;
    }

    public function queueTries(): int
    {
        return max(1, (int) $this->value('whatsapp_queue_tries', (int) config('whatsapp.queue.tries', 3)));
    }

    public function queueBackoffSeconds(): int
    {
        return max(0, (int) $this->value('whatsapp_queue_backoff_seconds', (int) config('whatsapp.queue.backoff_seconds', 30)));
    }

    public function queueTimeout(): int
    {
        return max(10, (int) $this->value('whatsapp_queue_timeout', (int) config('whatsapp.queue.timeout', 120)));
    }
    public function queueBackoffStrategy(): string
    {
        $strategy = (string) $this->value('whatsapp_queue_backoff_strategy', (string) config('whatsapp.queue.backoff_strategy', 'exponential'));

        return in_array($strategy, ['fixed', 'exponential'], true) ? $strategy : 'exponential';
    }

    public function queueBackoffMaxSeconds(): int
    {
        return max(30, (int) $this->value('whatsapp_queue_backoff_max_seconds', (int) config('whatsapp.queue.backoff_max_seconds', 300)));
    }

    public function queueBackoffSchedule(): array|int
    {
        $base = max(1, $this->queueBackoffSeconds());

        if ($this->queueBackoffStrategy() !== 'exponential') {
            return $base;
        }

        $max = $this->queueBackoffMaxSeconds();

        return [
            $base,
            min($max, $base * 2),
            min($max, $base * 4),
            min($max, $base * 8),
        ];
    }


    public function duplicateWindowMinutes(): int
    {
        $value = (int) $this->value('whatsapp_duplicate_window_minutes', (int) config('whatsapp.duplicate_guard.window_minutes', 30));

        return max(1, $value);
    }
    public function metaConnectTimeout(): int
    {
        return max(3, (int) $this->meta('connect_timeout', 10));
    }

    public function metaRetryTimes(): int
    {
        return max(0, (int) $this->meta('retry_times', 2));
    }

    public function metaRetrySleepMilliseconds(): int
    {
        return max(100, (int) $this->meta('retry_sleep_ms', 400));
    }


    public function fallbackLocale(): string
    {
        $locale = (string) $this->value('whatsapp_fallback_locale', 'ar');

        return $locale === 'ar' ? 'ar' : 'en';
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        $configKey = 'whatsapp.meta.'.$key;

        return $this->value('whatsapp_meta_'.$key, config($configKey, $default));
    }

    public function template(string $messageType, string $locale): array
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $base = config('whatsapp.templates.'.$messageType, []);

        return [
            'name' => (string) $this->value('whatsapp_template_'.$messageType.'_name_'.$locale, Arr::get($base, 'name_'.$locale)),
            'language' => (string) $this->value('whatsapp_template_'.$messageType.'_language_'.$locale, Arr::get($base, 'language_'.$locale)),
            'body_map' => Arr::get($base, 'body_map', []),
            'sample_body' => (string) Arr::get($base, 'sample_body_'.$locale, ''),
        ];
    }

    protected function value(string $key, mixed $default = null): mixed
    {
        try {
            if (Schema::hasTable('website_settings')) {
                return WebsiteSetting::getValue($key, $default);
            }
        } catch (\Throwable) {
        }

        return $default;
    }

    protected function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->value($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }
}
