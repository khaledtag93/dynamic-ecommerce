<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DeliveryStatusUpdatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function __construct(
        protected WhatsAppServiceInterface $whatsAppService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'delivery_status' => (string) $request->string('delivery_status'),
            'delivery_method' => (string) $request->string('delivery_method'),
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
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.deliveries.index', [
            'orders' => $orders,
            'filters' => $filters,
            'deliveryStatusOptions' => Order::deliveryStatusOptions(),
            'deliveryMethodOptions' => Order::deliveryMethodOptions(),
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

        $updates = $validated;
        if ($validated['delivery_status'] === Order::DELIVERY_STATUS_SHIPPED && ! $order->shipped_at) {
            $updates['shipped_at'] = now();
        }
        if ($validated['delivery_status'] === Order::DELIVERY_STATUS_DELIVERED) {
            $updates['delivered_at'] = now();
        }
        if ($validated['delivery_status'] === Order::DELIVERY_STATUS_CANCELLED) {
            $updates['delivered_at'] = null;
        }

        $order->update($updates);

        $freshOrder = $order->fresh();

        if ($freshOrder->user) {
            $freshOrder->user->notify(new DeliveryStatusUpdatedNotification($freshOrder));
        }

        User::query()->where('role_as', 1)->get()->each(function (User $admin) use ($freshOrder) {
            $admin->notify(new DeliveryStatusUpdatedNotification($freshOrder));
        });

        $this->whatsAppService->queueDeliveryUpdate($freshOrder);

        return back()->with('success', __('Delivery details updated successfully.'));
    }
}
