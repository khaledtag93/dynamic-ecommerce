<?php

namespace App\Services\Commerce;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function receive(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $purchase->loadMissing('items');

            foreach ($purchase->items as $item) {
                $product = $item->product ?? Product::find($item->product_id);
                if (! $product) { continue; }
                $variant = $item->variant ?? ($item->product_variant_id ? ProductVariant::find($item->product_variant_id) : null);

                $this->inventoryService->increase($product, $variant, (int) $item->quantity, InventoryMovement::TYPE_PURCHASE_IN, [
                    'purchase_id' => $purchase->id,
                    'reason' => 'Purchase received',
                    'unit_cost' => (float) $item->unit_cost,
                    'expiration_date' => $item->expiration_date,
                    'meta' => ['purchase_reference' => $purchase->reference],
                ]);
            }

            $purchase->update([
                'status' => Purchase::STATUS_RECEIVED,
                'received_date' => now()->toDateString(),
            ]);

            return $purchase->fresh('items');
        });
    }
}
