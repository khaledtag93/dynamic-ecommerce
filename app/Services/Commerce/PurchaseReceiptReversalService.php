<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceivingProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptReversalService
{
    public function __construct(
        protected AdminActivityLogService $activityLogService,
        protected InventoryLotService $inventoryLotService,
        protected PurchaseSettlementService $purchaseSettlementService,
    ) {}

    public function reverse(
        Purchase $purchase,
        PurchaseReceipt $receipt,
        string $reason,
        ?int $adminUserId = null
    ): bool {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reversal_reason' => __('Enter a receipt reversal reason.'),
            ]);
        }
        if (mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'reversal_reason' => __('Purchase receipt reversal reason cannot exceed 1000 characters.'),
            ]);
        }

        return DB::transaction(function () use ($purchase, $receipt, $reason, $adminUserId) {
            $lockedPurchase = Purchase::query()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReceipt = PurchaseReceipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedReceipt->purchase_id !== (int) $lockedPurchase->id) {
                throw ValidationException::withMessages([
                    'receipt' => __('The selected receipt does not belong to this purchase.'),
                ]);
            }

            if ($lockedReceipt->reversed_at) {
                return false;
            }

            $receiptItems = $lockedReceipt->items()
                ->orderBy('purchase_item_id')
                ->lockForUpdate()
                ->get();

            if ($receiptItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'receipt' => __('This receipt has no item ledger to reverse safely.'),
                ]);
            }

            $this->purchaseSettlementService->assertReceiptReversalAllowed($lockedPurchase, $lockedReceipt);

            $purchaseItems = $lockedPurchase->items()
                ->whereIn('id', $receiptItems->pluck('purchase_item_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($receiptItems as $receiptItem) {
                $purchaseItem = $purchaseItems->get($receiptItem->purchase_item_id);

                if (! $purchaseItem
                    || (int) $purchaseItem->received_quantity < (int) $receiptItem->quantity) {
                    throw ValidationException::withMessages([
                        'receipt' => __('Purchase receipt quantities are inconsistent. Reversal was not applied.'),
                    ]);
                }
            }

            if ($lockedReceipt->receipt_method === 'legacy_unknown') {
                throw ValidationException::withMessages([
                    'receipt' => __('This legacy receipt cannot be reversed automatically because its receiving method is unknown.'),
                ]);
            }

            $purchaseMovements = InventoryMovement::query()
                ->where('purchase_id', $lockedPurchase->id)
                ->where('type', InventoryMovement::TYPE_PURCHASE_IN)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $receiptMovements = $purchaseMovements
                ->filter(fn (InventoryMovement $movement) =>
                    (int) data_get($movement->meta, 'purchase_receipt_id') === (int) $lockedReceipt->id
                )
                ->values();

            $movementItemIds = $receiptMovements
                ->map(fn (InventoryMovement $movement) => (int) data_get($movement->meta, 'purchase_item_id'))
                ->sort()
                ->values()
                ->all();
            $receiptItemIds = $receiptItems
                ->pluck('purchase_item_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($receiptMovements->count() !== $receiptItems->count()
                || $movementItemIds !== $receiptItemIds) {
                throw ValidationException::withMessages([
                    'receipt' => __('The receipt inventory ledger is incomplete. Reversal was not applied.'),
                ]);
            }

            $products = Product::query()
                ->whereIn('id', $purchaseItems->pluck('product_id')->filter()->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $variants = ProductVariant::query()
                ->whereIn('id', $purchaseItems->pluck('product_variant_id')->filter()->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $plans = [];

            foreach ($receiptMovements->groupBy(fn (InventoryMovement $movement) =>
                $movement->product_id.':'.($movement->product_variant_id ?? 'product')
            ) as $targetMovements) {
                $targetMovements = $targetMovements->sortByDesc('id')->values();
                $first = $targetMovements->first();
                $product = $products->get($first->product_id);
                $variant = $first->product_variant_id ? $variants->get($first->product_variant_id) : null;
                if (! $product
                    || ($first->product_variant_id && ! $variant)
                    || ($variant && (int) $variant->product_id !== (int) $product->id)) {
                    throw ValidationException::withMessages([
                        'receipt' => __('The received catalog target no longer exists. Reversal was not applied.'),
                    ]);
                }

                $newerMovements = InventoryMovement::query()
                    ->where('product_id', $product->id)
                    ->when(
                        $variant,
                        fn ($query) => $query->where('product_variant_id', $variant->id),
                        fn ($query) => $query->whereNull('product_variant_id')
                    )
                    ->where('id', '>', $first->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $reversedOriginalIds = $newerMovements
                    ->where('type', InventoryMovement::TYPE_PURCHASE_REVERSAL)
                    ->map(fn (InventoryMovement $movement) =>
                        (int) data_get($movement->meta, 'original_inventory_movement_id')
                    )
                    ->filter()
                    ->all();

                $hasUnsafeNewerActivity = $newerMovements->contains(
                    fn (InventoryMovement $movement) =>
                        $movement->type !== InventoryMovement::TYPE_PURCHASE_REVERSAL
                        && ! (
                            $movement->type === InventoryMovement::TYPE_PURCHASE_IN
                            && in_array((int) $movement->id, $reversedOriginalIds, true)
                        )
                );

                if ($hasUnsafeNewerActivity) {
                    throw ValidationException::withMessages([
                        'receipt' => __('This receipt cannot be reversed because newer inventory activity exists for one of its items.'),
                    ]);
                }

                $stockColumn = $variant ? 'stock' : 'quantity';
                $currentStock = (int) ($variant ? $variant->stock : $product->quantity);
                $currentValuation = (float) (
                    $variant
                        ? ($variant->inventory_cost_price ?? $variant->cost_price ?? 0)
                        : ($product->inventory_cost_price ?? $product->cost_price ?? 0)
                );
                $firstMeta = $first->meta ?? [];
                $expectedValuation = data_get($firstMeta, 'valuation_cost_after');

                if ($expectedValuation === null
                    || abs($currentValuation - (float) $expectedValuation) > 0.01) {
                    throw ValidationException::withMessages([
                        'receipt' => __('The receipt valuation no longer matches current inventory. Reversal was not applied.'),
                    ]);
                }

                if (array_key_exists('expiration_after', $firstMeta)) {
                    $currentExpiration = ($variant ? $variant->expiration_date : $product->expiration_date)?->toDateString();

                    if ($currentExpiration !== $firstMeta['expiration_after']) {
                        throw ValidationException::withMessages([
                            'receipt' => __('The receipt expiry state no longer matches current inventory. Reversal was not applied.'),
                        ]);
                    }
                }

                $expectedBalance = $currentStock;
                foreach ($targetMovements as $movement) {
                    if ((int) $movement->quantity_change < 1
                        || (int) $movement->balance_after !== $expectedBalance
                        || data_get($movement->meta, 'valuation_cost_before') === null) {
                        throw ValidationException::withMessages([
                            'receipt' => __('The receipt inventory history no longer matches current stock. Reversal was not applied.'),
                        ]);
                    }

                    $expectedBalance -= (int) $movement->quantity_change;

                    if ($expectedBalance < 0) {
                        throw ValidationException::withMessages([
                            'receipt' => __('The receipt cannot be reversed because the required stock is no longer available.'),
                        ]);
                    }
                }

                $plans[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'stock_column' => $stockColumn,
                    'movements' => $targetMovements,
                ];
            }

            $barcodeProgress = collect();

            if ($lockedReceipt->receipt_method === PurchaseReceipt::METHOD_BARCODE_VERIFIED) {
                $barcodeProgress = PurchaseReceivingProgress::query()
                    ->whereIn('purchase_item_id', $receiptItems->pluck('purchase_item_id'))
                    ->orderBy('purchase_item_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('purchase_item_id');

                foreach ($receiptItems as $receiptItem) {
                    $progress = $barcodeProgress->get($receiptItem->purchase_item_id);

                    if (! $progress || (int) $progress->verified_quantity < (int) $receiptItem->quantity) {
                        throw ValidationException::withMessages([
                            'receipt' => __('Barcode verification history is inconsistent. Reversal was not applied.'),
                        ]);
                    }
                }
            }

            foreach ($plans as $plan) {
                $target = $plan['variant'] ?: $plan['product'];
                $stockColumn = $plan['stock_column'];

                foreach ($plan['movements'] as $movement) {
                    $quantity = (int) $movement->quantity_change;
                    $restoredStock = (int) $movement->balance_after - $quantity;
                    $movementMeta = $movement->meta ?? [];
                    $restoredCost = round((float) data_get($movementMeta, 'valuation_cost_before'), 2);
                    $restoredExpiration = array_key_exists('expiration_before', $movementMeta)
                        ? $movementMeta['expiration_before']
                        : $target->expiration_date?->toDateString();

                    $target->forceFill([
                        $stockColumn => $restoredStock,
                        'inventory_cost_price' => $restoredCost,
                        'expiration_date' => $restoredExpiration,
                    ])->save();

                    $reversalMovement = InventoryMovement::query()->create([
                        'product_id' => $movement->product_id,
                        'product_variant_id' => $movement->product_variant_id,
                        'purchase_id' => $lockedPurchase->id,
                        'type' => InventoryMovement::TYPE_PURCHASE_REVERSAL,
                        'reason' => 'Purchase receipt reversed',
                        'quantity_change' => -$quantity,
                        'balance_after' => $restoredStock,
                        'unit_cost' => $movement->unit_cost,
                        'expiration_date' => $restoredExpiration,
                        'meta' => [
                            'purchase_reference' => $lockedPurchase->reference,
                            'purchase_receipt_id' => $lockedReceipt->id,
                            'original_inventory_movement_id' => $movement->id,
                            'purchase_item_id' => data_get($movementMeta, 'purchase_item_id'),
                            'reversal_reason' => $reason,
                            'reversed_by' => $adminUserId,
                            'valuation_cost_before_reversal' => data_get($movementMeta, 'valuation_cost_after'),
                            'valuation_cost_after_reversal' => $restoredCost,
                            'expiration_before_reversal' => data_get($movementMeta, 'expiration_after'),
                            'expiration_after_reversal' => $restoredExpiration,
                        ],
                    ]);

                    $this->inventoryLotService->recordMovement(
                        $reversalMovement,
                        $plan['product'],
                        $plan['variant'],
                        ['meta' => $reversalMovement->meta ?? []]
                    );

                    $target->refresh();
                }
            }
            foreach ($receiptItems as $receiptItem) {
                $purchaseItem = $purchaseItems->get($receiptItem->purchase_item_id);
                $purchaseItem->forceFill([
                    'received_quantity' => (int) $purchaseItem->received_quantity - (int) $receiptItem->quantity,
                ])->save();

                if ($lockedReceipt->receipt_method === PurchaseReceipt::METHOD_BARCODE_VERIFIED) {
                    $progress = $barcodeProgress->get($purchaseItem->id);

                    $remainingVerified = (int) $progress->verified_quantity - (int) $receiptItem->quantity;

                    $progress->forceFill([
                        'verified_quantity' => $remainingVerified,
                        'last_scanned_by' => $remainingVerified > 0 ? $progress->last_scanned_by : null,
                        'last_scanned_at' => $remainingVerified > 0 ? $progress->last_scanned_at : null,
                    ])->save();
                }
            }
            $allItems = $lockedPurchase->items()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $receivedUnits = (int) $allItems->sum('received_quantity');
            $fullyReceived = $allItems->isNotEmpty() && $allItems->every(
                fn ($item) => (int) $item->received_quantity === (int) $item->quantity
            );

            $lockedPurchase->update([
                'status' => $fullyReceived
                    ? Purchase::STATUS_RECEIVED
                    : ($receivedUnits > 0
                        ? Purchase::STATUS_PARTIALLY_RECEIVED
                        : Purchase::STATUS_ORDERED),
                'received_date' => $fullyReceived ? $lockedPurchase->received_date : null,
            ]);

            $lockedReceipt->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $adminUserId,
                'reversal_reason' => $reason,
            ])->save();
            $this->activityLogService->log(
                'purchasing',
                'purchase_receipt_reversed',
                __('Purchase receipt #:receipt for :reference reversed.', [
                    'receipt' => $lockedReceipt->id,
                    'reference' => $lockedPurchase->reference,
                ]),
                $adminUserId,
                $lockedReceipt,
                [
                    'purchase_id' => $lockedPurchase->id,
                    'reason' => $reason,
                    'receipt_method' => $lockedReceipt->receipt_method,
                ]
            );

            return true;
        });
    }
}
