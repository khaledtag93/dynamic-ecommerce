<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminFormWorkspaceV2Test extends TestCase
{
    public function test_coupon_editor_uses_shared_header_and_focused_sections(): void
    {
        $source = file_get_contents(resource_path('views/admin/coupons/form.blade.php'));

        $this->assertStringContainsString('<x-admin.page-header', $source);
        $this->assertStringContainsString('data-admin-section-tabs="coupon-editor"', $source);
        $this->assertStringContainsString('<x-admin.section-tabs id="coupon-editor"', $source);
        $this->assertStringContainsString('data-admin-section-panel="offer"', $source);
        $this->assertStringContainsString('data-admin-section-panel="limits"', $source);
        $this->assertStringContainsString('data-admin-section-panel="schedule"', $source);
        $this->assertStringContainsString('id="couponLiveValue"', $source);
    }

    public function test_promotion_editor_uses_shared_header_and_focused_sections(): void
    {
        $source = file_get_contents(resource_path('views/admin/promotions/create.blade.php'));

        $this->assertStringContainsString('<x-admin.page-header', $source);
        $this->assertStringContainsString('data-admin-section-tabs="promotion-editor"', $source);
        $this->assertStringContainsString('<x-admin.section-tabs id="promotion-editor"', $source);
        $this->assertStringContainsString('data-admin-section-panel="rule"', $source);
        $this->assertStringContainsString('data-admin-section-panel="eligibility"', $source);
        $this->assertStringContainsString('data-admin-section-panel="schedule"', $source);
        $this->assertStringContainsString("type?.value === 'buy_x_get_y'", $source);
    }

    public function test_supplier_form_uses_global_switch_contract_without_local_input_override(): void
    {
        $source = file_get_contents(resource_path('views/admin/suppliers/_form.blade.php'));

        $this->assertStringContainsString('form-check form-switch admin-switch-wrap', $source);
        $this->assertStringNotContainsString('.admin-switch-wrap .form-check-input', $source);
    }

    public function test_arabic_editor_section_labels_are_available(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('العرض', $translations['Offer'] ?? null);
        $this->assertSame('الحدود وشروط الاستحقاق', $translations['Limits & eligibility'] ?? null);
        $this->assertSame('الجدولة والملاحظات', $translations['Schedule & notes'] ?? null);
        $this->assertSame('القاعدة', $translations['Rule'] ?? null);
        $this->assertSame('شروط الاستحقاق', $translations['Eligibility'] ?? null);
        $this->assertSame('الجدولة والحالة', $translations['Schedule & status'] ?? null);
    }
}
