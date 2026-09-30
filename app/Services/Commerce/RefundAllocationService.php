<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class RefundAllocationService
{
    public const SCOPE_ORDER = 'order';
    public const SCOPE_MERCHANDISE = 'merchandise';
    public const SCOPE_SHIPPING = 'shipping';
    public const SCOPE_TAX = 'tax';
    public const SCOPE_PAYMENT_EXCESS = 'payment_excess';

    public static function scopes(): array
    {
        return [
            self::SCOPE_ORDER,
            self::SCOPE_MERCHANDISE,
            self::SCOPE_SHIPPING,
            self::SCOPE_TAX,
            self::SCOPE_PAYMENT_EXCESS,
        ];
    }

    public function allocateNewRefund(
        Order $order,
        float $amount,
        string $scope = self::SCOPE_ORDER,
        float $exchangeCompensation = 0
    ): array {
        $scope = in_array($scope, self::scopes(), true)
            ? $scope
            : self::SCOPE_ORDER;
        $amountCents = $this->moneyToCents($amount);

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'refund' => __('Refund amount must be greater than zero.'),
            ]);
        }

        $remaining = $this->remainingComponentsCents(
            $order,
            $this->moneyToCents($exchangeCompensation)
        );

        $priority = $scope === self::SCOPE_ORDER
            ? [
                self::SCOPE_MERCHANDISE,
                self::SCOPE_SHIPPING,
                self::SCOPE_TAX,
                self::SCOPE_PAYMENT_EXCESS,
            ]
            : [$scope];

        $allocation = $this->allocateAcrossRemaining($amountCents, $remaining, $priority);

        if (array_sum($allocation) !== $amountCents) {
            throw ValidationException::withMessages([
                'allocation_scope' => __('Refund amount exceeds the remaining value for the selected allocation.'),
            ]);
        }

        return $this->serializeAllocation($scope, $allocation);
    }

    public function allocatedComponentsCents(Order $order): array
    {
        $order->loadMissing(['items', 'refunds', 'payments']);

        $gross = $this->grossComponentsCents($order);
        $remaining = $gross;
        $allocated = $this->emptyComponents();

        foreach ($order->refunds->sortBy('id') as $refund) {
            $amountCents = max(0, $this->moneyToCents($refund->amount));
            $stored = $this->storedAllocationCents($refund);

            if ($stored === null) {
                $stored = $this->allocateAcrossRemaining(
                    $amountCents,
                    $remaining,
                    [
                        self::SCOPE_MERCHANDISE,
                        self::SCOPE_SHIPPING,
                        self::SCOPE_TAX,
                        self::SCOPE_PAYMENT_EXCESS,
                    ]
                );
            }

            foreach ($allocated as $component => $value) {
                $applied = min(
                    max(0, (int) ($stored[$component] ?? 0)),
                    max(0, (int) ($remaining[$component] ?? 0))
                );
                $allocated[$component] += $applied;
                $remaining[$component] = max(0, $remaining[$component] - $applied);
            }
        }

        return $allocated;
    }

    public function commercialRefundTotalCents(Order $order): int
    {
        $components = $this->allocatedComponentsCents($order);

        return max(0,
            (int) ($components[self::SCOPE_MERCHANDISE] ?? 0)
            + (int) ($components[self::SCOPE_SHIPPING] ?? 0)
            + (int) ($components[self::SCOPE_TAX] ?? 0)
        );
    }

    public function remainingComponentsCents(Order $order, int $exchangeCompensationCents = 0): array
    {
        $gross = $this->grossComponentsCents($order);
        $allocated = $this->allocatedComponentsCents($order);

        foreach ($gross as $component => $value) {
            $gross[$component] = max(
                0,
                $value - (int) ($allocated[$component] ?? 0)
            );
        }

        if ($exchangeCompensationCents > 0) {
            $gross[self::SCOPE_MERCHANDISE] = max(
                0,
                $gross[self::SCOPE_MERCHANDISE] - $exchangeCompensationCents
            );
        }

        return $gross;
    }

    public function grossComponentsCents(Order $order): array
    {
        $order->loadMissing(['items', 'payments']);

        $lineTotalCents = (int) $order->items->sum(
            fn ($item) => max(0, $this->moneyToCents($item->line_total))
        );
        $shippingCents = max(0, $this->moneyToCents($order->shipping_total));
        $taxCents = max(0, $this->moneyToCents($order->tax_total));
        $grandTotalCents = max(0, $this->moneyToCents($order->grand_total));
        $merchandiseCents = min(
            $lineTotalCents,
            max(0, $grandTotalCents - $shippingCents - $taxCents)
        );
        $capturedCents = (int) $order->payments
            ->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
            ->sum(fn ($payment) => max(0, $this->moneyToCents($payment->amount)));

        return [
            self::SCOPE_MERCHANDISE => $merchandiseCents,
            self::SCOPE_SHIPPING => $shippingCents,
            self::SCOPE_TAX => $taxCents,
            self::SCOPE_PAYMENT_EXCESS => max(0, $capturedCents - $grandTotalCents),
        ];
    }

    private function storedAllocationCents(OrderRefund $refund): ?array
    {
        $allocation = $refund->allocation;

        if (! is_array($allocation) || (int) ($allocation['version'] ?? 0) !== 1) {
            return null;
        }

        $components = [
            self::SCOPE_MERCHANDISE => $this->moneyToCents($allocation['merchandise_amount'] ?? 0),
            self::SCOPE_SHIPPING => $this->moneyToCents($allocation['shipping_amount'] ?? 0),
            self::SCOPE_TAX => $this->moneyToCents($allocation['tax_amount'] ?? 0),
            self::SCOPE_PAYMENT_EXCESS => $this->moneyToCents($allocation['payment_excess_amount'] ?? 0),
        ];

        if (array_sum($components) !== $this->moneyToCents($refund->amount)) {
            return null;
        }

        return $components;
    }

    private function serializeAllocation(string $scope, array $allocation): array
    {
        return [
            'version' => 1,
            'scope' => $scope,
            'merchandise_amount' => $this->centsToMoney($allocation[self::SCOPE_MERCHANDISE] ?? 0),
            'shipping_amount' => $this->centsToMoney($allocation[self::SCOPE_SHIPPING] ?? 0),
            'tax_amount' => $this->centsToMoney($allocation[self::SCOPE_TAX] ?? 0),
            'payment_excess_amount' => $this->centsToMoney($allocation[self::SCOPE_PAYMENT_EXCESS] ?? 0),
        ];
    }

    private function allocateAcrossRemaining(int $amountCents, array $remaining, array $priority): array
    {
        $allocation = $this->emptyComponents();
        $unallocated = max(0, $amountCents);

        foreach ($priority as $component) {
            if ($unallocated <= 0) {
                break;
            }

            $available = max(0, (int) ($remaining[$component] ?? 0));
            $applied = min($available, $unallocated);
            $allocation[$component] += $applied;
            $unallocated -= $applied;
        }

        return $allocation;
    }

    private function emptyComponents(): array
    {
        return [
            self::SCOPE_MERCHANDISE => 0,
            self::SCOPE_SHIPPING => 0,
            self::SCOPE_TAX => 0,
            self::SCOPE_PAYMENT_EXCESS => 0,
        ];
    }

    private function moneyToCents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
