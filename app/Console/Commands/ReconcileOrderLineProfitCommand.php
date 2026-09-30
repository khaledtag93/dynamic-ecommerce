<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Commerce\ProfitService;
use Illuminate\Console\Command;

class ReconcileOrderLineProfitCommand extends Command
{
    protected $signature = 'commerce:reconcile-order-line-profit
                            {--after-id=0 : Only scan orders with an ID above this value}
                            {--limit=500 : Maximum number of orders to scan}
                            {--apply : Persist corrected line-profit allocations}';

    protected $description = 'Audit and optionally correct discounted order line profits using allocated merchandise revenue.';

    public function handle(ProfitService $profitService): int
    {
        $afterId = max(0, (int) $this->option('after-id'));
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $apply = (bool) $this->option('apply');

        $orders = Order::query()
            ->where('id', '>', $afterId)
            ->where('discount_total', '>', 0)
            ->whereHas('items')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No discounted candidate orders found after the requested ID.');

            return self::SUCCESS;
        }

        $changed = 0;
        $lineProfitChanges = 0;
        $applied = 0;

        foreach ($orders as $order) {
            $itemChanges = $profitService->countOrderItemProfitChanges($order);
            $lineProfitChanges += $itemChanges;

            if ($itemChanges === 0) {
                continue;
            }

            $changed++;
            $this->line(sprintf(
                'Order #%d %s | line-profit changes %d | discount %0.2f',
                $order->id,
                $order->order_number ?: '(no number)',
                $itemChanges,
                (float) $order->discount_total,
            ));

            if ($apply) {
                $profitService->refreshOrderItemProfits($order);
                $applied++;
            }
        }

        $lastId = (int) $orders->max('id');
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->info(sprintf(
            '%s complete | scanned=%d | changed=%d | line-profit-changes=%d | applied=%d | last_id=%d',
            $mode,
            $orders->count(),
            $changed,
            $lineProfitChanges,
            $applied,
            $lastId,
        ));

        if (! $apply && $changed > 0) {
            $this->warn('Dry-run only. Re-run the same bounded range with --apply after review.');
        }

        return self::SUCCESS;
    }
}
