<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Commerce\DeliveryService;
use App\Services\Commerce\AdminActivityLogService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService,
        protected AdminActivityLogService $adminActivityLogService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'delivery_status' => (string) $request->string('delivery_status'),
            'delivery_method' => (string) $request->string('delivery_method'),
            'queue' => (string) $request->string('queue'),
            'per_page' => max(15, min(100, (int) $request->integer('per_page', 15))),
        ];

        $orders = Order::query()
            ->with('user')
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('shipping_provider', 'like', "%{$search}%");
                });
            })
            ->when($filters['delivery_status'], fn ($query, $status) => $query->where('delivery_status', $status))
            ->when($filters['delivery_method'], fn ($query, $method) => $query->where('delivery_method', $method))
            ->when($filters['queue'] === 'action', fn ($query) => $query->whereIn('delivery_status', [Order::DELIVERY_STATUS_PENDING, Order::DELIVERY_STATUS_PREPARING]))
            ->when($filters['queue'] === 'transit', fn ($query) => $query->whereIn('delivery_status', [Order::DELIVERY_STATUS_SHIPPED, Order::DELIVERY_STATUS_OUT_FOR_DELIVERY]))
            ->when($filters['queue'] === 'exceptions', fn ($query) => $query->whereIn('delivery_status', [Order::DELIVERY_STATUS_RETURNED, Order::DELIVERY_STATUS_CANCELLED]))
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $queueStats = [
            'action' => Order::whereIn('delivery_status', [Order::DELIVERY_STATUS_PENDING, Order::DELIVERY_STATUS_PREPARING])->count(),
            'transit' => Order::whereIn('delivery_status', [Order::DELIVERY_STATUS_SHIPPED, Order::DELIVERY_STATUS_OUT_FOR_DELIVERY])->count(),
            'exceptions' => Order::whereIn('delivery_status', [Order::DELIVERY_STATUS_RETURNED, Order::DELIVERY_STATUS_CANCELLED])->count(),
        ];

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.deliveries._results', [
                'orders' => $orders,
                'filters' => $filters,
                'queueStats' => $queueStats,
            ]);
        }

        $stats = $queueStats + [
            'total' => Order::count(),
            'delivered' => Order::where('delivery_status', Order::DELIVERY_STATUS_DELIVERED)->count(),
        ];

        return view('admin.deliveries.index', [
            'orders' => $orders,
            'filters' => $filters,
            'deliveryStatusOptions' => Order::deliveryStatusOptions(),
            'deliveryMethodOptions' => Order::deliveryMethodOptions(),
            'stats' => $stats,
            'queueStats' => $queueStats,
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'delivery_status' => ['required', Rule::in(array_keys(Order::deliveryStatusOptions()))],
            'shipping_provider' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'estimated_delivery_date' => ['nullable', 'date'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldStatus = $order->delivery_status;
        $oldProvider = $order->shipping_provider;
        $oldTracking = $order->tracking_number;

        try {
            $result = $this->deliveryService->update($order, $validated);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $updatedOrder = $result['order'];

        $this->adminActivityLogService->log(
            'delivery',
            $result['status_changed'] ? 'delivery_status_updated' : 'delivery_details_updated',
            __('Delivery updated for order :order.', ['order' => $updatedOrder->order_number]),
            $request->user()?->id,
            $updatedOrder,
            [
                'old_status' => $oldStatus,
                'new_status' => $updatedOrder->delivery_status,
                'old_provider' => $oldProvider,
                'new_provider' => $updatedOrder->shipping_provider,
                'old_tracking' => $oldTracking,
                'new_tracking' => $updatedOrder->tracking_number,
            ]
        );

        return back()->with(
            'success',
            $result['status_changed']
                ? __('Delivery status updated successfully.')
                : __('Delivery details updated successfully.')
        );
    }
}
