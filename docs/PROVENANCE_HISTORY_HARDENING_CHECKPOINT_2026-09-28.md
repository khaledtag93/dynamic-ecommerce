# Provenance / History Hardening Checkpoint — 2026-09-28

## Branch and verification state
- Branch: `sec03-framework-upgrade`
- Latest verified application SHA: `904ef4cf`
- Commit: `fix: preserve lot allocation order provenance`
- GitHub application check: completed / success
- Production remains unchanged.
- QAS has not been advanced to this branch head by this checkpoint. Do not describe `904ef4cf` as deployed to QAS unless a later server deployment explicitly verifies it.

## Pass status
**Provenance / History Hardening Pass: CLOSED**

This pass focused on preserving commercial, financial, inventory, purchasing, returns, and reservation audit history at the database boundary. The application/service guards remain in place, but critical ownership and history invariants are now also enforced by relational constraints.

## Closed invariants

### Orders / financial history
- Order refund history is preserved; an order owning refund ledger rows cannot be directly deleted.
- Order payment history is preserved; an order owning payment ledger rows cannot be directly deleted.
- Sales line history is preserved; an order owning order items cannot be directly deleted.
- Inventory movements linked to orders retain order provenance instead of being orphaned by parent deletion.

### Stock reservations / online inventory
- A stock reservation must reference an order item belonging to the same order.
- Stock reservation history cannot be erased by direct order or order-item deletion.
- Lot movement ownership requires its order item and reservation to belong to the same order.
- Lot allocation provenance is preserved; linked order, order item, and reservation references use restrictive deletion semantics.

### Returns / refunds / POS
- RMA items carry explicit order ownership and cannot reference an order item from another order.
- Refunds linked to an RMA must belong to the same order.
- Refund idempotency includes the linked RMA identity.
- POS return items carry explicit order ownership and cannot mix refund/order-item provenance across orders.
- POS return history uses restrictive deletion semantics instead of silent cascading removal.

### Purchasing / inventory provenance
- Purchase receipt items are constrained to the same purchase as their receipt and purchase item.
- Supplier settlements are constrained to the purchase supplier.
- Inventory lots referencing purchase receipts/items are constrained to the same purchase.
- Inventory lot movement history cannot be erased by directly deleting its lot or inventory movement.
- Inventory movements linked to purchases preserve purchase provenance.
- Inventory lots linked to purchases preserve purchase provenance.

## Verified commit chain for this pass
- `130a6b86` — enforce order reservation item ownership
- `3862a3d1` — enforce lot movement order ownership
- `3e3ba5e6` — enforce RMA item order ownership
- `75ae42a2` — enforce refund RMA ownership
- `12fe9c0e` — enforce POS return order ownership
- `e71e9368` — align legacy POS return test fixture with ownership schema
- `af6a7a8f` — preserve order refund history
- `d2ba2f05` — preserve order payment history
- `f693278c` — preserve order item history
- `49fe7521` — preserve stock reservation history
- `8d672d74` — preserve inventory movement order history
- `6b50d38d` — preserve inventory movement purchase history
- `39a91c37` — preserve inventory lot purchase history
- `904ef4cf` — preserve lot allocation order provenance

All listed application heads were followed through GitHub CI, with the final head `904ef4cf` verified successful.

## Deliberately not changed
- `inventory_lots.source_inventory_movement_id` remains nullable / null-on-delete. Current lot-movement history constraints already prevent deletion of source movements that own allocation history, so an additional constraint was not added without a distinct reachable defect.
- Non-audit relationships such as messaging/logging/support references were not mechanically converted to RESTRICT. This pass avoided blanket FK changes and changed only business-history relationships with a clear preservation requirement.

## Next phase
Move from micro-level FK/provenance hardening to **end-to-end Business Process & ERP Integrity Audit**.

Recommended next slice:
1. Fulfillment / delivery lifecycle integrity.
2. Validate status transitions, partial/failed delivery, cancellation interaction, stock/payment/COD effects, and immutable history.
3. Close one concrete business invariant at a time with targeted regression coverage.
4. Keep QAS deployment separate until a meaningful checkpoint is intentionally promoted.
