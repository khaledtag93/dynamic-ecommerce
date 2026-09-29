<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Commerce\ProfitService;
use Illuminate\Console\Command;

class ReconcileLotCogsCommand extends Command
{
    protected $signature = 'commerce:reconcile-lot-cogs
                            {--after-id=0 : Only scan orders with a higher ID}
                            {--limit=500 : Maximum orders to scan in this run}
                            {--apply : Persist corrected order COGS/profit totals}';

    protected $description = 'Audit and optionally reconcile historical order COGS from lot provenance';

    public function handle(ProfitService $profitService): int
    {
        $afterId = max(0, (int) $this->option('after-id'));
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $apply = (bool) $this->option('apply');

        $orders = Order::query()
            ->where('id', '>', $afterId)
            ->whereHas('items', fn ($query) => $query->whereNotNull('meta'))
            ->with(['items:id,order_id,meta'])
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

        foreach ($orders as $order) {
            if (! $this->hasLotProvenance($order)) {
                continue;
            }

            $eligible++;
            $expected = $profitService->calculateOrderTotals($order);
            $currentCost = $this->money($order->cost_total);
            $currentProfit = $this->money($order->profit_total);

            if ($currentCost === $expected['cost_total']
                && $currentProfit === $expected['profit_total']) {
                continue;
            }

            $changed++;
            $this->line(sprintf(
                'Order #%d %s | cost %s -> %s | profit %s -> %s',
                $order->id,
                $order->order_number ?: '(no number)',
                $currentCost,
                $expected['cost_total'],
                $currentProfit,
                $expected['profit_total'],
            ));

            if ($apply) {
                $profitService->refreshOrderTotals($order);
                $applied++;
            }
        }
        $lastId = (int) $orders->max('id');
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->info(sprintf(
            '%s complete | scanned=%d | lot-provenance=%d | changed=%d | applied=%d | last_id=%d',
            $mode,
            $orders->count(),
            $eligible,
            $changed,
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
