# Sales COGS / Line Profit Reconciliation Checkpoint — 2026-09-29

## Verified application state

- Branch: `sec03-framework-upgrade`.
- Verified application / CI / QAS SHA: `cc8a3406ea4ada2bcdbcbec064b283296dc236b4` (`cc8a3406`).
- Immutable application checkpoint tag: `qas-sales-cogs-reconciliation-2026-09-29`.
- Hardening CI #2258: Green on that exact application SHA.
- Full PHPUnit: 770 passed / 17,099 assertions.
- CheckoutIdempotencyTest, PosCashierTest and SalesCogsReconciliationCommandTest all passed.
- Shared browser interactions and frontend production build passed.

## Closed integrity scope

- Order-level COGS/profit continues to derive from actual FEFO lot provenance where available, with legacy unit-cost fallback only for historical rows that have no lot provenance.
- `OrderItem.profit_amount` is now recalculated from persisted line revenue minus actual lot COGS when lot allocations exist, so new POS/storefront flows no longer retain stale standard-cost line profit after FEFO consumption.
- `commerce:reconcile-lot-cogs` now audits both aggregate order COGS/profit and stale line-profit snapshots.
- Dry-run remains non-destructive; `--apply` is explicit.
- The regression test snapshots Artisan command output once before making multiple assertions; previous CI failures #2255–#2257 were test-output consumption issues, not a production-command accounting failure.

## QAS evidence

- QAS deployed successfully from `7c66bc19` to `cc8a3406` with `deploy-qas.sh sec03-framework-upgrade`.
- No migrations were pending.
- QAS health HTTP 200.
- Static asset health HTTP 200.
- Maintenance mode OFF.
- Independent verification: remote HEAD exactly `cc8a3406ea4ada2bcdbcbec064b283296dc236b4`.
- Historical reconciliation dry-run on QAS:
  - scanned=2
  - lot-provenance=0
  - changed=0
  - line-profit-changes=0
  - applied=0
- No QAS historical apply was needed.

## Important accounting boundary

Line profit is now COGS-consistent, but it must not be interpreted as a fully allocated accounting contribution margin where order-level coupon/promotion discounts are not explicitly allocated to lines. Do not invent a discount-allocation policy. Shipping/landed cost, statutory tax/VAT, GL posting and provider dispute/chargeback accounting remain separate policy/integration boundaries.

## Next execution slice

Continue the Business Process & ERP Integrity Audit from the first unclosed profitability/accounting evidence boundary:

1. Audit reporting/history consumers of `inventory_movements.unit_cost`; where a report claims actual COGS, require lot provenance with a deliberate legacy fallback.
2. Then continue the next genuine accounting/business boundary rather than reopening already-closed Commerce Financial Integrity, Lot/Batch Integrity, Payroll Integrity or this COGS reconciliation slice without a reproduced defect.
3. Keep controlled authenticated QAS acceptance for Workforce/Payroll, Purchasing/Inventory and role-based end-to-end journeys as separate acceptance evidence.

## Production boundary

Production remains unchanged. Release gates still include:
- OPS-01 historical credential rotation/revocation evidence.
- PAY-01 real Paymob E2E evidence.
- OPS-02 isolated database restore rehearsal.
- Production scheduler/queue setup and verification.
