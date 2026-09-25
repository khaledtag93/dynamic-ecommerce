<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Channels\WhatsApp\Support\WhatsAppConfig;
use App\Services\Commerce\StoreSettingsService;
use App\Services\Payments\PaymobGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSecretHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_store_settings_redact_legacy_provider_secrets(): void
    {
        foreach (StoreSettingsService::sensitiveSettingKeys() as $key) {
            WebsiteSetting::setValue($key, 'legacy-secret-'.$key, 'test');
        }

        $settings = app(StoreSettingsService::class)->all();

        foreach (StoreSettingsService::sensitiveSettingKeys() as $key) {
            $this->assertSame('', $settings[$key] ?? null, $key.' should be redacted.');
        }
    }

    public function test_paymob_runtime_ignores_plaintext_db_secrets_in_favor_of_server_config(): void
    {
        config([
            'services.paymob.api_key' => 'env-api-key',
            'services.paymob.hmac_secret' => 'env-hmac-secret',
            'services.paymob.base_url' => 'https://accept.paymob.com/api',
            'services.paymob.currency' => 'EGP',
        ]);

        WebsiteSetting::setValue('paymob_api_key', 'legacy-db-api', 'payment');
        WebsiteSetting::setValue('paymob_hmac_secret', 'legacy-db-hmac', 'payment');
        WebsiteSetting::setValue('paymob_integration_id', '123456', 'payment');
        WebsiteSetting::setValue('paymob_iframe_id', '654321', 'payment');

        $diagnostics = app(PaymobGatewayService::class)->configurationDiagnostics();

        $this->assertTrue($diagnostics['is_configured']);
        $this->assertSame('config/env', $diagnostics['settings_source']['api_key']);
        $this->assertSame('config/env', $diagnostics['settings_source']['hmac_secret']);
    }

    public function test_whatsapp_runtime_ignores_plaintext_db_secrets_in_favor_of_server_config(): void
    {
        config([
            'whatsapp.meta.access_token' => 'env-access-token',
            'whatsapp.meta.app_secret' => 'env-app-secret',
            'whatsapp.meta.verify_token' => 'env-verify-token',
        ]);

        WebsiteSetting::setValue('whatsapp_meta_access_token', 'legacy-db-token', 'whatsapp');
        WebsiteSetting::setValue('whatsapp_meta_app_secret', 'legacy-db-app-secret', 'whatsapp');
        WebsiteSetting::setValue('whatsapp_meta_verify_token', 'legacy-db-verify-token', 'whatsapp');

        $config = app(WhatsAppConfig::class);

        $this->assertSame('env-access-token', $config->meta('access_token'));
        $this->assertSame('env-app-secret', $config->meta('app_secret'));
        $this->assertSame('env-verify-token', $config->meta('verify_token'));
    }

    public function test_payment_settings_save_cannot_persist_submitted_provider_secrets(): void
    {
        $admin = $this->createSuperAdmin();

        WebsiteSetting::setValue('paymob_api_key', 'legacy-db-api', 'payment');
        WebsiteSetting::setValue('paymob_hmac_secret', 'legacy-db-hmac', 'payment');

        $response = $this
            ->actingAs($admin)
            ->put(route('admin.settings.payments.update'), [
                'payment_gateway_provider' => 'paymob',
                'payment_gateway_mode' => 'sandbox',
                'payment_stock_reservation_minutes' => 30,
                'paymob_api_key' => 'attempted-new-api-secret',
                'paymob_hmac_secret' => 'attempted-new-hmac-secret',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('website_settings', ['key' => 'paymob_api_key']);
        $this->assertDatabaseMissing('website_settings', ['key' => 'paymob_hmac_secret']);
    }

    public function test_payment_settings_page_never_renders_legacy_secret_values(): void
    {
        $admin = $this->createSuperAdmin();

        WebsiteSetting::setValue('paymob_api_key', 'SHOULD-NOT-RENDER-API', 'payment');
        WebsiteSetting::setValue('paymob_hmac_secret', 'SHOULD-NOT-RENDER-HMAC', 'payment');

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.settings.payments'));

        $response->assertOk();
        $response->assertDontSee('SHOULD-NOT-RENDER-API', false);
        $response->assertDontSee('SHOULD-NOT-RENDER-HMAC', false);
    }
}
