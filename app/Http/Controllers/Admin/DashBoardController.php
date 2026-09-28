<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\InventoryAvailabilityService;
use Illuminate\Http\Request;

class DashBoardController extends Controller
{
    public function index(Request $request, InventoryAvailabilityService $inventoryAvailabilityService)
    {
        $q = mb_substr(trim((string) $request->string('q')), 0, 80);
        $like = $this->likePattern($q);
        $user = $request->user();
        $can = fn (string $permission): bool => $user && $user->hasPermission($permission);

        $searchResults = [
            'products' => collect(),
            'orders' => collect(),
            'customers' => collect(),
            'coupons' => collect(),
            'categories' => collect(),
        ];

        if ($q !== '' && $can('catalog.manage')) {
            $searchResults['products'] = Product::query()
                ->where(fn ($query) => $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('sku', 'like', $like))
                ->latest('id')
                ->take(5)
                ->get();

            $searchResults['categories'] = Category::query()
                ->where(fn ($query) => $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like))
                ->latest('id')
                ->take(5)
                ->get();
        }

        if ($q !== '' && $can('orders.view')) {
            $searchResults['orders'] = Order::query()
                ->where(fn ($query) => $query->where('order_number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_email', 'like', $like))
                ->latest('id')
                ->take(5)
                ->get();
        }

        if ($q !== '' && $can('customers.manage')) {
            $searchResults['customers'] = User::query()
                ->where('role_as', 0)
                ->where(fn ($query) => $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like))
                ->latest('id')
                ->take(5)
                ->get();
        }

        if ($q !== '' && $can('promotions.manage')) {
            $searchResults['coupons'] = Coupon::query()
                ->where(fn ($query) => $query->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like))
                ->latest('id')
                ->take(5)
                ->get();
        }

        if ($request->header('X-Live-Dashboard-Search') === '1') {
            return response()->view('admin.dashboard._search-results', compact('q', 'searchResults', 'can'));
        }

        $kpiCards = [];

        if ($can('orders.view')) {
            $period = Order::query()
                ->whereBetween('created_at', [now()->subDays(29)->startOfDay(), now()])
                ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(grand_total), 0) as order_value')
                ->selectRaw('COALESCE(SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END), 0) as paid_orders', [Order::PAYMENT_STATUS_PAID])
                ->first();

            $ordersCount = (int) $period->orders_count;
            $orderValue = (float) $period->order_value;
            $paidShare = $ordersCount > 0 ? ((int) $period->paid_orders / $ordersCount) * 100 : 0;

            $kpiCards = [
                ['label' => __('Gross order value'), 'value' => 'EGP ' . number_format($orderValue, 2), 'copy' => __('Order value across all statuses in the last 30 days.'), 'icon' => 'mdi-cash-multiple'],
                ['label' => __('Orders'), 'value' => number_format($ordersCount), 'copy' => __('Orders created in the last 30 days.'), 'icon' => 'mdi-cart-outline'],
                ['label' => __('Paid order share'), 'value' => number_format($paidShare, 1) . '%', 'copy' => __('Paid orders as a share of recent orders.'), 'icon' => 'mdi-chart-line'],
                ['label' => __('Average order value'), 'value' => 'EGP ' . number_format($ordersCount > 0 ? $orderValue / $ordersCount : 0, 2), 'copy' => __('Average basket size for the recent window.'), 'icon' => 'mdi-basket-outline'],
            ];
        }

        $stats = [
            'orders_pending' => $can('orders.view') ? Order::where('status', Order::STATUS_PENDING)->count() : 0,
            'products_low_stock' => $can('catalog.manage')
                ? $inventoryAvailabilityService->lowStockItemCount()
                : 0,
        ];

        $failedPaymentsCount = $can('payments.view')
            ? Payment::where('status', Payment::STATUS_FAILED)->count()
            : 0;

        $recentOrders = $can('orders.view')
            ? Order::latest('id')->take(6)->get()
            : collect();

        $lowStockItems = $can('catalog.manage')
            ? $inventoryAvailabilityService->lowStockItems(6)
            : collect();

        $hasPriorities = $can('orders.view') || $can('catalog.manage') || $can('payments.view');
        $hasRecentActivity = $can('orders.view') || $can('catalog.manage');
        $hasWorkspaces = $can('orders.view')
            || $can('catalog.manage')
            || $can('growth.view')
            || $can('settings.manage')
            || $can('notifications.view');

        return view('admin.dashboard', compact(
            'q',
            'kpiCards',
            'stats',
            'failedPaymentsCount',
            'recentOrders',
            'lowStockItems',
            'searchResults',
            'hasPriorities',
            'hasRecentActivity',
            'hasWorkspaces',
        ));
    }

    protected function likePattern(string $value): string
    {
        return '%' . str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        ) . '%';
    }

}