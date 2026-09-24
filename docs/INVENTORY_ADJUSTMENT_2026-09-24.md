# Inventory counted-stock adjustment — 2026-09-24

Working branch: `v42-clean-baseline`. Source implementation only; QAS currently runs application commit `0a08253` and does not include this new batch.

## Behavior

- Admins with `inventory.manage` can search by product name, SKU or barcode, then choose a simple product or an exact variant. The selected record shows its current quantity.
- The form takes a final counted quantity and a required reason. The submitted original quantity is checked again under a product/variant database lock before the change. A stale form cannot overwrite another inventory change. A repeated successful submission becomes stale; saving an already equal quantity creates no movement.
- Product/variant mismatches and omitted variants for variant products are rejected. The final quantity must be nonnegative. A legacy negative balance can be corrected by entering a nonnegative counted quantity.
- A successful change creates an `adjustment` inventory movement with the signed difference, resulting balance, reason, original/final counts and admin ID. It also writes the existing admin activity log within the same transaction. Unit cost and expiry are not changed.
- The movement explorer labels this source as a manual adjustment. The workflow uses the existing admin confirmation and bilingual UI.

## Verification and boundary

- `InventoryAdjustmentTest` covers signed movements and audit attribution, stale/repeated counts, no-op, variant ownership and isolation, permission boundary, request validation and the admin flow.
- CI: pending code commit. Manual QAS review has been deferred by the owner for a consolidated review phase; Production and `main` remain unchanged.
- This adds an audited **manual adjustment path**. Existing direct product edits and other stock sources have their own behavior; the batch does not claim that every historic or alternate quantity edit has an inventory movement.

## Consolidated QAS review later

1. Count a simple product and a variant; save increases and decreases, then check exactly one movement per change with the correct signed amount, balance and reason.
2. Open the form twice, save one count, then submit the stale tab. Confirm the second attempt is rejected and stock remains unchanged.
3. Check zero stock, no-op count, invalid variant link, permission boundary, Arabic/English desktop/mobile layout and confirmation.
