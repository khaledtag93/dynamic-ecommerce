<?php

namespace App\Services\Analytics;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Services\Commerce\OrderRevenueAllocationService;
use App\Services\Commerce\ProfitService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsRevenueService
{
    public function __construct(
        protected OrderRevenueAllocationService $orderRevenueAllocationService,
        protected ProfitService $profitService
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
                DB::raw("SUM(CASE WHEN payment_status != '".Order::PAYMENT_STATUS_REFUNDED."' THEN 1 ELSE 0 END) as orders_count"),
                DB::raw('SUM(grand_total - COALESCE(commercial_refund_total, refund_total)) as revenue_gross'),
                DB::raw("SUM(CASE WHEN payment_status != '".Order::PAYMENT_STATUS_REFUNDED."' THEN discount_total ELSE 0 END) as discount_total"),
                DB::raw('SUM(profit_total) as profit_total')
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

            return (object) [
                'coupon_code' => (string) $row->coupon_code,
                'orders_count' => (int) $row->orders_count,
                'revenue_gross' => $revenue,
                'realized_revenue' => $revenue,
                'discount_total' => round((float) $row->discount_total, 2),
                'profit_total' => $profit,
                'gross_margin_percent' => $revenue > 0
                    ? round(($profit / $revenue) * 100, 2)
                    : null,
                'average_order_value' => (int) $row->orders_count > 0
                    ? round($revenue / (int) $row->orders_count, 2)
                    : 0.0,
                'usage_limit' => $coupon?->usage_limit,
                'used_count' => $coupon?->used_count,
                'is_active' => $coupon?->is_active,
                'remaining_usage' => $coupon && $coupon->usage_limit !== null
                    ? max(0, (int) $coupon->usage_limit - (int) $coupon->used_count)
                    : null,
            ];
        });
    }

    public function topVariantsForProduct(
        Product $product,
        Carbon $from,
        Carbon $to,
        int $limit = 8
    ): Collection {
        $buckets = [];

        $this->economicOrdersQuery($from, $to)
            ->whereHas('items', fn (Builder $query) => $query
                ->where('product_id', $product->id))
            ->with(['items' => fn ($query) => $query->orderBy('id')])
            ->chunkById(200, function (Collection $orders) use ($product, &$buckets): void {
                foreach ($orders as $order) {
                    $allocations = $this->orderRevenueAllocationService
                        ->allocateCents($order);
                    $quantities = $this->orderRevenueAllocationService
                        ->realizedQuantities($order);

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
                            'cogs_cents' => 0,
                            'profit_cents' => 0,
                        ];

                        $realizedRevenueCents = (int) (
                            $allocations[(int) $item->id] ?? 0
                        );
                        $economics = $this->profitService->calculateOrderItemEconomics(
                            $item,
                            $this->centsToMoney($realizedRevenueCents)
                        );

                        $buckets[$key]['quantity'] += (int) ($quantities[(int) $item->id] ?? 0);
                        $buckets[$key]['revenue_cents'] += $realizedRevenueCents;
                        $buckets[$key]['cogs_cents'] += $this->moneyToCents($economics['realized_cogs']);
                        $buckets[$key]['profit_cents'] += $this->moneyToCents($economics['profit_total']);
                    }
                }
            });

        return collect($buckets)
            ->map(function (array $row): object {
                $revenue = $this->centsToMoney($row['revenue_cents']);
                $cogs = $this->centsToMoney($row['cogs_cents']);
                $profit = $this->centsToMoney($row['profit_cents']);

                return (object) [
                    'product_variant_id' => $row['product_variant_id'],
                    'variant_name' => $row['variant_name'],
                    'quantity' => $row['quantity'],
                    'revenue_gross' => $revenue,
                    'realized_revenue' => $revenue,
                    'realized_cogs' => $cogs,
                    'profit_total' => $profit,
                    'gross_margin_percent' => $row['revenue_cents'] > 0
                        ? round(($row['profit_cents'] / $row['revenue_cents']) * 100, 2)
                        : null,
                ];
            })
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

    private function moneyToCents(mixed $value): int
    {
        $numeric = is_numeric($value) ? (string) $value : '0';

        return BigDecimal::of($numeric)
            ->multipliedBy('100')
            ->toInt();
    }

    private function centsToMoney(int $cents): string
    {
        return (string) BigDecimal::of((string) $cents)
            ->dividedBy('100', 2, RoundingMode::Unnecessary);
    }

    protected function economicOrdersQuery(Carbon $from, Carbon $to): Builder
    {
        return Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->whereIn('payment_status', [
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
                Order::PAYMENT_STATUS_REFUNDED,
            ])
            ->whereBetween(
                DB::raw('DATE(COALESCE(placed_at, created_at))'),
                [$from->toDateString(), $to->toDateString()]
            )
            ->orderBy('id');
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
