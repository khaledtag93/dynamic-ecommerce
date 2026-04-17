<?php

namespace App\Services\Commerce;

use App\Models\Product;
use App\Models\UserBehavior;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BehaviorTrackingService
{
    public function __construct(protected AnalyticsTracker $analyticsTracker)
    {
    }
    public const EVENT_VIEW_PRODUCT = 'view_product';
    public const EVENT_ADD_TO_CART = 'add_to_cart';
    public const EVENT_CHECKOUT_START = 'checkout_start';
    public const EVENT_ORDER_COMPLETE = 'order_complete';
    public const EVENT_PURCHASE_SUCCESS = 'purchase_success';
    public const EVENT_VIEW_CART = 'view_cart';
    public const EVENT_REMOVE_FROM_CART = 'remove_from_cart';

    public function track(string $event, ?int $productId = null, array $meta = []): void
    {
        UserBehavior::query()->create([
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'event' => $event,
            'product_id' => $productId,
            'meta' => $meta,
            'occurred_at' => now(),
        ]);

        $normalizedEvent = match ($event) {
            self::EVENT_ORDER_COMPLETE => self::EVENT_PURCHASE_SUCCESS,
            default => $event,
        };

        $entityType = match ($normalizedEvent) {
            self::EVENT_VIEW_PRODUCT, self::EVENT_ADD_TO_CART, self::EVENT_REMOVE_FROM_CART => 'product',
            self::EVENT_VIEW_CART => 'cart',
            self::EVENT_CHECKOUT_START => 'checkout',
            self::EVENT_PURCHASE_SUCCESS => 'order',
            default => $productId ? 'product' : null,
        };

        $entityId = $normalizedEvent === self::EVENT_PURCHASE_SUCCESS
            ? ($meta['order_id'] ?? null)
            : $productId;

        $payload = array_merge([
            'legacy_event' => $event,
            'product_id' => $productId,
        ], $meta);

        $this->analyticsTracker->track($normalizedEvent, $entityType, $entityId, $payload);
    }

    public function trackProductView(Product $product): void
    {
        $lastViewAt = $this->currentVisitorQuery()
            ->where('event', self::EVENT_VIEW_PRODUCT)
            ->where('product_id', $product->id)
            ->latest('id')
            ->value('occurred_at');

        if ($lastViewAt && now()->diffInSeconds($lastViewAt) < 20) {
            return;
        }

        $this->track(self::EVENT_VIEW_PRODUCT, $product->id, [
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'price' => (float) $product->current_price,
        ]);
    }

    public function currentVisitorQuery(): Builder
    {
        return UserBehavior::query()->where(function (Builder $query) {
            if (auth()->check()) {
                $query->where('user_id', auth()->id())
                    ->orWhere('session_id', session()->getId());
                return;
            }

            $query->where('session_id', session()->getId());
        });
    }

    public function currentVisitorEvents(?string $event = null): Collection
    {
        return $this->currentVisitorQuery()
            ->when($event, fn (Builder $query) => $query->where('event', $event))
            ->latest('id')
            ->get();
    }
}
