<?php

namespace Tests\Feature;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Models\NotificationAutomationRule;
use App\Models\NotificationDispatchLog;
use App\Models\Order;
use App\Models\WhatsAppLog;
use App\Services\Commerce\NotificationAutomationService;
use App\Services\Commerce\NotificationTemplateService;
use App\Services\Commerce\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationWorkspaceClosureTest extends TestCase
{
    use RefreshDatabase;
    public function test_notification_dispatch_search_is_bounded_and_escaped(): void
    {
        $model = file_get_contents(app_path('Models/NotificationDispatchLog.php'));

        $this->assertStringContainsString(
            'mb_substr(trim((string) $search), 0, 100)',
            $model
        );
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $model);
        $this->assertStringNotContainsString('"%{$search}%"', $model);
    }

    public function test_missing_notification_recipients_are_skipped_instead_of_marked_sent(): void
    {
        $order = Order::query()->create([
            'order_number' => 'NOTIFY-NO-RECIPIENT',
            'customer_name' => 'No Recipient',
            'customer_email' => '',
            'customer_phone' => '',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
        ]);

        $service = app(OrderNotificationService::class);

        foreach (['database', 'email', 'whatsapp'] as $channel) {
            $service->dispatch(OrderNotificationService::EVENT_STATUS_UPDATED, $order, [
                'channels' => [$channel],
                'title' => 'Status update',
                'message' => 'Order status changed.',
            ]);
        }

        $logs = NotificationDispatchLog::query()
            ->where('order_id', $order->id)
            ->whereIn('channel', ['database', 'email', 'whatsapp'])
            ->get()
            ->keyBy('channel');

        $this->assertCount(3, $logs);

        foreach (['database', 'email', 'whatsapp'] as $channel) {
            $this->assertSame(NotificationDispatchLog::STATUS_SKIPPED, $logs[$channel]->status);
            $this->assertNull($logs[$channel]->sent_at);
            $this->assertSame(
                __('Recipient is missing or the channel is reserved for later.'),
                $logs[$channel]->error_message
            );
        }
    }

    public function test_failed_whatsapp_test_send_is_not_reported_as_success(): void
    {
        $order = Order::query()->create([
            'order_number' => 'WA-TEST-FAIL',
            'customer_name' => 'WhatsApp Test',
            'customer_email' => 'wa-test@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
        ]);

        $failedLog = new WhatsAppLog([
            'status' => WhatsAppLog::STATUS_FAILED,
            'error_message' => 'Provider rejected the test request.',
        ]);

        $whatsApp = \Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldReceive('sendOrderStatusUpdate')->once()->with(\Mockery::on(fn ($value) => $value instanceof Order && $value->is($order)))->andReturn($failedLog);
        $this->app->instance(WhatsAppServiceInterface::class, $whatsApp);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider rejected the test request.');

        app(NotificationTemplateService::class)->sendTest(
            $order,
            OrderNotificationService::EVENT_STATUS_UPDATED,
            'whatsapp',
            'en'
        );
    }

    public function test_whatsapp_automation_failure_is_not_recorded_as_sent(): void
    {
        $order = Order::query()->create([
            'order_number' => 'WA-AUTOMATION-FAIL',
            'customer_name' => 'WhatsApp Automation',
            'customer_email' => 'wa-automation@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => '1 Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
        ]);

        $rule = NotificationAutomationRule::query()->create([
            'name' => 'WhatsApp fallback test',
            'trigger_status' => NotificationAutomationRule::TRIGGER_FAILED,
            'action_type' => NotificationAutomationRule::ACTION_FALLBACK_CHANNEL,
            'target_channel' => 'whatsapp',
        ]);

        $failedWhatsAppLog = new WhatsAppLog([
            'status' => WhatsAppLog::STATUS_FAILED,
            'error_message' => 'Provider rejected automation delivery.',
        ]);

        $whatsApp = \Mockery::mock(WhatsAppServiceInterface::class);
        $whatsApp->shouldReceive('sendOrderStatusUpdate')->once()->andReturn($failedWhatsAppLog);
        $this->app->instance(WhatsAppServiceInterface::class, $whatsApp);

        $service = app(NotificationAutomationService::class);
        $method = new \ReflectionMethod($service, 'sendUsingChannel');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            $order,
            OrderNotificationService::EVENT_STATUS_UPDATED,
            'whatsapp',
            'Automation message',
            'Automation title',
            $rule,
            true
        );

        $dispatchLog = NotificationDispatchLog::query()
            ->where('order_id', $order->id)
            ->where('channel', 'whatsapp')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringStartsWith('failed:', $result);
        $this->assertSame(NotificationDispatchLog::STATUS_FAILED, $dispatchLog->status);
        $this->assertNull($dispatchLog->sent_at);
        $this->assertSame('Provider rejected automation delivery.', $dispatchLog->error_message);
    }


    public function test_notification_error_summaries_are_localized_and_never_expose_exception_details(): void
    {
        $privateError = 'htmlspecialchars(): Illuminate\\Mail\\Message given (View: /var/www/private/client-account/.env?token=secret)';
        $originalLocale = app()->getLocale();

        try {
            foreach (['en', 'ar'] as $locale) {
                app()->setLocale($locale);
                $summary = \App\Support\NotificationFailureSummary::forOperator($privateError, 'failed');
                $this->assertSame(__('Notification content could not be rendered. Check the template before retrying.'), $summary);
                $this->assertStringNotContainsString('/var/www', $summary);
                $this->assertStringNotContainsString('secret', $summary);
                $this->assertStringNotContainsString('Illuminate', $summary);
            }
            $this->assertSame(
                __('Delivery failed. Review protected system logs before retrying.'),
                \App\Support\NotificationFailureSummary::forOperator('Unexpected /home/user/private/path', 'failed')
            );
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_admin_notification_logs_hide_internal_exception_paths_but_keep_recovery_actions(): void
    {
        $admin = $this->createSuperAdmin();
        $order = Order::query()->create([
            'order_number' => 'NOTIFY-REDACT-001',
            'customer_name' => 'Synthetic Customer',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '01000000000',
            'shipping_address_line_1' => 'Test street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
        ]);
        NotificationDispatchLog::query()->create([
            'order_id' => $order->id,
            'event' => OrderNotificationService::EVENT_CANCELLED,
            'channel' => 'email',
            'status' => NotificationDispatchLog::STATUS_FAILED,
            'recipient' => 'customer@example.test',
            'error_message' => 'htmlspecialchars(): /var/www/private/client-account/.env?token=secret Illuminate\\Mail\\Message given',
        ]);

        $result = $this->actingAs($admin)->get(route('admin.settings.notifications.logs'));
        $result->assertOk()
            ->assertSee('NOTIFY-REDACT-001')
            ->assertSee(__('Notification content could not be rendered. Check the template before retrying.'))
            ->assertSee(route('admin.settings.notifications.retry-log', NotificationDispatchLog::query()->firstOrFail()))
            ->assertDontSee('/var/www/private', false)
            ->assertDontSee('token=secret', false);
    }

    public function test_notification_overview_exposes_labeled_matrix_with_full_width_channel_grid(): void
    {
        $admin = $this->createSuperAdmin();
        $result = $this->actingAs($admin)->get(route('admin.settings.notifications'));

        $result->assertOk()
            ->assertSee('notification-settings-layout', false)
            ->assertSee('notification-channel-grid', false)
            ->assertSee('notification-matrix-table', false)
            ->assertSee('scope="row"', false)
            ->assertSee('scope="col"', false)
            ->assertSee('aria-describedby="notification-matrix-help"', false)
            ->assertSee('aria-label=', false)
            ->assertSee('notification_event_order_cancelled_email', false);
    }


    public function test_notification_templates_and_automation_fields_have_associated_labels(): void
    {
        $this->actingAs($this->createSuperAdmin());

        foreach (['admin.settings.notifications.templates', 'admin.settings.notifications.automation'] as $route) {
            $response = $this->get(route($route));
            $response->assertOk();

            $dom = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $dom->loadHTML($response->getContent());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            $xpath = new \DOMXPath($dom);
            $forms = $xpath->query('//form[@method="POST" or @method="GET"]');
            $checked = 0;
            foreach ($forms as $form) {
                if (! str_contains($form->getAttribute('action'), '/admin/settings/notifications')) {
                    continue;
                }
                foreach ($xpath->query('.//input[not(@type="hidden") and not(@type="submit")] | .//select | .//textarea', $form) as $control) {
                    $name = $control->getAttribute('name');
                    if ($name === '' || $control->getAttribute('type') === 'checkbox') {
                        continue; // Checked switches already use dedicated for/id labels.
                    }
                    $id = $control->getAttribute('id');
                    $this->assertNotSame('', $id, "{$route}: {$name} is missing id");
                    $this->assertSame(1, $xpath->query('.//label[@for="'. $id .'"]', $form)->length, "{$route}: {$name} has no matching label");
                    $checked++;
                }
            }
            $this->assertGreaterThan(10, $checked, 'Expected actual template and rule builder fields to be inspected');
        }
    }

    public function test_notification_diagnostics_redacts_failed_job_and_incident_exception_paths(): void
    {
        $admin = $this->createSuperAdmin();
        $leak = 'htmlspecialchars(): secret=NOTIFICATION_SECRET_TRACE (View: /var/www/private/.env)';
        $order = Order::query()->create([
            'order_number' => 'NOTIFY-DIAG-001',
            'customer_name' => 'Test Customer',
            'customer_email' => 'diag@example.test',
            'shipping_address_line_1' => 'Test Street',
            'shipping_city' => 'Cairo',
            'shipping_country' => 'Egypt',
        ]);
        NotificationDispatchLog::query()->create([
            'order_id' => $order->id,
            'channel' => 'email',
            'event' => OrderNotificationService::EVENT_STATUS_UPDATED,
            'status' => NotificationDispatchLog::STATUS_FAILED,
            'error_message' => $leak,
        ]);
        \Illuminate\Support\Facades\DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SendOrderNotification'], JSON_THROW_ON_ERROR),
            'exception' => $leak,
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.notifications.diagnostics'));
        $response->assertOk()
            ->assertSee(__('Notification content could not be rendered. Check the template before retrying.'))
            ->assertSee(__('Channel health table'))
            ->assertSee('notification-diagnostics-scroll', false)
            ->assertSee('scope="row"', false)
            ->assertDontSee('NOTIFICATION_SECRET_TRACE', false)
            ->assertDontSee('/var/www/private', false)
            ->assertDontSee('.env', false);
    }

    public function test_notification_center_navigation_exposes_current_page_semantics(): void
    {
        $layout = file_get_contents(resource_path('views/admin/settings/notification-center/layout.blade.php'));

        $this->assertStringContainsString('aria-current="page"', $layout);
        $this->assertStringContainsString('($currentSection ?? \'overview\') === $key', $layout);
    }
}
