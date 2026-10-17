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
            abort_unless(
                Schema::hasTable('website_settings')
                    && Schema::hasTable('notification_automation_rules')
                    && Schema::hasTable('notification_dispatch_logs'),
                503
            );

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

            $notifications = [
                'engine' => $this->flag($settings, 'notification_center_enabled', true),
                'email_channel' => $this->flag($settings, 'notification_channel_email_enabled', true),
                'whatsapp_channel' => $this->flag($settings, 'notification_channel_whatsapp_enabled', true),
                'active_automation_rules' => (int) DB::table('notification_automation_rules')
                    ->where('is_active', 1)->count(),
            ];

            return [
                'status' => 'EVIDENCE_ONLY',
                'environment' => $environment,
                'mail' => $mail,
                'whatsapp' => $whatsApp,
                'notifications' => $notifications,
                'failed_email_dispatch' => $this->failedEmailCategories(),
                'external_isolation' => 'UNVERIFIED',
            ];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            // Aborted/mismatched preflight is intentional; never degrade it
            // into a weaker "best effort" response.
            throw $e;
        } catch (Throwable) {
            // No DB/driver/exception details may escape on failure.
            abort(503, 'Safe diagnostic snapshot unavailable.');
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
        // Only categories and counts leave MySQL; never SELECT error_message,
        // recipients, raw payload, IDs, or whole Eloquent models.
        // Priority mirrors NotificationFailureSummary's approved pattern
        // precedence, without exporting its free-text or localized messages.
        $error = "LOWER(COALESCE(error_message, ''))";
        $case = "CASE
            WHEN $error LIKE '%htmlspecialchars%' OR $error LIKE '%viewexception%' THEN 'TEMPLATE_RENDER'
            WHEN $error LIKE '%timed out%' OR $error LIKE '%timeout%' THEN 'TIMEOUT'
            WHEN $error LIKE '%rate limit%' OR $error LIKE '%too many requests%' THEN 'RATE_LIMIT'
            WHEN $error LIKE '%recipient%' OR $error LIKE '%invalid phone%' THEN 'RECIPIENT_VALIDATION'
            ELSE 'OTHER_OR_UNKNOWN' END";

        $groups = DB::table('notification_dispatch_logs')
            ->where('channel', 'email')
            ->where('status', 'failed')
            ->selectRaw("$case AS category, COUNT(*) AS failure_count")
            ->groupByRaw($case)
            ->limit(count(self::FAILURE_CATEGORIES))
            ->get();

        $counts = array_fill_keys(self::FAILURE_CATEGORIES, 0);
        foreach ($groups as $row) {
            if (array_key_exists((string) $row->category, $counts)) {
                $counts[$row->category] = (int) $row->failure_count;
            }
        }

        return [
            'total' => array_sum($counts),
            'categories' => $counts,
        ];
    }
}
