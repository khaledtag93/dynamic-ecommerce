@php($adminBrandPath = \App\Support\AdminBranding::resolveMediaPath($storeSettings['admin_logo_path'] ?? $storeSettings['admin_logo'] ?? $storeSettings['logo_path'] ?? $storeSettings['logo'] ?? null, 'admin_logo'))
@php($adminUser = auth()->user())
@php($canDeployManage = $adminUser && method_exists($adminUser, 'hasPermission') && $adminUser->hasPermission('deploy.manage'))
<nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row admin-topbar">
    <div class="navbar-brand-wrapper d-flex justify-content-center">
        <div class="navbar-brand-inner-wrapper d-flex justify-content-between align-items-center w-100 px-3">
            <a class="navbar-brand brand-logo admin-brand" href="{{ route('admin.dashboard') }}">
                @if($adminBrandPath)
                    <span class="admin-brand-mark admin-brand-mark-image">
                        <img src="{{ \App\Support\AdminBranding::mediaUrl($adminBrandPath, 'admin_logo') }}" alt="{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront' }}">
                    </span>
                @else
                    <span class="admin-brand-mark">
                        <i class="mdi mdi-cupcake"></i>
                    </span>
                @endif

                <span class="admin-brand-text">
                    <strong>{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront' }}</strong>
                    <small>{{ $storeSettings['store_name'] ?? __('Admin Dashboard') }}</small>
                </span>
            </a>

            <a class="navbar-brand brand-logo-mini admin-brand-mini" href="{{ route('admin.dashboard') }}">
                @if($adminBrandPath)
                    <span class="admin-brand-mark admin-brand-mark-image">
                        <img src="{{ \App\Support\AdminBranding::mediaUrl($adminBrandPath, 'admin_logo') }}" alt="{{ $storeSettings['project_name'] ?? $storeSettings['store_name'] ?? 'Storefront' }}">
                    </span>
                @else
                    <span class="admin-brand-mark">
                        <i class="mdi mdi-cupcake"></i>
                    </span>
                @endif
            </a>

            <button class="navbar-toggler align-self-center" type="button" data-toggle="minimize">
                <span class="mdi mdi-menu"></span>
            </button>
        </div>
    </div>

    <div class="navbar-menu-wrapper admin-topbar-shell d-flex align-items-center justify-content-between px-3 px-lg-4">
        <div class="admin-topbar-start d-flex align-items-center gap-2 gap-lg-3 flex-grow-1">
            <button class="navbar-toggler d-lg-none align-self-center me-1 admin-mobile-sidebar-toggle-inline" type="button" data-toggle="offcanvas" aria-label="{{ __('Toggle sidebar') }}">
                <span class="mdi mdi-menu"></span>
            </button>

            <form action="{{ route('admin.dashboard') }}" method="GET" class="admin-topbar-search d-none d-lg-flex align-items-center">
                <i class="mdi mdi-magnify"></i>
                <input type="text" name="q" class="form-control border-0 bg-transparent shadow-none" placeholder="{{ __('Search products, orders, customers, and coupons') }}" value="{{ request('q') }}">
            </form>
        </div>

        <ul class="navbar-nav navbar-nav-right align-items-center gap-2 gap-lg-3">
            <li class="nav-item d-none d-lg-flex align-items-center">
                @include('layouts.inc.language-switcher', ['class' => 'language-switcher-admin', 'variant' => 'admin-compact'])
            </li>

            <li class="nav-item d-none d-xl-flex align-items-center">
                <a class="admin-topbar-action admin-topbar-action--icon admin-topbar-action--notifications" href="{{ route('admin.notifications.index') }}" aria-label="{{ __('Notifications') }}" title="{{ __('Notifications') }}">
                    <i class="mdi mdi-bell-outline"></i>
                    @if($authNotificationCount > 0)
                        <span class="admin-topbar-count">{{ $authNotificationCount > 99 ? '99+' : $authNotificationCount }}</span>
                        <span class="admin-topbar-dot" aria-hidden="true"></span>
                    @endif
                </a>
            </li>

            <li class="nav-item d-none d-xl-flex align-items-center position-relative admin-menu-wrap">
                <button type="button" class="admin-topbar-action admin-custom-menu-toggle" data-admin-menu-target="quick-create-menu" aria-expanded="false">
                    <i class="mdi mdi-plus-circle-outline"></i>
                    <span class="d-none d-xxl-inline">{{ __('Quick create') }}</span>
                    <i class="mdi mdi-chevron-down admin-menu-chevron"></i>
                </button>

                <div class="admin-custom-menu" id="quick-create-menu" hidden>
                    <a class="admin-custom-menu__item" href="{{ route('admin.products.create') }}"><i class="mdi mdi-package-variant-closed"></i><span>{{ __('New product') }}</span></a>
                    <a class="admin-custom-menu__item" href="{{ route('admin.categories.create') }}"><i class="mdi mdi-shape-outline"></i><span>{{ __('New category') }}</span></a>
                    <a class="admin-custom-menu__item" href="{{ route('admin.coupons.create') }}"><i class="mdi mdi-ticket-percent-outline"></i><span>{{ __('New coupon') }}</span></a>
                    <a class="admin-custom-menu__item" href="{{ route('admin.orders.index') }}"><i class="mdi mdi-cart-outline"></i><span>{{ __('Review orders') }}</span></a>
                </div>
            </li>

            <li class="nav-item nav-profile position-relative admin-menu-wrap">
                <button type="button" class="nav-link admin-profile-trigger admin-custom-menu-toggle" data-admin-menu-target="profile-menu" aria-expanded="false">
                    <span class="admin-profile-avatar">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </span>
                    <span class="nav-profile-name d-none d-sm-inline">{{ Auth::user()->name }}</span>
                    <i class="mdi mdi-chevron-down admin-menu-chevron"></i>
                </button>

                <div class="admin-custom-menu admin-custom-menu--profile" id="profile-menu" hidden>
                    <div class="admin-profile-menu__header">
                        <div class="admin-profile-menu__name">{{ Auth::user()->name }}</div>
                        <div class="admin-profile-menu__email">{{ Auth::user()->email }}</div>
                    </div>

                    <div class="admin-custom-menu__divider"></div>

                    <a class="admin-custom-menu__item" href="{{ route('admin.dashboard') }}">
                        <i class="mdi mdi-view-dashboard-outline"></i>
                        <span>{{ __('Admin dashboard') }}</span>
                    </a>

                    <a class="admin-custom-menu__item" href="{{ route('admin.notifications.index') }}">
                        <i class="mdi mdi-bell-outline"></i>
                        <span>{{ __('Notifications') }}</span>
                        @if($authNotificationCount > 0)
                            <span class="admin-custom-menu__count">{{ $authNotificationCount }}</span>
                        @endif
                    </a>

                    <a class="admin-custom-menu__item" href="{{ route('admin.settings.notifications') }}">
                        <i class="mdi mdi-bell-cog-outline"></i>
                        <span>{{ __('Notification Center') }}</span>
                    </a>

                    @if($canDeployManage)
                    <a class="admin-custom-menu__item" href="{{ route('admin.settings.deploy-center') }}">
                        <i class="mdi mdi-rocket-launch-outline"></i>
                        <span>{{ __('Deploy Center') }}</span>
                    </a>
                    @endif

                    <a class="admin-custom-menu__item" href="{{ Route::has('frontend.home') ? route('frontend.home') : url('/') }}" target="_blank" rel="noopener">
                            <i class="mdi mdi-storefront-outline"></i>
                            <span>{{ __('View storefront') }}</span>
                        </a>


                    @if(Route::has('admin.orders.index'))
                        <a class="admin-custom-menu__item" href="{{ route('admin.orders.index') }}">
                            <i class="mdi mdi-cart-outline"></i>
                            <span>{{ __('Orders') }}</span>
                        </a>
                    @endif

                    <div class="admin-custom-menu__divider"></div>

                    <a class="admin-custom-menu__item admin-custom-menu__item--danger" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="mdi mdi-logout"></i>
                        <span>{{ __('Sign out') }}</span>
                    </a>

                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </li>
        </ul>
    </div>
</nav>

<button class="admin-mobile-sidebar-toggle d-lg-none" type="button" data-toggle="offcanvas" aria-label="{{ __('Toggle sidebar') }}" title="{{ __('Open navigation') }}">
    <i class="mdi mdi-menu"></i>
</button>

<style>

.admin-mobile-sidebar-toggle,
.admin-mobile-sidebar-toggle-inline {
    border: 1px solid var(--admin-border);
    background: color-mix(in srgb, var(--admin-surface) 92%, white);
    color: var(--admin-text);
    box-shadow: 0 14px 32px color-mix(in srgb, var(--admin-sidebar) 18%, transparent);
}
.admin-mobile-sidebar-toggle-inline {
    width: 2.85rem;
    height: 2.85rem;
    padding: 0;
    border-radius: 999px;
    display: none;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.admin-mobile-sidebar-toggle {
    display: none;
}
.admin-mobile-sidebar-toggle i,
.admin-mobile-sidebar-toggle-inline i,
.admin-mobile-sidebar-toggle-inline .mdi,
.admin-mobile-sidebar-toggle .mdi {
    font-size: 1.3rem;
}

.admin-mobile-sidebar-toggle {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.admin-mobile-sidebar-toggle:focus-visible,
.admin-mobile-sidebar-toggle-inline:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--admin-primary) 22%, transparent),
                0 14px 32px color-mix(in srgb, var(--admin-sidebar) 18%, transparent);
}

.admin-custom-menu-toggle {
    border: 0;
    background: transparent;
}
.admin-menu-wrap { position: relative; }
.admin-menu-chevron {
    font-size: 1rem;
    opacity: .8;
    transition: transform .2s ease;
}
.admin-custom-menu-toggle.is-open .admin-menu-chevron {
    transform: rotate(180deg);
}
.admin-custom-menu {
    position: absolute;
    top: calc(100% + 10px);
    inset-inline-end: 0;
    min-width: 280px;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 18px;
    box-shadow: var(--admin-shadow);
    padding: .6rem;
    z-index: 1080;
}
.admin-custom-menu--profile { min-width: 290px; }
.admin-custom-menu__item {
    display: flex;
    align-items: center;
    gap: .7rem;
    width: 100%;
    padding: .8rem .9rem;
    border-radius: 14px;
    color: var(--admin-text);
    text-decoration: none;
    font-weight: 700;
}
.admin-custom-menu__item i {
    font-size: 1.1rem;
    color: var(--admin-primary-dark);
    flex: 0 0 auto;
}
.admin-custom-menu__item:hover {
    background: color-mix(in srgb, var(--admin-primary-soft) 55%, white);
    color: var(--admin-text);
    text-decoration: none;
}
.admin-custom-menu__divider {
    height: 1px;
    background: var(--admin-border);
    margin: .45rem 0;
}
.admin-custom-menu__count {
    margin-inline-start: auto;
    min-width: 24px;
    height: 24px;
    padding: 0 .4rem;
    border-radius: 999px;
    background: #ff5a4f;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .72rem;
    font-weight: 800;
}
.admin-custom-menu__item--danger,
.admin-custom-menu__item--danger i { color: #8f1d1d; }
body[dir='rtl'] .admin-custom-menu { inset-inline-end: 0; inset-inline-start: auto; text-align: right; }
body[dir='ltr'] .admin-custom-menu { inset-inline-start: auto; inset-inline-end: 0; text-align: left; }
@media (max-width: 991.98px) {
    .admin-custom-menu {
        position: fixed;
        top: 76px;
        inset-inline-end: 12px;
        min-width: min(320px, calc(100vw - 24px));
        max-width: calc(100vw - 24px);
    }
    .admin-mobile-sidebar-toggle-inline {
        display: inline-flex;
    }
    .admin-mobile-sidebar-toggle {
        position: fixed;
        top: calc(env(safe-area-inset-top, 0px) + 78px);
        inset-inline-start: 14px;
        z-index: 1051;
        width: 3.2rem;
        height: 3.2rem;
        padding: 0;
        border-radius: 999px;
        align-items: center;
        justify-content: center;
        display: inline-flex;
    }
    .navbar .navbar-brand-wrapper .navbar-toggler {
        display: none !important;
    }
    body[dir='rtl'] .admin-mobile-sidebar-toggle {
        inset-inline-start: auto;
        inset-inline-end: 12px;
    }
}
@media (min-width: 992px) {
    .admin-mobile-sidebar-toggle {
        display: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggles = Array.from(document.querySelectorAll('.admin-custom-menu-toggle'));

    function closeMenus(exceptMenuId = null) {
        toggles.forEach((toggle) => {
            const menuId = toggle.getAttribute('data-admin-menu-target');
            const menu = menuId ? document.getElementById(menuId) : null;
            const isKeepOpen = exceptMenuId && menuId === exceptMenuId;
            if (!menu || isKeepOpen) return;
            menu.hidden = true;
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    }

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const menuId = this.getAttribute('data-admin-menu-target');
            const menu = menuId ? document.getElementById(menuId) : null;
            if (!menu) return;
            const willOpen = menu.hidden;
            closeMenus(willOpen ? menuId : null);
            menu.hidden = !willOpen;
            this.classList.toggle('is-open', willOpen);
            this.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('.admin-menu-wrap')) return;
        closeMenus();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenus();
    });
});
</script>
