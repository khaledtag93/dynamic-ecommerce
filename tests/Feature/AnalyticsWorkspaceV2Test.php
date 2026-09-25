<?php

namespace Tests\Feature;

use Tests\TestCase;

class AnalyticsWorkspaceV2Test extends TestCase
{
    public function test_main_analytics_uses_focused_section_tabs_instead_of_anchor_navigation(): void
    {
        $source = file_get_contents(resource_path('views/admin/analytics/index.blade.php'));

        $this->assertStringContainsString('data-admin-section-tabs="analytics"', $source);
        $this->assertStringContainsString('<x-admin.section-tabs id="analytics"', $source);
        $this->assertStringContainsString('data-admin-section-panel="performance"', $source);
        $this->assertStringContainsString('data-admin-section-panel="decision"', $source);
        $this->assertStringContainsString('data-admin-section-panel="trends"', $source);
        $this->assertStringContainsString('data-admin-section-panel="funnel"', $source);
        $this->assertStringContainsString('data-admin-section-panel="drilldowns"', $source);
        $this->assertStringNotContainsString('<nav class="analytics-section-nav"', $source);
        $this->assertStringNotContainsString('admin-card admin-stat-card analytics-kpi-card', $source);
    }

    public function test_offers_analytics_uses_section_tabs_and_shared_stat_cards(): void
    {
        $source = file_get_contents(resource_path('views/admin/analytics/offers.blade.php'));

        $this->assertStringContainsString('data-admin-section-tabs="offers-analytics"', $source);
        $this->assertStringContainsString('<x-admin.section-tabs id="offers-analytics"', $source);
        $this->assertStringContainsString('data-admin-section-panel="summary"', $source);
        $this->assertStringContainsString('data-admin-section-panel="charts"', $source);
        $this->assertStringContainsString('data-admin-section-panel="management"', $source);
        $this->assertStringContainsString('data-admin-section-panel="details"', $source);
        $this->assertStringContainsString('<x-admin.stat-card', $source);
        $this->assertStringNotContainsString('<div class="offers-anchor-nav">', $source);
        $this->assertStringNotContainsString('admin-card admin-stat-card offers-trend-card', $source);
    }

    public function test_growth_analytics_uses_focused_section_tabs(): void
    {
        $source = file_get_contents(resource_path('views/admin/analytics/growth.blade.php'));

        $this->assertStringContainsString('data-admin-section-tabs="growth-analytics"', $source);
        $this->assertStringContainsString('<x-admin.section-tabs id="growth-analytics"', $source);
        $this->assertStringContainsString('data-admin-section-panel="overview"', $source);
        $this->assertStringContainsString('data-admin-section-panel="campaigns"', $source);
        $this->assertStringContainsString('data-admin-section-panel="products"', $source);
        $this->assertStringContainsString('data-admin-section-panel="offers"', $source);
    }

    public function test_analytics_translation_catalogs_cover_all_workspace_literal_keys(): void
    {
        $sources = [
            resource_path('views/admin/analytics/index.blade.php'),
            resource_path('views/admin/analytics/growth.blade.php'),
            resource_path('views/admin/analytics/offers.blade.php'),
            resource_path('views/admin/analytics/product.blade.php'),
        ];

        $keys = [];

        foreach ($sources as $sourcePath) {
            $source = file_get_contents($sourcePath);
            preg_match_all("/__\\('([^']+)'/", $source, $matches);
            $keys = array_merge($keys, $matches[1] ?? []);
        }

        $keys = array_values(array_unique($keys));
        $english = json_decode(file_get_contents(base_path('lang/en.json')), true, 512, JSON_THROW_ON_ERROR);
        $arabic = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $english, "Missing English analytics translation key: {$key}");
            $this->assertArrayHasKey($key, $arabic, "Missing Arabic analytics translation key: {$key}");
        }
    }

    public function test_analytics_workspaces_keep_mobile_filter_and_theme_token_contracts(): void
    {
        $overview = file_get_contents(resource_path('views/admin/analytics/index.blade.php'));
        $growth = file_get_contents(resource_path('views/admin/analytics/growth.blade.php'));
        $offers = file_get_contents(resource_path('views/admin/analytics/offers.blade.php'));

        $this->assertStringContainsString('.analytics-pills{flex-wrap:nowrap;overflow-x:auto', $overview);
        $this->assertStringContainsString('.analytics-form{display:grid;grid-template-columns:1fr 1fr', $overview);
        $this->assertStringContainsString('@media(max-width:480px){.analytics-form{grid-template-columns:1fr}', $overview);

        $this->assertStringContainsString('.growth-filter{display:grid;grid-template-columns:minmax(0,1fr) auto', $growth);
        $this->assertStringContainsString('@media (max-width: 480px){.growth-filter{grid-template-columns:1fr}', $growth);

        $this->assertStringContainsString('.offers-lane-card{padding:20px;border-radius:22px;border:1px solid var(--admin-border);background:var(--admin-surface)', $offers);
        $this->assertStringNotContainsString('border:1px solid rgba(249,115,22,.12)', $offers);
    }

    public function test_analytics_workspaces_preserve_active_section_in_the_url(): void
    {
        foreach ([
            'resources/views/admin/analytics/index.blade.php',
            'resources/views/admin/analytics/growth.blade.php',
            'resources/views/admin/analytics/offers.blade.php',
            'resources/views/admin/analytics/product.blade.php',
        ] as $view) {
            $source = file_get_contents(base_path($view));
            $this->assertStringContainsString('data-admin-section-history="true"', $source);
        }

        $tabsScript = file_get_contents(public_path('admin/js/admin-section-tabs.js'));
        $this->assertStringContainsString("url.searchParams.set('section', key)", $tabsScript);
        $this->assertStringContainsString("new URLSearchParams(window.location.search).get('section')", $tabsScript);
    }

    public function test_shared_analytics_shell_is_mobile_safe_and_theme_aware(): void
    {
        $nav = file_get_contents(resource_path('views/admin/analytics/_nav.blade.php'));
        $toolbar = file_get_contents(resource_path('views/admin/analytics/_report_toolbar.blade.php'));
        $trust = file_get_contents(resource_path('views/admin/analytics/_trust_panel.blade.php'));

        $this->assertStringContainsString('.analytics-nav{flex-wrap:nowrap;overflow-x:auto', $nav);
        $this->assertStringContainsString('scroll-snap-type:x proximity', $nav);
        $this->assertStringContainsString('.analytics-toolbar-actions{display:grid;grid-template-columns:1fr;width:100%}', $toolbar);
        $this->assertStringContainsString('role="status" aria-live="polite"', $toolbar);
        $this->assertStringContainsString("__('Copy failed. Copy the current browser address manually.')", $toolbar);
        $this->assertStringContainsString('var(--admin-success-soft)', $trust);
        $this->assertStringContainsString('var(--admin-warning-soft)', $trust);
        $this->assertStringContainsString('var(--admin-danger-soft)', $trust);
        $this->assertStringNotContainsString('#f8fff9', $trust);
    }

    public function test_product_drilldown_uses_shared_theme_tokens_and_mobile_navigation(): void
    {
        $source = file_get_contents(resource_path('views/admin/analytics/product.blade.php'));

        $this->assertStringContainsString('background:var(--admin-surface);border:1px solid var(--admin-border)', $source);
        $this->assertStringContainsString('.analytics-anchor-nav{flex-wrap:nowrap;overflow-x:auto', $source);
        $this->assertStringContainsString('.analytics-bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--admin-primary),var(--admin-primary-dark))', $source);
        $this->assertStringNotContainsString('background:#fff;border:1px solid rgba(15,23,42,.06)', $source);
        $this->assertStringContainsString('data-admin-section-tabs="product-analytics" data-admin-section-history="true"', $source);
        $this->assertStringContainsString('data-admin-section-panel="summary"', $source);
        $this->assertStringContainsString('data-admin-section-panel="performance"', $source);
        $this->assertStringContainsString('data-admin-section-panel="trends"', $source);
        $this->assertStringContainsString('data-admin-section-panel="variants"', $source);
    }

    public function test_arabic_analytics_workspace_labels_are_available(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('الأداء', $translations['Performance'] ?? null);
        $this->assertSame('قراءة القرار', $translations['Decision read'] ?? null);
        $this->assertSame('الاتجاهات', $translations['Trends'] ?? null);
        $this->assertSame('مسار التحويل والمقارنة', $translations['Funnel & comparison'] ?? null);
        $this->assertSame('التفاصيل التحليلية', $translations['Drilldowns'] ?? null);
        $this->assertSame('مخططات الكوبونات', $translations['Coupon charts'] ?? null);
        $this->assertSame('عرض الإدارة', $translations['Management view'] ?? null);
        $this->assertSame('الجدول التفصيلي', $translations['Detailed table'] ?? null);
        $this->assertSame('نظرة عامة على النمو', $translations['Growth overview'] ?? null);
        $this->assertSame('الحملات والأتمتة', $translations['Campaigns & automation'] ?? null);
        $this->assertSame('إشارات المنتجات', $translations['Product signals'] ?? null);
        $this->assertSame('العروض والكوبونات', $translations['Offers & coupons'] ?? null);
    }
}
