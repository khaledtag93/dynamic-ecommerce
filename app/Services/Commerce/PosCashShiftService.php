<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\PosCashShift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCashShiftService
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function openShift(User $cashier, float $openingCash, ?string $notes = null): PosCashShift
    {
        return DB::transaction(function () use ($cashier, $openingCash, $notes) {
            User::query()->whereKey($cashier->id)->lockForUpdate()->firstOrFail();

            if (PosCashShift::query()->where('cashier_user_id', $cashier->id)->whereNull('closed_at')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['shift' => __('You already have an open cash shift.')]);
            }

            $shift = PosCashShift::query()->create([
                'cashier_user_id' => $cashier->id,
                'opening_cash' => round(max(0, $openingCash), 2),
                'opened_at' => now(),
                'opening_notes' => $notes,
            ]);

            $this->activityLogService->log('pos', 'pos_cash_shift_opened', __('POS cash shift opened.'), $cashier->id, $shift, [
                'opening_cash' => (float) $shift->opening_cash,
            ]);

            return $shift;
        });
    }

    public function summary(PosCashShift $shift): array
    {
        $cashSales = (float) Order::query()
            ->where('sales_channel', Order::SALES_CHANNEL_POS)
            ->where('payment_method', Order::PAYMENT_METHOD_POS_CASH)
            ->where('payment_status', '!=', Order::PAYMENT_STATUS_REFUNDED)
            ->whereBetween('placed_at', [$shift->opened_at, $shift->closed_at ?? now()])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.cashier_user_id')) = ?", [(string) $shift->cashier_user_id])
            ->sum('grand_total');

        $cashRefunds = (float) DB::table('order_refunds')
            ->join('orders', 'orders.id', '=', 'order_refunds.order_id')
            ->where('orders.sales_channel', Order::SALES_CHANNEL_POS)
            ->where('orders.payment_method', Order::PAYMENT_METHOD_POS_CASH)
            ->whereBetween('order_refunds.processed_at', [$shift->opened_at, $shift->closed_at ?? now()])
            ->where('order_refunds.processed_by', $shift->cashier_user_id)
            ->sum('order_refunds.amount');

        return [
            'opening_cash' => round((float) $shift->opening_cash, 2),
            'cash_sales' => round($cashSales, 2),
            'cash_refunds' => round($cashRefunds, 2),
            'expected_cash' => round((float) $shift->opening_cash + $cashSales - $cashRefunds, 2),
        ];
    }

    public function closeShift(PosCashShift $shift, User $cashier, float $countedCash, ?string $notes = null): PosCashShift
    {
        return DB::transaction(function () use ($shift, $cashier, $countedCash, $notes) {
            $locked = PosCashShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->cashier_user_id !== (int) $cashier->id || ! $locked->isOpen()) {
                throw ValidationException::withMessages(['shift' => __('This cash shift cannot be closed by the current cashier.')]);
            }

            $summary = $this->summary($locked);
            $countedCash = round(max(0, $countedCash), 2);
            $variance = round($countedCash - $summary['expected_cash'], 2);

            $locked->update([
                'closing_cash_counted' => $countedCash,
                'expected_cash' => $summary['expected_cash'],
                'cash_variance' => $variance,
                'closed_at' => now(),
                'closing_notes' => $notes,
            ]);

            $this->activityLogService->log('pos', 'pos_cash_shift_closed', __('POS cash shift closed.'), $cashier->id, $locked, [
                ...$summary,
                'closing_cash_counted' => $countedCash,
                'cash_variance' => $variance,
            ]);

            return $locked->fresh();
        });
    }
}
