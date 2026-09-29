<?php

namespace App\Services\Analytics;

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
