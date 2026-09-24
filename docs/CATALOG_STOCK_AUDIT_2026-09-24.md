# Catalog stock audit — 2026-09-24

Working line: `v42-clean-baseline`. This batch is source-only; QAS remains on the operator-confirmed application commit `0a08253` until a separate upload.

## Behavior

- Saving a product now commits its catalog fields, variant rows and stock movement entries in one database transaction. Existing product and variant rows are locked; the editor compares current stock and variant membership with the quantities loaded when the form opened. A stale form stops before writing anything.
- New simple products and new variants with starting stock receive an opening `adjustment` movement from zero. Later stock edits receive a signed movement with the final balance, `catalog_editor` source, admin ID, SKU snapshot, and an admin activity entry. An unchanged stock value creates no movement. The editor refreshes saved variant IDs and stock snapshots after each save so another save does not recreate a variant.
- The catalog table's simple-product quick quantity edit uses the counted-stock adjustment service with its original value, `catalog_inline` source and admin audit. Stale, repeated and no-op submissions follow the same safeguards as the dedicated adjustment page.
- A variant with stock must be counted down to zero before deletion or conversion to a simple product. A simple product with stock must be counted down to zero before conversion to variants. This prevents the editor from silently discarding inventory when changing the product structure.
- Duplicating a product starts its independent stock at zero, instead of copying sellable units. The inventory explorer distinguishes manual adjustments, product editor changes and catalog quick edits. New messages are in English and Arabic.

## Verification and remaining boundary

- `CatalogStockAuditTest` covers opening/change/no-op movements, admin attribution, stale simple and variant edits, variant removal, catalog quick edit and copy stock. Existing product editor and inventory-adjustment regressions remain in the full CI suite.
- The editor records stock from its own save and the quick edit. Other stock sources outside these flows must be reviewed separately; the movement ledger is not asserted to reconstruct all historical inventory. Existing records receive no retroactive opening movements.
- Automated verification is complete: [Hardening CI run 36031782934](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36031782934) passed at `6889248` with 135 tests (736 assertions) and the production frontend build. The owner deferred manual QAS review to the consolidated later phase. QAS remains on `0a08253`; `main` and Production remain unchanged.

## Consolidated QAS scenarios later

1. Create simple and variant products with starting stock, then increase/decrease stock and compare quantity, one movement per change, actor, source and balance.
2. Open two edit tabs, save a stock change in one, then try to save content/stock in the stale tab. Confirm no part of the stale save persists.
3. Count a variant to zero, delete it, and check the movement history remains. Try to remove a variant or convert a simple product while stock is nonzero.
4. Save the same form again, duplicate a stocked product, and try catalog quick edit with a stale quantity. Review English/Arabic desktop/mobile feedback.
