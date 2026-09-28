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
        $amountText = trim((string) $amount);
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amountText)) {
            throw ValidationException::withMessages([
                'amount' => __('Enter a supplier payment with up to two decimal places within the supported amount limit.'),
            ]);
        }

        $amountCents = $this->moneyToCents($amountText);
        if ($amountCents > 999999999999) {
            throw ValidationException::withMessages([
                'amount' => __('Enter a supplier payment with up to two decimal places within the supported amount limit.'),
            ]);
        }

        $idempotencyKey = trim($idempotencyKey);
        $normalizedReference = filled($reference) ? trim((string) $reference) : null;
        if ($normalizedReference !== null && mb_strlen($normalizedReference) > 100) {
            throw ValidationException::withMessages([
                'reference' => __('Supplier payment reference cannot exceed 100 characters.'),
            ]);
        }
        $requestedPaidAt = null;
        $paidAtWasExplicit = filled($paidAt);

        if ($paidAtWasExplicit) {
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

        $requestHash = hash('sha256', json_encode([
            'purchase_id' => (int) $purchase->id,
            'amount_cents' => $amountCents,
            'payment_method' => $paymentMethod,
            'reference' => $normalizedReference,
            'paid_at_explicit' => $paidAtWasExplicit,
            'paid_at' => $requestedPaidAt?->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($purchase, $amountCents, $paymentMethod, $normalizedReference, $idempotencyKey, $requestHash, $requestedPaidAt, $adminUserId) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $existing = PurchaseSettlement::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                $sameRequest = filled($existing->request_hash)
                    ? hash_equals((string) $existing->request_hash, $requestHash)
                    : ($requestedPaidAt !== null
                        && (int) $existing->purchase_id === (int) $lockedPurchase->id
                        && $this->moneyToCents($existing->amount) === $amountCents
                        && $existing->payment_method === $paymentMethod
                        && (string) ($existing->reference ?? '') === (string) ($normalizedReference ?? '')
                        && $existing->paid_at?->equalTo($requestedPaidAt));

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
                ->where('received_at', '<=', $effectivePaidAt)
                ->where(function ($state) use ($effectivePaidAt) {
                    $state->whereNull('reversed_at')
                        ->orWhere('reversed_at', '>', $effectivePaidAt);
                })
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->value('received_at');

            if (! $firstActiveReceiptAt || $effectivePaidAt->lt(Carbon::parse($firstActiveReceiptAt)->setMicrosecond(0))) {
                throw ValidationException::withMessages([
                    'paid_at' => __('Supplier payment date cannot be before the first active goods receipt.'),
                ]);
            }

            $historicalPayableCents = $this->receivedValueCents($lockedPurchase, true, null, $effectivePaidAt);
            $historicalPaidCents = $this->activeSettlementCents($lockedPurchase, true, $effectivePaidAt);
            $historicalBalanceCents = max(0, $historicalPayableCents - $historicalPaidCents);

            if ($amountCents > $historicalBalanceCents) {
                throw ValidationException::withMessages([
                    'amount' => __('Supplier payment cannot exceed the received-value balance available on its payment date.'),
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
                'request_hash' => $requestHash,
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
        if (mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'void_reason' => __('Supplier payment void reason cannot exceed 1000 characters.'),
            ]);
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
    protected function receivedValueCents(
        Purchase $purchase,
        bool $lock = false,
        ?int $excludeReceiptId = null,
        ?Carbon $asOf = null
    ): int {
        $receipts = PurchaseReceipt::query()
            ->where('purchase_id', $purchase->id)
            ->when(
                $asOf,
                fn ($q) => $q
                    ->where('received_at', '<=', $asOf)
                    ->where(function ($state) use ($asOf) {
                        $state->whereNull('reversed_at')
                            ->orWhere('reversed_at', '>', $asOf);
                    }),
                fn ($q) => $q->whereNull('reversed_at')
            )
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
    protected function activeSettlementCents(Purchase $purchase, bool $lock = false, ?Carbon $asOf = null): int
    {
        $query = PurchaseSettlement::query()
            ->where('purchase_id', $purchase->id)
            ->when(
                $asOf,
                fn ($q) => $q
                    ->where('paid_at', '<=', $asOf)
                    ->where(function ($state) use ($asOf) {
                        $state->where('status', PurchaseSettlement::STATUS_ACTIVE)
                            ->orWhere(function ($voided) use ($asOf) {
                                $voided->where('status', PurchaseSettlement::STATUS_VOIDED)
                                    ->whereNotNull('voided_at')
                                    ->where('voided_at', '>', $asOf);
                            });
                    }),
                fn ($q) => $q->where('status', PurchaseSettlement::STATUS_ACTIVE)
            )
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
