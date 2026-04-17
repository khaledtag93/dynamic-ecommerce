<?php

namespace App\Services\Frontend;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\CouponService;
use App\Services\Commerce\PromotionEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(protected CouponService $couponService, protected PromotionEngine $promotionEngine)
    {
    }

    public function currentSessionId(): string
    {
        return (string) request()->session()->getId();
    }

    public function baseQuery()
    {
        return CartItem::query()
            ->when(
                Auth::check(),
                fn ($q) => $q->where('user_id', Auth::id())
            )
            ->when(
                ! Auth::check(),
                fn ($q) => $q->where('session_id', $this->currentSessionId())
            );
    }

    public function items()
    {
        return $this->baseQuery()
            ->with(['product', 'variant'])
            ->latest()
            ->get();
    }

    public function summary(): array
    {
        $items = $this->items();
        $subtotal = (float) $items->sum(fn ($item) => $item->line_total);
        $count = (int) $items->sum('quantity');
        $shipping = 0.00;
        $tax = 0.00;
        $couponSummary = $this->couponService->resolveDiscountSummary($subtotal);
        $couponDiscount = (float) ($couponSummary['discount'] ?? 0);
        $promotionSummary = $this->promotionEngine->resolve($items, $subtotal);
        $promotionDiscount = (float) ($promotionSummary['discount'] ?? 0);
        $discount = $couponDiscount + $promotionDiscount;

        return [
            'items' => $items,
            'items_count' => $count,
            'subtotal' => round($subtotal, 2),
            'shipping' => $shipping,
            'tax' => $tax,
            'discount' => round($discount, 2),
            'coupon' => $couponSummary['coupon'],
            'coupon_discount' => round($couponDiscount, 2),
            'coupon_code' => $couponSummary['code'],
            'coupon_label' => $couponSummary['label'],
            'promotion_discount' => round($promotionDiscount, 2),
            'promotion_label' => $promotionSummary['label'],
            'total' => round(max(0, $subtotal + $shipping + $tax - $discount), 2),
        ];
    }

    public function count(): int
    {
        return (int) $this->baseQuery()->sum('quantity');
    }

   public function add(Product $product, int $quantity = 1, ?int $variantId = null): CartItem
{
    $variant = null;

    if ($variantId) {
        $variant = ProductVariant::query()
            ->with('attributes.attribute')
            ->where('product_id', $product->id)
            ->whereKey($variantId)
            ->firstOrFail();
    } elseif ($product->has_variants) {
        $variant = ProductVariant::query()
            ->with('attributes.attribute')
            ->where('product_id', $product->id)
            ->where('status', true)
            ->where('stock', '>', 0)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    $availableStock = $variant
        ? (int) ($variant->stock ?? 0)
        : (int) $product->quantity_value;

    $price = $variant
        ? (float) $variant->current_price
        : (float) $product->current_price;

    if ($availableStock < 1) {
        throw ValidationException::withMessages([
            'cart' => __('This product is currently out of stock.'),
        ]);
    }

    $lookup = $this->baseQuery()
        ->where('product_id', $product->id)
        ->where('product_variant_id', $variant?->id)
        ->first();

    $newQty = min(
        ($lookup?->quantity ?? 0) + max(1, $quantity),
        $availableStock
    );

    $variantName = $variant ? $this->variantLabel($variant) : null;
    $imagePath = $this->resolveSnapshotImage($product, $variant);

    if ($lookup) {
        $lookup->update([
            'quantity' => $newQty,
            'unit_price' => $price,
            'product_name' => $product->name,
            'variant_name' => $variantName,
            'sku' => $variant?->sku ?? $product->sku,
            'image' => $imagePath,
            'meta' => [
                'product_slug' => $product->slug,
            ],
        ]);

        return $lookup->fresh();
    }

    return CartItem::create([
        'user_id' => Auth::id(),
        'session_id' => Auth::check() ? null : $this->currentSessionId(),
        'product_id' => $product->id,
        'product_variant_id' => $variant?->id,
        'product_name' => $product->name,
        'variant_name' => $variantName,
        'sku' => $variant?->sku ?? $product->sku,
        'image' => $imagePath,
        'unit_price' => $price,
        'quantity' => min(max(1, $quantity), $availableStock),
        'meta' => [
            'product_slug' => $product->slug,
        ],
    ]);
}
    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        $this->ensureOwns($item);

        $availableStock = $item->variant
            ? (int) ($item->variant->stock ?? 0)
            : (int) optional($item->product)->quantity_value;

        $quantity = max(1, min($quantity, max(1, $availableStock)));

        $item->update([
            'quantity' => $quantity,
        ]);

        return $item->fresh();
    }

    public function remove(CartItem $item): void
    {
        $this->ensureOwns($item);

        $item->delete();
    }

    public function clear(): void
    {
        $this->baseQuery()->delete();
    }

    public function mergeGuestCartIntoUserCart(int $userId): void
    {
        $sessionId = $this->currentSessionId();

        $guestItems = CartItem::query()
            ->whereNull('user_id')
            ->where('session_id', $sessionId)
            ->get();

        foreach ($guestItems as $guestItem) {
            $existing = CartItem::query()
                ->where('user_id', $userId)
                ->where('product_id', $guestItem->product_id)
                ->where('product_variant_id', $guestItem->product_variant_id)
                ->first();

            $availableStock = $guestItem->variant
                ? (int) ($guestItem->variant->stock ?? 0)
                : (int) optional($guestItem->product)->quantity_value;

            if ($existing) {
                $existing->update([
                    'quantity' => min($existing->quantity + $guestItem->quantity, max(1, $availableStock)),
                    'unit_price' => $guestItem->unit_price,
                    'product_name' => $guestItem->product_name,
                    'variant_name' => $guestItem->variant_name,
                    'sku' => $guestItem->sku,
                    'image' => $guestItem->image,
                    'meta' => $guestItem->meta,
                ]);

                $guestItem->delete();
                continue;
            }

            $guestItem->update([
                'user_id' => $userId,
                'session_id' => null,
                'quantity' => min($guestItem->quantity, max(1, $availableStock)),
            ]);
        }
    }

    protected function ensureOwns(CartItem $item): void
    {
        $owned = Auth::check()
            ? (int) $item->user_id === (int) Auth::id()
            : $item->session_id === $this->currentSessionId();

        abort_unless($owned, 403);
    }

    protected function variantLabel(ProductVariant $variant): string
    {
        $attributes = $variant->attributes ?? collect();

        if ($attributes->isEmpty()) {
            return $variant->sku ?: 'Default Variant';
        }

        return $attributes
            ->map(fn ($item) => trim(($item->attribute?->name ?? 'Option') . ': ' . $item->attribute_value))
            ->implode(' / ');
    }

    protected function resolveSnapshotImage(Product $product, ?ProductVariant $variant = null): ?string
    {
        if ($variant && ! empty($variant->image)) {
            return $variant->image;
        }

        if ($product->relationLoaded('mainImage') && $product->mainImage && ! empty($product->mainImage->image_path)) {
            return $product->mainImage->image_path;
        }

        if ($product->relationLoaded('productImages')) {
            $firstImage = $product->productImages->first();

            if ($firstImage && ! empty($firstImage->image_path)) {
                return $firstImage->image_path;
            }
        }

        return null;
    }
}