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
            ->when($delivery->user_id, fn (Builder $query) => $query->where('user_id', $delivery->user_id))
            ->when(! $delivery->user_id && $delivery->recipient, fn (Builder $query) => $query->where('customer_email', $delivery->recipient))
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$sentAt, $windowEnd])
            ->orderByRaw('COALESCE(placed_at, created_at) asc')
            ->get();

        $matchedOrderIds = [];

        foreach ($orders as $index => $order) {
            $matchedOrderIds[] = $order->id;
            $touchType = $index === 0 ? 'last_touch' : 'assist';
            $weight = $index === 0 ? 1.0 : 0.35;

            if ($couponCode && strcasecmp((string) $couponCode, (string) $order->coupon_code) === 0) {
                $weight = 1.0;
                $touchType = 'coupon_match';
            }

            GrowthAttributionTouch::query()->updateOrCreate(
                [
                    'delivery_id' => $delivery->id,
                    'order_id' => $order->id,
                ],
                [
                    'campaign_id' => $delivery->campaign_id,
                    'experiment_id' => $delivery->experiment_id,
                    'user_id' => $order->user_id ?: $delivery->user_id,
                    'touch_type' => $touchType,
                    'status' => 'attributed',
                    'attribution_weight' => $weight,
                    'revenue' => round((float) $order->grand_total * $weight, 2),
                    'discount_total' => round((float) $order->discount_total * $weight, 2),
                    'profit_total' => round((float) ($order->profit_total ?? 0) * $weight, 2),
                    'occurred_at' => $order->placed_at ?: $order->created_at,
                    'attributed_at' => now(),
                    'meta' => [
                        'coupon_match' => $couponCode ? strcasecmp((string) $couponCode, (string) $order->coupon_code) === 0 : false,
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
        $staleQuery->delete();
    }

    public function summary(): array
    {
        if (! Schema::hasTable('growth_attribution_touches')) {
            return [
                'attributed_orders' => 0,
                'attributed_revenue' => 0.0,
                'attributed_profit' => 0.0,
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

        $attributedOrdersRecent = (int) $recent->distinct('order_id')->count('order_id');
        $liftOrders = $deliveriesWithRevenue > 0 ? round(($attributedOrdersRecent / max(1, $deliveriesWithRevenue)) * 100, 2) : 0.0;
        $liftRevenue = (float) $recent->sum('revenue');

        return [
            'attributed_orders' => (int) $base->distinct('order_id')->count('order_id'),
            'attributed_revenue' => round((float) $base->sum('revenue'), 2),
            'attributed_profit' => round((float) $base->sum('profit_total'), 2),
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
            ->selectRaw('campaign_id, COUNT(DISTINCT order_id) as orders_count, SUM(revenue) as revenue_total, SUM(profit_total) as profit_total')
            ->groupBy('campaign_id')
            ->with('campaign')
            ->orderByDesc('revenue_total')
            ->limit($limit)
            ->get();
    }
}
