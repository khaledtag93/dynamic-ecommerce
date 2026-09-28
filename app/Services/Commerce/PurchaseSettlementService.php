<?php

namespace App\Services\Commerce;

use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseSettlement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseSettlementService
{
    public function __construct(protected AdminActivityLogService $activityLogService) {}

    public function summary(Purchase $purchase): array
    {
        $payableCents = $this->receivedValueCents($purchase);
        $paidCents = $this->activeSettlementCents($purchase);

        return [
            'payable' => $this->centsToMoney($payableCents),
            'paid' => $this->centsToMoney($paidCents),
            'balance' => $this->centsToMoney(max(0, $payableCents - $paidCents)),
            'status' => $this->statusFor($payableCents, $paidCents),
        ];
    }
    public function record(
        Purchase $purchase,
        mixed $amount,
        string $paymentMethod,
        ?string $reference,
        string $idempotencyKey,
        ?string $paidAt = null,
        ?int $adminUserId = null
    ): bool {
        $amountCents = $this->moneyToCents($amount);
        $idempotencyKey = trim($idempotencyKey);
        $normalizedReference = filled($reference) ? trim((string) $reference) : null;
        $requestedPaidAt = null;

        if (filled($paidAt)) {
            try {
                $requestedPaidAt = Carbon::parse((string) $paidAt)->setMicrosecond(0);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['paid_at' => __('Enter a valid supplier payment date.')]);
            }

            if ($requestedPaidAt->isAfter(now()->setMicrosecond(0))) {
                throw ValidationException::withMessages(['paid_at' => __('Supplier payment date cannot be in the future.')]);
            }
        }

        if ($amountCents < 1) {
            throw ValidationException::withMessages(['amount' => __('Enter a supplier payment greater than zero.')]);
        }
        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['settlement_key' => __('The supplier payment request key is invalid. Refresh and try again.')]);
        }
        if (! array_key_exists($paymentMethod, PurchaseSettlement::paymentMethodOptions())) {
            throw ValidationException::withMessages(['payment_method' => __('Choose a valid supplier payment method.')]);
        }

        return DB::transaction(function () use ($purchase, $amountCents, $paymentMethod, $normalizedReference, $idempotencyKey, $requestedPaidAt, $adminUserId) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $existing = PurchaseSettlement::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                $sameRequest = (int) $existing->purchase_id === (int) $lockedPurchase->id
                    && $this->moneyToCents($existing->amount) === $amountCents
                    && $existing->payment_method === $paymentMethod
                    && (string) ($existing->reference ?? '') === (string) ($normalizedReference ?? '')
                    && ($requestedPaidAt === null || $existing->paid_at?->equalTo($requestedPaidAt));

                if (! $sameRequest) {
                    throw ValidationException::withMessages([
                        'settlement_key' => __('This supplier payment request key was already used with different details. Refresh and try again.'),
                    ]);
                }

                return false;
            }

            $payableCents = $this->receivedValueCents($lockedPurchase, true);
            $paidCents = $this->activeSettlementCents($lockedPurchase, true);
            $balanceCents = max(0, $payableCents - $paidCents);

            if ($payableCents < 1) {
                throw ValidationException::withMessages(['amount' => __('No received supplier value is available to settle yet.')]);
            }
            if ($amountCents > $balanceCents) {
                throw ValidationException::withMessages(['amount' => __('Supplier payment cannot exceed the current received-value balance.')]);
            }

            $effectivePaidAt = $requestedPaidAt ?: now()->setMicrosecond(0);
            $firstActiveReceiptAt = PurchaseReceipt::query()
                ->where('purchase_id', $lockedPurchase->id)
                ->whereNull('reversed_at')
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->value('received_at');

            if (! $firstActiveReceiptAt || $effectivePaidAt->lt(Carbon::parse($firstActiveReceiptAt)->setMicrosecond(0))) {
                throw ValidationException::withMessages([
                    'paid_at' => __('Supplier payment date cannot be before the first active goods receipt.'),
                ]);
            }

            $settlement = PurchaseSettlement::query()->create([
                'purchase_id' => $lockedPurchase->id,
                'supplier_id' => $lockedPurchase->supplier_id,
                'amount' => $this->centsToMoney($amountCents),
                'currency' => $lockedPurchase->currency,
                'payment_method' => $paymentMethod,
                'reference' => $normalizedReference,
                'idempotency_key' => $idempotencyKey,
                'paid_at' => $effectivePaidAt,
                'recorded_by' => $adminUserId,
                'status' => PurchaseSettlement::STATUS_ACTIVE,
            ]);

            $this->activityLogService->log(
                'purchasing',
                'supplier_payment_recorded',
                __('Supplier payment recorded for :reference.', ['reference' => $lockedPurchase->reference]),
                $adminUserId,
                $settlement,
                ['purchase_id' => $lockedPurchase->id, 'amount' => $settlement->amount, 'currency' => $settlement->currency]
            );

            return true;
        });
    }
    public function void(Purchase $purchase, PurchaseSettlement $settlement, string $reason, ?int $adminUserId = null): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['void_reason' => __('Enter a reason for voiding this supplier payment.')]);
        }

        return DB::transaction(function () use ($purchase, $settlement, $reason, $adminUserId) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $locked = PurchaseSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->purchase_id !== (int) $lockedPurchase->id) {
                throw ValidationException::withMessages(['settlement' => __('The supplier payment does not belong to this purchase.')]);
            }
            if ($locked->status === PurchaseSettlement::STATUS_VOIDED) {
                return false;
            }

            $locked->forceFill([
                'status' => PurchaseSettlement::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $adminUserId,
                'void_reason' => $reason,
            ])->save();
            $this->activityLogService->log(
                'purchasing',
                'supplier_payment_voided',
                __('Supplier payment voided for :reference.', ['reference' => $lockedPurchase->reference]),
                $adminUserId,
                $locked,
                ['purchase_id' => $lockedPurchase->id, 'reason' => $reason]
            );

            return true;
        });
    }

    public function assertReceiptReversalAllowed(Purchase $purchase, PurchaseReceipt $receipt): void
    {
        $activePaidCents = $this->activeSettlementCents($purchase, true);
        if ($activePaidCents < 1) {
            return;
        }

        $remainingPayableCents = $this->receivedValueCents($purchase, true, $receipt->id);
        if ($activePaidCents > $remainingPayableCents) {
            throw ValidationException::withMessages([
                'receipt' => __('Void or reduce supplier payments before reversing this receipt; the remaining received value would be lower than the amount already paid.'),
            ]);
        }
    }
    protected function receivedValueCents(Purchase $purchase, bool $lock = false, ?int $excludeReceiptId = null): int
    {
        $receipts = PurchaseReceipt::query()
            ->where('purchase_id', $purchase->id)
            ->whereNull('reversed_at')
            ->when($excludeReceiptId, fn ($q) => $q->where('id', '!=', $excludeReceiptId))
            ->orderBy('id');
        if ($lock) {
            $receipts->lockForUpdate();
        }
        $receiptIds = $receipts->pluck('id');
        if ($receiptIds->isEmpty()) {
            return 0;
        }

        $items = PurchaseReceiptItem::query()->whereIn('purchase_receipt_id', $receiptIds)->orderBy('id');
        if ($lock) {
            $items->lockForUpdate();
        }

        return (int) $items->get()->sum(
            fn (PurchaseReceiptItem $item) => ((int) $item->quantity) * $this->moneyToCents($item->unit_cost)
        );
    }
    protected function activeSettlementCents(Purchase $purchase, bool $lock = false): int
    {
        $query = PurchaseSettlement::query()
            ->where('purchase_id', $purchase->id)
            ->where('status', PurchaseSettlement::STATUS_ACTIVE)
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return (int) $query->get()->sum(fn (PurchaseSettlement $row) => $this->moneyToCents($row->amount));
    }

    protected function statusFor(int $payableCents, int $paidCents): string
    {
        if ($payableCents < 1) return 'not_due';
        if ($paidCents < 1) return 'unpaid';
        if ($paidCents < $payableCents) return 'partially_paid';

        return 'paid';
    }
    protected function moneyToCents(mixed $value): int
    {
        $money = trim((string) ($value ?? '0'));
        [$whole, $fraction] = array_pad(explode('.', $money, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    protected function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
