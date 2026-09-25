<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStockReservation;
use App\Services\Commerce\PaymentService;
use Illuminate\Console\Command;

class ExpireOnlineStockReservationsCommand extends Command
{
    protected $signature = 'payments:expire-stock-reservations {--limit=100}';

    protected $description = 'Release expired online-payment stock reservations and mark their pending payments failed.';

    public function handle(PaymentService $paymentService): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));

        $orderIds = OrderStockReservation::query()
            ->where('status', OrderStockReservation::STATUS_RESERVED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->pluck('order_id')
            ->unique()
            ->values();

        $expired = 0;

        foreach ($orderIds as $orderId) {
            $order = Order::query()->find($orderId);

            if (! $order) {
                continue;
            }

            if ($paymentService->expireStockReservation($order)) {
                $expired++;
            }
        }

        $this->info("Expired {$expired} online-payment stock reservation order(s).");

        return self::SUCCESS;
    }
}
