<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\PosCashShift;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCashShiftService
{
    public function __construct(protected AdminActivityLogService $activityLogService)
    {
    }

    public function openShift(User $cashier, int|float|string $openingCash, ?string $notes = null): PosCashShift
    {
        $openingCashCents = $this->cashInputToCents($openingCash, 'opening_cash');

        return DB::transaction(function () use ($cashier, $openingCashCents, $notes) {
            User::query()->whereKey($cashier->id)->lockForUpdate()->firstOrFail();

            if (PosCashShift::query()->where('cashier_user_id', $cashier->id)->whereNull('closed_at')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['shift' => __('You already have an open cash shift.')]);
            }

            $shift = PosCashShift::query()->create([
                'cashier_user_id' => $cashier->id,
                'opening_cash' => $this->centsToMoney($openingCashCents),
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
        $cashSalesCents = $this->moneyToCents(
            Order::query()
                ->where('sales_channel', Order::SALES_CHANNEL_POS)
                ->where('payment_method', Order::PAYMENT_METHOD_POS_CASH)
                ->whereBetween('placed_at', [$shift->opened_at, $shift->closed_at ?? now()])
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.cashier_user_id')) = ?", [(string) $shift->cashier_user_id])
                ->sum('grand_total')
        );

        $cashRefundsCents = $this->moneyToCents(
            DB::table('order_refunds')
                ->join('orders', 'orders.id', '=', 'order_refunds.order_id')
                ->where('orders.sales_channel', Order::SALES_CHANNEL_POS)
                ->where('orders.payment_method', Order::PAYMENT_METHOD_POS_CASH)
                ->whereBetween('order_refunds.processed_at', [$shift->opened_at, $shift->closed_at ?? now()])
                ->where('order_refunds.processed_by', $shift->cashier_user_id)
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('pos_return_items')
                        ->whereColumn('pos_return_items.order_refund_id', 'order_refunds.id');
                })
                ->sum('order_refunds.amount')
        );

        $openingCashCents = $this->moneyToCents($shift->opening_cash);
        $expectedCashCents = $openingCashCents + $cashSalesCents - $cashRefundsCents;

        return [
            'opening_cash' => $this->centsToFloat($openingCashCents),
            'cash_sales' => $this->centsToFloat($cashSalesCents),
            'cash_refunds' => $this->centsToFloat($cashRefundsCents),
            'expected_cash' => $this->centsToFloat($expectedCashCents),
        ];
    }

    public function closeShift(PosCashShift $shift, User $cashier, int|float|string $countedCash, ?string $notes = null): PosCashShift
    {
        $countedCashCents = $this->cashInputToCents($countedCash, 'closing_cash_counted');

        return DB::transaction(function () use ($shift, $cashier, $countedCashCents, $notes) {
            $locked = PosCashShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->cashier_user_id !== (int) $cashier->id || ! $locked->isOpen()) {
                throw ValidationException::withMessages(['shift' => __('This cash shift cannot be closed by the current cashier.')]);
            }

            $summary = $this->summary($locked);
            $expectedCashCents = $this->moneyToCents($summary['expected_cash']);
            $varianceCents = $countedCashCents - $expectedCashCents;

            $locked->update([
                'closing_cash_counted' => $this->centsToMoney($countedCashCents),
                'expected_cash' => $this->centsToMoney($expectedCashCents),
                'cash_variance' => $this->centsToMoney($varianceCents),
                'closed_at' => now(),
                'closing_notes' => $notes,
            ]);

            $this->activityLogService->log('pos', 'pos_cash_shift_closed', __('POS cash shift closed.'), $cashier->id, $locked, [
                ...$summary,
                'closing_cash_counted' => $this->centsToFloat($countedCashCents),
                'cash_variance' => $this->centsToFloat($varianceCents),
            ]);

            return $locked->fresh();
        });
    }

    private function cashInputToCents(int|float|string $amount, string $field): int
    {
        try {
            $decimal = BigDecimal::of((string) $amount)
                ->toScale(2, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => __('Cash amount must use at most two decimal places.'),
            ]);
        }

        if ($decimal->compareTo('0.00') < 0) {
            throw ValidationException::withMessages([
                $field => __('Cash amount cannot be negative.'),
            ]);
        }

        if ($decimal->compareTo('999999999.99') > 0) {
            throw ValidationException::withMessages([
                $field => __('Cash amount exceeds the supported monetary range.'),
            ]);
        }

        return $decimal->multipliedBy('100')->toInt();
    }

    private function moneyToCents(mixed $amount): int
    {
        return BigDecimal::of((string) $amount)
            ->toScale(2, RoundingMode::Unnecessary)
            ->multipliedBy('100')
            ->toInt();
    }

    private function centsToMoney(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function centsToFloat(int $cents): float
    {
        return $cents / 100;
    }
}
