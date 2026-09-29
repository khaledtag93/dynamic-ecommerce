<?php

namespace App\Services\Growth;

use App\Models\GrowthAttributionTouch;
use App\Models\GrowthDelivery;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GrowthAttributionService
{
    public function syncRecentAttribution(int $limit = 250): void
    {
        if (! Schema::hasTable('growth_attribution_touches') || ! Schema::hasTable('growth_deliveries')) {
            return;
        }

        $windowHours = max(1, (int) config('growth.conversion_window_hours', 168));
        $cutoff = now()->subHours($windowHours + 24);

        $deliveries = GrowthDelivery::query()
            ->with(['campaign', 'experiment', 'user'])
            ->whereIn('status', ['sent', 'delivered', 'simulated'])
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $cutoff)
            ->latest('sent_at')
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            $this->syncForDelivery($delivery, $windowHours);
        }

        $staleOrderIds = GrowthAttributionTouch::query()
            ->join('orders', 'orders.id', '=', 'growth_attribution_touches.order_id')
            ->where(function (Builder $query) {
                $query->whereNull('growth_attribution_touches.attributed_at')
                    ->orWhereColumn('orders.updated_at', '>=', 'growth_attribution_touches.attributed_at');
            })
            ->distinct()
            ->limit($limit)
            ->pluck('growth_attribution_touches.order_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($staleOrderIds as $orderId) {
            $this->normalizeOrderAttribution($orderId);
        }
    }

    public function syncForDelivery(GrowthDelivery $delivery, ?int $windowHours = null): void
    {
        if (! Schema::hasTable('growth_attribution_touches')) {
            return;
        }

        $windowHours ??= max(1, (int) config('growth.conversion_window_hours', 168));
        $sentAt = $delivery->sent_at ?: $delivery->created_at;

        if (! $sentAt) {
            return;
        }

        $windowEnd = $sentAt->copy()->addHours($windowHours);
        $couponCode = data_get($delivery->payload, 'coupon_code') ?: data_get($delivery->meta, 'coupon_code');

        $orders = Order::query()
            ->commerciallyRealized()
            ->when($delivery->user_id, fn (Builder $query) => $query->where('user_id', $delivery->user_id))
            ->when(! $delivery->user_id && $delivery->recipient, fn (Builder $query) => $query->where('customer_email', $delivery->recipient))
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$sentAt, $windowEnd])
            ->orderByRaw('COALESCE(placed_at, created_at) asc')
            ->get();

        $matchedOrderIds = [];

        foreach ($orders as $order) {
            $matchedOrderIds[] = $order->id;
            $couponMatch = $couponCode
                && strcasecmp((string) $couponCode, (string) $order->coupon_code) === 0;

            GrowthAttributionTouch::query()->updateOrCreate(
                [
                    'delivery_id' => $delivery->id,
                    'order_id' => $order->id,
                ],
                [
                    'campaign_id' => $delivery->campaign_id,
                    'experiment_id' => $delivery->experiment_id,
                    'user_id' => $order->user_id ?: $delivery->user_id,
                    'touch_type' => $couponMatch ? 'coupon_match' : 'assist',
                    'status' => 'attributed',
                    'attribution_weight' => $couponMatch ? 1.0 : 0.35,
                    'revenue' => 0,
                    'discount_total' => 0,
                    'profit_total' => 0,
                    'occurred_at' => $order->placed_at ?: $order->created_at,
                    'attributed_at' => now(),
                    'meta' => [
                        'coupon_match' => (bool) $couponMatch,
                        'order_coupon_code' => $order->coupon_code,
                        'delivery_status' => $delivery->status,
                        'channel' => $delivery->channel,
                        'provider' => $delivery->provider,
                        'window_hours' => $windowHours,
                    ],
                ]
            );
        }

        $staleQuery = GrowthAttributionTouch::query()->where('delivery_id', $delivery->id);
        if ($matchedOrderIds !== []) {
            $staleQuery->whereNotIn('order_id', $matchedOrderIds);
        }

        $staleOrderIds = (clone $staleQuery)
            ->pluck('order_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $staleQuery->delete();

        foreach (array_values(array_unique(array_merge($matchedOrderIds, $staleOrderIds))) as $orderId) {
            $this->normalizeOrderAttribution((int) $orderId);
        }
    }

    public function countOrderProfitSnapshotChanges(Order $order, mixed $expectedProfitTotal = null): int
    {
        if (! Schema::hasTable('growth_attribution_touches')) {
            return 0;
        }

        $touches = GrowthAttributionTouch::query()
            ->where('order_id', $order->id)
            ->get(['profit_total']);

        if ($touches->isEmpty()) {
            return 0;
        }

        $touchProfitCents = (int) round((float) $touches->sum('profit_total') * 100);
        $expectedProfitCents = (int) round((float) ($expectedProfitTotal ?? $order->profit_total ?? 0) * 100);

        return $touchProfitCents === $expectedProfitCents ? 0 : 1;
    }

    public function refreshOrderAttribution(int $orderId): void
    {
        if (! Schema::hasTable('growth_attribution_touches')) {
            return;
        }

        $this->normalizeOrderAttribution($orderId);
    }

    protected function normalizeOrderAttribution(int $orderId): void
    {
        $order = Order::query()->commerciallyRealized()->find($orderId);

        if (! $order) {
            GrowthAttributionTouch::query()->where('order_id', $orderId)->delete();
            return;
        }

        $touches = GrowthAttributionTouch::query()
            ->where('order_id', $orderId)
            ->with('delivery')
            ->get();

        if ($touches->isEmpty()) {
            return;
        }

        $latestTouch = $touches->sortByDesc(function (GrowthAttributionTouch $touch): int {
            $sentAt = $touch->delivery?->sent_at ?: $touch->created_at;

            return (($sentAt?->getTimestamp() ?? 0) * 1000000) + (int) $touch->id;
        })->first();

        $weighted = $touches->map(function (GrowthAttributionTouch $touch) use ($latestTouch): array {
            $couponMatch = (bool) data_get($touch->meta, 'coupon_match', false)
                || $touch->touch_type === 'coupon_match';
            $isLastTouch = ! $couponMatch && $latestTouch && $touch->is($latestTouch);
            $rawWeight = ($couponMatch || $isLastTouch) ? 1.0 : 0.35;

            return [
                'touch' => $touch,
                'coupon_match' => $couponMatch,
                'is_last_touch' => $isLastTouch,
                'raw_weight' => $rawWeight,
            ];
        })->values();

        $rawTotal = max(0.0001, (float) $weighted->sum('raw_weight'));
        $revenueCents = (int) round((float) $order->realized_revenue * 100);
        $discountCents = (int) round((float) $order->discount_total * 100);
        $profitCents = (int) round((float) ($order->profit_total ?? 0) * 100);
        $allocatedWeight = 0.0;
        $allocatedRevenue = 0;
        $allocatedDiscount = 0;
        $allocatedProfit = 0;
        $lastIndex = $weighted->count() - 1;

        foreach ($weighted as $index => $entry) {
            /** @var GrowthAttributionTouch $touch */
            $touch = $entry['touch'];
            $ratio = (float) $entry['raw_weight'] / $rawTotal;

            if ($index === $lastIndex) {
                $weight = round(max(0, 1 - $allocatedWeight), 4);
                $touchRevenueCents = $revenueCents - $allocatedRevenue;
                $touchDiscountCents = $discountCents - $allocatedDiscount;
                $touchProfitCents = $profitCents - $allocatedProfit;
            } else {
                $weight = floor($ratio * 10000) / 10000;
                $touchRevenueCents = (int) floor($revenueCents * $ratio);
                $touchDiscountCents = (int) floor($discountCents * $ratio);
                $touchProfitCents = (int) ($profitCents * $ratio);

                $allocatedWeight += $weight;
                $allocatedRevenue += $touchRevenueCents;
                $allocatedDiscount += $touchDiscountCents;
                $allocatedProfit += $touchProfitCents;
            }

            $meta = $touch->meta ?? [];
            $meta['raw_attribution_weight'] = (float) $entry['raw_weight'];
            $meta['normalized_attribution'] = true;

            $touch->update([
                'touch_type' => $entry['coupon_match']
                    ? 'coupon_match'
                    : ($entry['is_last_touch'] ? 'last_touch' : 'assist'),
                'attribution_weight' => $weight,
                'revenue' => round($touchRevenueCents / 100, 2),
                'discount_total' => round($touchDiscountCents / 100, 2),
                'profit_total' => round($touchProfitCents / 100, 2),
                'attributed_at' => now(),
                'meta' => $meta,
            ]);
        }
    }

    public function summary(): array
    {
        if (! Schema::hasTable('growth_attribution_touches')) {
            return [
                'attributed_orders' => 0,
                'attributed_revenue' => 0.0,
                'attributed_profit' => 0.0,
                'attributed_gross_margin_percent' => null,
                'coupon_assisted_orders' => 0,
                'lift_revenue_30d' => 0.0,
                'lift_orders_30d' => 0.0,
            ];
        }

        $base = GrowthAttributionTouch::query();
        $recent = GrowthAttributionTouch::query()->where('occurred_at', '>=', now()->subDays(30));

        $deliveriesWithRevenue = GrowthDelivery::query()
            ->whereIn('status', ['sent', 'delivered', 'simulated'])
            ->where('sent_at', '>=', now()->subDays(30))
            ->count();

        $attributedOrdersRecent = (int) (clone $recent)->distinct('order_id')->count('order_id');
        $liftOrders = $deliveriesWithRevenue > 0 ? round(($attributedOrdersRecent / max(1, $deliveriesWithRevenue)) * 100, 2) : 0.0;
        $liftRevenue = (float) (clone $recent)->sum('revenue');
        $attributedRevenue = round((float) (clone $base)->sum('revenue'), 2);
        $attributedProfit = round((float) (clone $base)->sum('profit_total'), 2);
        $attributedGrossMargin = $attributedRevenue > 0
            ? round(($attributedProfit / $attributedRevenue) * 100, 2)
            : null;

        return [
            'attributed_orders' => (int) (clone $base)->distinct('order_id')->count('order_id'),
            'attributed_revenue' => $attributedRevenue,
            'attributed_profit' => $attributedProfit,
            'attributed_gross_margin_percent' => $attributedGrossMargin,
            'coupon_assisted_orders' => (int) GrowthAttributionTouch::query()->where('touch_type', 'coupon_match')->distinct('order_id')->count('order_id'),
            'lift_revenue_30d' => round($liftRevenue, 2),
            'lift_orders_30d' => $liftOrders,
        ];
    }

    public function campaignBreakdown(int $limit = 6): Collection
    {
        if (! Schema::hasTable('growth_attribution_touches')) {
            return collect();
        }

        return GrowthAttributionTouch::query()
            ->selectRaw('campaign_id, COUNT(*) as touches_count, COUNT(DISTINCT order_id) as orders_count, SUM(revenue) as revenue_total, SUM(profit_total) as profit_total')
            ->groupBy('campaign_id')
            ->with('campaign')
            ->orderByDesc('revenue_total')
            ->limit($limit)
            ->get()
            ->map(function (GrowthAttributionTouch $row): array {
                $revenue = round((float) $row->revenue_total, 2);
                $profit = round((float) $row->profit_total, 2);

                return [
                    'campaign_id' => $row->campaign_id,
                    'campaign_name' => $row->campaign?->name ?: __('Unassigned'),
                    'revenue' => $revenue,
                    'profit' => $profit,
                    'gross_margin_percent' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : null,
                    'orders' => (int) $row->orders_count,
                    'touches' => (int) $row->touches_count,
                ];
            });
    }
}
