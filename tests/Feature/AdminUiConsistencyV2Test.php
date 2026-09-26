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
        $this->assertStringContainsString("html[dir='rtl'] .form-check.form-switch", $source);
        $this->assertStringContainsString('grid-template-columns: 2.5rem minmax(0, 1fr)', $source);
        $this->assertStringContainsString('padding: .55rem .7rem !important', $source);
    }
    public function test_admin_flash_feedback_is_floating_dismissible_and_accessible(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('class="admin-toast-stack"', $layout);
        $this->assertStringContainsString('data-admin-toast-close', $layout);
        $this->assertStringContainsString("__('Dismiss notification')", $layout);
        $this->assertStringContainsString("stack.querySelectorAll('.admin-flash--success')", $layout);
        $this->assertStringContainsString('window.setTimeout(() => dismissToast(toast), 4200)', $layout);
        $this->assertStringContainsString('.admin-flash.is-leaving', $layout);
        $this->assertSame(1, substr_count($layout, "stack.querySelectorAll('.admin-flash--success')"));
        $this->assertStringNotContainsString("document.querySelectorAll('#adminToastStack .admin-flash')", $layout);
        $this->assertSame(5, substr_count($layout, 'data-admin-toast-close aria-label='));
        $this->assertStringContainsString("querySelectorAll('[data-admin-toast-close]')", $layout);
        $this->assertSame(5, substr_count($layout, 'class="admin-flash-content"'));
        $this->assertSame(2, preg_match_all('/<div class="alert alert-success admin-flash admin-flash--success">/', $layout));
        $this->assertSame(1, preg_match_all('/<div class="alert alert-warning admin-flash admin-flash--warning">/', $layout));
        $this->assertSame(2, preg_match_all('/<div class="alert alert-danger admin-flash admin-flash--danger">/', $layout));
    }

    public function test_admin_topbar_search_has_keyboard_and_clear_controls(): void
    {
        $navbar = file_get_contents(resource_path('views/layouts/inc/admin/navbar.blade.php'));

        $this->assertStringContainsString("aria-label=\"{{ __('Search admin workspace') }}\"", $navbar);
        $this->assertStringContainsString('class="admin-topbar-search-clear"', $navbar);
        $this->assertStringContainsString('class="admin-topbar-search-shortcut"', $navbar);
        $this->assertStringContainsString("event.key === '/'", $navbar);
        $this->assertStringContainsString("searchInput.focus()", $navbar);
        $this->assertStringContainsString("['input', 'textarea', 'select'].includes(activeTag)", $navbar);
    }

    public function test_mobile_admin_sidebar_keeps_navigation_focused(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/inc/admin/sidebar.blade.php'));

        $this->assertGreaterThanOrEqual(6, substr_count($sidebar, 'data-admin-sidebar-group'));
        $this->assertStringContainsString("window.matchMedia('(max-width: 991.98px)')", $sidebar);
        $this->assertStringContainsString("if (other !== this) other.open = false", $sidebar);
        $this->assertStringContainsString("sidebar.querySelector('.sidebar-current .nav-link, .sidebar-quick-chip.active')", $sidebar);
        $this->assertStringNotContainsString('current.scrollIntoView', $sidebar);
    }

    public function test_mobile_sidebar_has_complete_open_close_lifecycle(): void
    {
        $script = file_get_contents(public_path('admin/js/off-canvas.js'));
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('admin-sidebar-backdrop', $script);
        $this->assertStringContainsString("aria-expanded", $script);
        $this->assertStringContainsString("event.key !== 'Escape'", $script);
        $this->assertStringContainsString("document.body.classList.toggle('admin-sidebar-open'", $script);
        $this->assertStringContainsString("const MOBILE_QUERY = '(max-width: 1199.98px), (pointer: coarse) and (max-width: 1366px)'", $script);
        $this->assertStringContainsString("toggle.addEventListener('click'", $script);
        $this->assertStringNotContainsString('jQuery', $script);
        $this->assertStringContainsString('.admin-sidebar-backdrop.is-active', $layout);
        $this->assertStringContainsString('body.admin-sidebar-open', $layout);
    }

    public function test_admin_topbar_menus_support_keyboard_navigation(): void
    {
        $navbar = file_get_contents(resource_path('views/layouts/inc/admin/navbar.blade.php'));

        $this->assertStringContainsString('aria-controls="quick-create-menu" aria-haspopup="menu"', $navbar);
        $this->assertStringContainsString('aria-controls="profile-menu" aria-haspopup="menu"', $navbar);
        $this->assertStringContainsString('role="menu"', $navbar);
        $this->assertGreaterThanOrEqual(6, substr_count($navbar, 'role="menuitem"'));
        $this->assertStringContainsString('function focusableMenuItems(menu)', $navbar);
        $this->assertStringContainsString("['ArrowDown', 'ArrowUp'].includes(event.key)", $navbar);
        $this->assertStringContainsString('closeMenus(null, true)', $navbar);
        $this->assertStringContainsString('window.requestAnimationFrame(() => firstItem.focus())', $navbar);
    }

    public function test_sidebar_links_expose_current_page_semantics(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/inc/admin/sidebar.blade.php'));

        $this->assertGreaterThanOrEqual(20, substr_count($sidebar, 'aria-current="{{ $isRoute('));
        $this->assertStringContainsString("? 'page' : 'false' }}", $sidebar);
    }

    public function test_mobile_admin_navigation_uses_one_primary_sidebar_trigger(): void
    {
        $navbar = file_get_contents(resource_path('views/layouts/inc/admin/navbar.blade.php'));

        $this->assertSame(1, substr_count($navbar, 'data-toggle="offcanvas"'));
        $this->assertStringContainsString('admin-mobile-sidebar-toggle-inline', $navbar);
        $this->assertStringNotContainsString('class="admin-mobile-sidebar-toggle d-lg-none"', $navbar);
        $this->assertGreaterThanOrEqual(1, substr_count($navbar, ".admin-mobile-sidebar-toggle-inline {"));
        $this->assertSame(1, substr_count($navbar, ".admin-mobile-sidebar-toggle-inline .mdi {"));
    }

    public function test_sidebar_group_summaries_expose_expanded_state(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/inc/admin/sidebar.blade.php'));

        $this->assertGreaterThanOrEqual(6, substr_count($sidebar, 'class="sidebar-group-summary" aria-expanded='));
        $this->assertStringContainsString("summary.setAttribute('aria-expanded', this.open ? 'true' : 'false')", $sidebar);
    }

    public function test_collapsed_admin_sidebar_keeps_utility_actions_inside_icon_rail(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $sidebar = file_get_contents(resource_path('views/layouts/inc/admin/sidebar.blade.php'));

        $this->assertStringContainsString('.sidebar-icon-only .custom-sidebar .sidebar-utility-links', $layout);
        $this->assertStringContainsString('.sidebar-icon-only .custom-sidebar .sidebar-utility-link span', $layout);
        $this->assertStringContainsString('display: none !important;', $layout);
        $this->assertStringContainsString('justify-content: center;', $layout);
        $this->assertStringContainsString("aria-label=\"{{ __('Open storefront') }}\"", $sidebar);
        $this->assertStringContainsString("title=\"{{ __('Open storefront') }}\"", $sidebar);
        $this->assertStringContainsString("aria-label=\"{{ __('Sign out') }}\"", $sidebar);
        $this->assertStringContainsString("title=\"{{ __('Sign out') }}\"", $sidebar);
    }

}
