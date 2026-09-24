<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function receive(Purchase $purchase): bool
    {
        return DB::transaction(function () use ($purchase) {
            // The status check and stock writes must share one lock across repeated requests.
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($lockedPurchase->status === Purchase::STATUS_RECEIVED) {
                return false;
            }
            if ($lockedPurchase->status !== Purchase::STATUS_ORDERED) {
                throw ValidationException::withMessages([
                    'purchase' => __('Only ordered purchases can be received.'),
                ]);
            }

            $items = $lockedPurchase->items()->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase' => __('Add purchase items before receiving stock.'),
                ]);
            }

            $products = Product::query()->whereKey($items->pluck('product_id')->filter()->unique()->all())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $variants = ProductVariant::query()->whereKey($items->pluck('product_variant_id')->filter()->unique()->all())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $variant = $item->product_variant_id ? $variants->get($item->product_variant_id) : null;

                if (! $product || (int) $item->quantity < 1 ||
                    ($item->product_variant_id && ! $variant) ||
                    ($variant && (int) $variant->product_id !== (int) $product->id) ||
                    (! $variant && ($product->has_variants || filled($item->variant_name)))) {
                    throw ValidationException::withMessages([
                        'purchase' => __('Purchase items have missing or mismatched products or variants. Stock was not changed.'),
                    ]);
                }
            }

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $variant = $item->product_variant_id ? $variants->get($item->product_variant_id) : null;

                $this->inventoryService->increase($product, $variant, (int) $item->quantity, InventoryMovement::TYPE_PURCHASE_IN, [
                    'purchase_id' => $lockedPurchase->id,
                    'reason' => 'Purchase received',
                    'unit_cost' => (float) $item->unit_cost,
                    'expiration_date' => $item->expiration_date,
                    'meta' => ['purchase_reference' => $lockedPurchase->reference, 'purchase_item_id' => $item->id],
                ]);
            }

            $lockedPurchase->update([
                'status' => Purchase::STATUS_RECEIVED,
                'received_date' => now()->toDateString(),
            ]);

            return true;
        });
    }
}
