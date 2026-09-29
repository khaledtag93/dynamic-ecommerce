# Sales COGS Integrity QAS Checkpoint — 2026-09-29

## Verified revision
- Branch: `sec03-framework-upgrade`.
- Application / CI / QAS SHA: `7c66bc19f074d69c007be8fcc1027fb011f7af0d`.
- Commit: `fix: derive sales cogs from lot provenance`.
- Hardening CI #2252: Green.
- Immutable tag: `qas-sales-cogs-integrity-2026-09-29`.

## Integrity rules closed
- Order-level COGS now uses the actual FEFO lot allocations persisted on each order item.
- Mixed-cost lots are costed by exact allocated quantity × lot unit cost instead of current/average catalog valuation.
- Legacy order items without lot provenance retain the historical `unit_cost` fallback.
- POS recalculates order COGS/profit after inventory consumption, when actual lot provenance exists.
- Online payment retry recalculates COGS when released stock is re-reserved from different lots.
- Paid/authorized online reservation transitions also refresh COGS after reservation allocation.
- Restocked POS/RMA units recover COGS from the original lot movements for lot-tracked orders.
- Money aggregation is performed in integer cents before persisting decimal totals.

## Regression coverage
- Checkout FEFO allocation with different lot costs proves order `cost_total` follows lot provenance.
- POS mixed-lot sale proves post-consumption COGS/profit refresh.
- POS return proves recovered COGS follows the original restored lot cost.
- Online payment retry proves a changed re-reservation changes historical COGS to the new actual allocation.

## QAS evidence
- QAS promoted from `123fcbc9` to `7c66bc19` through `deploy-qas.sh sec03-framework-upgrade`.
- No new migrations were pending.
- Remote HEAD matches the verified CI SHA.
- Maintenance mode OFF.
- Home HTTP 200 and Login HTTP 200.
- Static asset deploy health HTTP 200.
- Protected Payroll route redirects unauthenticated requests to Login.

## Explicit boundary
- Order-level `cost_total` / `profit_total` is the authoritative profitability evidence for this slice.
- `order_items.profit_amount` is not promoted to authoritative accounting evidence because storefront coupon/promotion discounts currently live at order level and no line-allocation policy has been defined.
- Shipping cost, tax liability, statutory accounting/GL posting, and line-level discount allocation remain separate policy/accounting boundaries.
- Signed-in destructive browser acceptance remains separate from source/full-CI/deployed-QAS integrity evidence.
