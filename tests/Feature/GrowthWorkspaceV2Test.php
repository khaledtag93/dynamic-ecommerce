<?php

namespace Tests\Feature;

use Tests\TestCase;

class GrowthWorkspaceV2Test extends TestCase
{
    public function test_growth_workspace_uses_shared_admin_patterns(): void
    {
        $layout = file_get_contents(resource_path('views/admin/growth/layout.blade.php'));
        $overview = file_get_contents(resource_path('views/admin/growth/index.blade.php'));
        $insights = file_get_contents(resource_path('views/admin/growth/insights.blade.php'));

        $this->assertStringContainsString('<x-admin.page-help', $layout);
        $this->assertStringContainsString('overflow-x:auto', $layout);
        $this->assertSame(4, substr_count($overview, '<x-admin.stat-card'));
        $this->assertSame(4, substr_count($insights, '<x-admin.stat-card'));
        $this->assertStringContainsString('form-check form-switch gm-toggle-row', $overview);
    }

    public function test_growth_content_is_split_into_focused_collapsible_modules(): void
    {
        $source = file_get_contents(resource_path('views/admin/growth/content.blade.php'));

        $this->assertSame(5, substr_count($source, '<details class="gm-panel gm-module"'));
        $this->assertStringContainsString("__('Campaigns')", $source);
        $this->assertStringContainsString("__('Automation rules')", $source);
        $this->assertStringContainsString("__('Templates')", $source);
        $this->assertStringContainsString("__('Audience segments')", $source);
        $this->assertStringContainsString("__('Experiments')", $source);
        $this->assertStringNotContainsString('class="gm-three"', $source);
    }

    public function test_growth_operations_hides_developer_cli_copy_from_the_admin_ui(): void
    {
        $source = file_get_contents(resource_path('views/admin/growth/operations.blade.php'));

        $this->assertStringContainsString("__('Test-data tools')", $source);
        $this->assertStringNotContainsString('CLI alternative:', $source);
        $this->assertStringNotContainsString('php artisan growth:seed-demo', $source);
    }

    public function test_growth_v2_core_arabic_copy_is_available(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('مركز النمو', $translations['Growth center'] ?? null);
        $this->assertSame('مساعدة الصفحة', $translations['Page help'] ?? null);
        $this->assertSame('إعداد الرحلات', $translations['Journey setup'] ?? null);
        $this->assertSame('أدوات بيانات الاختبار', $translations['Test-data tools'] ?? null);
        $this->assertSame('صحة العملاء', $translations['Customer health'] ?? null);
    }
}
