<?php

namespace Tests\Feature;

use Tests\TestCase;

class FinalAdminV2CleanupTest extends TestCase
{
    public function test_whatsapp_summary_uses_shared_stat_cards_and_global_switch_contract(): void
    {
        $source = file_get_contents(resource_path('views/admin/settings/whatsapp.blade.php'));

        $this->assertStringContainsString('<x-admin.stat-card', $source);
        $this->assertStringNotContainsString('admin-card admin-stat-card wa-metric-card', $source);
        $this->assertStringNotContainsString('.wa-switch-card .form-check-input', $source);
    }

    public function test_admin_topbar_search_css_is_not_redeclared_in_primary_topbar_block(): void
    {
        $source = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $primaryBlock = substr($source, 0, strpos($source, '/* =========================', strpos($source, 'TOPBAR') + 1) ?: 14000);

        $this->assertSame(1, substr_count($primaryBlock, '.admin-topbar-search {'));
        $this->assertSame(1, substr_count($primaryBlock, '.admin-topbar-shell {'));
        $this->assertStringContainsString('max-width: 38rem;', $primaryBlock);
        $this->assertStringContainsString('margin-inline: auto;', $primaryBlock);
    }

    public function test_global_admin_layout_has_no_browser_native_confirm_or_alert_calls(): void
    {
        $source = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertDoesNotMatchRegularExpression('/\bonclick\s*=\s*["\'][^"\']*confirm\s*\(/i', $source);
        $this->assertDoesNotMatchRegularExpression('/\balert\s*\(/', $source);
    }
}
