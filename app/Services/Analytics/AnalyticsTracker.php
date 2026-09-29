<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class AnalyticsTracker
{
    public function track(string $eventType, ?string $entityType = null, int|string|null $entityId = null, array $meta = []): void
    {
        AnalyticsEvent::query()->create([
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'occurred_at' => now(),
            'meta' => $this->buildMetaPayload($meta),
        ]);
    }

    public function trackProductView(Product $product, array $meta = []): void
    {
        $lastViewAt = $this->currentVisitorQuery()
            ->where('event_type', AnalyticsEvent::EVENT_VIEW_PRODUCT)
            ->where('entity_type', AnalyticsEvent::ENTITY_PRODUCT)
            ->where('entity_id', (string) $product->id)
            ->latest('id')
            ->value('occurred_at');

        if ($lastViewAt && now()->diffInSeconds($lastViewAt) < 20) {
            return;
        }

        $this->track(AnalyticsEvent::EVENT_VIEW_PRODUCT, AnalyticsEvent::ENTITY_PRODUCT, $product->id, array_merge([
            'product_id' => (int) $product->id,
            'product_slug' => $product->slug,
            'category_id' => $product->category_id,
            'price' => (float) $product->current_price,
        ], $meta));
    }

    public function trackCartViewed(array $cartSummary): void
    {
        $this->track(AnalyticsEvent::EVENT_VIEW_CART, AnalyticsEvent::ENTITY_CART, null, [
            'items_count' => (int) ($cartSummary['items_count'] ?? 0),
            'subtotal' => (float) ($cartSummary['subtotal'] ?? 0),
            'discount' => (float) ($cartSummary['discount'] ?? 0),
            'total' => (float) ($cartSummary['total'] ?? 0),
            'coupon_code' => $cartSummary['coupon_code'] ?? null,
            'product_ids' => collect($cartSummary['items'] ?? [])->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]);
    }

    public function trackAddToCart(Product $product, int $quantity = 1, ?int $variantId = null, array $meta = []): void
    {
        $this->track(AnalyticsEvent::EVENT_ADD_TO_CART, AnalyticsEvent::ENTITY_PRODUCT, $product->id, array_merge([
            'product_id' => (int) $product->id,
            'product_slug' => $product->slug,
            'category_id' => $product->category_id,
            'quantity' => max(1, $quantity),
            'variant_id' => $variantId,
            'price' => (float) $product->current_price,
        ], $meta));
    }

    public function trackRemoveFromCart(?int $productId, int $quantity = 1, array $meta = []): void
    {
        $this->track(AnalyticsEvent::EVENT_REMOVE_FROM_CART, AnalyticsEvent::ENTITY_PRODUCT, $productId, array_merge([
            'product_id' => $productId,
            'quantity' => max(1, $quantity),
        ], $meta));
    }

    public function trackCheckoutStart(array $cartSummary): void
    {
        $this->track(AnalyticsEvent::EVENT_CHECKOUT_START, AnalyticsEvent::ENTITY_CHECKOUT, null, [
            'items_count' => (int) ($cartSummary['items_count'] ?? 0),
            'subtotal' => (float) ($cartSummary['subtotal'] ?? 0),
            'discount' => (float) ($cartSummary['discount'] ?? 0),
            'total' => (float) ($cartSummary['total'] ?? 0),
            'coupon_code' => $cartSummary['coupon_code'] ?? null,
            'product_ids' => collect($cartSummary['items'] ?? [])->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]);
    }

    public function trackPurchaseSuccess(Order $order): void
    {
        $this->syncRealizedPurchase($order);
    }

    public function syncRealizedPurchase(Order $order): void
    {
        $order->loadMissing('items');

        $eventQuery = AnalyticsEvent::query()
            ->where('event_type', AnalyticsEvent::EVENT_PURCHASE_SUCCESS)
            ->where('entity_type', AnalyticsEvent::ENTITY_ORDER)
            ->where('entity_id', (string) $order->id);

        $isRealized = $order->status === Order::STATUS_COMPLETED
            && in_array($order->payment_status, [
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            ], true);

        if (! $isRealized) {
            $event = $eventQuery->first();
            $restatementDate = $event?->occurred_at?->toDateString();

            if ($event) {
                $event->delete();
                $this->markDailyStatForRestatement($restatementDate);
            }

            return;
        }

        $payload = [
            'order_id' => (int) $order->id,
            'order_number' => (string) $order->order_number,
            'grand_total' => (float) $order->realized_revenue,
            'original_grand_total' => (float) $order->grand_total,
            'refund_total' => (float) $order->refund_total,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'shipping_total' => (float) $order->shipping_total,
            'payment_method' => (string) $order->payment_method,
            'delivery_method' => (string) $order->delivery_method,
            'coupon_code' => $order->coupon_code,
            'items_count' => (int) $order->items->sum('quantity'),
            'product_ids' => $order->items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'line_items' => $this->buildRealizedLineItems($order),
        ];

        $event = $eventQuery->first();
        $attributes = [
            'user_id' => $order->user_id,
            'session_id' => null,
            'occurred_at' => $event?->occurred_at ?: ($order->delivered_at ?: now()),
            'meta' => $payload,
        ];

        if ($event) {
            $event->fill($attributes);

            if ($event->isDirty()) {
                $event->save();
                $this->markDailyStatForRestatement($event->occurred_at?->toDateString());
            }

            return;
        }

        $event = AnalyticsEvent::query()->create(array_merge($attributes, [
            'event_type' => AnalyticsEvent::EVENT_PURCHASE_SUCCESS,
            'entity_type' => AnalyticsEvent::ENTITY_ORDER,
            'entity_id' => (string) $order->id,
        ]));

        $this->markDailyStatForRestatement($event->occurred_at?->toDateString());
    }

    protected function buildRealizedLineItems(Order $order): array
    {
        $items = $order->items->values();
        $lineTotalCents = (int) $items->sum(
            fn ($item) => max(0, $this->moneyToCents($item->line_total))
        );
        $realizedRevenueCents = max(0, $this->moneyToCents($order->realized_revenue));
        $lastRevenueIndex = $items
            ->keys()
            ->filter(fn ($index) => $this->moneyToCents($items[$index]->line_total) > 0)
            ->last();
        $allocatedRevenueCents = 0;

        return $items->map(function ($item, $index) use (
            $lineTotalCents,
            $realizedRevenueCents,
            $lastRevenueIndex,
            &$allocatedRevenueCents
        ): array {
            $lineCents = max(0, $this->moneyToCents($item->line_total));

            if ($lineTotalCents <= 0 || $lineCents <= 0) {
                $lineRealizedRevenueCents = 0;
            } elseif ($index === $lastRevenueIndex) {
                $lineRealizedRevenueCents = max(0, $realizedRevenueCents - $allocatedRevenueCents);
            } else {
                $lineRealizedRevenueCents = (int) floor(
                    ($realizedRevenueCents * $lineCents) / $lineTotalCents
                );
                $allocatedRevenueCents += $lineRealizedRevenueCents;
            }

            return [
                'product_id' => (int) $item->product_id,
                'variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'realized_revenue' => round($lineRealizedRevenueCents / 100, 2),
            ];
        })->values()->all();
    }

    protected function markDailyStatForRestatement(?string $date): void
    {
        if (! $date) {
            return;
        }

        $stat = AnalyticsDailyStat::query()->firstOrNew(['stat_date' => $date]);
        $meta = $stat->meta ?? [];
        $meta['restatement_requested_at'] = now()->toIso8601String();
        $meta['restatement_reason'] = 'realized_purchase_changed';

        $stat->meta = $meta;
        $stat->save();
    }

    public function currentVisitorQuery(): Builder
    {
        return AnalyticsEvent::query()->where(function (Builder $query) {
            if (auth()->check()) {
                $query->where('user_id', auth()->id())
                    ->orWhere('session_id', session()->getId());

                return;
            }

            $query->where('session_id', session()->getId());
        });
    }

    private function moneyToCents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    protected function buildMetaPayload(array $meta): array
    {
        $request = request();

        return array_filter(array_merge([
            'url' => $request?->fullUrl(),
            'path' => $request?->path(),
            'route_name' => optional($request?->route())->getName(),
            'method' => $request?->method(),
            'ip_hash' => $request?->ip() ? hash('sha256', (string) $request->ip()) : null,
            'user_agent' => $request?->userAgent(),
            'locale' => app()->getLocale(),
            'referrer' => $request?->headers->get('referer'),
            'utm' => array_filter([
                'source' => $request?->query('utm_source'),
                'medium' => $request?->query('utm_medium'),
                'campaign' => $request?->query('utm_campaign'),
                'term' => $request?->query('utm_term'),
                'content' => $request?->query('utm_content'),
            ]),
        ], $meta), static fn ($value) => ! in_array($value, [null, '', []], true));
    }
}
