<?php

namespace App\Providers;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Http\Livewire\Admin\Product\Index as ProductIndex;
use App\Http\Livewire\Admin\Product\ProductForm;
use App\Models\Category;
use App\Services\Auth\AuthorizationService;
use App\Services\Channels\WhatsApp\WhatsAppManager;
use App\Services\Commerce\StoreSettingsService;
use App\Services\Frontend\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Support\LocalSafeBoot;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppServiceInterface::class, WhatsAppManager::class);
    }

    public function boot(): void
    {
        if ((bool) config('app.force_https', false)) {
            URL::forceScheme('https');
        }

        Paginator::useBootstrapFive();

        Livewire::component('admin.product.index', ProductIndex::class);
        Livewire::component('admin.product.product-form', ProductForm::class);

        if (! LocalSafeBoot::shouldSkipBootDatabaseTouches()) {
            try {
                if (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
                    app(AuthorizationService::class)->syncDefaults();
                }
            } catch (\Throwable $e) {
                // Keep boot safe when the database is not ready yet.
            }
        }

        View::composer('*', function ($view) {
            $storefrontView = $view->getName() === 'layouts.app'
                || str_starts_with($view->getName(), 'frontend.')
                || str_starts_with($view->getName(), 'auth.');

            // Request attributes avoid process-wide state leaking between customers or queue jobs.
            // Views rendered outside a matched HTTP route retain the original uncached behavior.
            $request = request();
            $cacheable = $request->route() !== null;
            $context = $cacheable ? $request->attributes->get('dynamic.shared_view_context', []) : [];

            if (! isset($context['common'])) {
                $context['common'] = ['settings' => [], 'notificationCount' => 0];

                try {
                    if (Schema::hasTable('website_settings')) {
                        $context['common']['settings'] = app(StoreSettingsService::class)->all();
                    }

                    if (! LocalSafeBoot::shouldSkipBootDatabaseTouches()) {
                        $user = Auth::user();
                        if ($user && Schema::hasTable('notifications')) {
                            $context['common']['notificationCount'] = $user->unreadNotifications()->count();
                        }
                    }
                } catch (\Throwable $e) {
                    // Keep boot safe until the database is ready.
                }
            }

            if ($storefrontView && ! isset($context['storefront'])) {
                $context['storefront'] = ['categories' => collect(), 'cartCount' => 0];

                try {
                    // Local safe boot still shows real categories when the database is available.
                    if (Schema::hasTable('categories')) {
                        try {
                            $categories = Category::query()
                                ->visibleOnStorefront()
                                ->with('translations')
                                ->latest('id')
                                ->take(14)
                                ->get();

                            if ($categories->isEmpty()) {
                                $categories = Category::query()
                                    ->with('translations')
                                    ->latest('id')
                                    ->take(14)
                                    ->get();
                            }

                            $context['storefront']['categories'] = $categories;
                        } catch (\Throwable $categoryException) {
                            // Keep the storefront usable if category lookup fails.
                        }
                    }

                    try {
                        $context['storefront']['cartCount'] = app(CartService::class)->count();
                    } catch (\Throwable $cartException) {
                        // Keep the cart badge stable until the database is ready.
                    }
                } catch (\Throwable $e) {
                    // Keep the storefront usable until the database is ready.
                }
            }

            if ($cacheable) {
                $request->attributes->set('dynamic.shared_view_context', $context);
            }

            if ($storefrontView) {
                $view->with('layoutCategories', $context['storefront']['categories']);
                $view->with('layoutCartCount', $context['storefront']['cartCount']);
            }

            $view->with('authNotificationCount', $context['common']['notificationCount']);
            $view->with('storeSettings', $context['common']['settings']);
            $view->with('currentLocale', app()->getLocale());
            $view->with('isRtl', app()->getLocale() === 'ar');
        });
    }
}
