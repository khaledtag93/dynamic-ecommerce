<?php

namespace Tests\Feature;

use Tests\TestCase;

class SharedShellTranslationIntegrityTest extends TestCase
{
    public function test_shared_shell_translation_keys_exist_in_english_and_arabic(): void
    {
        $paths = [
            resource_path('views/layouts/inc/admin/navbar.blade.php'),
            resource_path('views/layouts/inc/admin/sidebar.blade.php'),
            resource_path('views/layouts/inc/language-switcher.blade.php'),
            resource_path('views/components/admin/page-help.blade.php'),
            resource_path('views/components/admin/section-tabs.blade.php'),
            resource_path('views/admin/dashboard.blade.php'),
            resource_path('views/admin/orders/index.blade.php'),
            resource_path('views/admin/orders/_results.blade.php'),
            resource_path('views/admin/orders/show.blade.php'),
            resource_path('views/admin/customers/index.blade.php'),
            resource_path('views/admin/customers/_results.blade.php'),
            resource_path('views/admin/customers/show.blade.php'),
            resource_path('views/admin/permissions/index.blade.php'),
            resource_path('views/admin/workforce/attendance/index.blade.php'),
            resource_path('views/admin/workforce/attendance/_results.blade.php'),
            resource_path('views/admin/workforce/corrections/index.blade.php'),
            resource_path('views/admin/workforce/corrections/_results.blade.php'),
            resource_path('views/admin/workforce/corrections/create.blade.php'),
            resource_path('views/admin/workforce/employees/index.blade.php'),
            resource_path('views/admin/workforce/employees/_results.blade.php'),
            resource_path('views/admin/workforce/employees/_form.blade.php'),
            resource_path('views/admin/workforce/leave/index.blade.php'),
            resource_path('views/admin/workforce/leave/_results.blade.php'),
            resource_path('views/admin/workforce/leave/my-leave.blade.php'),
            resource_path('views/admin/workforce/my-schedule.blade.php'),
            resource_path('views/admin/workforce/time-clock.blade.php'),
            resource_path('views/admin/settings/branding.blade.php'),
            resource_path('views/admin/settings/content.blade.php'),
            resource_path('views/admin/settings/payments.blade.php'),
            resource_path('views/admin/settings/whatsapp.blade.php'),
            resource_path('views/admin/settings/shipping/_nav.blade.php'),
            resource_path('views/admin/settings/shipping/methods.blade.php'),
            resource_path('views/admin/settings/shipping/rates.blade.php'),
            resource_path('views/admin/settings/shipping/zones.blade.php'),
            resource_path('views/admin/settings/notification-center/layout.blade.php'),
            resource_path('views/admin/settings/notification-center/overview.blade.php'),
            resource_path('views/frontend/account/addresses/form.blade.php'),
            resource_path('views/frontend/account/addresses/index.blade.php'),
            resource_path('views/frontend/account/index.blade.php'),
            resource_path('views/frontend/account/partials/navigation.blade.php'),
            resource_path('views/frontend/cart/index.blade.php'),
            resource_path('views/frontend/checkout/index.blade.php'),
            resource_path('views/frontend/orders/_results.blade.php'),
            resource_path('views/frontend/orders/index.blade.php'),
            resource_path('views/frontend/orders/show.blade.php'),
            resource_path('views/frontend/orders/success.blade.php'),
            resource_path('views/admin/pos/index.blade.php'),
            resource_path('views/admin/pos/sale.blade.php'),
            resource_path('views/admin/pos/receipt.blade.php'),
            resource_path('views/admin/pos/shifts/index.blade.php'),
            resource_path('views/admin/pos/shifts/_results.blade.php'),
            resource_path('views/admin/category/_form.blade.php'),
            resource_path('views/admin/category/_results.blade.php'),
            resource_path('views/admin/category/index.blade.php'),
            resource_path('views/admin/inventory/index.blade.php'),
            resource_path('views/admin/inventory/_results.blade.php'),
            resource_path('views/admin/inventory/adjust.blade.php'),
            resource_path('views/admin/inventory/labels.blade.php'),
            resource_path('views/admin/inventory/scan.blade.php'),
            resource_path('views/admin/deliveries/index.blade.php'),
            resource_path('views/admin/deliveries/_results.blade.php'),
            resource_path('views/admin/payments/index.blade.php'),
            resource_path('views/admin/payments/_results.blade.php'),
            resource_path('views/admin/payments/show.blade.php'),
            resource_path('views/admin/returns/index.blade.php'),
            resource_path('views/admin/returns/_results.blade.php'),
            resource_path('views/admin/returns/show.blade.php'),
            resource_path('views/admin/reviews/index.blade.php'),
            resource_path('views/admin/reviews/_results.blade.php'),
            resource_path('views/admin/purchases/index.blade.php'),
            resource_path('views/admin/purchases/_results.blade.php'),
            resource_path('views/admin/purchases/create.blade.php'),
            resource_path('views/admin/purchases/show.blade.php'),
            resource_path('views/admin/purchases/receiving.blade.php'),
            resource_path('views/admin/suppliers/index.blade.php'),
            resource_path('views/admin/suppliers/_results.blade.php'),
            resource_path('views/admin/suppliers/_form.blade.php'),
            resource_path('views/admin/coupons/index.blade.php'),
            resource_path('views/admin/coupons/_results.blade.php'),
            resource_path('views/admin/coupons/form.blade.php'),
            resource_path('views/admin/promotions/index.blade.php'),
            resource_path('views/admin/promotions/_results.blade.php'),
            resource_path('views/admin/promotions/create.blade.php'),
        ];

        $keys = [];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            preg_match_all('/__\\(\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $source, $matches);

            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }

        $english = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (array_keys($keys) as $key) {
            $this->assertArrayHasKey($key, $english, "Missing English shared-shell translation: {$key}");
            $this->assertArrayHasKey($key, $arabic, "Missing Arabic shared-shell translation: {$key}");
            $this->assertNotSame('', trim((string) $english[$key]), "Empty English shared-shell translation: {$key}");
            $this->assertNotSame('', trim((string) $arabic[$key]), "Empty Arabic shared-shell translation: {$key}");
        }
    }
}
