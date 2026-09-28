<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceivingProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected AdminActivityLogService $activityLogService,
    ) {}

    public function cancel(Purchase $purchase, string $reason, ?int $adminUserId = null): bool
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancellation_reason' => __('Enter a cancellation reason.'),
            ]);
        }
        if (mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'cancellation_reason' => __('Purchase cancellation reason cannot exceed 1000 characters.'),
            ]);
        }

        return DB::transaction(function () use ($purchase, $reason, $adminUserId) {
            $lockedPurchase = Purchase::query()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPurchase->status === Purchase::STATUS_CANCELLED) {
                return false;
            }

            if (! in_array($lockedPurchase->status, [
                Purchase::STATUS_DRAFT,
                Purchase::STATUS_ORDERED,
            ], true)) {
                throw ValidationException::withMessages([
                    'purchase' => __('Purchases with received stock cannot be cancelled. Reverse the receipt first.'),
                ]);
            }

            $lockedItems = $lockedPurchase->items()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'received_quantity']);
            $receivedUnits = (int) $lockedItems->sum('received_quantity');

            if ($receivedUnits > 0 || $lockedPurchase->receipts()->exists()) {
                throw ValidationException::withMessages([
                    'purchase' => __('Purchases with received stock cannot be cancelled. Reverse the receipt first.'),
                ]);
            }

            $lockedPurchase->update([
                'status' => Purchase::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $adminUserId,
                'cancellation_reason' => $reason,
                'received_date' => null,
            ]);

            $this->activityLogService->log(
                'purchasing',
                'purchase_cancelled',
                __('Purchase :reference cancelled.', ['reference' => $lockedPurchase->reference]),
                $adminUserId,
                $lockedPurchase,
                ['reason' => $reason]
            );

            return true;
        });
    }

    public function receive(Purchase $purchase, ?int $adminUserId = null): bool
    {
        return $this->receiveInternal($purchase, false, null, (string) Str::uuid(), $adminUserId);
    }

    public function receiveVerified(Purchase $purchase, ?int $adminUserId = null): bool
    {
        return $this->receiveInternal($purchase, true, null, (string) Str::uuid(), $adminUserId);
    }

    public function receivePartial(
        Purchase $purchase,
        array $quantities,
        string $idempotencyKey,
        ?int $adminUserId = null
    ): bool {
        return $this->receiveInternal(
            $purchase,
            false,
            $quantities,
            $idempotencyKey,
            $adminUserId
        );
    }

    protected function receiveInternal(
        Purchase $purchase,
        bool $requireBarcodeVerification,
        ?array $quantities,
        string $idempotencyKey,
        ?int $adminUserId
    ): bool {
        return DB::transaction(function () use (
            $purchase,
            $requireBarcodeVerification,
            $quantities,
            $idempotencyKey,
            $adminUserId
        ) {
            $idempotencyKey = trim($idempotencyKey);
            $canonicalQuantities = $quantities === null
                ? null
                : collect($quantities)->mapWithKeys(
                    fn ($quantity, $itemId) => [(int) $itemId => (int) $quantity]
                )->sortKeys()->all();
            $requestHash = hash('sha256', json_encode([
                'mode' => $quantities === null ? 'remaining' : 'partial',
                'items' => $canonicalQuantities,
            ], JSON_THROW_ON_ERROR));

            if (! Str::isUuid($idempotencyKey)) {
                throw ValidationException::withMessages([
                    'receipt_key' => __('The receipt request key is invalid. Refresh the purchase and try again.'),
                ]);
            }

            $lockedPurchase = Purchase::query()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingReceipt = PurchaseReceipt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingReceipt) {
                if ((int) $existingReceipt->purchase_id !== (int) $lockedPurchase->id) {
                    throw ValidationException::withMessages([
                        'receipt_key' => __('The receipt request key belongs to another purchase. Refresh and try again.'),
                    ]);
                }

                if (! hash_equals((string) $existingReceipt->request_hash, $requestHash)) {
                    throw ValidationException::withMessages([
                        'receipt_key' => __('This receipt request key was already used with different quantities. Refresh and submit a new receipt.'),
                    ]);
                }

                return false;
            }

            if ($lockedPurchase->status === Purchase::STATUS_RECEIVED) {
                return false;
            }

            if ($lockedPurchase->purchase_date && $lockedPurchase->purchase_date->isAfter(today())) {
                throw ValidationException::withMessages([
                    'purchase' => __('Purchase stock cannot be received before the purchase order date.'),
                ]);
            }

            if (! in_array($lockedPurchase->status, [
                Purchase::STATUS_ORDERED,
                Purchase::STATUS_PARTIALLY_RECEIVED,
            ], true)) {
                throw ValidationException::withMessages([
                    'purchase' => __('Only open purchases can receive stock.'),
                ]);
            }

            if ($requireBarcodeVerification && $lockedPurchase->status !== Purchase::STATUS_ORDERED) {
                throw ValidationException::withMessages([
                    'purchase' => __('Barcode-verified receiving must be completed before any partial manual receipt.'),
                ]);
            }

            $items = $lockedPurchase->items()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase' => __('Add purchase items before receiving stock.'),
                ]);
            }

            if ($requireBarcodeVerification) {
                $progress = PurchaseReceivingProgress::query()
                    ->where('purchase_id', $lockedPurchase->id)
                    ->whereIn('purchase_item_id', $items->pluck('id'))
                    ->orderBy('purchase_item_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('purchase_item_id');

                foreach ($items as $item) {
                    if ((int) $item->received_quantity !== 0
                        || (int) optional($progress->get($item->id))->verified_quantity !== (int) $item->quantity) {
                        throw ValidationException::withMessages([
                            'purchase' => __('Scan and verify every ordered unit before completing barcode receiving.'),
                        ]);
                    }
                }
            }

            $requestedByItem = collect($quantities ?? [])
                ->mapWithKeys(fn ($quantity, $itemId) => [(int) $itemId => (int) $quantity]);

            if ($quantities !== null) {
                $unknownItemIds = $requestedByItem->keys()->diff($items->pluck('id')->map(fn ($id) => (int) $id));

                if ($unknownItemIds->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => __('One or more receipt lines do not belong to this purchase.'),
                    ]);
                }
            }

            $receiptQuantities = [];

            foreach ($items as $item) {
                $orderedQuantity = (int) $item->quantity;
                $receivedQuantity = (int) $item->received_quantity;

                if ($orderedQuantity < 1 || $receivedQuantity < 0 || $receivedQuantity > $orderedQuantity) {
                    throw ValidationException::withMessages([
                        'purchase' => __('Purchase receiving quantities are inconsistent. Stock was not changed.'),
                    ]);
                }

                $remainingQuantity = $orderedQuantity - $receivedQuantity;
                $requestedQuantity = $quantities === null
                    ? $remainingQuantity
                    : (int) $requestedByItem->get($item->id, 0);

                if ($requestedQuantity < 0 || $requestedQuantity > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => __('Received quantity cannot exceed the remaining ordered quantity.'),
                    ]);
                }

                if ($requestedQuantity > 0) {
                    $receiptQuantities[$item->id] = $requestedQuantity;
                }
            }

            if ($receiptQuantities === []) {
                throw ValidationException::withMessages([
                    'items' => __('Enter at least one quantity to receive.'),
                ]);
            }
            $receiptItems = $items->whereIn('id', array_keys($receiptQuantities));
            $products = Product::query()
                ->whereKey($receiptItems->pluck('product_id')->filter()->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $variants = ProductVariant::query()
                ->whereKey($receiptItems->pluck('product_variant_id')->filter()->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($receiptItems as $item) {
                $product = $products->get($item->product_id);
                $variant = $item->product_variant_id ? $variants->get($item->product_variant_id) : null;

                if (! $product
                    || ($item->product_variant_id && ! $variant)
                    || ($variant && (int) $variant->product_id !== (int) $product->id)
                    || (! $variant && ($product->has_variants || filled($item->variant_name)))) {
                    throw ValidationException::withMessages([
                        'purchase' => __('Purchase items have missing or mismatched products or variants. Stock was not changed.'),
                    ]);
                }
            }
            $receipt = PurchaseReceipt::create([
                'purchase_id' => $lockedPurchase->id,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'receipt_method' => $requireBarcodeVerification
                    ? PurchaseReceipt::METHOD_BARCODE_VERIFIED
                    : ($quantities !== null
                        ? PurchaseReceipt::METHOD_MANUAL_PARTIAL
                        : PurchaseReceipt::METHOD_MANUAL_REMAINING),
                'received_by' => $adminUserId,
                'received_at' => now(),
            ]);

            foreach ($receiptItems as $item) {
                $quantity = $receiptQuantities[$item->id];
                $before = (int) $item->received_quantity;
                $after = $before + $quantity;
                $product = $products->get($item->product_id);
                $variant = $item->product_variant_id ? $variants->get($item->product_variant_id) : null;

                $this->inventoryService->increase(
                    $product,
                    $variant,
                    $quantity,
                    InventoryMovement::TYPE_PURCHASE_IN,
                    [
                        'purchase_id' => $lockedPurchase->id,
                        'reason' => 'Purchase received',
                        'unit_cost' => (float) $item->unit_cost,
                        'expiration_date' => $item->expiration_date,
                        'meta' => [
                            'purchase_reference' => $lockedPurchase->reference,
                            'purchase_receipt_id' => $receipt->id,
                            'receipt_key' => $idempotencyKey,
                            'purchase_item_id' => $item->id,
                            'received_quantity_before' => $before,
                            'received_quantity_after' => $after,
                            'ordered_quantity' => (int) $item->quantity,
                        ],
                    ]
                );

                $receipt->items()->create([
                    'purchase_item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                ]);

                $item->forceFill(['received_quantity' => $after])->save();
            }

            $fullyReceived = $items->every(
                fn ($item) => (int) $item->received_quantity === (int) $item->quantity
            );
            $lockedPurchase->update([
                'status' => $fullyReceived
                    ? Purchase::STATUS_RECEIVED
                    : Purchase::STATUS_PARTIALLY_RECEIVED,
                'received_date' => $fullyReceived ? now()->toDateString() : null,
            ]);

            return true;
        });
    }
}
