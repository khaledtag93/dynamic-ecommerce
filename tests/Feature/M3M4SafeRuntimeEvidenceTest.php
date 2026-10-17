<?php

namespace Tests\Feature;

use App\Models\NotificationDispatchLog;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Source-only safety contract: the new GET must not initialize defaults,
 * write to the DB, enqueue work, contact a provider, or echo raw failures.
 * No QAS or Production access is performed by these synthetic fixtures.
 */
class M3M4SafeRuntimeEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_requires_authenticated_admin_permission(): void
    {
        $this->getJson('/admin/settings/notifications/safe-runtime-evidence')->assertUnauthorized();

        $customer = User::factory()->create(['role_as' => 0]);
        // The existing isAdmin middleware redirects non-admin customers
        // rather than returning a JSON 403; either way no evidence leaks.
        $this->actingAs($customer)
            ->getJson('/admin/settings/notifications/safe-runtime-evidence')
            ->assertStatus(302)
            ->assertDontSee('external_isolation');
    }

    public function test_default_snapshot_is_bounded_and_without_database_writes_or_provider_calls(): void
    {
        $admin = $this->createSuperAdmin();
        Mail::fake();
        Notification::fake();
        Queue::fake();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($admin)
            ->getJson(route('admin.settings.notifications.safe-evidence'));

        $response->assertOk()
            ->assertJsonPath('environment', 'TEST_ONLY')
            ->assertJsonPath('mail.transport', 'array')
            ->assertJsonPath('mail.boundary', 'LARAVEL_ARRAY_ONLY')
            ->assertJsonPath('whatsapp.master', 'OFF')
            ->assertJsonPath('whatsapp.order_confirmation', 'ON')
            ->assertJsonPath('whatsapp.queue', 'OFF')
            ->assertJsonPath('whatsapp.queue_off_can_dispatch_sync', true)
            ->assertJsonPath('whatsapp.provider_isolation', 'UNVERIFIED')
            ->assertJsonPath('notifications.email_channel', 'ON')
            ->assertJsonPath('notifications.whatsapp_channel', 'ON')
            ->assertJsonPath('external_isolation', 'UNVERIFIED')
            ->assertJsonPath('failed_email_dispatch.total', 0);

        $this->assertSame(0, DB::table('notification_automation_rules')->count());
        $this->assertSame(0, DB::table('notification_message_templates')->count());
        $this->assertSame(0, DB::table('website_settings')->count());
        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/^\\s*(?:insert|update|delete|replace|alter|create|drop|truncate|call|exec|set)\\b/i', $sql);
        }

        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_mail_classification_fails_closed_for_external_composite_and_unknown_mailers(): void
    {
        $admin = $this->createSuperAdmin();

        foreach ([
            ['mailer' => 'smtp', 'boundary' => 'EXTERNAL_POSSIBLE', 'transport' => 'smtp'],
            ['mailer' => 'failover', 'boundary' => 'COMPOSITE_UNVERIFIED', 'transport' => 'failover'],
            ['mailer' => 'roundrobin', 'boundary' => 'COMPOSITE_UNVERIFIED', 'transport' => 'roundrobin'],
            ['mailer' => 'untrusted_alias', 'boundary' => 'UNKNOWN', 'transport' => 'other'],
        ] as $case) {
            config()->set('mail.default', $case['mailer']);
            $response = $this->actingAs($admin)->getJson(route('admin.settings.notifications.safe-evidence'));

            $response->assertOk()
                ->assertJsonPath('mail.boundary', $case['boundary'])
                ->assertJsonPath('mail.transport', $case['transport'])
                ->assertJsonPath('external_isolation', 'UNVERIFIED');
        }
    }

    public function test_whatsapp_db_override_precedence_and_event_on_does_not_mean_isolated(): void
    {
        $admin = $this->createSuperAdmin();
        config()->set('whatsapp.enabled', true);
        config()->set('whatsapp.queue.enabled', true);

        WebsiteSetting::setValue('whatsapp_enabled', '0');
        WebsiteSetting::setValue('whatsapp_queue_enabled', '0');
        WebsiteSetting::setValue('whatsapp_feature_order_confirmation', '1');

        $this->actingAs($admin)->getJson(route('admin.settings.notifications.safe-evidence'))
            ->assertOk()
            ->assertJsonPath('whatsapp.master', 'OFF')
            ->assertJsonPath('whatsapp.queue', 'OFF')
            ->assertJsonPath('whatsapp.order_confirmation', 'ON')
            ->assertJsonPath('whatsapp.provider_isolation', 'UNVERIFIED');

        WebsiteSetting::setValue('whatsapp_enabled', 'invalid');

        $this->actingAs($admin)->getJson(route('admin.settings.notifications.safe-evidence'))
            ->assertJsonPath('whatsapp.master', 'UNKNOWN')
            ->assertJsonPath('external_isolation', 'UNVERIFIED');
    }

    public function test_failed_email_categories_are_aggregate_only_never_raw_payloads_or_identifiers(): void
    {
        $admin = $this->createSuperAdmin();
        foreach ([
            ['htmlspecialchars private-token-abc', 'someone1@example.test'],
            ['timed out password=supersecret', 'someone2@example.test'],
            ['Rate limit account-id:1234', 'someone3@example.test'],
            ['Invalid recipient alice@example.test', 'someone4@example.test'],
            ['raw exception /secret/path token=not-for-output', 'someone5@example.test'],
        ] as [$error, $recipient]) {
            NotificationDispatchLog::query()->create([
                'event' => 'order_updated',
                'channel' => 'email',
                'status' => 'failed',
                'error_message' => $error,
                'recipient' => $recipient,
                'message' => 'Confidential order message',
                'payload' => ['password' => 'never-output-me'],
            ]);
        }

        // Unrelated categories/communications are intentionally excluded.
        NotificationDispatchLog::query()->create([
            'event' => 'order_updated',
            'channel' => 'database',
            'status' => 'failed',
            'error_message' => 'timed out',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.settings.notifications.safe-evidence'))
            ->assertOk()
            ->assertJsonPath('failed_email_dispatch.total', 5)
            ->assertJsonPath('failed_email_dispatch.categories.TEMPLATE_RENDER', 1)
            ->assertJsonPath('failed_email_dispatch.categories.TIMEOUT', 1)
            ->assertJsonPath('failed_email_dispatch.categories.RATE_LIMIT', 1)
            ->assertJsonPath('failed_email_dispatch.categories.RECIPIENT_VALIDATION', 1)
            ->assertJsonPath('failed_email_dispatch.categories.OTHER_OR_UNKNOWN', 1);

        $body = $response->getContent();
        foreach (['someone1', 'alice@', 'supersecret', 'Confidential', 'private-token', '/secret/path', 'never-output-me', 'error_message', 'recipient', 'payload'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $body);
        }
    }

    public function test_wrong_connected_database_reports_only_identity_stage_with_original_404(): void
    {
        // CI uses the disposable dynamic_test schema; simulate staging without
        // ever connecting to or selecting a QAS/Production database.
        app()->detectEnvironment(fn () => 'staging');

        try {
            app(\App\Services\System\SafeRuntimeEvidenceService::class)->snapshot();
            self::fail('A mismatched connected database must fail closed.');
        } catch (\App\Services\System\SafeRuntimeEvidenceFailure $failure) {
            self::assertSame('IDENTITY_GATE', $failure->stage);
            self::assertSame(404, $failure->getStatusCode());
            self::assertSame('Not Found', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    public function test_missing_required_schema_reports_opaque_schema_stage_and_stays_blocked(): void
    {
        // Only the synthetic test connection is used. There are no DDL writes.
        \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')
            ->once()
            ->with('website_settings')
            ->andReturn(false);

        try {
            app(\App\Services\System\SafeRuntimeEvidenceService::class)->snapshot();
            self::fail('Missing schema must remain blocked.');
        } catch (\App\Services\System\SafeRuntimeEvidenceFailure $failure) {
            self::assertSame('REQUIRED_SCHEMA', $failure->stage);
            self::assertSame(503, $failure->getStatusCode());
            self::assertSame('Safe diagnostic snapshot unavailable.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    public function test_aggregate_query_failure_reports_only_stage_never_sensitive_exception(): void
    {
        // Trigger an isolated synthetic exception during the read-only count,
        // without changing database rows or touching outbound services.
        DB::listen(static function ($query): void {
            if (str_contains(strtolower($query->sql), 'from `notification_automation_rules`')) {
                throw new \RuntimeException('private@example.invalid token=never-publish');
            }
        });

        try {
            app(\App\Services\System\SafeRuntimeEvidenceService::class)->snapshot();
            self::fail('A failed aggregate must never return EVIDENCE_ONLY.');
        } catch (\App\Services\System\SafeRuntimeEvidenceFailure $failure) {
            self::assertSame('AUTOMATION_COUNT', $failure->stage);
            self::assertSame(503, $failure->getStatusCode());
            self::assertSame('Safe diagnostic snapshot unavailable.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString('private@example.invalid', $failure->getMessage());
            self::assertStringNotContainsString('never-publish', $failure->getMessage());
        }
    }

    public function test_production_environment_is_unavailable_before_any_database_read(): void
    {
        $admin = $this->createSuperAdmin();
        app()->detectEnvironment(fn () => 'production');

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($admin)
            ->getJson(route('admin.settings.notifications.safe-evidence'))
            ->assertNotFound();

        // Auth and permission middleware may SELECT users/roles before
        // the staging/test-only service rejects Production. Assert that no
        // diagnostic business-table access or database-identity probe ran.
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression(
                '/notification_dispatch_logs|notification_automation_rules|SELECT DATABASE\\(\\)/i',
                $sql
            );
        }
    }
}
