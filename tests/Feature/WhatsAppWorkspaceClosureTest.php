<?php

namespace Tests\Feature;

use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWorkspaceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_log_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/WhatsAppSettingsController.php'));

        $this->assertStringContainsString(
            "mb_substr(trim((string) \\$request->string('search')), 0, 100)",
            $controller
        );
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$term}%"', $controller);
    }

    public function test_whatsapp_rate_limit_settings_are_validated_and_persisted(): void
    {
        $admin = $this->createSuperAdmin();

        $payload = $this->validPayload([
            'whatsapp_queue_backoff_seconds' => 45,
            'whatsapp_rate_limit_window_minutes' => 20,
            'whatsapp_rate_limit_max_attempts' => 7,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.settings.whatsapp.update'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('45', WebsiteSetting::getValue('whatsapp_queue_backoff_seconds'));
        $this->assertSame('20', WebsiteSetting::getValue('whatsapp_rate_limit_window_minutes'));
        $this->assertSame('7', WebsiteSetting::getValue('whatsapp_rate_limit_max_attempts'));

        $this->put(route('admin.settings.whatsapp.update'), $this->validPayload([
            'whatsapp_queue_backoff_seconds' => '60,180,300',
        ]))->assertSessionHasErrors('whatsapp_queue_backoff_seconds');
    }

    public function test_whatsapp_primary_settings_and_log_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/settings/whatsapp.blade.php'));

        foreach ([
            'whatsappDefaultProvider',
            'whatsappFallbackLocale',
            'whatsappQueueConnection',
            'whatsappQueueName',
            'whatsappQueueTries',
            'whatsappQueueBackoff',
            'whatsappQueueTimeout',
            'whatsappDuplicateWindow',
            'whatsappRateLimitWindow',
            'whatsappRateLimitAttempts',
            'whatsappLogSearch',
            'whatsappLogStatus',
            'whatsappLogMessageType',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        $this->assertStringContainsString(
            'id="whatsappQueueBackoff" type="number" min="0" max="3600"',
            $view
        );
        $this->assertStringNotContainsString("'60,180,300'", $view);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'whatsapp_default_provider' => 'meta',
            'whatsapp_fallback_locale' => 'ar',
            'whatsapp_meta_graph_version' => 'v23.0',
            'whatsapp_template_order_confirmation_language_ar' => 'ar',
            'whatsapp_template_order_confirmation_language_en' => 'en_US',
            'whatsapp_template_order_status_update_language_ar' => 'ar',
            'whatsapp_template_order_status_update_language_en' => 'en_US',
            'whatsapp_template_delivery_update_language_ar' => 'ar',
            'whatsapp_template_delivery_update_language_en' => 'en_US',
        ], $overrides);
    }
}
