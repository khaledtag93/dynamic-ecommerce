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
        $this->assertSame(4, substr_count($overview, 'class="gm-setting-row"'));
        $this->assertSame(4, substr_count($overview, '<input type="hidden" name="growth_'));
        $this->assertStringContainsString('gm-setting-switch', $overview);
        $this->assertStringNotContainsString('gm-toggle-row', $overview);
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

    public function test_growth_rules_use_an_explicit_campaign_link_instead_of_a_free_text_runtime_key(): void
    {
        $source = file_get_contents(resource_path('views/admin/growth/rule-form.blade.php'));

        $this->assertStringContainsString("__('Linked campaign')", $source);
        $this->assertStringContainsString('<select name="rule_key"', $source);
        $this->assertStringContainsString('$campaign->campaign_key', $source);
        $this->assertStringNotContainsString('placeholder="returning_customer_followup"', $source);
    }

    public function test_growth_safe_mutations_use_progressive_no_reload_enhancement(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/GrowthController.php'));
        $layout = file_get_contents(resource_path('views/admin/growth/layout.blade.php'));
        $overview = file_get_contents(resource_path('views/admin/growth/index.blade.php'));
        $content = file_get_contents(resource_path('views/admin/growth/content.blade.php'));
        $operations = file_get_contents(resource_path('views/admin/growth/operations.blade.php'));

        $this->assertStringContainsString('RedirectResponse|JsonResponse', $controller);
        $this->assertStringContainsString('mutationResponse(', $controller);
        $this->assertStringContainsString('data-growth-async', $overview);
        $this->assertSame(3, substr_count($content, 'data-growth-async'));
        $this->assertSame(1, substr_count($operations, 'data-growth-retry'));
        $this->assertStringContainsString("fetch(form.action", $layout);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $layout);
        $this->assertStringContainsString('data-growth-status=', $layout);
        $this->assertStringContainsString('data-growth-feedback', $layout);
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
        $this->assertSame('الحملة المرتبطة', $translations['Linked campaign'] ?? null);
        $this->assertSame('حملات تحتاج إلى قاعدة', $translations['Campaigns needing a rule'] ?? null);
        $this->assertSame('تعذر إكمال التغيير. حدّث الصفحة وحاول مرة أخرى.', $translations['The change could not be completed. Please refresh and try again.'] ?? null);
        $this->assertSame('سجلات التشغيل', $translations['Trigger records'] ?? null);
        $this->assertSame('سجلات تشغيل الأتمتة المتاحة للمراجعة.', $translations['Tracked automation trigger records available for review.'] ?? null);
    }

    public function test_growth_large_workspaces_use_real_pagination_and_bounded_overview_reads(): void
    {
        $service = file_get_contents(app_path('Services/Growth/GrowthCampaignService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/GrowthController.php'));
        $overview = file_get_contents(resource_path('views/admin/growth/index.blade.php'));
        $content = file_get_contents(resource_path('views/admin/growth/content.blade.php'));
        $operations = file_get_contents(resource_path('views/admin/growth/operations.blade.php'));
        $insights = file_get_contents(resource_path('views/admin/growth/insights.blade.php'));

        $this->assertStringContainsString("paginate(12, ['*'], 'campaign_page')", $service);
        $this->assertStringContainsString("paginate(12, ['*'], 'rule_page')", $service);
        $this->assertStringContainsString("paginate(12, ['*'], 'template_page')", $service);
        $this->assertStringContainsString("paginate(12, ['*'], 'segment_page')", $service);
        $this->assertStringContainsString("paginate(12, ['*'], 'experiment_page')", $service);
        $this->assertStringContainsString("paginate(15, ['*'], 'delivery_page')", $service);
        $this->assertStringContainsString("paginate(12, ['*'], 'trigger_page')", $service);
        $this->assertStringContainsString("paginate(15, ['*'], 'message_page')", $service);
        $this->assertStringContainsString("paginate(8, ['*'], 'insight_experiment_page')", $service);
        $this->assertStringContainsString("'overview_health' => $overviewHealth", $service);
        $this->assertStringContainsString("'operations_summary' => $operationsSummary", $service);
        $this->assertStringNotContainsString("'campaigns' => collect($snapshot", $controller);
        $this->assertStringContainsString('$overviewHealth[', $overview);
        $this->assertStringNotContainsString('$campaigns->take(12)', $content);
        $this->assertStringNotContainsString('$rules->take(12)', $content);
        $this->assertStringContainsString("fragment('growth-campaigns')->links()", $content);
        $this->assertStringContainsString("fragment('growth-deliveries')->links()", $operations);
        $this->assertStringContainsString("fragment('growth-experiment-performance')->links()", $insights);
    }

}
