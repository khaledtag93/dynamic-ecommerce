<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Commerce\ProfitService;
use App\Services\Growth\GrowthAttributionService;
use Illuminate\Console\Command;

class ReconcileLotCogsCommand extends Command
{
    protected $signature = 'commerce:reconcile-lot-cogs
                            {--after-id=0 : Only scan orders with a higher ID}
                            {--limit=500 : Maximum orders to scan in this run}
                            {--apply : Persist corrected consumed-cost/profit totals}';

    protected $description = 'Audit and optionally reconcile historical consumed cost, realized COGS, and profit from lot provenance';

    public function handle(
        ProfitService $profitService,
        GrowthAttributionService $growthAttributionService,
    ): int
    {
        $afterId = max(0, (int) $this->option('after-id'));
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $apply = (bool) $this->option('apply');

        $orders = Order::query()
            ->where('id', '>', $afterId)
            ->whereHas('items', fn ($query) => $query->whereNotNull('meta'))
            ->with(['items:id,order_id,line_total,unit_cost,quantity,profit_amount,meta'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No candidate orders found after the requested ID.');

            return self::SUCCESS;
        }
        $eligible = 0;
        $changed = 0;
        $applied = 0;
        $lineProfitChanges = 0;
        $attributionProfitChanges = 0;

        foreach ($orders as $order) {
            if (! $this->hasLotProvenance($order)) {
                continue;
            }

            $eligible++;
            $economics = $profitService->calculateOrderEconomics($order);
            $expected = [
                'cost_total' => $economics['original_consumed_cost'],
                'profit_total' => $economics['profit_total'],
            ];
            $currentCost = $this->money($order->cost_total);
            $currentProfit = $this->money($order->profit_total);
            $itemProfitChanges = $profitService->countOrderItemProfitChanges($order);
            $attributionProfitChange = $growthAttributionService->countOrderProfitSnapshotChanges(
                $order,
                $expected['profit_total'],
            );
            $lineProfitChanges += $itemProfitChanges;
            $attributionProfitChanges += $attributionProfitChange;

            $coreChanged = $currentCost !== $expected['cost_total']
                || $currentProfit !== $expected['profit_total']
                || $itemProfitChanges > 0;

            if (! $coreChanged && $attributionProfitChange === 0) {
                continue;
            }

            $changed++;
            $grossMargin = $economics['gross_margin_percent'] === null
                ? 'n/a'
                : number_format((float) $economics['gross_margin_percent'], 2, '.', '').'%';
            $this->line(sprintf(
                'Order #%d %s | original consumed cost %s -> %s | recovered restock cost %s | realized COGS %s | profit %s -> %s | gross margin %s | line-profit changes %d | attribution-profit changes %d',
                $order->id,
                $order->order_number ?: '(no number)',
                $currentCost,
                $expected['cost_total'],
                $economics['recovered_restock_cost'],
                $economics['realized_cogs'],
                $currentProfit,
                $expected['profit_total'],
                $grossMargin,
                $itemProfitChanges,
                $attributionProfitChange,
            ));

            if ($apply) {
                if ($coreChanged) {
                    $profitService->refreshOrderTotals($order);
                }

                $growthAttributionService->refreshOrderAttribution((int) $order->id);
                $applied++;
            }
        }
        $lastId = (int) $orders->max('id');
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->info(sprintf(
            '%s complete | scanned=%d | lot-provenance=%d | changed=%d | line-profit-changes=%d | attribution-profit-changes=%d | applied=%d | last_id=%d',
            $mode,
            $orders->count(),
            $eligible,
            $changed,
            $lineProfitChanges,
            $attributionProfitChanges,
            $applied,
            $lastId,
        ));

        if (! $apply && $changed > 0) {
            $this->warn('Dry-run only. Re-run the same bounded range with --apply after review.');
        }

        return self::SUCCESS;
    }

    protected function hasLotProvenance(Order $order): bool
    {
        return $order->items->contains(function ($item): bool {
            $allocations = data_get($item->meta, 'inventory_lot_allocations');

            return is_array($allocations) && $allocations !== [];
        });
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
