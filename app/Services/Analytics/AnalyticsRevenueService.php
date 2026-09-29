<?php

namespace App\Services\Analytics;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Services\Commerce\OrderRevenueAllocationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsRevenueService
{
    public function __construct(
        protected OrderRevenueAllocationService $orderRevenueAllocationService
    ) {
    }

    public function couponPerformance(
        Carbon $from,
        Carbon $to,
        ?int $limit = null
    ): Collection {
        $query = $this->realizedOrdersQuery($from, $to)
            ->reorder()
            ->whereNotNull('coupon_code')
            ->select(
                'coupon_code',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(grand_total - refund_total) as revenue_gross'),
                DB::raw('SUM(discount_total) as discount_total'),
                DB::raw('SUM(profit_total) as profit_total'),
                DB::raw('AVG(grand_total - refund_total) as average_order_value')
            )
            ->groupBy('coupon_code')
            ->orderByDesc('revenue_gross');

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        $rows = $query->get();
        $coupons = Coupon::query()
            ->whereIn('code', $rows->pluck('coupon_code')->filter()->all())
            ->get()
            ->keyBy('code');

        return $rows->map(function ($row) use ($coupons) {
            $revenue = round((float) $row->revenue_gross, 2);
            $profit = round((float) $row->profit_total, 2);
            $coupon = $coupons->get($row->coupon_code);

            $row->realized_revenue = $revenue;
            $row->profit_total = $profit;
            $row->gross_margin_percent = $revenue > 0
                ? round(($profit / $revenue) * 100, 2)
                : null;
            $row->usage_limit = $coupon?->usage_limit;
            $row->used_count = $coupon?->used_count;
            $row->is_active = $coupon?->is_active;
            $row->remaining_usage = $coupon && $coupon->usage_limit !== null
                ? max(0, (int) $coupon->usage_limit - (int) $coupon->used_count)
                : null;

            return $row;
        });
    }

    public function topVariantsForProduct(
        Product $product,
        Carbon $from,
        Carbon $to,
        int $limit = 8
    ): Collection {
        $buckets = [];

        $this->realizedOrdersQuery($from, $to)
            ->whereHas('items', fn (Builder $query) => $query
                ->where('product_id', $product->id))
            ->with(['items' => fn ($query) => $query->orderBy('id')])
            ->chunkById(200, function (Collection $orders) use ($product, &$buckets): void {
                foreach ($orders as $order) {
                    $allocations = $this->orderRevenueAllocationService
                        ->allocateCents($order);

                    foreach ($order->items as $item) {
                        if ((int) $item->product_id !== (int) $product->id) {
                            continue;
                        }

                        $key = $item->product_variant_id
                            ? 'variant:'.(int) $item->product_variant_id
                            : 'default';

                        $buckets[$key] ??= [
                            'product_variant_id' => $item->product_variant_id
                                ? (int) $item->product_variant_id
                                : null,

                            'variant_name' => $item->variant_name,
                            'quantity' => 0,
                            'revenue_cents' => 0,
                        ];

                        $buckets[$key]['quantity'] += (int) $item->quantity;
                        $buckets[$key]['revenue_cents'] += (int) (
                            $allocations[(int) $item->id] ?? 0
                        );
                    }
                }
            });

        return collect($buckets)
            ->map(fn (array $row) => (object) [
                'product_variant_id' => $row['product_variant_id'],
                'variant_name' => $row['variant_name'],
                'quantity' => $row['quantity'],
                'revenue_gross' => round($row['revenue_cents'] / 100, 2),
            ])
            ->sortByDesc('revenue_gross')
            ->take(max(1, $limit))
            ->values();
    }

    public function topDiscountedProduct(
        Carbon $from,
        Carbon $to
    ): ?object {
        $buckets = [];

        $this->realizedOrdersQuery($from, $to)
            ->where('discount_total', '>', 0)
            ->whereHas('items', fn (Builder $query) => $query
                ->whereNotNull('product_id'))
            ->with(['items' => fn ($query) => $query->orderBy('id')])
            ->chunkById(200, function (Collection $orders) use (&$buckets): void {
                foreach ($orders as $order) {
                    $allocations = $this->orderRevenueAllocationService
                        ->allocateCents($order);

                    foreach ($order->items as $item) {
                        $productId = (int) $item->product_id;

                        if ($productId <= 0) {
                            continue;
                        }

                        $buckets[$productId] ??= [
                            'product_id' => $productId,
                            'product_name' => $item->product_name,

                            'quantity' => 0,
                            'revenue_cents' => 0,
                        ];

                        $buckets[$productId]['quantity'] += (int) $item->quantity;
                        $buckets[$productId]['revenue_cents'] += (int) (
                            $allocations[(int) $item->id] ?? 0
                        );
                    }
                }
            });

        $row = collect($buckets)
            ->sortByDesc('revenue_cents')
            ->first();

        if (! $row) {
            return null;
        }

        return (object) [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'revenue_gross' => round($row['revenue_cents'] / 100, 2),
        ];
    }

    protected function realizedOrdersQuery(Carbon $from, Carbon $to): Builder
    {
        return Order::query()
            ->commerciallyRealized()
            ->whereBetween(
                DB::raw('DATE(COALESCE(placed_at, created_at))'),
                [$from->toDateString(), $to->toDateString()]
            )
            ->orderBy('id');
    }
}
