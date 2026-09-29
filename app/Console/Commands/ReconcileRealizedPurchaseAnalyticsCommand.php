<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Console\Command;

class ReconcileRealizedPurchaseAnalyticsCommand extends Command
{
    protected $signature = 'analytics:reconcile-realized-purchases
                            {--after-id=0 : Scan orders with an id greater than this value}
                            {--limit=500 : Maximum orders to scan}
                            {--apply : Apply required event corrections}';

    protected $description = 'Audit and optionally reconcile realized-purchase analytics events with authoritative order state';

    public function handle(AnalyticsTracker $tracker): int
    {
        $afterId = max(0, (int) $this->option('after-id'));
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $apply = (bool) $this->option('apply');

        $orders = Order::query()
            ->where('id', '>', $afterId)
            ->with(['items' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $changed = 0;
        $applied = 0;
        $creates = 0;
        $updates = 0;
        $deletes = 0;
        $duplicates = 0;
        $lastId = $afterId;

        foreach ($orders as $order) {
            $lastId = (int) $order->id;
            $inspection = $tracker->inspectRealizedPurchaseSync($order);
            $action = (string) ($inspection['action'] ?? 'none');
            $duplicateCount = (int) ($inspection['duplicates'] ?? 0);
            $duplicates += $duplicateCount;

            if ($action === 'none') {
                continue;
            }

            $changed++;
            match ($action) {
                'create' => $creates++,
                'update' => $updates++,
                'delete' => $deletes++,
                default => null,
            };

            $this->line(sprintf(
                'Order #%d %s | action=%s | duplicate-events=%d',
                $order->id,
                $order->order_number ?: '(no number)',
                $action,
                $duplicateCount,
            ));

            if ($apply) {
                $tracker->syncRealizedPurchase($order);
                $applied++;
            }
        }

        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->info(sprintf(
            '%s complete | scanned=%d | changed=%d | creates=%d | updates=%d | deletes=%d | duplicate-events=%d | applied=%d | last_id=%d',
            $mode,
            $orders->count(),
            $changed,
            $creates,
            $updates,
            $deletes,
            $duplicates,
            $applied,
            $lastId,
        ));

        return self::SUCCESS;
    }
}
