<?php

namespace App\Services\Commerce;

use App\Exceptions\ProductIdentifierAmbiguityException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceivingProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReceivingService
{
    public function __construct(protected ProductIdentifierService $identifierService) {}

    /**
     * Scan exactly one physical unit without mutating inventory.
     *
     * @return array{
     *     status: 'scanned'|'line_complete'|'already_complete'|'needs_line_selection',
     *     purchase_item_id?: int,
     *     verified_quantity?: int,
     *     ordered_quantity?: int,
     *     choices?: array<int, array<string, mixed>>
     * }
     */
    public function scan(
        Purchase $purchase,
        string $barcode,
        int $adminUserId,
        ?int $purchaseItemId = null
    ): array {
        $barcode = trim($barcode);

        if ($barcode === '') {
            throw ValidationException::withMessages([
                'barcode' => __('Scan or enter a barcode before verifying a purchase item.'),
            ]);
        }

        try {
            $match = $this->identifierService->resolveBarcode($barcode);
        } catch (ProductIdentifierAmbiguityException) {
            throw ValidationException::withMessages([
                'barcode' => __('This barcode matches multiple catalog records. Resolve the duplicate identifiers before receiving by scan.'),
            ]);
        }

        if (! $match) {
            throw ValidationException::withMessages([
                'barcode' => __('No catalog item uses this barcode. Nothing was verified.'),
            ]);
        }

        if ($match['requires_variant_selection']) {
            throw ValidationException::withMessages([
                'barcode' => __('This barcode identifies a product with variants, not one exact variant. Scan the variant barcode instead.'),
            ]);
        }

        $product = $match['product'];
        $variant = $match['variant'];

        return DB::transaction(function () use (
            $purchase,
            $product,
            $variant,
            $adminUserId,
            $purchaseItemId
        ) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($lockedPurchase->status !== Purchase::STATUS_ORDERED) {
                throw ValidationException::withMessages([
                    'purchase' => __('Only ordered purchases can be verified by barcode.'),
                ]);
            }

            $matchingItems = PurchaseItem::query()
                ->where('purchase_id', $lockedPurchase->id)
                ->where('product_id', $product->id)
                ->when(
                    $variant,
                    fn ($query) => $query->where('product_variant_id', $variant->id),
                    fn ($query) => $query->whereNull('product_variant_id')
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($matchingItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'barcode' => __('The scanned item is not part of this purchase. Nothing was verified.'),
                ]);
            }

            if ($purchaseItemId) {
                $target = $matchingItems->firstWhere('id', $purchaseItemId);

                if (! $target) {
                    throw ValidationException::withMessages([
                        'purchase_item_id' => __('The selected purchase line does not match the scanned barcode.'),
                    ]);
                }
            } elseif ($matchingItems->count() > 1) {
                return [
                    'status' => 'needs_line_selection',
                    'choices' => $matchingItems->map(fn (PurchaseItem $item) => [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'variant_name' => $item->variant_name,
                        'sku' => $item->sku,
                        'quantity' => (int) $item->quantity,
                        'unit_cost' => (float) $item->unit_cost,
                        'expiration_date' => optional($item->expiration_date)?->toDateString(),
                    ])->values()->all(),
                ];
            } else {
                $target = $matchingItems->first();
            }

            if ((int) $target->quantity < 1) {
                throw ValidationException::withMessages([
                    'purchase' => __('Purchase items must have a positive ordered quantity before barcode verification.'),
                ]);
            }

            $progress = PurchaseReceivingProgress::query()
                ->where('purchase_item_id', $target->id)
                ->lockForUpdate()
                ->first();

            if (! $progress) {
                $progress = PurchaseReceivingProgress::create([
                    'purchase_id' => $lockedPurchase->id,
                    'purchase_item_id' => $target->id,
                    'verified_quantity' => 0,
                ]);
            }

            $orderedQuantity = (int) $target->quantity;
            $verifiedQuantity = (int) $progress->verified_quantity;

            if ($verifiedQuantity >= $orderedQuantity) {
                return [
                    'status' => 'already_complete',
                    'purchase_item_id' => $target->id,
                    'verified_quantity' => $verifiedQuantity,
                    'ordered_quantity' => $orderedQuantity,
                ];
            }

            $verifiedQuantity++;
            $progress->forceFill([
                'verified_quantity' => $verifiedQuantity,
                'last_scanned_by' => $adminUserId,
                'last_scanned_at' => now(),
            ])->save();

            return [
                'status' => $verifiedQuantity === $orderedQuantity ? 'line_complete' : 'scanned',
                'purchase_item_id' => $target->id,
                'verified_quantity' => $verifiedQuantity,
                'ordered_quantity' => $orderedQuantity,
            ];
        });
    }

    public function undoOne(Purchase $purchase, PurchaseItem $purchaseItem, int $adminUserId): bool
    {
        return DB::transaction(function () use ($purchase, $purchaseItem, $adminUserId) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($lockedPurchase->status !== Purchase::STATUS_ORDERED) {
                throw ValidationException::withMessages([
                    'purchase' => __('Only ordered purchases can change barcode verification counts.'),
                ]);
            }

            $lockedItem = PurchaseItem::query()
                ->whereKey($purchaseItem->id)
                ->where('purchase_id', $lockedPurchase->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedItem) {
                throw ValidationException::withMessages([
                    'purchase_item_id' => __('The purchase line does not belong to this purchase.'),
                ]);
            }

            $progress = PurchaseReceivingProgress::query()
                ->where('purchase_item_id', $lockedItem->id)
                ->lockForUpdate()
                ->first();

            if (! $progress || (int) $progress->verified_quantity < 1) {
                return false;
            }

            $progress->forceFill([
                'verified_quantity' => max(0, (int) $progress->verified_quantity - 1),
                'last_scanned_by' => $adminUserId,
                'last_scanned_at' => now(),
            ])->save();

            return true;
        });
    }
}
