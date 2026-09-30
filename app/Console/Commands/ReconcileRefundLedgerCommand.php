<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Commerce\PaymentService;
use App\Services\Commerce\ProfitService;
use App\Services\Growth\GrowthAttributionService;
use Illuminate\Console\Command;

class ReconcileRefundLedgerCommand extends Command
{
    protected $signature = 'commerce:reconcile-refund-ledger
                            {--after-id=0 : Scan orders with an id greater than this value}
                            {--limit=500 : Maximum candidate orders to scan}
                            {--apply : Apply refund/payment snapshot corrections}';

    protected $description = 'Audit and optionally reconcile order refund/payment snapshots with the authoritative refund ledger';

    public function handle(
        PaymentService $paymentService,
        ProfitService $profitService,
        AnalyticsTracker $analyticsTracker,
        GrowthAttributionService $growthAttributionService,
    ): int {
        $afterId = max(0, (int) $this->option('after-id'));
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $apply = (bool) $this->option('apply');
        $orders = Order::query()
            ->where('id', '>', $afterId)
            ->where(function ($query) {
                $query->where('refund_total', '>', 0)
                    ->orWhereIn('payment_status', [
                        Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
                        Order::PAYMENT_STATUS_REFUNDED,
                    ])
                    ->orWhereHas('refunds');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $changed = 0;
        $refundSnapshotChanges = 0;
        $commercialRefundSnapshotChanges = 0;
        $paymentStatusChanges = 0;
        $applied = 0;
        $lastId = $afterId;

        foreach ($orders as $order) {
            $lastId = (int) $order->id;
            $expected = $paymentService->inspectOrderPaymentSync($order);
            $currentRefund = $this->money($order->refund_total);
            $expectedRefund = $this->money($expected['refund_total']);
            $currentCommercialRefund = $this->money($order->commercial_refund_total);
            $expectedCommercialRefund = $this->money($expected['commercial_refund_total']);
            $currentStatus = (string) $order->payment_status;
            $expectedStatus = (string) $expected['payment_status'];
            $refundChanged = $currentRefund !== $expectedRefund;
            $commercialRefundChanged = $currentCommercialRefund !== $expectedCommercialRefund;
            $statusChanged = $currentStatus !== $expectedStatus;
            if (! $refundChanged && ! $commercialRefundChanged && ! $statusChanged) {
                continue;
            }

            $changed++;
            $refundSnapshotChanges += $refundChanged ? 1 : 0;
            $commercialRefundSnapshotChanges += $commercialRefundChanged ? 1 : 0;
            $paymentStatusChanges += $statusChanged ? 1 : 0;

            $this->line(sprintf(
                'Order #%d %s | refund %s -> %s | commercial-refund %s -> %s | payment-status %s -> %s',
                $order->id,
                $order->order_number ?: '(no number)',
                $currentRefund,
                $expectedRefund,
                $currentCommercialRefund,
                $expectedCommercialRefund,
                $currentStatus,
                $expectedStatus,
            ));

            if ($apply) {
                $paymentService->syncOrderPaymentStatus($order);

                $fresh = Order::query()
                    ->with(['items' => fn ($query) => $query->orderBy('id')])
                    ->findOrFail($order->id);

                $fresh = $profitService->refreshOrderTotals($fresh);
                $analyticsTracker->syncRealizedPurchase($fresh->loadMissing('items'));
                $growthAttributionService->refreshOrderAttribution((int) $fresh->id);
                $applied++;
            }
        }
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->info(sprintf(
            '%s complete | scanned=%d | changed=%d | refund-snapshot-changes=%d | commercial-refund-snapshot-changes=%d | payment-status-changes=%d | applied=%d | last_id=%d',
            $mode,
            $orders->count(),
            $changed,
            $refundSnapshotChanges,
            $commercialRefundSnapshotChanges,
            $paymentStatusChanges,
            $applied,
            $lastId,
        ));

        if (! $apply && $changed > 0) {
            $this->warn('Dry-run only. Re-run the same bounded range with --apply after review.');
        }

        return self::SUCCESS;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
