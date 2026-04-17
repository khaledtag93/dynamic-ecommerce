<?php

namespace App\Services\Frontend;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Services\Commerce\CouponService;
use App\Services\Commerce\InventoryService;
use App\Services\Channels\WhatsApp\WhatsAppManager;
use App\Services\Commerce\PaymentService;
use App\Services\Commerce\ProfitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected CouponService $couponService,
        protected InventoryService $inventoryService,
        protected PaymentService $paymentService,
        protected ProfitService $profitService,
        protected WhatsAppManager $whatsAppManager,
    ) {
    }

    public function place(array $data, $user): Order
    {
        $summary = $this->cartService->summary();

        if ($summary['items']->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        return DB::transaction(function () use ($data, $user, $summary) {
            $coupon = $summary['coupon'] ?? null;
            foreach ($summary['items'] as $item) {
                if ($item->variant) {
                    $currentStock = (int) ($item->variant->stock ?? 0);

                    if ($currentStock < $item->quantity) {
                        throw ValidationException::withMessages([
                            'cart' => "Not enough stock for {$item->product_name}. Available: {$currentStock}. Please update your cart.",
                        ]);
                    }
                } elseif ($item->product) {
                    $currentStock = (int) ($item->product->quantity ?? 0);

                    if ($currentStock < $item->quantity) {
                        throw ValidationException::withMessages([
                            'cart' => "Not enough stock for {$item->product_name}. Available: {$currentStock}. Please update your cart.",
                        ]);
                    }
                } else {
                    throw ValidationException::withMessages([
                        'cart' => "Product data is missing for {$item->product_name}. Please review your cart.",
                    ]);
                }
            }

            $billingSameAsShipping = (bool) ($data['billing_same_as_shipping'] ?? true);
            $paymentMethod = (string) ($data['payment_method'] ?? Order::PAYMENT_METHOD_COD);
            $deliveryMethod = (string) ($data['delivery_method'] ?? Order::DELIVERY_METHOD_STANDARD);

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'LC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'status' => Order::STATUS_PENDING,
                'payment_status' => $this->paymentService->initialOrderPaymentStatus($paymentMethod),
                'payment_method' => $paymentMethod,
                'delivery_status' => Order::DELIVERY_STATUS_PENDING,
                'delivery_method' => $deliveryMethod,
                'currency' => 'EGP',
                'subtotal' => $summary['subtotal'],
                'discount_total' => $summary['discount'],
                'shipping_total' => $summary['shipping'],
                'tax_total' => $summary['tax'],
                'grand_total' => $summary['total'],
                'notes' => $data['notes'] ?? null,

                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],

                'shipping_address_line_1' => $data['shipping_address_line_1'],
                'shipping_address_line_2' => $data['shipping_address_line_2'] ?? null,
                'shipping_city' => $data['shipping_city'],
                'shipping_state' => $data['shipping_state'] ?? null,
                'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
                'shipping_country' => $data['shipping_country'] ?? 'Egypt',

                'billing_same_as_shipping' => $billingSameAsShipping,
                'billing_address_line_1' => $billingSameAsShipping ? $data['shipping_address_line_1'] : ($data['billing_address_line_1'] ?? null),
                'billing_address_line_2' => $billingSameAsShipping ? ($data['shipping_address_line_2'] ?? null) : ($data['billing_address_line_2'] ?? null),
                'billing_city' => $billingSameAsShipping ? $data['shipping_city'] : ($data['billing_city'] ?? null),
                'billing_state' => $billingSameAsShipping ? ($data['shipping_state'] ?? null) : ($data['billing_state'] ?? null),
                'billing_postal_code' => $billingSameAsShipping ? ($data['shipping_postal_code'] ?? null) : ($data['billing_postal_code'] ?? null),
                'billing_country' => $billingSameAsShipping ? ($data['shipping_country'] ?? 'Egypt') : ($data['billing_country'] ?? 'Egypt'),

                'coupon_code' => $coupon?->code,
                'coupon_snapshot' => $coupon ? $this->couponService->couponSnapshot($coupon, (float) $summary['subtotal']) : null,

                'meta' => [
                    'items_count' => $summary['items_count'],
                    'promotion_label' => $summary['promotion_label'] ?? null,
                    'promotion_discount' => $summary['promotion_discount'] ?? 0,
                    'locale' => app()->getLocale(),
                ],
                'placed_at' => now(),
            ]);

            foreach ($summary['items'] as $item) {
                $unitCost = (float) ($item->variant?->cost_price ?? $item->product?->cost_price ?? 0);
                $expiresAt = $item->variant?->expiration_date ?? $item->product?->expiration_date;
                $lineProfit = ((float) $item->unit_price - $unitCost) * (int) $item->quantity;

                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'sku' => $item->sku,
                    'image' => $item->image,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $unitCost,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                    'profit_amount' => round($lineProfit, 2),
                    'expires_at' => $expiresAt,
                    'meta' => $item->meta,
                ]);

                if ($item->variant) {
                    $this->inventoryService->decrease($item->product, $item->variant, (int) $item->quantity, InventoryMovement::TYPE_ORDER_OUT, [
                        'order_id' => $order->id,
                        'reason' => 'Customer order placed',
                        'unit_cost' => $unitCost,
                        'expiration_date' => $expiresAt,
                        'meta' => ['order_number' => $order->order_number],
                    ]);
                } else {
                    $this->inventoryService->decrease($item->product, null, (int) $item->quantity, InventoryMovement::TYPE_ORDER_OUT, [
                        'order_id' => $order->id,
                        'reason' => 'Customer order placed',
                        'unit_cost' => $unitCost,
                        'expiration_date' => $expiresAt,
                        'meta' => ['order_number' => $order->order_number],
                    ]);
                }
            }

            $payment = $this->paymentService->createForOrder($order);
            $this->paymentService->syncOrderPaymentStatus($order);
            $this->profitService->refreshOrderTotals($order);
            $this->couponService->markCouponAsUsed($coupon);
            $this->cartService->clear();
            $this->couponService->remove();

            $order = $order->fresh(['items', 'payments', 'user']);
            if ($order->user && ! $this->hasRecentOrderPlacedNotification($order->user, $order->id)) {
                $order->user->notify(new OrderPlacedNotification($order, $payment));
            }

            User::query()->where('role_as', 1)->whereKeyNot(optional($order->user)->id)->get()->each(function (User $admin) use ($order, $payment) {
                if (! $this->hasRecentOrderPlacedNotification($admin, $order->id)) {
                    $admin->notify(new OrderPlacedNotification($order, $payment));
                }
            });

            $this->whatsAppManager->queueOrderConfirmation($order);

            return $order;
        });
    }


    protected function hasRecentOrderPlacedNotification(User $user, int $orderId): bool
    {
        return $user->notifications()
            ->where('type', OrderPlacedNotification::class)
            ->latest()
            ->take(10)
            ->get()
            ->contains(function ($notification) use ($orderId) {
                return (int) data_get($notification->data, 'order_id') === $orderId;
            });
    }
}
