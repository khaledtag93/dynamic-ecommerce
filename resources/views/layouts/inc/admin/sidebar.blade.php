<nav class="sidebar sidebar-offcanvas custom-sidebar" id="sidebar">
  @php
    $pendingOrdersCount = \App\Models\Order::where('status', \App\Models\Order::STATUS_PENDING)->count();

    $activeCouponsCount = 0;

    if (\Schema::hasTable('coupons')) {
        if (\Schema::hasColumn('coupons', 'status')) {
            $activeCouponsCount = \App\Models\Coupon::where('status', 1)->count();
        } elseif (\Schema::hasColumn('coupons', 'is_active')) {
            $activeCouponsCount = \App\Models\Coupon::where('is_active', 1)->count();
        } elseif (\Schema::hasColumn('coupons', 'active')) {
            $activeCouponsCount = \App\Models\Coupon::where('active', 1)->count();
        }
    }

    $suppliersCount = \Schema::hasTable('suppliers') ? \App\Models\Supplier::count() : 0;
    $adminUser = auth()->user();

    $can = function (string $permission) use ($adminUser) {
        return $adminUser && method_exists($adminUser, 'hasPermission') && $adminUser->hasPermission($permission);
    };

    $isRoute = function (...$patterns) {
        return request()->routeIs(...$patterns);
    };

    $overviewOpen = $isRoute('admin.dashboard', 'admin.analytics.*', 'admin.growth.*');
    $catalogOpen = $isRoute('admin.categories.*', 'admin.products.*', 'admin.attributes.*', 'admin.brands.*');
    $operationsOpen = $isRoute('admin.orders.*', 'admin.customers.*', 'admin.deliveries.*', 'admin.payments.*');
    $inventoryOpen = $isRoute('admin.purchases.*', 'admin.inventory.*', 'admin.suppliers.*');
    $marketingOpen = $isRoute('admin.coupons.*', 'admin.promotions.*');
    $channelsOpen = $isRoute('admin.settings.branding', 'admin.settings.content*', 'admin.settings.whatsapp*', 'admin.settings.notifications*', 'admin.settings.payments*', 'admin.settings.deploy-center*', 'admin.imports.*');
    $teamOpen = $isRoute('admin.notifications.*', 'admin.permissions.*');

    $sidebarPrimaryFocus = __('System looks steady');
    if ($pendingOrdersCount > 0) {
        $sidebarPrimaryFocus = __('Pending orders need review');
    } elseif ($authNotificationCount > 0) {
        $sidebarPrimaryFocus = __('Unread admin notifications');
    }

    $sidebarPrimaryCount = $pendingOrdersCount > 0 ? $pendingOrdersCount : $authNotificationCount;

    $currentWorkspace = __('Workspace');
    if ($overviewOpen) {
        $currentWorkspace = __('Overview');
    } elseif ($catalogOpen) {
        $currentWorkspace = __('Catalog');
    } elseif ($operationsOpen) {
        $currentWorkspace = __('Operations');
    } elseif ($inventoryOpen) {
        $currentWorkspace = __('Inventory & sourcing');
    } elseif ($marketingOpen) {
        $currentWorkspace = __('Marketing');
    } elseif ($channelsOpen) {
        $currentWorkspace = __('Channels & setup');
    } elseif ($teamOpen) {
        $currentWorkspace = __('Team & alerts');
    }

    $overviewCount = 3;
    $catalogCount = 4;
    $operationsCount = ($can('orders.view') ? 1 : 0) + ($can('customers.manage') ? 1 : 0) + ($can('payments.view') ? 1 : 0) + ($can('delivery.view') ? 1 : 0);
    $inventoryCount = 3;
    $marketingCount = 2;
    $channelsCount = ($can('settings.manage') ? 4 : 0) + ($can('deploy.manage') ? 1 : 0) + ($can('payments.settings') ? 1 : 0) + ($can('imports.manage') ? 1 : 0);
    $teamCount = ($can('notifications.view') ? 1 : 0) + ($can('permissions.manage') ? 1 : 0);
  @endphp

    <div class="sidebar-header px-3 py-3">
        <div class="sidebar-store-card">
            <span class="sidebar-store-icon"><i class="mdi mdi-storefront-outline"></i></span>
            <div class="sidebar-store-text">
                <div class="sidebar-store-title">{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront' }}</div>
                <small>{{ $storeSettings['store_name'] ?? __('Store overview') }}</small>
            </div>
        </div>
    </div>

    <div class="sidebar-quick-access px-3 pt-3 pb-2">
        <div class="sidebar-focus-card mb-3">
            <div class="sidebar-focus-card__label">{{ __('Today focus') }}</div>
            <div class="sidebar-focus-card__title">{{ $sidebarPrimaryFocus }}</div>
            <div class="sidebar-focus-card__meta">
                @if($sidebarPrimaryCount > 0)
                    {{ __(':count items need a closer look.', ['count' => number_format($sidebarPrimaryCount)]) }}
                @else
                    {{ __('No urgent blocker is dominating the admin workspace right now.') }}
                @endif
            </div>
            <div class="sidebar-focus-card__workspace">{{ __('Current area') }}: <strong>{{ $currentWorkspace }}</strong></div>
        </div>
        <span class="sidebar-section-label mb-2">{{ __('Quick access') }}</span>
        <div class="sidebar-quick-grid">
            @if($can('dashboard.view'))
                <a href="{{ route('admin.dashboard') }}" class="sidebar-quick-chip {{ $isRoute('admin.dashboard') ? 'active' : '' }}">
                    <i class="mdi mdi-view-dashboard-outline"></i>
                    <span>{{ __('Dashboard') }}</span>
                </a>
            @endif
            @if($can('orders.view'))
                <a href="{{ route('admin.orders.index') }}" class="sidebar-quick-chip {{ $isRoute('admin.orders.*') ? 'active' : '' }}">
                    <i class="mdi mdi-cart-outline"></i>
                    <span>{{ __('Orders') }}</span>
                    @if($pendingOrdersCount > 0)
                        <strong>{{ $pendingOrdersCount }}</strong>
                    @endif
                </a>
            @endif
            @if($can('notifications.view'))
                <a href="{{ route('admin.notifications.index') }}" class="sidebar-quick-chip {{ $isRoute('admin.notifications.*') ? 'active' : '' }}">
                    <i class="mdi mdi-bell-outline"></i>
                    <span>{{ __('Notifications') }}</span>
                    @if($authNotificationCount > 0)
                        <strong>{{ $authNotificationCount }}</strong>
                    @endif
                </a>
            @endif
            @if($can('settings.manage'))
                <a href="{{ route('admin.settings.notifications') }}" class="sidebar-quick-chip {{ $isRoute('admin.settings.notifications*') ? 'active' : '' }}">
                    <i class="mdi mdi-bell-cog-outline"></i>
                    <span>{{ __('Notification Center') }}</span>
                </a>
            @endif
            @if($can('deploy.manage'))
                <a href="{{ route('admin.settings.deploy-center') }}" class="sidebar-quick-chip {{ $isRoute('admin.settings.deploy-center*') ? 'active' : '' }}">
                    <i class="mdi mdi-rocket-launch-outline"></i>
                    <span>{{ __('Deploy Center') }}</span>
                </a>
            @endif
            <a href="{{ Route::has('frontend.home') ? route('frontend.home') : url('/') }}" target="_blank" rel="noopener" class="sidebar-quick-chip">
                <i class="mdi mdi-storefront-outline"></i>
                <span>{{ __('Storefront') }}</span>
            </a>
        </div>
    </div>

    @if($can('dashboard.view'))
    <details class="sidebar-group" {{ $overviewOpen ? 'open' : '' }}>
        <summary class="sidebar-group-summary">
            <span class="sidebar-group-title-wrap">
                <span class="sidebar-group-icon"><i class="mdi mdi-monitor-dashboard"></i></span>
                <span>
                    <span class="sidebar-group-title">{{ __('Overview') }}</span><span class="sidebar-group-count">{{ $overviewCount }}</span>
                    <small>{{ __('Tracking, analytics, and growth') }}</small>
                </span>
            </span>
            <i class="mdi mdi-chevron-down sidebar-group-arrow"></i>
        </summary>
        <div class="sidebar-group-body">
            <ul class="nav flex-column">
                <li class="nav-item {{ $isRoute('admin.dashboard') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.dashboard') }}"><i class="mdi mdi-view-dashboard-outline menu-icon"></i><span class="menu-title">{{ __('Dashboard') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.analytics.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.analytics.index') }}"><i class="mdi mdi-chart-areaspline menu-icon"></i><span class="menu-title">{{ __('Analytics & Insights') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.growth.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.growth.index') }}"><i class="mdi mdi-rocket-launch-outline menu-icon"></i><span class="menu-title">{{ __('Growth Engine') }}</span></a>
                </li>
            </ul>
        </div>
    </details>
    @endif

    @if($can('catalog.manage'))
    <details class="sidebar-group" {{ $catalogOpen ? 'open' : '' }}>
        <summary class="sidebar-group-summary">
            <span class="sidebar-group-title-wrap">
                <span class="sidebar-group-icon"><i class="mdi mdi-package-variant"></i></span>
                <span>
                    <span class="sidebar-group-title">{{ __('Catalog') }}</span><span class="sidebar-group-count">{{ $catalogCount }}</span>
                    <small>{{ __('Products, structure, and variants') }}</small>
                </span>
            </span>
            <i class="mdi mdi-chevron-down sidebar-group-arrow"></i>
        </summary>
        <div class="sidebar-group-body">
            <ul class="nav flex-column">
                <li class="nav-item {{ $isRoute('admin.categories.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.categories.index') }}"><i class="mdi mdi-shape-outline menu-icon"></i><span class="menu-title">{{ __('Categories') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.products.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.products.index') }}"><i class="mdi mdi-package-variant-closed menu-icon"></i><span class="menu-title">{{ __('Products') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.attributes.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.attributes.index') }}"><i class="mdi mdi-tune-variant menu-icon"></i><span class="menu-title">{{ __('Attributes') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.brands.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.brands.index') }}"><i class="mdi mdi-tag-outline menu-icon"></i><span class="menu-title">{{ __('Brands') }}</span></a>
                </li>
            </ul>
        </div>
    </details>
    @endif

    @if($can('orders.view') || $can('customers.manage') || $can('payments.view') || $can('delivery.view'))
    <details class="sidebar-group" {{ $operationsOpen ? 'open' : '' }}>
        <summary class="sidebar-group-summary">
            <span class="sidebar-group-title-wrap">
                <span class="sidebar-group-icon"><i class="mdi mdi-clipboard-text-clock-outline"></i></span>
                <span>
                    <span class="sidebar-group-title">{{ __('Operations') }}</span><span class="sidebar-group-count">{{ $operationsCount }}</span>
                    <small>{{ __('Orders, customers, payments, delivery') }}</small>
                </span>
            </span>
            <i class="mdi mdi-chevron-down sidebar-group-arrow"></i>
        </summary>
        <div class="sidebar-group-body">
            <ul class="nav flex-column">
                @if($can('orders.view'))
                <li class="nav-item {{ $isRoute('admin.orders.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.orders.index') }}"><i class="mdi mdi-cart-outline menu-icon"></i><span class="menu-title">{{ __('Orders') }}</span>@if($pendingOrdersCount > 0)<span class="sidebar-inline-badge sidebar-inline-badge-warn">{{ $pendingOrdersCount }}</span>@endif</a>
                </li>
                @endif
                @if($can('customers.manage'))
                <li class="nav-item {{ $isRoute('admin.customers.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.customers.index') }}"><i class="mdi mdi-account-group-outline menu-icon"></i><span class="menu-title">{{ __('Customers') }}</span></a>
                </li>
                @endif
                @if($can('payments.view'))
                <li class="nav-item {{ $isRoute('admin.payments.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.payments.index') }}"><i class="mdi mdi-credit-card-outline menu-icon"></i><span class="menu-title">{{ __('Payments') }}</span></a>
                </li>
                @endif
                @if($can('delivery.view'))
                <li class="nav-item {{ $isRoute('admin.deliveries.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.deliveries.index') }}"><i class="mdi mdi-truck-fast-outline menu-icon"></i><span class="menu-title">{{ __('Deliveries') }}</span></a>
                </li>
                @endif
            </ul>
        </div>
    </details>
    @endif

    @if($can('inventory.manage'))
    <details class="sidebar-group" {{ $inventoryOpen ? 'open' : '' }}>
        <summary class="sidebar-group-summary">
            <span class="sidebar-group-title-wrap">
                <span class="sidebar-group-icon"><i class="mdi mdi-warehouse"></i></span>
                <span>
                    <span class="sidebar-group-title">{{ __('Inventory & sourcing') }}</span><span class="sidebar-group-count">{{ $inventoryCount }}</span>
                    <small>{{ __('Stock, purchases, and suppliers') }}</small>
                </span>
            </span>
            <i class="mdi mdi-chevron-down sidebar-group-arrow"></i>
        </summary>
        <div class="sidebar-group-body">
            <ul class="nav flex-column">
                <li class="nav-item {{ $isRoute('admin.inventory.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.inventory.index') }}"><i class="mdi mdi-warehouse menu-icon"></i><span class="menu-title">{{ __('Inventory') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.purchases.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.purchases.index') }}"><i class="mdi mdi-package-variant-plus menu-icon"></i><span class="menu-title">{{ __('Purchases') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.suppliers.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.suppliers.index') }}"><i class="mdi mdi-truck-delivery-outline menu-icon"></i><span class="menu-title">{{ __('Suppliers') }}</span>@if($suppliersCount > 0)<span class="sidebar-inline-badge sidebar-inline-badge-success">{{ $suppliersCount }}</span>@endif</a>
                </li>
            </ul>
        </div>
    </details>
    @endif

    @if($can('promotions.manage'))
    <details class="sidebar-group" {{ $marketingOpen ? 'open' : '' }}>
        <summary class="sidebar-group-summary">
            <span class="sidebar-group-title-wrap">
                <span class="sidebar-group-icon"><i class="mdi mdi-bullhorn-outline"></i></span>
                <span>
                    <span class="sidebar-group-title">{{ __('Marketing') }}</span><span class="sidebar-group-count">{{ $marketingCount }}</span>
                    <small>{{ __('Offers, coupons, and campaigns') }}</small>
                </span>
            </span>
            <i class="mdi mdi-chevron-down sidebar-group-arrow"></i>
        </summary>
        <div class="sidebar-group-body">
            <ul class="nav flex-column">
                <li class="nav-item {{ $isRoute('admin.promotions.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.promotions.index') }}"><i class="mdi mdi-sale menu-icon"></i><span class="menu-title">{{ __('Promotions') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.coupons.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.coupons.index') }}"><i class="mdi mdi-ticket-percent-outline menu-icon"></i><span class="menu-title">{{ __('Coupons') }}</span>@if($activeCouponsCount > 0)<span class="sidebar-inline-badge sidebar-inline-badge-success">{{ $activeCouponsCount }}</span>@endif</a>
                </li>
            </ul>
        </div>
    </details>
    @endif

    @if($can('settings.manage') || $can('deploy.manage') || $can('payments.settings') || $can('imports.manage'))
    <details class="sidebar-group" {{ $channelsOpen ? 'open' : '' }}>
        <summary class="sidebar-group-summary">
            <span class="sidebar-group-title-wrap">
                <span class="sidebar-group-icon"><i class="mdi mdi-cog-outline"></i></span>
                <span>
                    <span class="sidebar-group-title">{{ __('Channels & setup') }}</span><span class="sidebar-group-count">{{ $channelsCount }}</span>
                    <small>{{ __('Brand, content, notifications, and integrations') }}</small>
                </span>
            </span>
            <i class="mdi mdi-chevron-down sidebar-group-arrow"></i>
        </summary>
        <div class="sidebar-group-body">
            <ul class="nav flex-column">
                @if($can('settings.manage'))
                <li class="nav-item {{ $isRoute('admin.settings.branding') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.settings.branding') }}"><i class="mdi mdi-palette-outline menu-icon"></i><span class="menu-title">{{ __('Brand & Identity') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.settings.content*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.settings.content') }}"><i class="mdi mdi-file-document-edit-outline menu-icon"></i><span class="menu-title">{{ __('Store content') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.settings.whatsapp*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.settings.whatsapp') }}"><i class="mdi mdi-whatsapp menu-icon"></i><span class="menu-title">{{ __('WhatsApp Channel') }}</span></a>
                </li>
                <li class="nav-item {{ $isRoute('admin.settings.notifications*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.settings.notifications') }}"><i class="mdi mdi-bell-cog-outline menu-icon"></i><span class="menu-title">{{ __('Notification Center') }}</span></a>
                </li>
                @if($can('deploy.manage'))
                <li class="nav-item {{ $isRoute('admin.settings.deploy-center*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.settings.deploy-center') }}"><i class="mdi mdi-rocket-launch-outline menu-icon"></i><span class="menu-title">{{ __('Deploy Center') }}</span></a>
                </li>
                @endif
                @endif
                @if($can('payments.settings'))
                <li class="nav-item {{ $isRoute('admin.settings.payments*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.settings.payments') }}"><i class="mdi mdi-credit-card-settings-outline menu-icon"></i><span class="menu-title">{{ __('Payment methods') }}</span></a>
                </li>
                @endif
                @if($can('imports.manage'))
                <li class="nav-item {{ $isRoute('admin.imports.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.imports.index') }}"><i class="mdi mdi-database-import-outline menu-icon"></i><span class="menu-title">{{ __('Data Imports') }}</span></a>
                </li>
                @endif
            </ul>
        </div>
    </details>
    @endif

    @if($can('notifications.view') || $can('permissions.manage'))
    <details class="sidebar-group sidebar-group-last" {{ $teamOpen ? 'open' : '' }}>
        <summary class="sidebar-group-summary">
            <span class="sidebar-group-title-wrap">
                <span class="sidebar-group-icon"><i class="mdi mdi-account-supervisor-circle-outline"></i></span>
                <span>
                    <span class="sidebar-group-title">{{ __('Team & alerts') }}</span><span class="sidebar-group-count">{{ $teamCount }}</span>
                    <small>{{ __('Admin inbox, access, and monitoring') }}</small>
                </span>
            </span>
            <i class="mdi mdi-chevron-down sidebar-group-arrow"></i>
        </summary>
        <div class="sidebar-group-body">
            <ul class="nav flex-column mb-3">
                @if($can('notifications.view'))
                <li class="nav-item {{ $isRoute('admin.notifications.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.notifications.index') }}"><i class="mdi mdi-bell-outline menu-icon"></i><span class="menu-title">{{ __('Admin Inbox') }}</span>@if($authNotificationCount > 0)<span class="sidebar-inline-badge sidebar-inline-badge-warn">{{ $authNotificationCount }}</span>@endif</a>
                </li>
                @endif
                @if($can('permissions.manage'))
                <li class="nav-item {{ $isRoute('admin.permissions.*') ? 'sidebar-current active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.permissions.index') }}"><i class="mdi mdi-shield-account-outline menu-icon"></i><span class="menu-title">{{ __('Roles & Permissions') }}</span></a>
                </li>
                @endif
            </ul>
            <div class="px-3 pb-3">
                <div class="sidebar-mini-note">
                    <div class="fw-bold text-white mb-2">{{ __('Next production steps') }}</div>
                    <ul class="sidebar-mini-list mb-0">
                        <li>{{ __('Payment + promotions foundation ready') }}</li>
                        <li>{{ __('Suppliers, cost, and purchases') }}</li>
                        <li>{{ __('Inventory, expiry, and profit tracking') }}</li>
                        <li>{{ __('White-label + import readiness') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </details>
    @endif

    <div class="sidebar-utility-links px-3 pb-4">
        <a class="sidebar-utility-link" href="{{ Route::has('frontend.home') ? route('frontend.home') : url('/') }}" target="_blank" rel="noopener">
            <i class="mdi mdi-storefront-outline"></i>
            <span>{{ __('Open storefront') }}</span>
        </a>
        <a class="sidebar-utility-link" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
            <i class="mdi mdi-logout"></i>
            <span>{{ __('Sign out') }}</span>
        </a>
        <form id="sidebar-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
    </div>
</nav>
