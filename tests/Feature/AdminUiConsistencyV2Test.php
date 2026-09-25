<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminUiConsistencyV2Test extends TestCase
{
    public function test_shared_stat_card_component_exposes_the_v2_contract(): void
    {
        $html = Blade::render(
            '<x-admin.stat-card label="Orders" value="42" icon="mdi-cart-outline" help="Tracked orders." tone="success"><span>+12%</span></x-admin.stat-card>'
        );

        $this->assertStringContainsString('admin-stat-card--v2', $html);
        $this->assertStringContainsString('admin-stat-card--success', $html);
        $this->assertStringContainsString('admin-stat-icon', $html);
        $this->assertStringContainsString('admin-stat-label', $html);
        $this->assertStringContainsString('admin-stat-value', $html);
        $this->assertStringContainsString('admin-stat-meta', $html);
        $this->assertStringContainsString('admin-stat-help', $html);
        $this->assertStringContainsString('Tracked orders.', $html);
    }

    public function test_core_admin_workspaces_use_the_shared_stat_card_component(): void
    {
        $paths = [
            resource_path('views/admin/analytics/index.blade.php'),
            resource_path('views/admin/permissions/index.blade.php'),
            resource_path('views/admin/orders/index.blade.php'),
            resource_path('views/admin/deliveries/index.blade.php'),
            resource_path('views/admin/customers/index.blade.php'),
            resource_path('views/admin/purchases/index.blade.php'),
            resource_path('views/admin/payments/index.blade.php'),
            resource_path('views/admin/inventory/index.blade.php'),
            resource_path('views/admin/workforce/employees/index.blade.php'),
            resource_path('views/admin/workforce/schedule/index.blade.php'),
            resource_path('views/admin/workforce/corrections/index.blade.php'),
            resource_path('views/admin/workforce/leave/index.blade.php'),
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString(
                '<x-admin.stat-card',
                $source,
                basename($path).' should use the shared stat card component.'
            );
        }
    }

    public function test_admin_layout_contains_logical_rtl_safe_switch_alignment_contract(): void
    {
        $source = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('/* Unified admin switches */', $source);
        $this->assertStringContainsString('.form-check.form-switch', $source);
        $this->assertStringContainsString("body[dir='rtl'] .form-check.form-switch", $source);
        $this->assertStringContainsString('grid-template-columns: 2.5rem minmax(0, 1fr)', $source);
        $this->assertStringContainsString('padding: .55rem .7rem !important', $source);
    }
}
