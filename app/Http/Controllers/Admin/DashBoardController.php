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
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
            $currencyExpression = "UPPER(COALESCE(NULLIF(TRIM(currency), ''), 'EGP'))";
            $periodByCurrency = Order::query()
                ->whereBetween('created_at', [now()->subDays(29)->startOfDay(), now()])
                ->selectRaw($currencyExpression.' as statement_currency')
                ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(grand_total), 0) as order_value')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN payment_status IN (?, ?) THEN 1 ELSE 0 END), 0) as paid_orders',
                    [Order::PAYMENT_STATUS_PAID, Order::PAYMENT_STATUS_PARTIALLY_REFUNDED]
                )
                ->groupBy('statement_currency')
                ->orderBy('statement_currency')
                ->get();

            $ordersCount = (int) $periodByCurrency->sum(fn ($row) => (int) $row->orders_count);
            $paidOrders = (int) $periodByCurrency->sum(fn ($row) => (int) $row->paid_orders);
            $paidShare = $ordersCount > 0 ? ($paidOrders / $ordersCount) * 100 : 0;

            $grossOrderValue = $periodByCurrency->isEmpty()
                ? 'EGP 0.00'
                : $periodByCurrency
                    ->map(fn ($row) => $this->formatCurrencyMoney(
                        (string) $row->statement_currency,
                        (string) $row->order_value
                    ))
                    ->implode(' · ');

            $averageOrderValue = $periodByCurrency->isEmpty()
                ? 'EGP 0.00'
                : $periodByCurrency
                    ->map(function ($row): string {
                        $average = BigDecimal::of((string) $row->order_value)
                            ->dividedBy(
                                (string) max(1, (int) $row->orders_count),
                                2,
                                RoundingMode::HalfUp
                            );

                        return $this->formatCurrencyMoney(
                            (string) $row->statement_currency,
                            (string) $average
                        );
                    })
                    ->implode(' · ');

            $kpiCards = [
                ['label' => __('Gross order value'), 'value' => $grossOrderValue, 'copy' => __('Order value across all statuses in the last 30 days.'), 'icon' => 'mdi-cash-multiple'],
                ['label' => __('Orders'), 'value' => number_format($ordersCount), 'copy' => __('Orders created in the last 30 days.'), 'icon' => 'mdi-cart-outline'],
                ['label' => __('Paid order share'), 'value' => number_format($paidShare, 1) . '%', 'copy' => __('Paid orders as a share of recent orders.'), 'icon' => 'mdi-chart-line'],
                ['label' => __('Average order value'), 'value' => $averageOrderValue, 'copy' => __('Average basket size for the recent window.'), 'icon' => 'mdi-basket-outline'],
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

    private function formatCurrencyMoney(string $currency, mixed $value): string
    {
        $currency = strtoupper(trim($currency)) ?: 'EGP';
        $numeric = is_numeric($value) ? (string) $value : '0';
        $money = (string) BigDecimal::of($numeric)->toScale(2, RoundingMode::HalfUp);
        [$whole, $fraction] = array_pad(explode('.', $money, 2), 2, '00');

        $negative = str_starts_with($whole, '-');
        $digits = ltrim($whole, '-');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits) ?? $digits;

        return $currency.' '.($negative ? '-' : '').$grouped.'.'.$fraction;
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