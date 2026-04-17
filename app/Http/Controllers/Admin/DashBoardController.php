<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashBoardController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->string('q'));

        $stats = [
            'orders_total' => Order::count(),
            'orders_pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'revenue_gross' => (float) Order::sum('grand_total'),
            'refund_total' => (float) OrderRefund::sum('amount'),
            'revenue_net' => (float) Order::sum('grand_total') - (float) OrderRefund::sum('amount'),
            'products_total' => Product::count(),
            'products_low_stock' => Product::query()
                ->where(function ($query) {
                    $query->where('has_variants', false)
                        ->whereColumn('quantity', '<=', 'low_stock_threshold');
                })
                ->orWhere(function ($query) {
                    $query->where('has_variants', false)
                        ->where('quantity', '<=', 0);
                })
                ->count(),
            'customers_total' => User::where('role_as', 0)->count(),
            'active_coupons' => Coupon::where('is_active', true)->count(),
            'suppliers_total' => class_exists(Supplier::class) ? Supplier::count() : 0,
            'purchase_total' => class_exists(Purchase::class) ? (float) Purchase::sum('grand_total') : 0,
            'gross_profit' => (float) Order::sum('profit_total'),
            'products_near_expiry' => Product::whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', now()->addDays(30))
                ->count(),
        ];

        $periodEnd = now();
        $periodStart = now()->subDays(29)->startOfDay();
        $previousPeriodEnd = (clone $periodStart)->subSecond();
        $previousPeriodStart = (clone $periodStart)->subDays(30);

        $currentOrders = $this->ordersBetween($periodStart, $periodEnd);
        $previousOrders = $this->ordersBetween($previousPeriodStart, $previousPeriodEnd);

        $currentRevenue = (float) $currentOrders->sum('grand_total');
        $previousRevenue = (float) $previousOrders->sum('grand_total');
        $currentOrdersCount = (int) $currentOrders->count();
        $previousOrdersCount = (int) $previousOrders->count();
        $currentAov = $currentOrdersCount > 0 ? $currentRevenue / $currentOrdersCount : 0.0;
        $previousAov = $previousOrdersCount > 0 ? $previousRevenue / $previousOrdersCount : 0.0;

        $currentNewCustomers = User::query()
            ->where('role_as', 0)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();
        $previousNewCustomers = User::query()
            ->where('role_as', 0)
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->count();

        $currentReturningCustomers = (int) $currentOrders->pluck('customer_email')->filter()->duplicates()->count();
        $previousReturningCustomers = (int) $previousOrders->pluck('customer_email')->filter()->duplicates()->count();

        $currentPaidOrders = (int) $currentOrders->where('payment_status', Order::PAYMENT_STATUS_PAID)->count();
        $previousPaidOrders = (int) $previousOrders->where('payment_status', Order::PAYMENT_STATUS_PAID)->count();
        $currentConversion = $currentOrdersCount > 0 ? ($currentPaidOrders / $currentOrdersCount) * 100 : 0.0;
        $previousConversion = $previousOrdersCount > 0 ? ($previousPaidOrders / $previousOrdersCount) * 100 : 0.0;

        $kpiCards = [
            [
                'label' => __('Revenue'),
                'value' => 'EGP ' . number_format($currentRevenue, 2),
                'delta' => $this->formatDelta($currentRevenue, $previousRevenue, true),
                'copy' => __('Gross order value in the last 30 days.'),
                'icon' => 'mdi-cash-multiple',
                'tone' => $this->toneFromDelta($currentRevenue, $previousRevenue),
            ],
            [
                'label' => __('Orders'),
                'value' => number_format($currentOrdersCount),
                'delta' => $this->formatDelta($currentOrdersCount, $previousOrdersCount),
                'copy' => __('Orders created in the last 30 days.'),
                'icon' => 'mdi-cart-outline',
                'tone' => $this->toneFromDelta($currentOrdersCount, $previousOrdersCount),
            ],
            [
                'label' => __('Conversion quality'),
                'value' => number_format($currentConversion, 1) . '%',
                'delta' => $this->formatDelta($currentConversion, $previousConversion),
                'copy' => __('Paid orders as a share of recent orders.'),
                'icon' => 'mdi-chart-line',
                'tone' => $this->toneFromDelta($currentConversion, $previousConversion),
            ],
            [
                'label' => __('Average order value'),
                'value' => 'EGP ' . number_format($currentAov, 2),
                'delta' => $this->formatDelta($currentAov, $previousAov, true),
                'copy' => __('Average basket size for the recent window.'),
                'icon' => 'mdi-basket-outline',
                'tone' => $this->toneFromDelta($currentAov, $previousAov),
            ],
            [
                'label' => __('New customers'),
                'value' => number_format($currentNewCustomers),
                'delta' => $this->formatDelta($currentNewCustomers, $previousNewCustomers),
                'copy' => __('Fresh customer accounts created in the last 30 days.'),
                'icon' => 'mdi-account-plus-outline',
                'tone' => $this->toneFromDelta($currentNewCustomers, $previousNewCustomers),
            ],
            [
                'label' => __('Repeat customer signals'),
                'value' => number_format($currentReturningCustomers),
                'delta' => $this->formatDelta($currentReturningCustomers, $previousReturningCustomers),
                'copy' => __('Customers with more than one recent order signal repeat demand.'),
                'icon' => 'mdi-account-heart-outline',
                'tone' => $this->toneFromDelta($currentReturningCustomers, $previousReturningCustomers),
            ],
        ];

        $failedPaymentsCount = Order::where('payment_status', Order::PAYMENT_STATUS_FAILED)->count();
        $cancelledOrdersCount = Order::where('status', Order::STATUS_CANCELLED)->count();
        $completedOrdersCount = Order::where('status', Order::STATUS_COMPLETED)->count();
        $processingOrdersCount = Order::where('status', Order::STATUS_PROCESSING)->count();

        $alerts = collect();
        if ($stats['orders_pending'] > 0) {
            $alerts->push([
                'tone' => 'warning',
                'icon' => 'mdi-timer-sand',
                'title' => __('Pending orders need follow-up'),
                'description' => __(':count orders are still pending and may need faster review.', ['count' => number_format($stats['orders_pending'])]),
            ]);
        }
        if ($stats['products_low_stock'] > 0) {
            $alerts->push([
                'tone' => 'danger',
                'icon' => 'mdi-package-variant-remove',
                'title' => __('Low-stock pressure is visible'),
                'description' => __(':count products need replenishment attention.', ['count' => number_format($stats['products_low_stock'])]),
            ]);
        }
        if ($failedPaymentsCount > 0) {
            $alerts->push([
                'tone' => 'danger',
                'icon' => 'mdi-credit-card-off-outline',
                'title' => __('Failed payments are waiting'),
                'description' => __(':count failed payment records may need manual recovery or customer follow-up.', ['count' => number_format($failedPaymentsCount)]),
            ]);
        }
        if ($alerts->isEmpty()) {
            $alerts->push([
                'tone' => 'success',
                'icon' => 'mdi-check-decagram-outline',
                'title' => __('No critical blockers right now'),
                'description' => __('Operations look steady across orders, stock, and payment flow at the moment.'),
            ]);
        }

        $opportunities = collect();
        if ($this->deltaRate($currentRevenue, $previousRevenue) > 0.08) {
            $opportunities->push([
                'icon' => 'mdi-trending-up',
                'title' => __('Revenue momentum is improving'),
                'description' => __('Recent revenue is ahead of the previous 30-day window, which gives room to push more winning products or campaigns.'),
            ]);
        }
        if ($this->deltaRate($currentNewCustomers, $previousNewCustomers) > 0.05) {
            $opportunities->push([
                'icon' => 'mdi-account-group-outline',
                'title' => __('Customer acquisition is picking up'),
                'description' => __('New customer creation is stronger than the previous comparable window.'),
            ]);
        }
        if ($completedOrdersCount > $cancelledOrdersCount && $completedOrdersCount > 0) {
            $opportunities->push([
                'icon' => 'mdi-check-circle-outline',
                'title' => __('Fulfillment quality is healthier than cancellations'),
                'description' => __('Completed orders currently outweigh cancelled orders, which supports stronger customer confidence.'),
            ]);
        }
        if ($opportunities->isEmpty()) {
            $opportunities->push([
                'icon' => 'mdi-lightbulb-on-outline',
                'title' => __('Look for the next growth lever'),
                'description' => __('Use analytics and growth pages to identify categories, offers, or channels that deserve extra promotion.'),
            ]);
        }

        $problems = collect();
        if ($this->deltaRate($currentConversion, $previousConversion) < -0.05) {
            $problems->push([
                'icon' => 'mdi-chart-bell-curve-cumulative',
                'title' => __('Conversion quality softened'),
                'description' => __('The paid-order share is behind the previous 30-day window and may need checkout, payment, or offer tuning.'),
            ]);
        }
        if ($stats['orders_pending'] > max(5, $processingOrdersCount)) {
            $problems->push([
                'icon' => 'mdi-alert-circle-outline',
                'title' => __('Backlog is building inside order handling'),
                'description' => __('Pending orders are stacking faster than healthy processing flow.'),
            ]);
        }
        if ($stats['products_low_stock'] > 0) {
            $problems->push([
                'icon' => 'mdi-archive-alert-outline',
                'title' => __('Stock risk can block sales'),
                'description' => __('Low-stock items should be replenished before they affect conversion or delivery promises.'),
            ]);
        }
        if ($problems->isEmpty()) {
            $problems->push([
                'icon' => 'mdi-shield-check-outline',
                'title' => __('No major risk pattern detected'),
                'description' => __('The dashboard does not show a sharp issue right now, but keep an eye on payments, stock, and pending orders.'),
            ]);
        }

        $topProducts = OrderItem::query()
            ->select('product_id', 'product_name')
            ->selectRaw('SUM(quantity) as units_sold')
            ->selectRaw('SUM(line_total) as revenue_total')
            ->whereHas('order', fn (Builder $query) => $query->whereBetween('created_at', [$periodStart, $periodEnd]))
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('units_sold')
            ->take(5)
            ->get();

        $decisionSummary = $this->buildDecisionSummary(
            currentRevenue: $currentRevenue,
            previousRevenue: $previousRevenue,
            currentOrders: $currentOrdersCount,
            previousOrders: $previousOrdersCount,
            currentConversion: $currentConversion,
            previousConversion: $previousConversion,
            pendingOrders: (int) $stats['orders_pending'],
            lowStock: (int) $stats['products_low_stock']
        );

        $recentOrders = Order::latest('id')->take(6)->get();

        $monthlyRevenue = Order::query()
            ->selectRaw("DATE_FORMAT(created_at, '%b') as month_label")
            ->selectRaw('SUM(grand_total) as total')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"), DB::raw("DATE_FORMAT(created_at, '%b')"))
            ->orderBy(DB::raw('MIN(created_at)'))
            ->get();

        $statusBreakdown = [
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'processing' => Order::where('status', Order::STATUS_PROCESSING)->count(),
            'completed' => Order::where('status', Order::STATUS_COMPLETED)->count(),
            'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
        ];

        $recentCustomers = User::latest('id')->take(6)->get();

        $lowStockProducts = Product::with(['category', 'brand'])
            ->orderBy('quantity')
            ->take(6)
            ->get();


        $notificationFailedCount = 0;
        if (Schema::hasTable('notification_dispatch_logs')) {
            $notificationFailedCount = (int) DB::table('notification_dispatch_logs')
                ->where('status', 'failed')
                ->count();
        }

        $issueSignals = collect();
        if ($stats['orders_pending'] > max(5, $processingOrdersCount)) {
            $issueSignals->push([
                'score' => 95,
                'tone' => 'danger',
                'icon' => 'mdi-timer-sand',
                'title' => __('Pending orders are the biggest operational risk right now'),
                'description' => __('The pending queue is heavier than the current processing flow and can slow fulfillment speed.'),
                'metric' => __(':count pending orders', ['count' => number_format($stats['orders_pending'])]),
                'action_label' => __('Review pending orders'),
                'action_route' => route('admin.orders.index'),
            ]);
        }
        if ($failedPaymentsCount > 0) {
            $issueSignals->push([
                'score' => 88,
                'tone' => 'warning',
                'icon' => 'mdi-credit-card-off-outline',
                'title' => __('Payment recovery needs attention'),
                'description' => __('Failed payment records can turn into lost revenue unless the team follows up quickly.'),
                'metric' => __(':count failed payments', ['count' => number_format($failedPaymentsCount)]),
                'action_label' => __('Open orders'),
                'action_route' => route('admin.orders.index'),
            ]);
        }
        if ($stats['products_low_stock'] > 0) {
            $issueSignals->push([
                'score' => 82,
                'tone' => 'warning',
                'icon' => 'mdi-package-variant-remove',
                'title' => __('Stock gaps can block near-term sales'),
                'description' => __('Low-stock products are close to hurting conversion and customer confidence.'),
                'metric' => __(':count low-stock products', ['count' => number_format($stats['products_low_stock'])]),
                'action_label' => __('Open products'),
                'action_route' => route('admin.products.index'),
            ]);
        }
        if ($notificationFailedCount > 0) {
            $issueSignals->push([
                'score' => 76,
                'tone' => 'info',
                'icon' => 'mdi-bell-alert-outline',
                'title' => __('Notification failures need cleanup'),
                'description' => __('Failed notification deliveries can hide important operational follow-up from the team.'),
                'metric' => __(':count failed notification logs', ['count' => number_format($notificationFailedCount)]),
                'action_label' => __('Open logs & retry'),
                'action_route' => route('admin.settings.notifications.logs'),
            ]);
        }
        if ($this->deltaRate($currentConversion, $previousConversion) < -0.05) {
            $issueSignals->push([
                'score' => 84,
                'tone' => 'danger',
                'icon' => 'mdi-chart-bell-curve-cumulative',
                'title' => __('Checkout quality slipped versus the previous window'),
                'description' => __('The paid-order share is softer than before, so checkout flow and payment follow-up deserve a closer look.'),
                'metric' => __(':value paid conversion', ['value' => number_format($currentConversion, 1) . '%']),
                'action_label' => __('Open revenue insights'),
                'action_route' => route('admin.analytics.index'),
            ]);
        }
        if ($issueSignals->isEmpty()) {
            $issueSignals->push([
                'score' => 40,
                'tone' => 'success',
                'icon' => 'mdi-shield-check-outline',
                'title' => __('No major operating risk is dominating the dashboard'),
                'description' => __('The main commercial and operational signals look stable right now.'),
                'metric' => __('Stable operating picture'),
                'action_label' => __('Open revenue insights'),
                'action_route' => route('admin.analytics.index'),
            ]);
        }
        $issueSignals = $issueSignals->sortByDesc('score')->values();

        $opportunitySignals = collect();
        if ($this->deltaRate($currentRevenue, $previousRevenue) > 0.08) {
            $opportunitySignals->push([
                'score' => 92,
                'tone' => 'success',
                'icon' => 'mdi-trending-up',
                'title' => __('Revenue momentum is strong enough to scale'),
                'description' => __('Recent revenue is ahead of the prior 30-day window, which creates room to push winning products harder.'),
                'metric' => __(':value revenue change', ['value' => $this->formatDelta($currentRevenue, $previousRevenue, true)['text']]),
                'action_label' => __('Open growth engine'),
                'action_route' => route('admin.growth.index'),
            ]);
        }
        if ($this->deltaRate($currentNewCustomers, $previousNewCustomers) > 0.05) {
            $opportunitySignals->push([
                'score' => 86,
                'tone' => 'info',
                'icon' => 'mdi-account-group-outline',
                'title' => __('Customer acquisition is opening room for repeat growth'),
                'description' => __('New customer creation is stronger than the previous comparable window.'),
                'metric' => __(':count new customers in the last 30 days', ['count' => number_format($currentNewCustomers)]),
                'action_label' => __('Open growth engine'),
                'action_route' => route('admin.growth.index'),
            ]);
        }
        if ($completedOrdersCount > $cancelledOrdersCount && $completedOrdersCount > 0) {
            $opportunitySignals->push([
                'score' => 78,
                'tone' => 'success',
                'icon' => 'mdi-check-circle-outline',
                'title' => __('Fulfillment quality is supporting retention'),
                'description' => __('Completed orders clearly outweigh cancelled orders, which strengthens trust and repeat intent.'),
                'metric' => __(':count completed orders', ['count' => number_format($completedOrdersCount)]),
                'action_label' => __('Open orders'),
                'action_route' => route('admin.orders.index'),
            ]);
        }
        if ($opportunitySignals->isEmpty()) {
            $opportunitySignals->push([
                'score' => 50,
                'tone' => 'neutral',
                'icon' => 'mdi-lightbulb-on-outline',
                'title' => __('The next growth lever still needs to be chosen'),
                'description' => __('The dashboard is steady, but the best expansion move is not obvious yet.'),
                'metric' => __('Explore growth and analytics next'),
                'action_label' => __('Open growth engine'),
                'action_route' => route('admin.growth.index'),
            ]);
        }
        $opportunitySignals = $opportunitySignals->sortByDesc('score')->values();

        $firstAction = $issueSignals->first();
        if (($firstAction['tone'] ?? '') === 'success') {
            $firstAction = $opportunitySignals->first();
        }

        $smartBoard = [
            'problem' => $issueSignals->first(),
            'opportunity' => $opportunitySignals->first(),
            'first_action' => $firstAction,
            'priority_queue' => $issueSignals->take(3),
            'opportunity_queue' => $opportunitySignals->take(3),
        ];

        $dailyWindowStart = now()->subDays(13)->startOfDay();
        $dailyOrders = Order::query()
            ->where('created_at', '>=', $dailyWindowStart)
            ->get(['created_at', 'grand_total', 'payment_status']);

        $dailyRows = collect(range(0, 13))->map(function (int $offset) use ($dailyWindowStart, $dailyOrders) {
            $day = (clone $dailyWindowStart)->addDays($offset);
            $orders = $dailyOrders->filter(fn ($order) => optional($order->created_at)->isSameDay($day));

            return [
                'date' => $day,
                'label' => $day->format('M j'),
                'revenue' => (float) $orders->sum('grand_total'),
                'orders' => (int) $orders->count(),
                'paid_orders' => (int) $orders->where('payment_status', Order::PAYMENT_STATUS_PAID)->count(),
            ];
        });

        $maxDailyRevenue = (float) max(1, (float) $dailyRows->max('revenue'));
        $maxDailyOrders = (int) max(1, (int) $dailyRows->max('orders'));

        $dailyDepth = $dailyRows->map(function (array $row) use ($maxDailyRevenue, $maxDailyOrders) {
            $conversion = $row['orders'] > 0 ? ($row['paid_orders'] / $row['orders']) * 100 : 0;

            return [
                'label' => $row['label'],
                'revenue' => $row['revenue'],
                'orders' => $row['orders'],
                'conversion' => round($conversion),
                'revenue_width' => round(($row['revenue'] / $maxDailyRevenue) * 100),
                'orders_width' => round(($row['orders'] / $maxDailyOrders) * 100),
            ];
        });

        $todayOrders = $dailyRows->last();
        $yesterdayOrders = $dailyRows->slice(-2, 1)->first() ?? ['revenue' => 0, 'orders' => 0, 'paid_orders' => 0];
        $todayRevenue = (float) ($todayOrders['revenue'] ?? 0);
        $todayOrdersCount = (int) ($todayOrders['orders'] ?? 0);
        $todayPaidCount = (int) ($todayOrders['paid_orders'] ?? 0);
        $todayPaidShare = $todayOrdersCount > 0 ? ($todayPaidCount / $todayOrdersCount) * 100 : 0;
        $todayAov = $todayOrdersCount > 0 ? $todayRevenue / $todayOrdersCount : 0;
        $yesterdayRevenue = (float) ($yesterdayOrders['revenue'] ?? 0);
        $yesterdayOrdersCount = (int) ($yesterdayOrders['orders'] ?? 0);

        $depthHighlights = [
            [
                'label' => __('Today revenue'),
                'value' => 'EGP ' . number_format($todayRevenue, 0),
                'meta' => $this->formatDelta($todayRevenue, $yesterdayRevenue, true)['text'],
                'icon' => 'mdi-cash-fast',
                'tone' => $this->toneFromDelta($todayRevenue, $yesterdayRevenue),
            ],
            [
                'label' => __('Today orders'),
                'value' => number_format($todayOrdersCount),
                'meta' => $this->formatDelta($todayOrdersCount, $yesterdayOrdersCount)['text'],
                'icon' => 'mdi-cart-arrow-down',
                'tone' => $this->toneFromDelta($todayOrdersCount, $yesterdayOrdersCount),
            ],
            [
                'label' => __('Paid share today'),
                'value' => number_format($todayPaidShare, 0) . '%',
                'meta' => __(':count paid orders today', ['count' => number_format($todayPaidCount)]),
                'icon' => 'mdi-credit-card-check-outline',
                'tone' => $todayPaidShare >= 60 ? 'success' : ($todayPaidShare >= 30 ? 'warning' : 'danger'),
            ],
            [
                'label' => __('Average order today'),
                'value' => 'EGP ' . number_format($todayAov, 0),
                'meta' => __("Based on today's order mix"),
                'icon' => 'mdi-basket-fill',
                'tone' => 'info',
            ],
        ];

        $paymentBreakdown = [
            'paid' => Order::where('payment_status', Order::PAYMENT_STATUS_PAID)->count(),
            'pending' => Order::where('payment_status', Order::PAYMENT_STATUS_PENDING)->count(),
            'failed' => Order::where('payment_status', Order::PAYMENT_STATUS_FAILED)->count(),
            'refunded' => Order::where('payment_status', Order::PAYMENT_STATUS_REFUNDED)->count(),
        ];
        $paymentTotal = max(1, array_sum($paymentBreakdown));
        $paymentShare = collect($paymentBreakdown)
            ->map(fn ($count) => (int) round(($count / $paymentTotal) * 100))
            ->all();

        $bestDay = $dailyRows->sortByDesc('revenue')->first();
        $weakestDay = $dailyRows->sortBy('revenue')->first();
        $paymentHealth = ($paymentShare['paid'] ?? 0) - (($paymentShare['failed'] ?? 0) + ($paymentShare['refunded'] ?? 0));

        $analyticsSignals = [
            [
                'title' => __('Best day'),
                'value' => ($bestDay['label'] ?? __('N/A')),
                'description' => __('Peak daily revenue reached EGP :value.', ['value' => number_format((float) ($bestDay['revenue'] ?? 0), 0)]),
                'tone' => 'success',
            ],
            [
                'title' => __('Weakest day'),
                'value' => ($weakestDay['label'] ?? __('N/A')),
                'description' => __('Lowest daily revenue landed at EGP :value.', ['value' => number_format((float) ($weakestDay['revenue'] ?? 0), 0)]),
                'tone' => 'warning',
            ],
            [
                'title' => __('Payment health'),
                'value' => ($paymentShare['paid'] ?? 0) . '% ' . __('paid share'),
                'description' => $paymentHealth >= 40
                    ? __('Payment outcomes are supporting conversion well right now.')
                    : __('Payment outcomes need closer follow-up to protect revenue.'),
                'tone' => $paymentHealth >= 40 ? 'success' : 'danger',
            ],
        ];

        $searchResults = [
            'products' => collect(),
            'orders' => collect(),
            'customers' => collect(),
            'coupons' => collect(),
            'categories' => collect(),
        ];

        if ($q !== '') {
            $searchResults['products'] = Product::query()
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                })
                ->latest('id')
                ->take(5)
                ->get();

            $searchResults['orders'] = Order::query()
                ->where(function ($query) use ($q) {
                    $query->where('order_number', 'like', "%{$q}%")
                        ->orWhere('customer_name', 'like', "%{$q}%")
                        ->orWhere('customer_email', 'like', "%{$q}%");
                })
                ->latest('id')
                ->take(5)
                ->get();

            $searchResults['customers'] = User::query()
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                })
                ->latest('id')
                ->take(5)
                ->get();

            $searchResults['coupons'] = Coupon::query()
                ->where(function ($query) use ($q) {
                    $query->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                })
                ->latest('id')
                ->take(5)
                ->get();

            $searchResults['categories'] = Category::query()
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%");
                })
                ->latest('id')
                ->take(5)
                ->get();
        }

        $quickActions = [
            [
                'label' => __('Review pending orders'),
                'description' => __('Clear the current order queue and move new sales faster.'),
                'route' => route('admin.orders.index'),
                'icon' => 'mdi-cart-outline',
                'permission' => 'orders.view',
            ],
            [
                'label' => __('Open revenue insights'),
                'description' => __('Jump into analytics for deeper trends and product signals.'),
                'route' => route('admin.analytics.index'),
                'icon' => 'mdi-chart-areaspline',
                'permission' => 'dashboard.view',
            ],
            [
                'label' => __('Launch growth engine'),
                'description' => __('Review campaigns, rules, and experiments from one place.'),
                'route' => route('admin.growth.index'),
                'icon' => 'mdi-rocket-launch-outline',
                'permission' => 'dashboard.view',
            ],
            [
                'label' => __('Refill stock watchlist'),
                'description' => __('Check low-stock products before they hurt conversion.'),
                'route' => route('admin.products.index'),
                'icon' => 'mdi-package-variant-closed',
                'permission' => 'catalog.manage',
            ],
        ];

        $commandCenterCards = [
            [
                'label' => __('Operate'),
                'title' => __('Order and payment pressure'),
                'value' => number_format($stats['orders_pending']),
                'meta' => __(':count pending · :failed payment issues', [
                    'count' => number_format($stats['orders_pending']),
                    'failed' => number_format($failedPaymentsCount),
                ]),
                'description' => __('Use this lane when fulfillment speed or payment recovery needs the fastest response.'),
                'icon' => 'mdi-clipboard-check-outline',
                'tone' => $stats['orders_pending'] > 0 || $failedPaymentsCount > 0 ? 'warning' : 'success',
                'route' => route('admin.orders.index'),
                'cta' => __('Open operations'),
            ],
            [
                'label' => __('Monitor'),
                'title' => __('Notifications and reliability'),
                'value' => number_format($notificationFailedCount),
                'meta' => __(':count failed logs to review', ['count' => number_format($notificationFailedCount)]),
                'description' => __('Stay on top of notification failures, retries, and observability from one place.'),
                'icon' => 'mdi-bell-alert-outline',
                'tone' => $notificationFailedCount > 0 ? 'danger' : 'success',
                'route' => route('admin.settings.notifications'),
                'cta' => __('Open reliability center'),
            ],
            [
                'label' => __('Grow'),
                'title' => __('Revenue and campaign momentum'),
                'value' => number_format($currentConversion, 1) . '%',
                'meta' => __('Paid conversion in the last 30 days'),
                'description' => __('Jump into analytics and growth tooling when the operating picture is stable enough to scale.'),
                'icon' => 'mdi-rocket-launch-outline',
                'tone' => $currentConversion >= 60 ? 'success' : ($currentConversion >= 35 ? 'info' : 'warning'),
                'route' => route('admin.growth.index'),
                'cta' => __('Open growth workspace'),
            ],
            [
                'label' => __('Stock'),
                'title' => __('Catalog and replenishment'),
                'value' => number_format($stats['products_low_stock']),
                'meta' => __(':count low-stock products', ['count' => number_format($stats['products_low_stock'])]),
                'description' => __('Use the catalog lane when supply gaps or product cleanup could block fresh sales.'),
                'icon' => 'mdi-package-variant-closed',
                'tone' => $stats['products_low_stock'] > 0 ? 'warning' : 'success',
                'route' => route('admin.products.index'),
                'cta' => __('Open catalog'),
            ],
        ];

        $workspaceLanes = [
            [
                'title' => __('Daily operations'),
                'description' => __('Orders, payments, deliveries, and fast operational follow-up.'),
                'items' => [
                    ['label' => __('Orders'), 'value' => number_format($currentOrdersCount), 'route' => route('admin.orders.index')],
                    ['label' => __('Pending'), 'value' => number_format($stats['orders_pending']), 'route' => route('admin.orders.index')],
                    ['label' => __('Payments'), 'value' => number_format(array_sum($paymentBreakdown)), 'route' => route('admin.payments.index')],
                ],
                'tone' => $stats['orders_pending'] > 0 || $failedPaymentsCount > 0 ? 'warning' : 'success',
            ],
            [
                'title' => __('Commercial performance'),
                'description' => __('Revenue, AOV, conversion, and commercial trend reading.'),
                'items' => [
                    ['label' => __('Revenue'), 'value' => 'EGP ' . number_format($currentRevenue, 0), 'route' => route('admin.analytics.index')],
                    ['label' => __('AOV'), 'value' => 'EGP ' . number_format($currentAov, 0), 'route' => route('admin.analytics.index')],
                    ['label' => __('Growth'), 'value' => number_format($currentNewCustomers), 'route' => route('admin.growth.index')],
                ],
                'tone' => $this->deltaRate($currentRevenue, $previousRevenue) >= 0 ? 'success' : 'warning',
            ],
            [
                'title' => __('Reliability and setup'),
                'description' => __('Notifications, deploy control, payment methods, and system follow-up.'),
                'items' => [
                    ['label' => __('Notification failures'), 'value' => number_format($notificationFailedCount), 'route' => route('admin.settings.notifications')],
                    ['label' => __('Coupons'), 'value' => number_format($stats['active_coupons']), 'route' => route('admin.coupons.index')],
                    ['label' => __('Customers'), 'value' => number_format($stats['customers_total']), 'route' => route('admin.customers.index')],
                ],
                'tone' => $notificationFailedCount > 0 ? 'danger' : 'info',
            ],
        ];

        $adminUser = auth()->user();
        $can = fn (string $permission): bool => $adminUser && method_exists($adminUser, 'hasPermission') && $adminUser->hasPermission($permission);
        $navigationSections = array_values(array_filter([
            $can('orders.view') ? [
                'label' => __('Operations'),
                'description' => __('Handle order pressure, payment follow-up, and delivery handoff first.'),
                'value' => number_format($stats['orders_pending']),
                'meta' => __('Pending orders'),
                'route' => route('admin.orders.index'),
                'icon' => 'mdi-clipboard-text-clock-outline',
                'tone' => $stats['orders_pending'] > 0 || $failedPaymentsCount > 0 ? 'warning' : 'success',
            ] : null,
            $can('dashboard.view') ? [
                'label' => __('Analytics'),
                'description' => __('Read revenue, conversion, and product momentum without leaving context.'),
                'value' => 'EGP ' . number_format($currentRevenue, 0),
                'meta' => __('30-day revenue'),
                'route' => route('admin.analytics.index'),
                'icon' => 'mdi-chart-areaspline',
                'tone' => $this->deltaRate($currentRevenue, $previousRevenue) >= 0 ? 'success' : 'info',
            ] : null,
            $can('settings.manage') ? [
                'label' => __('Reliability'),
                'description' => __('Review notification health, incidents, and channel readiness from one lane.'),
                'value' => number_format($notificationFailedCount),
                'meta' => __('Notification failures'),
                'route' => route('admin.settings.notifications'),
                'icon' => 'mdi-bell-cog-outline',
                'tone' => $notificationFailedCount > 0 ? 'danger' : 'success',
            ] : null,
            $can('catalog.manage') ? [
                'label' => __('Catalog'),
                'description' => __('Jump into products when low stock or content quality needs fast cleanup.'),
                'value' => number_format($stats['products_low_stock']),
                'meta' => __('Low-stock products'),
                'route' => route('admin.products.index'),
                'icon' => 'mdi-package-variant-closed',
                'tone' => $stats['products_low_stock'] > 0 ? 'warning' : 'success',
            ] : null,
        ]));

        $navigationHighlights = array_values(array_filter([
            $can('settings.manage') ? [
                'label' => __('Notification Center'),
                'description' => __('Best place to inspect failed logs, retry safety, and provider diagnostics.'),
                'route' => route('admin.settings.notifications'),
                'icon' => 'mdi-bell-cog-outline',
            ] : null,
            $can('deploy.manage') ? [
                'label' => __('Deploy Center'),
                'description' => __('Open deploy readiness, monitoring, and guarded rollout controls.'),
                'route' => route('admin.settings.deploy-center'),
                'icon' => 'mdi-rocket-launch-outline',
            ] : null,
            $can('orders.view') ? [
                'label' => __('Orders workspace'),
                'description' => __('Use this when the command center points to pending operational pressure.'),
                'route' => route('admin.orders.index'),
                'icon' => 'mdi-cart-outline',
            ] : null,
            $can('dashboard.view') ? [
                'label' => __('Growth Engine'),
                'description' => __('Move here after operations are stable and you want to push demand.'),
                'route' => route('admin.growth.index'),
                'icon' => 'mdi-rocket-launch-outline',
            ] : null,
        ]));

        return view('admin.dashboard', compact(
            'stats',
            'recentOrders',
            'recentCustomers',
            'lowStockProducts',
            'q',
            'searchResults',
            'monthlyRevenue',
            'statusBreakdown',
            'kpiCards',
            'decisionSummary',
            'alerts',
            'opportunities',
            'problems',
            'topProducts',
            'quickActions',
            'smartBoard',
            'dailyDepth',
            'depthHighlights',
            'analyticsSignals',
            'paymentBreakdown',
            'paymentShare',
            'commandCenterCards',
            'workspaceLanes',
            'navigationSections',
            'navigationHighlights'
        ));
    }

    protected function ordersBetween($from, $to): Collection
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'status', 'payment_status', 'grand_total', 'customer_email']);
    }

    protected function formatDelta(float|int $current, float|int $previous, bool $currency = false): array
    {
        $rate = $this->deltaRate($current, $previous);
        $prefix = $rate >= 0 ? '+' : '';

        return [
            'rate' => $rate,
            'text' => $prefix . number_format($rate * 100, 1) . '%',
            'comparison' => $currency
                ? __('vs previous 30 days')
                : __('vs previous 30 days'),
        ];
    }

    protected function deltaRate(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 1.0 : 0.0;
        }

        return ((float) $current - (float) $previous) / abs((float) $previous);
    }

    protected function toneFromDelta(float|int $current, float|int $previous): string
    {
        $rate = $this->deltaRate($current, $previous);

        if ($rate > 0.03) {
            return 'success';
        }

        if ($rate < -0.03) {
            return 'danger';
        }

        return 'neutral';
    }


    protected function focusActionCopy(?array $signal): array
    {
        if (! $signal) {
            return [
                'title' => __('Keep monitoring the dashboard'),
                'description' => __('No urgent action is leading the board right now.'),
            ];
        }

        return [
            'title' => $signal['title'] ?? __('Next best action'),
            'description' => $signal['description'] ?? __('Review the latest dashboard signal and take action from there.'),
        ];
    }

    protected function buildDecisionSummary(
        float $currentRevenue,
        float $previousRevenue,
        int $currentOrders,
        int $previousOrders,
        float $currentConversion,
        float $previousConversion,
        int $pendingOrders,
        int $lowStock
    ): array {
        $revenueTone = $this->deltaRate($currentRevenue, $previousRevenue) >= 0 ? 'up' : 'down';
        $ordersTone = $this->deltaRate($currentOrders, $previousOrders) >= 0 ? 'up' : 'down';
        $conversionTone = $this->deltaRate($currentConversion, $previousConversion) >= 0 ? 'up' : 'down';

        $message = __('Revenue is :rev while orders are :orders compared with the previous 30-day window.', [
            'rev' => $revenueTone === 'up' ? __('moving up') : __('under pressure'),
            'orders' => $ordersTone === 'up' ? __('accelerating') : __('cooling'),
        ]);

        if ($conversionTone === 'down') {
            $message .= ' ' . __('Conversion quality is softer, so checkout and payment follow-up deserve attention.');
        } else {
            $message .= ' ' . __('Conversion quality is stable enough to support stronger follow-through on demand.');
        }

        if ($pendingOrders > 0) {
            $message .= ' ' . __('There are :count pending orders worth reviewing next.', ['count' => number_format($pendingOrders)]);
        }

        if ($lowStock > 0) {
            $message .= ' ' . __('Also keep an eye on :count low-stock products.', ['count' => number_format($lowStock)]);
        }

        return [
            'title' => __('Executive summary'),
            'message' => $message,
            'period' => __('Last 30 days compared with the previous 30 days'),
        ];
    }
}
