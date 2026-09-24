<?php

namespace App\Services\Commerce;

use App\Exceptions\ProductIdentifierAmbiguityException;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(
        protected ProductIdentifierService $identifierService,
        protected InventoryService $inventoryService,
        protected AdminActivityLogService $activityLogService,
    ) {
    }

    public function cartFor(User $cashier): PosCart
    {
        $token = 'cashier:' . $cashier->id;

        $cart = PosCart::query()->firstOrCreate(
            ['open_token' => $token],
            [
                'cashier_user_id' => $cashier->id,
                'status' => PosCart::STATUS_OPEN,
            ]
        );

        return $cart->load(['items.product', 'items.variant', 'customer']);
    }

    public function summary(PosCart $cart): array
    {
        $cart->loadMissing(['items.product', 'items.variant']);

        $subtotal = round((float) $cart->items->sum(
            fn (PosCartItem $item) => (float) $item->unit_price * (int) $item->quantity
        ), 2);

        return [
            'subtotal' => $subtotal,
            'grand_total' => $subtotal,
            'items_count' => (int) $cart->items->sum('quantity'),
            'lines_count' => $cart->items->count(),
        ];
    }

    public function heldCartsFor(User $cashier)
    {
        return PosCart::query()
            ->where('cashier_user_id', $cashier->id)
            ->where('status', PosCart::STATUS_HELD)
            ->with(['items.product', 'items.variant', 'customer'])
            ->orderByDesc('held_at')
            ->orderByDesc('id')
            ->get();
    }

    public function attachCustomer(PosCart $cart, User $customer, int $cashierUserId): PosCart
    {
        return DB::transaction(function () use ($cart, $customer, $cashierUserId) {
            $lockedCart = $this->lockOpenCart($cart, $cashierUserId);
            $lockedCustomer = User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedCustomer->role_as !== 0) {
                throw ValidationException::withMessages([
                    'customer' => __('Only customer accounts can be attached to a POS sale.'),
                ]);
            }

            $lockedCart->update([
                'customer_user_id' => $lockedCustomer->id,
                'customer_name' => $lockedCustomer->name,
            ]);

            $this->activityLogService->log(
                'pos',
                'pos_customer_attached',
                __('Customer attached to POS sale.'),
                $cashierUserId,
                $lockedCart,
                [
                    'customer_user_id' => $lockedCustomer->id,
                ]
            );

            return $lockedCart->fresh(['items.product', 'items.variant', 'customer']);
        });
    }

    public function detachCustomer(PosCart $cart, int $cashierUserId): PosCart
    {
        return DB::transaction(function () use ($cart, $cashierUserId) {
            $lockedCart = $this->lockOpenCart($cart, $cashierUserId);
            $customerUserId = $lockedCart->customer_user_id;

            $lockedCart->update([
                'customer_user_id' => null,
                'customer_name' => null,
            ]);

            $this->activityLogService->log(
                'pos',
                'pos_customer_detached',
                __('Customer detached from POS sale.'),
                $cashierUserId,
                $lockedCart,
                [
                    'customer_user_id' => $customerUserId,
                ]
            );

            return $lockedCart->fresh(['items.product', 'items.variant', 'customer']);
        });
    }

    public function scan(PosCart $cart, string $barcode, int $cashierUserId): PosCartItem
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            throw ValidationException::withMessages([
                'barcode' => __('Scan or enter a barcode before adding an item.'),
            ]);
        }

        try {
            $match = $this->identifierService->resolveBarcode($barcode);
        } catch (ProductIdentifierAmbiguityException) {
            throw ValidationException::withMessages([
                'barcode' => __('This barcode matches multiple catalog records. Resolve the duplicate identifiers before using POS.'),
            ]);
        }

        if (! $match) {
            throw ValidationException::withMessages([
                'barcode' => __('No catalog item uses this barcode. Nothing was added to the POS cart.'),
            ]);
        }

        if ($match['requires_variant_selection']) {
            throw ValidationException::withMessages([
                'barcode' => __('This barcode identifies a product with variants. Scan the exact variant barcode instead.'),
            ]);
        }

        $resolvedProductId = (int) $match['product']->id;
        $resolvedVariantId = $match['variant'] ? (int) $match['variant']->id : null;

        return DB::transaction(function () use (
            $cart,
            $barcode,
            $cashierUserId,
            $resolvedProductId,
            $resolvedVariantId
        ) {
            $lockedCart = $this->lockOpenCart($cart, $cashierUserId);
            $product = Product::query()->whereKey($resolvedProductId)->lockForUpdate()->first();

            if (! $product || ! $product->status) {
                throw ValidationException::withMessages([
                    'barcode' => __('This product is not available for POS sale.'),
                ]);
            }

            $variant = null;

            if ($resolvedVariantId) {
                $variant = ProductVariant::query()->whereKey($resolvedVariantId)->lockForUpdate()->first();

                if (
                    ! $variant ||
                    (int) $variant->product_id !== (int) $product->id ||
                    ! $variant->status ||
                    trim((string) $variant->barcode) !== $barcode
                ) {
                    throw ValidationException::withMessages([
                        'barcode' => __('The scanned variant is no longer available with this barcode. Scan the item again.'),
                    ]);
                }
            } else {
                if ($product->has_variants || trim((string) $product->barcode) !== $barcode) {
                    throw ValidationException::withMessages([
                        'barcode' => __('The scanned product is no longer available with this barcode. Scan the exact item again.'),
                    ]);
                }
            }

            $availableStock = (int) ($variant?->stock ?? $product->quantity);

            if ($availableStock < 1) {
                throw ValidationException::withMessages([
                    'barcode' => __('This item is out of stock and cannot be added to the POS cart.'),
                ]);
            }

            $itemKey = 'p:' . $product->id . ':v:' . ($variant?->id ?? 0);
            $cartItem = PosCartItem::query()
                ->where('pos_cart_id', $lockedCart->id)
                ->where('item_key', $itemKey)
                ->lockForUpdate()
                ->first();

            $newQuantity = (int) ($cartItem?->quantity ?? 0) + 1;

            if ($newQuantity > $availableStock) {
                throw ValidationException::withMessages([
                    'barcode' => __('The POS cart already contains all currently available stock for this item.'),
                ]);
            }

            $unitPrice = round((float) ($variant?->current_price ?? $product->current_price), 2);

            if ($cartItem) {
                $cartItem->update([
                    'product_name' => $product->name,
                    'variant_name' => $variant?->variant_name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'barcode' => $variant?->barcode ?? $product->barcode,
                    'unit_price' => $unitPrice,
                    'quantity' => $newQuantity,
                ]);
            } else {
                $cartItem = PosCartItem::query()->create([
                    'pos_cart_id' => $lockedCart->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'item_key' => $itemKey,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->variant_name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'barcode' => $variant?->barcode ?? $product->barcode,
                    'unit_price' => $unitPrice,
                    'quantity' => 1,
                ]);
            }

            return $cartItem->fresh(['product', 'variant']);
        });
    }

    public function updateQuantity(
        PosCart $cart,
        PosCartItem $item,
        int $expectedQuantity,
        int $newQuantity,
        int $cashierUserId
    ): PosCartItem {
        if ($newQuantity < 1 || $newQuantity > 9999) {
            throw ValidationException::withMessages([
                'quantity' => __('POS quantity must be between 1 and 9999.'),
            ]);
        }

        return DB::transaction(function () use (
            $cart,
            $item,
            $expectedQuantity,
            $newQuantity,
            $cashierUserId
        ) {
            $lockedCart = $this->lockOpenCart($cart, $cashierUserId);
            $lockedItem = PosCartItem::query()
                ->whereKey($item->id)
                ->where('pos_cart_id', $lockedCart->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedItem) {
                throw ValidationException::withMessages([
                    'cart' => __('This POS cart item no longer exists.'),
                ]);
            }

            if ((int) $lockedItem->quantity !== $expectedQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('The POS cart changed in another request. Review the latest quantity and try again.'),
                ]);
            }

            $product = Product::query()->whereKey($lockedItem->product_id)->lockForUpdate()->first();
            $variant = $lockedItem->product_variant_id
                ? ProductVariant::query()->whereKey($lockedItem->product_variant_id)->lockForUpdate()->first()
                : null;

            $this->validateSaleTarget($product, $variant, $lockedItem->product_variant_id);
            $availableStock = (int) ($variant?->stock ?? $product->quantity);

            if ($newQuantity > $availableStock) {
                throw ValidationException::withMessages([
                    'quantity' => __('Requested POS quantity exceeds current stock.'),
                ]);
            }

            $lockedItem->update([
                'unit_price' => round((float) ($variant?->current_price ?? $product->current_price), 2),
                'quantity' => $newQuantity,
            ]);

            return $lockedItem->fresh(['product', 'variant']);
        });
    }

    public function removeItem(PosCart $cart, PosCartItem $item, int $cashierUserId): void
    {
        DB::transaction(function () use ($cart, $item, $cashierUserId) {
            $lockedCart = $this->lockOpenCart($cart, $cashierUserId);
            $lockedItem = PosCartItem::query()
                ->whereKey($item->id)
                ->where('pos_cart_id', $lockedCart->id)
                ->lockForUpdate()
                ->first();

            if ($lockedItem) {
                $lockedItem->delete();
            }
        });
    }

    public function clear(PosCart $cart, int $cashierUserId): void
    {
        DB::transaction(function () use ($cart, $cashierUserId) {
            $lockedCart = $this->lockOpenCart($cart, $cashierUserId);
            $lockedCart->items()->delete();
            $lockedCart->update([
                'hold_label' => null,
                'customer_user_id' => null,
                'customer_name' => null,
                'notes' => null,
            ]);
        });
    }

    public function hold(PosCart $cart, array $data, int $cashierUserId): PosCart
    {
        return DB::transaction(function () use ($cart, $data, $cashierUserId) {
            $lockedCart = $this->lockOpenCart($cart, $cashierUserId);
            $items = PosCartItem::query()
                ->where('pos_cart_id', $lockedCart->id)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => __('Add at least one item before holding the POS sale.'),
                ]);
            }

            $holdLabel = trim((string) ($data['hold_label'] ?? ''));
            $customerName = trim((string) ($data['customer_name'] ?? ''));
            $notes = trim((string) ($data['notes'] ?? ''));

            if ($lockedCart->customer_user_id) {
                $attachedCustomer = User::query()
                    ->whereKey($lockedCart->customer_user_id)
                    ->lockForUpdate()
                    ->first();

                if (! $attachedCustomer || (int) $attachedCustomer->role_as !== 0) {
                    throw ValidationException::withMessages([
                        'customer' => __('The attached customer account is no longer available for POS.'),
                    ]);
                }

                $customerName = $attachedCustomer->name;
            }

            $lockedCart->update([
                'status' => PosCart::STATUS_HELD,
                'open_token' => null,
                'hold_label' => $holdLabel !== '' ? $holdLabel : null,
                'held_at' => now(),
                'customer_name' => $customerName !== '' ? $customerName : null,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $this->activityLogService->log(
                'pos',
                'pos_sale_held',
                __('POS sale held.'),
                $cashierUserId,
                $lockedCart,
                [
                    'items_count' => (int) $items->sum('quantity'),
                    'hold_label' => $lockedCart->hold_label,
                ]
            );

            return $lockedCart->fresh(['items.product', 'items.variant']);
        });
    }

    public function resume(PosCart $cart, int $cashierUserId): PosCart
    {
        return DB::transaction(function () use ($cart, $cashierUserId) {
            User::query()->whereKey($cashierUserId)->lockForUpdate()->firstOrFail();
            $heldCart = PosCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            if ((int) $heldCart->cashier_user_id !== $cashierUserId) {
                throw ValidationException::withMessages([
                    'cart' => __('This POS cart belongs to another cashier.'),
                ]);
            }

            if ($heldCart->status !== PosCart::STATUS_HELD) {
                throw ValidationException::withMessages([
                    'cart' => __('This POS cart is not held.'),
                ]);
            }

            $heldItems = PosCartItem::query()
                ->where('pos_cart_id', $heldCart->id)
                ->lockForUpdate()
                ->get();

            if ($heldItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => __('This held POS sale no longer contains any items.'),
                ]);
            }

            $token = 'cashier:' . $cashierUserId;
            $currentOpen = PosCart::query()
                ->where('open_token', $token)
                ->lockForUpdate()
                ->first();

            if ($currentOpen && (int) $currentOpen->id !== (int) $heldCart->id) {
                $currentItems = PosCartItem::query()
                    ->where('pos_cart_id', $currentOpen->id)
                    ->lockForUpdate()
                    ->get();

                if ($currentItems->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => __('Hold or clear the current POS sale before resuming another sale.'),
                    ]);
                }

                $currentOpen->update([
                    'status' => PosCart::STATUS_ABANDONED,
                    'open_token' => null,
                ]);
            }

            $heldCart->update([
                'status' => PosCart::STATUS_OPEN,
                'open_token' => $token,
                'held_at' => null,
            ]);

            $this->activityLogService->log(
                'pos',
                'pos_sale_resumed',
                __('POS sale resumed.'),
                $cashierUserId,
                $heldCart,
                [
                    'items_count' => (int) $heldItems->sum('quantity'),
                    'hold_label' => $heldCart->hold_label,
                ]
            );

            return $heldCart->fresh(['items.product', 'items.variant', 'customer']);
        });
    }

    public function discardHeld(PosCart $cart, int $cashierUserId): void
    {
        DB::transaction(function () use ($cart, $cashierUserId) {
            $heldCart = PosCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            if ((int) $heldCart->cashier_user_id !== $cashierUserId) {
                throw ValidationException::withMessages([
                    'cart' => __('This POS cart belongs to another cashier.'),
                ]);
            }

            if ($heldCart->status !== PosCart::STATUS_HELD) {
                throw ValidationException::withMessages([
                    'cart' => __('This POS cart is not held.'),
                ]);
            }

            $heldItems = PosCartItem::query()
                ->where('pos_cart_id', $heldCart->id)
                ->lockForUpdate()
                ->get();
            $itemsCount = (int) $heldItems->sum('quantity');

            $heldCart->update([
                'status' => PosCart::STATUS_ABANDONED,
                'open_token' => null,
                'held_at' => null,
            ]);

            $this->activityLogService->log(
                'pos',
                'pos_held_sale_discarded',
                __('Held POS sale discarded.'),
                $cashierUserId,
                $heldCart,
                [
                    'items_count' => $itemsCount,
                    'hold_label' => $heldCart->hold_label,
                ]
            );
        });
    }

    /**
     * @return array{order: Order, created: bool, change_due: float}
     */
    public function checkout(PosCart $cart, array $data, int $cashierUserId): array
    {
        $paymentMethod = (string) ($data['payment_method'] ?? '');

        if (! in_array($paymentMethod, [
            Order::PAYMENT_METHOD_POS_CASH,
            Order::PAYMENT_METHOD_POS_CARD,
        ], true)) {
            throw ValidationException::withMessages([
                'payment_method' => __('Choose a supported POS payment method.'),
            ]);
        }

        return DB::transaction(function () use ($cart, $data, $cashierUserId, $paymentMethod) {
            $lockedCart = PosCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedCart->cashier_user_id !== $cashierUserId) {
                throw ValidationException::withMessages([
                    'cart' => __('This POS cart belongs to another cashier.'),
                ]);
            }

            if ($lockedCart->status === PosCart::STATUS_COMPLETED && $lockedCart->order_id) {
                $existingOrder = Order::query()->whereKey($lockedCart->order_id)->firstOrFail();

                return [
                    'order' => $existingOrder,
                    'created' => false,
                    'change_due' => (float) data_get($existingOrder->meta, 'pos.change_due', 0),
                ];
            }

            if ($lockedCart->status !== PosCart::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'cart' => __('This POS cart is no longer open.'),
                ]);
            }

            $items = PosCartItem::query()
                ->where('pos_cart_id', $lockedCart->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => __('Add at least one item before completing the POS sale.'),
                ]);
            }

            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id')->filter()->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variants = ProductVariant::query()
                ->whereIn('id', $items->pluck('product_variant_id')->filter()->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $prepared = [];
            $subtotal = 0.0;
            $costTotal = 0.0;

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $variant = $item->product_variant_id ? $variants->get($item->product_variant_id) : null;

                $this->validateSaleTarget($product, $variant, $item->product_variant_id);

                $quantity = (int) $item->quantity;
                $availableStock = (int) ($variant?->stock ?? $product->quantity);

                if ($quantity < 1 || $availableStock < $quantity) {
                    throw ValidationException::withMessages([
                        'cart' => __('One or more POS items no longer have enough stock. Review the cart before checkout.'),
                    ]);
                }

                $unitPrice = round((float) ($variant?->current_price ?? $product->current_price), 2);
                $unitCost = round((float) ($variant?->cost_price ?? $product->cost_price ?? 0), 2);
                $lineTotal = round($unitPrice * $quantity, 2);
                $lineCost = round($unitCost * $quantity, 2);

                $prepared[] = compact(
                    'item',
                    'product',
                    'variant',
                    'quantity',
                    'unitPrice',
                    'unitCost',
                    'lineTotal',
                    'lineCost'
                );

                $subtotal += $lineTotal;
                $costTotal += $lineCost;
            }

            $subtotal = round($subtotal, 2);
            $costTotal = round($costTotal, 2);
            $cashReceived = null;
            $changeDue = 0.0;

            if ($paymentMethod === Order::PAYMENT_METHOD_POS_CASH) {
                if (! array_key_exists('cash_received', $data) || $data['cash_received'] === null || $data['cash_received'] === '') {
                    throw ValidationException::withMessages([
                        'cash_received' => __('Enter the cash received before completing a cash sale.'),
                    ]);
                }

                $cashReceived = round((float) $data['cash_received'], 2);

                if ($cashReceived < $subtotal) {
                    throw ValidationException::withMessages([
                        'cash_received' => __('Cash received cannot be less than the sale total.'),
                    ]);
                }

                $changeDue = round($cashReceived - $subtotal, 2);
            }

            $orderNumber = 'POS-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
            $customer = null;

            if ($lockedCart->customer_user_id) {
                $customer = User::query()
                    ->whereKey($lockedCart->customer_user_id)
                    ->lockForUpdate()
                    ->first();

                if (! $customer || (int) $customer->role_as !== 0) {
                    throw ValidationException::withMessages([
                        'customer' => __('The attached customer account is no longer available for POS.'),
                    ]);
                }
            }

            $customerName = $customer?->name
                ?: (trim((string) ($data['customer_name'] ?? '')) ?: 'Walk-in customer');
            $customerEmail = $customer?->email;

            $order = Order::query()->create([
                'user_id' => $customer?->id,
                'sales_channel' => Order::SALES_CHANNEL_POS,
                'order_number' => $orderNumber,
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'payment_method' => $paymentMethod,
                'delivery_status' => Order::DELIVERY_STATUS_DELIVERED,
                'delivery_method' => Order::DELIVERY_METHOD_PICKUP,
                'delivered_at' => now(),
                'currency' => 'EGP',
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'grand_total' => $subtotal,
                'cost_total' => $costTotal,
                'profit_total' => round($subtotal - $costTotal, 2),
                'notes' => $data['notes'] ?? null,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => null,
                'shipping_address_line_1' => null,
                'shipping_address_line_2' => null,
                'shipping_city' => null,
                'shipping_state' => null,
                'shipping_postal_code' => null,
                'shipping_country' => null,
                'billing_same_as_shipping' => true,
                'billing_address_line_1' => null,
                'billing_address_line_2' => null,
                'billing_city' => null,
                'billing_state' => null,
                'billing_postal_code' => null,
                'billing_country' => null,
                'meta' => [
                    'sales_channel' => Order::SALES_CHANNEL_POS,
                    'cashier_user_id' => $cashierUserId,
                    'pos_cart_id' => $lockedCart->id,
                    'customer_user_id' => $customer?->id,
                    'pos' => [
                        'cash_received' => $cashReceived,
                        'change_due' => $changeDue,
                    ],
                ],
                'placed_at' => now(),
            ]);

            foreach ($prepared as $line) {
                /** @var PosCartItem $cartItem */
                $cartItem = $line['item'];
                /** @var Product $product */
                $product = $line['product'];
                /** @var ProductVariant|null $variant */
                $variant = $line['variant'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->variant_name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'image' => $variant?->image ?? $product->main_image_url,
                    'unit_price' => $line['unitPrice'],
                    'unit_cost' => $line['unitCost'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['lineTotal'],
                    'profit_amount' => round($line['lineTotal'] - $line['lineCost'], 2),
                    'expires_at' => $variant?->expiration_date ?? $product->expiration_date,
                    'meta' => [
                        'sales_channel' => Order::SALES_CHANNEL_POS,
                        'barcode' => $cartItem->barcode,
                        'pos_cart_item_id' => $cartItem->id,
                    ],
                ]);

                $this->inventoryService->decrease(
                    $product,
                    $variant,
                    $line['quantity'],
                    InventoryMovement::TYPE_ORDER_OUT,
                    [
                        'order_id' => $order->id,
                        'reason' => 'POS sale completed',
                        'unit_cost' => $line['unitCost'],
                        'expiration_date' => $variant?->expiration_date ?? $product->expiration_date,
                        'meta' => [
                            'order_number' => $order->order_number,
                            'sales_channel' => Order::SALES_CHANNEL_POS,
                            'cashier_user_id' => $cashierUserId,
                            'pos_cart_id' => $lockedCart->id,
                        ],
                    ]
                );
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'method' => $paymentMethod,
                'provider' => $paymentMethod === Order::PAYMENT_METHOD_POS_CARD ? 'card_terminal' : 'cash_register',
                'status' => Payment::STATUS_PAID,
                'transaction_reference' => 'POSPAY-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5)),
                'amount' => $subtotal,
                'currency' => 'EGP',
                'paid_at' => now(),
                'notes' => $paymentMethod === Order::PAYMENT_METHOD_POS_CARD
                    ? 'Card terminal payment recorded at POS.'
                    : 'Cash payment recorded at POS.',
                'meta' => [
                    'sales_channel' => Order::SALES_CHANNEL_POS,
                    'cashier_user_id' => $cashierUserId,
                    'pos_cart_id' => $lockedCart->id,
                    'cash_received' => $cashReceived,
                    'change_due' => $changeDue,
                ],
            ]);

            $lockedCart->update([
                'status' => PosCart::STATUS_COMPLETED,
                'open_token' => null,
                'order_id' => $order->id,
                'customer_name' => $customerName,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->activityLogService->log(
                'pos',
                'pos_sale_completed',
                __('POS sale :order completed.', ['order' => $order->order_number]),
                $cashierUserId,
                $order,
                [
                    'pos_cart_id' => $lockedCart->id,
                    'payment_method' => $paymentMethod,
                    'grand_total' => $subtotal,
                    'items_count' => (int) $items->sum('quantity'),
                    'change_due' => $changeDue,
                ]
            );

            return [
                'order' => $order->fresh(['items', 'payments']),
                'created' => true,
                'change_due' => $changeDue,
            ];
        });
    }

    protected function lockOpenCart(PosCart $cart, int $cashierUserId): PosCart
    {
        $lockedCart = PosCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

        if ((int) $lockedCart->cashier_user_id !== $cashierUserId) {
            throw ValidationException::withMessages([
                'cart' => __('This POS cart belongs to another cashier.'),
            ]);
        }

        if ($lockedCart->status !== PosCart::STATUS_OPEN || ! $lockedCart->open_token) {
            throw ValidationException::withMessages([
                'cart' => __('This POS cart is no longer open.'),
            ]);
        }

        return $lockedCart;
    }

    protected function validateSaleTarget(
        ?Product $product,
        ?ProductVariant $variant,
        mixed $expectedVariantId
    ): void {
        if (! $product || ! $product->status) {
            throw ValidationException::withMessages([
                'cart' => __('One or more POS products are missing or inactive.'),
            ]);
        }

        if ($expectedVariantId) {
            if (! $variant || (int) $variant->product_id !== (int) $product->id || ! $variant->status) {
                throw ValidationException::withMessages([
                    'cart' => __('One or more POS variants are missing, inactive, or mismatched.'),
                ]);
            }

            return;
        }

        if ($product->has_variants) {
            throw ValidationException::withMessages([
                'cart' => __('A variant product in the POS cart is missing its exact variant.'),
            ]);
        }
    }
}
