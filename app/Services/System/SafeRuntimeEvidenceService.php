<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Bounded M3/M4 diagnostic snapshot. Source-only until a separately approved
 * QAS deployment. No mail/provider/queue services or seed/repair methods.
 *
 * NOTE: Standard authenticated HTTP middleware may maintain a session; this
 * service itself makes only bounded SQL SELECTs and no application writes.
 */
final class SafeRuntimeEvidenceService
{
    private const QAS_DATABASE = 'u637857322_v42_rehearsal';
    private const CI_DATABASE = 'dynamic_test';

    private const FAILURE_CATEGORIES = [
        'TEMPLATE_RENDER',
        'TIMEOUT',
        'RATE_LIMIT',
        'RECIPIENT_VALIDATION',
        'OTHER_OR_UNKNOWN',
    ];

    public function snapshot(): array
    {
        // Do not allow an accidental Production/public diagnostic.
        // CI is permitted only with its isolated dynamic_test database.
        abort_unless(app()->environment('staging', 'testing'), 404);

        $stage = SafeRuntimeEvidenceFailure::IDENTITY_GATE;

        try {
            abort_unless(DB::connection()->getDriverName() === 'mysql', 404);

            // Unlike configured DB_DATABASE, this checks the *connected* DB.
            $connected = DB::selectOne('SELECT DATABASE() AS connected_database');
            $actualDatabase = (string) ($connected->connected_database ?? '');
            $environment = app()->environment('staging') && hash_equals(self::QAS_DATABASE, $actualDatabase)
                ? 'QAS'
                : (app()->environment('testing') && hash_equals(self::CI_DATABASE, $actualDatabase)
                    ? 'TEST_ONLY'
                    : null);

            abort_unless($environment !== null, 404);
            $stage = SafeRuntimeEvidenceFailure::REQUIRED_SCHEMA;
            abort_unless(
                Schema::hasTable('website_settings')
                    && Schema::hasTable('notification_automation_rules')
                    && Schema::hasTable('notification_dispatch_logs'),
                503
            );

            $stage = SafeRuntimeEvidenceFailure::SETTINGS_READ;
            $settings = DB::table('website_settings')
                ->whereIn('key', [
                    'whatsapp_enabled',
                    'whatsapp_queue_enabled',
                    'whatsapp_feature_order_confirmation',
                    'whatsapp_feature_order_status_update',
                    'whatsapp_feature_delivery_update',
                    'notification_center_enabled',
                    'notification_channel_email_enabled',
                    'notification_channel_whatsapp_enabled',
                ])
                ->pluck('value', 'key')
                ->all();

            $mail = $this->safeMailTransport();
            $whatsApp = [
                'master' => $this->flag($settings, 'whatsapp_enabled', config('whatsapp.enabled', false)),
                'order_confirmation' => $this->flag($settings, 'whatsapp_feature_order_confirmation', config('whatsapp.features.order_confirmation', true)),
                'order_status_update' => $this->flag($settings, 'whatsapp_feature_order_status_update', config('whatsapp.features.order_status_update', false)),
                'delivery_update' => $this->flag($settings, 'whatsapp_feature_delivery_update', config('whatsapp.features.delivery_update', false)),
                'queue' => $this->flag($settings, 'whatsapp_queue_enabled', config('whatsapp.queue.enabled', false)),
                'queue_off_can_dispatch_sync' => true,
                // A manager-level OFF is not proof about all possible senders.
                'provider_isolation' => 'UNVERIFIED',
            ];

            $stage = SafeRuntimeEvidenceFailure::AUTOMATION_COUNT;
            $activeAutomationRules = (int) DB::table('notification_automation_rules')
                ->where('is_active', 1)->count();

            $notifications = [
                'engine' => $this->flag($settings, 'notification_center_enabled', true),
                'email_channel' => $this->flag($settings, 'notification_channel_email_enabled', true),
                'whatsapp_channel' => $this->flag($settings, 'notification_channel_whatsapp_enabled', true),
                'active_automation_rules' => $activeAutomationRules,
            ];

            $stage = SafeRuntimeEvidenceFailure::FAILED_EMAIL_AGGREGATES;
            $failedEmailDispatch = $this->failedEmailCategories();

            return [
                'status' => 'EVIDENCE_ONLY',
                'environment' => $environment,
                'mail' => $mail,
                'whatsapp' => $whatsApp,
                'notifications' => $notifications,
                'failed_email_dispatch' => $failedEmailDispatch,
                'external_isolation' => 'UNVERIFIED',
            ];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            // Preserve 404/503 and suppress original exception details, while
            // retaining a fixed, non-sensitive stage for authorized diagnostics.
            throw new SafeRuntimeEvidenceFailure($stage, $e->getStatusCode());
        } catch (Throwable) {
            // Never chain the original exception: it can contain SQL, recipients,
            // provider details or other secrets.
            throw new SafeRuntimeEvidenceFailure($stage);
        }
    }

    private function safeMailTransport(): array
    {
        $selected = config('mail.default');
        $transport = is_string($selected)
            ? config('mail.mailers.'.$selected.'.transport')
            : null;

        $known = [
            'array', 'log', 'smtp', 'sendmail', 'ses', 'ses-v2',
            'postmark', 'mailgun', 'failover', 'roundrobin',
        ];

        if (! is_string($transport) || ! in_array($transport, $known, true)) {
            return ['transport' => 'other', 'boundary' => 'UNKNOWN'];
        }

        $boundary = match ($transport) {
            'array' => 'LARAVEL_ARRAY_ONLY',
            'log' => 'LOCAL_LOG_ONLY_UNVERIFIED',
            'failover', 'roundrobin' => 'COMPOSITE_UNVERIFIED',
            default => 'EXTERNAL_POSSIBLE',
        };

        return ['transport' => $transport, 'boundary' => $boundary];
    }

    private function flag(array $settings, string $key, mixed $fallback): string
    {
        if (array_key_exists($key, $settings)) {
            return match ((string) $settings[$key]) {
                '1' => 'ON',
                '0' => 'OFF',
                default => 'UNKNOWN',
            };
        }

        if (is_bool($fallback)) {
            return $fallback ? 'ON' : 'OFF';
        }

        // Avoid labeling unrecognized defaults as OFF.
        return match ((string) $fallback) {
            '1' => 'ON',
            '0' => 'OFF',
            default => 'UNKNOWN',
        };
    }

    private function failedEmailCategories(): array
    {
        // Compute only fixed numeric aggregates inside MySQL. Avoid grouping
        // by an expression based on a free-text failure column and never
        // retrieve individual error messages, recipients, IDs or payloads.
        $error = "LOWER(COALESCE(error_message, ''))";
        $template = "($error LIKE '%htmlspecialchars%' OR $error LIKE '%viewexception%')";
        $timeout = "($error LIKE '%timed out%' OR $error LIKE '%timeout%')";
        $rateLimit = "($error LIKE '%rate limit%' OR $error LIKE '%too many requests%')";
        $recipient = "($error LIKE '%recipient%' OR $error LIKE '%invalid phone%')";

        $row = DB::table('notification_dispatch_logs')
            ->where('channel', 'email')
            ->where('status', 'failed')
            ->selectRaw(
                "COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN $template THEN 1 ELSE 0 END), 0) AS template_count,
                COALESCE(SUM(CASE WHEN NOT $template AND $timeout THEN 1 ELSE 0 END), 0) AS timeout_count,
                COALESCE(SUM(CASE WHEN NOT $template AND NOT $timeout AND $rateLimit THEN 1 ELSE 0 END), 0) AS rate_limit_count,
                COALESCE(SUM(CASE WHEN NOT $template AND NOT $timeout AND NOT $rateLimit AND $recipient THEN 1 ELSE 0 END), 0) AS recipient_count"
            )
            ->first();

        if ($row === null) {
            throw new \RuntimeException('Sanitized email aggregate unavailable.');
        }

        $counts = array_fill_keys(self::FAILURE_CATEGORIES, 0);
        $counts['TEMPLATE_RENDER'] = (int) $row->template_count;
        $counts['TIMEOUT'] = (int) $row->timeout_count;
        $counts['RATE_LIMIT'] = (int) $row->rate_limit_count;
        $counts['RECIPIENT_VALIDATION'] = (int) $row->recipient_count;

        $total = (int) $row->total_count;
        $known = array_sum($counts);
        if ($total < 0 || $known > $total) {
            // The surrounding snapshot() preserves its fail-closed stage.
            throw new \RuntimeException('Inconsistent sanitized email aggregate.');
        }

        $counts['OTHER_OR_UNKNOWN'] = $total - $known;

        return [
            'total' => $total,
            'categories' => $counts,
        ];
    }

}
