<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Payment;
use App\Services\Commerce\AdminActivityLogService;
use App\Services\Commerce\OrderActionService;
use App\Services\Commerce\StoreSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected OrderActionService $orderActionService,
        protected AdminActivityLogService $adminActivityLogService,
    ) {
    }

    public function index(Request $request)
    {
        $search = mb_substr(trim((string) $request->string('search')), 0, 100);
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%' . $escapedSearch . '%';

        $filters = [
            'search' => $search,
            'status' => (string) $request->string('status'),
            'payment_status' => (string) $request->string('payment_status'),
            'payment_method' => (string) $request->string('payment_method'),
            'queue' => (string) $request->string('queue'),
            'per_page' => max(12, min(100, (int) $request->integer('per_page', 12))),
            'sort' => (string) $request->string('sort', 'created_at'),
            'direction' => strtolower((string) $request->string('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        $sortMap = [
            'order_number' => 'order_number',
            'customer_name' => 'customer_name',
            'status' => 'status',
            'payment_status' => 'payment_status',
            'items_count' => 'items_count',
            'grand_total' => 'grand_total',
            'created_at' => 'created_at',
        ];

        $sortColumn = $sortMap[$filters['sort']] ?? 'created_at';

        $orders = Order::query()
            ->withCount('items')
            ->when($filters['search'], function ($query) use ($like) {
                $query->where(function ($innerQuery) use ($like) {
                    $innerQuery
                        ->where('order_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_email', 'like', $like)
                        ->orWhere('customer_phone', 'like', $like)
                        ->orWhere('coupon_code', 'like', $like);
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['payment_status'], fn ($query, $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->when($filters['payment_method'], fn ($query, $paymentMethod) => $query->where('payment_method', $paymentMethod))
            ->when($filters['queue'] === 'action', fn ($query) => $query->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING]))
            ->when($filters['queue'] === 'unpaid', fn ($query) => $query->whereNotIn('payment_status', [Order::PAYMENT_STATUS_PAID, Order::PAYMENT_STATUS_REFUNDED, Order::PAYMENT_STATUS_PARTIALLY_REFUNDED]))
            ->when($filters['queue'] === 'refunds', fn ($query) => $query->where('refund_total', '>', 0))
            ->orderBy($sortColumn, $filters['direction'])
            ->when($sortColumn !== 'created_at', fn ($query) => $query->orderByDesc('created_at'))
            ->paginate($filters['per_page'])
            ->withQueryString();

        $queueStats = [
            'needs_action' => Order::whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING])->count(),
            'unpaid' => Order::whereNotIn('payment_status', [Order::PAYMENT_STATUS_PAID, Order::PAYMENT_STATUS_REFUNDED, Order::PAYMENT_STATUS_PARTIALLY_REFUNDED])->count(),
            'with_refunds' => Order::where('refund_total', '>', 0)->count(),
        ];

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.orders._results', [
                'orders' => $orders,
                'filters' => $filters,
                'queueStats' => $queueStats,
                'statusOptions' => Order::statusOptions(),
            ]);
        }

        $currencyExpression = "COALESCE(NULLIF(payments.currency, ''), NULLIF(orders.currency, ''), 'EGP')";
        $capturedByCurrency = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereIn('payments.status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
            ->selectRaw($currencyExpression.' AS statement_currency')
            ->selectRaw('SUM(payments.amount) AS statement_total')
            ->groupByRaw($currencyExpression)
            ->get()
            ->keyBy(fn ($row) => strtoupper((string) $row->statement_currency));

        $orderCurrencyExpression = "COALESCE(NULLIF(orders.currency, ''), 'EGP')";
        $refundsByCurrency = DB::table('order_refunds')
            ->join('orders', 'orders.id', '=', 'order_refunds.order_id')
            ->where('order_refunds.amount', '>', 0)
            ->selectRaw($orderCurrencyExpression.' AS statement_currency')
            ->selectRaw('SUM(order_refunds.amount) AS statement_total')
            ->groupByRaw($orderCurrencyExpression)
            ->get()
            ->keyBy(fn ($row) => strtoupper((string) $row->statement_currency));

        $currencies = $capturedByCurrency->keys()
            ->merge($refundsByCurrency->keys())
            ->unique()
            ->sort()
            ->values();

        $netCollectedByCurrency = $currencies->map(function (string $currency) use ($capturedByCurrency, $refundsByCurrency): array {
            $captured = round((float) ($capturedByCurrency->get($currency)?->statement_total ?? 0), 2);
            $refunded = round((float) ($refundsByCurrency->get($currency)?->statement_total ?? 0), 2);

            return [
                'currency' => $currency,
                'amount' => round($captured - $refunded, 2),
            ];
        });

        $refundTotalsByCurrency = $currencies->map(fn (string $currency): array => [
            'currency' => $currency,
            'amount' => round((float) ($refundsByCurrency->get($currency)?->statement_total ?? 0), 2),
        ]);

        $totalOrders = Order::count();

        $stats = $queueStats + [
            'total' => $totalOrders,
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'processing' => Order::where('status', Order::STATUS_PROCESSING)->count(),
            'completed' => Order::where('status', Order::STATUS_COMPLETED)->count(),
            'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
            'net_collected_by_currency' => $netCollectedByCurrency,
            'refunds_by_currency' => $refundTotalsByCurrency,
            'avg_total' => (float) ($totalOrders > 0 ? Order::avg('grand_total') : 0),
        ];

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'stats' => $stats,
            'queueStats' => $queueStats,
            'statusOptions' => Order::statusOptions(),
            'paymentStatusOptions' => Order::paymentStatusOptions(),
            'paymentMethodOptions' => Order::paymentMethodOptions(),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.variant', 'user', 'refunds.processedBy', 'payments', 'returnRequests.items']);

        return view('admin.orders.show', [
            'order' => $order,
            'statusOptions' => Order::statusOptions(),
            'deliveryStatusOptions' => Order::deliveryStatusOptions(),
        ]);
    }

    public function receipt(Order $order)
    {
        $order->load(['items', 'payments', 'refunds']);

        return view('orders.receipt', [
            'order' => $order,
            'settings' => app(StoreSettingsService::class)->all(),
            'backUrl' => route('admin.orders.show', $order),
            'backLabel' => __('Back to order'),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::statusOptions()))],
        ]);

        try {
            $message = $this->performStatusUpdate($order, $validated['status']);

            return redirect()
                ->route('admin.orders.show', $order)
                ->with('success', $message);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function quickStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::statusOptions()))],
        ]);

        try {
            $message = $this->performStatusUpdate($order, $validated['status']);

            return back()->with('success', $message);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function refund(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'refund_idempotency_key' => ['required', 'uuid'],
        ]);

        try {
            $result = $this->orderActionService->refund(
                $order,
                (float) $validated['amount'],
                $validated['reason'],
                $validated['notes'] ?? null,
                optional(auth()->user())->id,
                null,
                $validated['refund_idempotency_key'],
            );

            if ($result['created']) {
                $this->adminActivityLogService->log(
                    'order_management',
                    'refund_recorded',
                    __('Refund recorded for order :order.', ['order' => $order->order_number]),
                    optional(auth()->user())->id,
                    $result['order'],
                    [
                        'amount' => (float) $validated['amount'],
                        'reason' => $validated['reason'],
                        'order_refund_id' => $result['refund']->id,
                    ]
                );
            }

            return redirect()
                ->route('admin.orders.show', $order)
                ->with(
                    'success',
                    $result['created']
                        ? __('Refund recorded successfully.')
                        : __('This refund request was already recorded.')
                );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function destroy(Order $order): RedirectResponse
    {
        return back()->with(
            'error',
            __('Permanent order deletion is disabled to preserve payment, refund, coupon, inventory, and audit history.')
        );
    }

    protected function performStatusUpdate(Order $order, string $newStatus): string
    {
        if ($newStatus === Order::STATUS_CANCELLED && $order->status !== Order::STATUS_CANCELLED) {
            $this->orderActionService->cancel($order, __('Cancelled by admin.'), optional(auth()->user())->id);

            $this->adminActivityLogService->log(
                'order_management',
                'order_cancelled',
                __('Order :order was cancelled by admin.', ['order' => $order->order_number]),
                optional(auth()->user())->id,
                $order,
                [
                    'new_status' => Order::STATUS_CANCELLED,
                ]
            );

            return __('Order cancelled successfully and stock was restored.');
        }

        $oldStatus = $order->status;
        $this->orderActionService->updateStatus($order, $newStatus);

        $this->adminActivityLogService->log(
            'order_management',
            'order_status_updated',
            __('Order :order status changed from :old to :new.', [
                'order' => $order->order_number,
                'old' => $oldStatus,
                'new' => $newStatus,
            ]),
            optional(auth()->user())->id,
            $order,
            [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]
        );

        return __('Order status updated successfully.');
    }
}
