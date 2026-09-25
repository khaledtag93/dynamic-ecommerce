<?php

namespace Tests\Feature;

use Tests\TestCase;

class CommerceSettingsWorkspaceV2Test extends TestCase
{
    public function test_payment_settings_are_split_into_focused_tabs(): void
    {
        $source = file_get_contents(resource_path('views/admin/settings/payments.blade.php'));

        $this->assertStringContainsString('data-admin-section-tabs="payment-settings"', $source);
        $this->assertStringContainsString('<x-admin.section-tabs id="payment-settings"', $source);
        $this->assertStringContainsString('data-admin-section-panel="methods"', $source);
        $this->assertStringContainsString('data-admin-section-panel="gateway"', $source);
        $this->assertStringContainsString('data-admin-section-panel="paymob"', $source);
        $this->assertStringContainsString('data-admin-section-panel="bank"', $source);
        $this->assertStringContainsString("route('payments.paymob.callback')", $source);
    }

    public function test_shipping_pages_share_one_workspace_navigation_partial(): void
    {
        foreach ([
            resource_path('views/admin/settings/shipping/methods.blade.php'),
            resource_path('views/admin/settings/shipping/zones.blade.php'),
            resource_path('views/admin/settings/shipping/rates.blade.php'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString("@include('admin.settings.shipping._nav'", $source);
        }

        $nav = file_get_contents(resource_path('views/admin/settings/shipping/_nav.blade.php'));

        $this->assertStringContainsString("route('admin.settings.shipping.methods')", $nav);
        $this->assertStringContainsString("route('admin.settings.shipping.zones')", $nav);
        $this->assertStringContainsString("route('admin.settings.shipping.rates')", $nav);
    }

    public function test_notification_center_remains_modular_instead_of_being_reflattened(): void
    {
        $overview = file_get_contents(resource_path('views/admin/settings/notification-center/overview.blade.php'));

        $this->assertStringContainsString("route('admin.settings.notifications.logs')", $overview);
        $this->assertStringContainsString("route('admin.settings.notifications.templates')", $overview);
        $this->assertStringContainsString("route('admin.settings.notifications.automation')", $overview);
        $this->assertStringContainsString("route('admin.settings.notifications.diagnostics')", $overview);
    }

    public function test_arabic_payment_and_shipping_workspace_labels_are_available(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('طرق الدفع', $translations['Payment methods'] ?? null);
        $this->assertSame('بوابة الدفع والمخزون', $translations['Gateway & stock'] ?? null);
        $this->assertSame('إعداد Paymob', $translations['Paymob setup'] ?? null);
        $this->assertSame('التحويل البنكي', $translations['Bank transfer'] ?? null);
        $this->assertSame('إعدادات الشحن', $translations['Shipping settings'] ?? null);
    }
}
