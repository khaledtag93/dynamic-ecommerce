<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class DashBoardController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->string('q'));
        $user = $request->user();
        $can = fn (string $permission): bool => $user && $user->hasPermission($permission);

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

        $stats = [
            'orders_pending' => $can('orders.view') ? Order::where('status', Order::STATUS_PENDING)->count() : 0,
            'products_low_stock' => $can('catalog.manage') ? Product::query()
                ->where('has_variants', false)
                ->where(function ($query) {
                    $query->whereColumn('quantity', '<=', 'low_stock_threshold')
                        ->orWhere('quantity', '<=', 0);
                })
                ->count() : 0,
        ];
        $failedPaymentsCount = $can('payments.view') ? Order::where('payment_status', Order::PAYMENT_STATUS_FAILED)->count() : 0;
        $recentOrders = $can('orders.view') ? Order::latest('id')->take(6)->get() : collect();
        $lowStockProducts = $can('catalog.manage') ? Product::orderBy('quantity')->take(6)->get() : collect();

        $searchResults = [
            'products' => collect(),
            'orders' => collect(),
            'customers' => collect(),
            'coupons' => collect(),
            'categories' => collect(),
        ];

        if ($q !== '' && $can('catalog.manage')) {
            $searchResults['products'] = Product::query()
                ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%"))
                ->latest('id')->take(5)->get();
            $searchResults['categories'] = Category::query()
                ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%"))
                ->latest('id')->take(5)->get();
        }
        if ($q !== '' && $can('orders.view')) {
            $searchResults['orders'] = Order::query()
                ->where(fn ($query) => $query->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_email', 'like', "%{$q}%"))
                ->latest('id')->take(5)->get();
        }
        if ($q !== '' && $can('customers.manage')) {
            $searchResults['customers'] = User::query()
                ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"))
                ->latest('id')->take(5)->get();
        }
        if ($q !== '' && $can('promotions.manage')) {
            $searchResults['coupons'] = Coupon::query()
                ->where(fn ($query) => $query->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%"))
                ->latest('id')->take(5)->get();
        }

        return view('admin.dashboard', compact('q', 'kpiCards', 'stats', 'failedPaymentsCount', 'recentOrders', 'lowStockProducts', 'searchResults'));
    }
}
