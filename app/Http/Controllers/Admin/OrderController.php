<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Services\Commerce\AdminActivityLogService;
use App\Services\Commerce\OrderActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),
            'payment_status' => (string) $request->string('payment_status'),
            'payment_method' => (string) $request->string('payment_method'),
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
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('coupon_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['payment_status'], fn ($query, $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->when($filters['payment_method'], fn ($query, $paymentMethod) => $query->where('payment_method', $paymentMethod))
            ->orderBy($sortColumn, $filters['direction'])
            ->when($sortColumn !== 'created_at', fn ($query) => $query->orderByDesc('created_at'))
            ->paginate(12)
            ->withQueryString();

        $paidStatuses = [
            Order::PAYMENT_STATUS_PAID,
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            Order::PAYMENT_STATUS_REFUNDED,
        ];

        $paidTotal = Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->get(['grand_total', 'refund_total'])
            ->sum(fn (Order $order) => max(0, (float) $order->grand_total - (float) $order->refund_total));

        $totalOrders = Order::count();

        $stats = [
            'total' => $totalOrders,
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'processing' => Order::where('status', Order::STATUS_PROCESSING)->count(),
            'completed' => Order::where('status', Order::STATUS_COMPLETED)->count(),
            'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
            'refunds_total' => (float) OrderRefund::sum('amount'),
            'paid_total' => (float) $paidTotal,
            'avg_total' => (float) ($totalOrders > 0 ? Order::avg('grand_total') : 0),
        ];

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'stats' => $stats,
            'statusOptions' => Order::statusOptions(),
            'paymentStatusOptions' => Order::paymentStatusOptions(),
            'paymentMethodOptions' => Order::paymentMethodOptions(),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.variant', 'user', 'refunds.processedBy', 'payments']);

        return view('admin.orders.show', [
            'order' => $order,
            'statusOptions' => Order::statusOptions(),
            'deliveryStatusOptions' => Order::deliveryStatusOptions(),
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
        ]);

        try {
            $this->orderActionService->refund(
                $order,
                (float) $validated['amount'],
                $validated['reason'],
                $validated['notes'] ?? null,
                optional(auth()->user())->id,
            );

            $this->adminActivityLogService->log(
                'order_management',
                'refund_recorded',
                __('Refund recorded for order :order.', ['order' => $order->order_number]),
                optional(auth()->user())->id,
                $order,
                [
                    'amount' => (float) $validated['amount'],
                    'reason' => $validated['reason'],
                ]
            );

            return redirect()
                ->route('admin.orders.show', $order)
                ->with('success', 'Refund recorded successfully.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function destroy(Order $order): RedirectResponse
    {
        if ($order->status !== Order::STATUS_CANCELLED) {
            return back()->with('error', 'Only cancelled orders can be deleted permanently.');
        }

        $orderNumber = $order->order_number;

        $this->adminActivityLogService->log(
            'order_management',
            'cancelled_order_deleted',
            __('Cancelled order :order was deleted permanently.', ['order' => $orderNumber]),
            optional(auth()->user())->id,
            $order,
            [
                'order_number' => $orderNumber,
            ]
        );

        $order->delete();

        return redirect()->route('admin.orders.index')->with('success', 'Cancelled order '.$orderNumber.' deleted successfully.');
    }

    protected function performStatusUpdate(Order $order, string $newStatus): string
    {
        if ($newStatus === Order::STATUS_CANCELLED && $order->status !== Order::STATUS_CANCELLED) {
            $this->orderActionService->cancel($order, 'Cancelled by admin.', optional(auth()->user())->id);

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

            return 'Order cancelled successfully and stock was restored.';
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

        return 'Order status updated successfully.';
    }
}
