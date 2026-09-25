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
