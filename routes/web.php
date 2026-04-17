<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\DeployCenterController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashBoardController;
use App\Http\Controllers\Admin\GrowthController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\NotificationCenterController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\WhatsAppSettingsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\FrontendController;
use App\Http\Controllers\Frontend\ProductController as FrontendProductController;
use App\Http\Controllers\Frontend\NotificationController as FrontendNotificationController;
use App\Http\Controllers\Frontend\PaymobController;
use App\Http\Controllers\Frontend\ContentPageController;
use App\Http\Controllers\Admin\ContentSettingsController;
use App\Http\Controllers\HomeController;
use App\Http\Livewire\Admin\Attribute\Index as AttributeIndex;
use App\Http\Livewire\Admin\Attribute\Values as AttributeValues;
use App\Http\Livewire\Admin\Brand\Index as BrandIndex;
use App\Support\LocalSafeBoot;

Auth::routes();

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'message' => 'web pong',
        'local_safe_boot' => LocalSafeBoot::status(),
    ]);
})->withoutMiddleware([
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\SetLocale::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
]);


Route::get('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, ['en', 'ar'], true), 404);

    session(['locale' => $locale]);

    return redirect()->to($request->query('redirect', url()->previous() ?: route('frontend.home')));
})->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/
Route::get('/', [FrontendController::class, 'index'])->name('frontend.home');
Route::get('/category/{id}', [FrontendController::class, 'showCategoryProducts'])->name('category.products');
Route::get('/products/{product:slug}', [FrontendProductController::class, 'show'])->name('frontend.products.show');
Route::get('/contact', [ContentPageController::class, 'contact'])->name('frontend.contact');
Route::get('/privacy-policy', [ContentPageController::class, 'privacy'])->name('frontend.privacy');
Route::get('/terms-and-conditions', [ContentPageController::class, 'terms'])->name('frontend.terms');
Route::get('/refund-policy', [ContentPageController::class, 'refund'])->name('frontend.refund');
Route::get('/shipping-policy', [ContentPageController::class, 'shipping'])->name('frontend.shipping');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::post('/cart/{product}', [CartController::class, 'store'])->name('cart.store');
Route::post('/cart/{product}/bundle', [CartController::class, 'storeBundle'])->name('cart.bundle.store');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [CheckoutController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [CheckoutController::class, 'showOrder'])->name('orders.show');
    Route::patch('/orders/{order}/cancel', [CheckoutController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/order-success/{order}', [CheckoutController::class, 'success'])->name('orders.success');

    Route::get('/notifications', [FrontendNotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [FrontendNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [FrontendNotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/payments/paymob/{order}/redirect', [PaymobController::class, 'redirect'])->name('payments.paymob.redirect');
    Route::get('/payments/paymob/{order}/result', [PaymobController::class, 'result'])->name('payments.paymob.result');
});

Route::match(['get', 'post'], '/payments/paymob/callback', [PaymobController::class, 'callback'])->name('payments.paymob.callback');

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::get('/branding-media/{path}', function (string $path) {
    $path = trim(str_replace('\\', '/', $path), '/');
    abort_if($path === '' || str_contains($path, '..'), 404);

    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }

    $stream = Storage::disk('public')->readStream($path);
    abort_unless($stream !== false, 404);

    $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
    $lastModified = Storage::disk('public')->lastModified($path);
    $etag = md5($path.'|'.$lastModified);

    return response()->stream(function () use ($stream) {
        fpassthru($stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }, 200, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000, immutable',
        'ETag' => $etag,
        'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
        'Content-Disposition' => 'inline; filename="'.basename($path).'"',
    ]);
})->where('path', '.*')->name('branding.media');


/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['web', 'auth', 'isAdmin'])
    ->name('admin.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::middleware('permission:dashboard.view')->get('/dashboard', [DashBoardController::class, 'index'])->name('dashboard');
        Route::middleware('permission:dashboard.view')->controller(AnalyticsController::class)->group(function () {
            Route::get('/analytics', 'index')->name('analytics.index');
            Route::get('/analytics/growth', 'growth')->name('analytics.growth');
            Route::get('/analytics/offers', 'offers')->name('analytics.offers');
            Route::get('/analytics/products/{product}', 'product')->name('analytics.products.show');
        });
        Route::middleware('permission:dashboard.view')->controller(GrowthController::class)->group(function () {
            Route::get('/growth', 'index')->name('growth.index');
            Route::get('/growth/content', 'content')->name('growth.content');
            Route::get('/growth/operations', 'operations')->name('growth.operations');
            Route::get('/growth/insights', 'insights')->name('growth.insights');
            Route::put('/growth/settings', 'updateSettings')->name('growth.settings.update');
            Route::post('/growth/run-now', 'runNow')->name('growth.run-now');
            Route::post('/growth/validation-demo/seed', 'seedValidationDemo')->name('growth.validation-demo.seed');
            Route::delete('/growth/validation-demo', 'clearValidationDemo')->name('growth.validation-demo.clear');

            Route::get('/growth/campaigns/create', 'createCampaign')->name('growth.campaigns.create');
            Route::post('/growth/campaigns', 'storeCampaign')->name('growth.campaigns.store');
            Route::get('/growth/campaigns/{campaign}/edit', 'editCampaign')->name('growth.campaigns.edit');
            Route::put('/growth/campaigns/{campaign}', 'updateCampaign')->name('growth.campaigns.update');
            Route::delete('/growth/campaigns/{campaign}', 'destroyCampaign')->name('growth.campaigns.destroy');
            Route::patch('/growth/campaigns/{campaign}/toggle', 'toggleCampaign')->name('growth.campaigns.toggle');
            Route::patch('/growth/campaigns/{campaign}/toggle-messaging', 'toggleCampaignMessaging')->name('growth.campaigns.toggle-messaging');

            Route::get('/growth/rules/create', 'createRule')->name('growth.rules.create');
            Route::post('/growth/rules', 'storeRule')->name('growth.rules.store');
            Route::get('/growth/rules/{rule}/edit', 'editRule')->name('growth.rules.edit');
            Route::put('/growth/rules/{rule}', 'updateRule')->name('growth.rules.update');
            Route::delete('/growth/rules/{rule}', 'destroyRule')->name('growth.rules.destroy');
            Route::patch('/growth/rules/{rule}/toggle', 'toggleRule')->name('growth.rules.toggle');

            Route::get('/growth/templates/create', 'createTemplate')->name('growth.templates.create');
            Route::post('/growth/templates', 'storeTemplate')->name('growth.templates.store');
            Route::get('/growth/templates/{template}/edit', 'editTemplate')->name('growth.templates.edit');
            Route::put('/growth/templates/{template}', 'updateTemplate')->name('growth.templates.update');
            Route::delete('/growth/templates/{template}', 'destroyTemplate')->name('growth.templates.destroy');

            Route::get('/growth/segments/create', 'createSegment')->name('growth.segments.create');
            Route::post('/growth/segments', 'storeSegment')->name('growth.segments.store');
            Route::get('/growth/segments/{segment}/edit', 'editSegment')->name('growth.segments.edit');
            Route::put('/growth/segments/{segment}', 'updateSegment')->name('growth.segments.update');
            Route::delete('/growth/segments/{segment}', 'destroySegment')->name('growth.segments.destroy');

            Route::get('/growth/experiments/create', 'createExperiment')->name('growth.experiments.create');
            Route::post('/growth/experiments', 'storeExperiment')->name('growth.experiments.store');
            Route::get('/growth/experiments/{experiment}/edit', 'editExperiment')->name('growth.experiments.edit');
            Route::put('/growth/experiments/{experiment}', 'updateExperiment')->name('growth.experiments.update');
            Route::delete('/growth/experiments/{experiment}', 'destroyExperiment')->name('growth.experiments.destroy');
            Route::patch('/growth/experiments/{experiment}/toggle', 'toggleExperiment')->name('growth.experiments.toggle');

            Route::patch('/growth/deliveries/{delivery}/retry', 'retryDelivery')->name('growth.deliveries.retry');
        });

        Route::middleware('permission:catalog.manage')->group(function () {
            Route::controller(CategoryController::class)->group(function () {
                Route::get('/categories', 'index')->name('categories.index');
                Route::get('/categories/create', 'create')->name('categories.create');
                Route::post('/categories', 'store')->name('categories.store');
                Route::get('/categories/{category}/edit', 'edit')->name('categories.edit');
                Route::put('/categories/{category}', 'update')->name('categories.update');
                Route::delete('/categories/{category}', 'destroy')->name('categories.destroy');
            });

            Route::get('/brands', BrandIndex::class)->name('brands.index');

            Route::controller(ProductController::class)->group(function () {
                Route::get('/products', 'index')->name('products.index');
                Route::get('/products/create', 'create')->name('products.create');
                Route::post('/products', 'store')->name('products.store');
                Route::get('/products/{product}/edit', 'edit')->name('products.edit');
                Route::put('/products/{product}', 'update')->name('products.update');
                Route::delete('/products/{product}', 'destroy')->name('products.destroy');
            });

            Route::get('/attributes', AttributeIndex::class)->name('attributes.index');
            Route::get('/attributes/{id}/values', AttributeValues::class)->name('attributes.values');
        });

        Route::middleware('permission:orders.view')->group(function () {
            Route::controller(AdminOrderController::class)->group(function () {
                Route::get('/orders', 'index')->name('orders.index');
                Route::get('/orders/{order}', 'show')->name('orders.show');
            });
        });
        Route::middleware('permission:orders.manage')->group(function () {
            Route::controller(AdminOrderController::class)->group(function () {
                Route::patch('/orders/{order}/status', 'updateStatus')->name('orders.update-status');
                Route::patch('/orders/{order}/quick-status', 'quickStatus')->name('orders.quick-status');
                Route::post('/orders/{order}/refund', 'refund')->name('orders.refund');
                Route::delete('/orders/{order}', 'destroy')->name('orders.destroy');
            });
        });

        Route::middleware('permission:customers.manage')->group(function () {
            Route::controller(AdminCustomerController::class)->group(function () {
                Route::get('/customers', 'index')->name('customers.index');
                Route::get('/customers/{user}', 'show')->name('customers.show');
                Route::patch('/customers/{user}/role', 'updateRole')->name('customers.update-role');
            });
        });

        Route::middleware('permission:inventory.manage')->group(function () {
            Route::controller(SupplierController::class)->group(function () {
                Route::get('/suppliers', 'index')->name('suppliers.index');
                Route::get('/suppliers/create', 'create')->name('suppliers.create');
                Route::post('/suppliers', 'store')->name('suppliers.store');
                Route::get('/suppliers/{supplier}/edit', 'edit')->name('suppliers.edit');
                Route::put('/suppliers/{supplier}', 'update')->name('suppliers.update');
                Route::delete('/suppliers/{supplier}', 'destroy')->name('suppliers.destroy');
            });

            Route::controller(PurchaseController::class)->group(function () {
                Route::get('/purchases', 'index')->name('purchases.index');
                Route::get('/purchases/create', 'create')->name('purchases.create');
                Route::post('/purchases', 'store')->name('purchases.store');
                Route::get('/purchases/{purchase}', 'show')->name('purchases.show');
                Route::post('/purchases/{purchase}/receive', 'receive')->name('purchases.receive');
            });

            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        });

        Route::middleware('permission:promotions.manage')->group(function () {
            Route::controller(PromotionController::class)->group(function () {
                Route::get('/promotions', 'index')->name('promotions.index');
                Route::get('/promotions/create', 'create')->name('promotions.create');
                Route::post('/promotions', 'store')->name('promotions.store');
                Route::get('/promotions/{promotion}/edit', 'edit')->name('promotions.edit');
                Route::put('/promotions/{promotion}', 'update')->name('promotions.update');
                Route::delete('/promotions/{promotion}', 'destroy')->name('promotions.destroy');
            });

            Route::controller(AdminCouponController::class)->group(function () {
                Route::get('/coupons', 'index')->name('coupons.index');
                Route::get('/coupons/create', 'create')->name('coupons.create');
                Route::post('/coupons', 'store')->name('coupons.store');
                Route::get('/coupons/{coupon}/edit', 'edit')->name('coupons.edit');
                Route::put('/coupons/{coupon}', 'update')->name('coupons.update');
                Route::delete('/coupons/{coupon}', 'destroy')->name('coupons.destroy');
            });
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/branding', [SettingController::class, 'edit'])->name('settings.branding');
            Route::put('/branding', [SettingController::class, 'update'])->name('settings.branding.update');
            Route::get('/content', [ContentSettingsController::class, 'edit'])->name('settings.content');
            Route::put('/content', [ContentSettingsController::class, 'update'])->name('settings.content.update');
            Route::get('/settings/whatsapp', [WhatsAppSettingsController::class, 'edit'])->name('settings.whatsapp');
            Route::get('/settings/notifications', [NotificationCenterController::class, 'edit'])->name('settings.notifications');
            Route::get('/settings/notifications/logs', [NotificationCenterController::class, 'logs'])->name('settings.notifications.logs');
            Route::get('/settings/notifications/templates', [NotificationCenterController::class, 'templates'])->name('settings.notifications.templates');
            Route::get('/settings/notifications/automation', [NotificationCenterController::class, 'automation'])->name('settings.notifications.automation');
            Route::get('/settings/notifications/diagnostics', [NotificationCenterController::class, 'diagnostics'])->name('settings.notifications.diagnostics');
            Route::put('/settings/notifications', [NotificationCenterController::class, 'update'])->name('settings.notifications.update');
            Route::post('/settings/notifications/templates', [NotificationCenterController::class, 'saveTemplate'])->name('settings.notifications.templates.save');
            Route::post('/settings/notifications/test-send', [NotificationCenterController::class, 'sendTest'])->name('settings.notifications.test-send');
            Route::post('/settings/notifications/automation-rules', [NotificationCenterController::class, 'saveAutomationRule'])->name('settings.notifications.automation-rules.save');
            Route::post('/settings/notifications/run-scanner', [NotificationCenterController::class, 'runScanner'])->name('settings.notifications.run-scanner');
            Route::post('/settings/notifications/queue/retry-failed-job', [NotificationCenterController::class, 'retryFailedQueueJob'])->name('settings.notifications.retry-failed-job');
            Route::post('/settings/notifications/logs/{log}/run-escalation', [NotificationCenterController::class, 'runEscalation'])->name('settings.notifications.run-escalation');
            Route::post('/settings/notifications/logs/{log}/retry', [NotificationCenterController::class, 'retryLog'])->name('settings.notifications.retry-log');
            Route::post('/settings/notifications/whatsapp-logs/{log}/retry', [NotificationCenterController::class, 'retryWhatsAppLog'])->name('settings.notifications.retry-whatsapp-log');
            Route::put('/settings/whatsapp', [WhatsAppSettingsController::class, 'update'])->name('settings.whatsapp.update');
            Route::post('/settings/whatsapp/logs/{log}/retry', [WhatsAppSettingsController::class, 'retry'])->name('settings.whatsapp.retry');
            Route::post('/settings/whatsapp/send-order-event', [WhatsAppSettingsController::class, 'sendOrderEvent'])->name('settings.whatsapp.send-order-event');
            Route::post('/settings/whatsapp/test-send', [WhatsAppSettingsController::class, 'testSend'])->name('settings.whatsapp.test-send');
            Route::post('/settings/whatsapp/orders/{order}/resend', [WhatsAppSettingsController::class, 'resendOrderEvent'])->name('settings.whatsapp.order-resend');
        });

        Route::middleware('permission:deploy.manage')->group(function () {
            Route::get('/settings/deploy-center', [DeployCenterController::class, 'index'])->name('settings.deploy-center');
            Route::post('/settings/deploy-center/deploy', [DeployCenterController::class, 'deploy'])->name('settings.deploy-center.deploy');
            Route::post('/settings/deploy-center/rollback', [DeployCenterController::class, 'rollback'])->name('settings.deploy-center.rollback');
        });


        Route::middleware('permission:imports.manage')->group(function () {
            Route::controller(ImportController::class)->group(function () {
                Route::get('/imports', 'index')->name('imports.index');
                Route::post('/imports', 'store')->name('imports.store');
            });
        });
  

        Route::middleware('permission:payments.view')->group(function () {
            Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        });
        Route::middleware('permission:payments.manage')->patch('/payments/{payment}/status', [PaymentController::class, 'updateStatus'])->name('payments.update-status');

        Route::middleware('permission:payments.settings')->group(function () {
            Route::get('/settings/payments', [PaymentSettingsController::class, 'edit'])->name('settings.payments');
            Route::put('/settings/payments', [PaymentSettingsController::class, 'update'])->name('settings.payments.update');
        });

        Route::middleware('permission:delivery.view')->get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::middleware('permission:delivery.manage')->patch('/deliveries/{order}', [DeliveryController::class, 'update'])->name('deliveries.update');

        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
            Route::patch('/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])->name('notifications.read-all');
            Route::patch('/notifications/{notification}/read', [AdminNotificationController::class, 'markRead'])->name('notifications.read');
        });

        Route::middleware('permission:permissions.manage')->group(function () {
            Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
            Route::post('/permissions/roles', [PermissionController::class, 'storeRole'])->name('permissions.roles.store');
            Route::patch('/permissions/roles/{role}', [PermissionController::class, 'updateRole'])->name('permissions.roles.update');
            Route::delete('/permissions/roles/{role}', [PermissionController::class, 'destroyRole'])->name('permissions.roles.destroy');
            Route::post('/permissions/custom', [PermissionController::class, 'storePermission'])->name('permissions.custom.store');
            Route::patch('/permissions/users/{user}/role', [PermissionController::class, 'updateUserRole'])->name('permissions.users.role');
        });
    });