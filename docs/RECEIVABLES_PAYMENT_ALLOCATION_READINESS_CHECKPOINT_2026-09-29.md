# Receivables / Payment Allocation Readiness Checkpoint — 2026-09-29

## Branch and verification state
- Branch: `sec03-framework-upgrade`
- Latest verified application SHA: `0da6af31`
- GitHub application check: completed / success
- Production remains unchanged.
- QAS has not been advanced to this branch head by this checkpoint.

## Pass status
**Receivables / Payment Allocation Readiness: CLOSED for the current commerce scope**

## Current accounting boundary
Flowra currently models commercial settlement at the order level through:
- Orders
- Payment ledger records
- Refund ledger records
- Returns / RMA

Flowra does not currently model:
- Accounts Receivable subledger
- Fiscal invoices
- Credit memos
- Customer credit limits
- Invoice-to-payment allocation
- Refund-to-specific-payment allocation

The current order-level aggregation is sufficient for the implemented commerce workflows. Explicit allocation tables should be introduced only when a real AR/invoice/accounting use case requires them.

## Closed invariants

### Split / multiple payment semantics
- Multiple payment records may settle one order.
- The order is not marked Paid merely because one payment is Paid.
- Paid status is derived from the aggregate captured amount.
- Partial captured value keeps the order payment status Pending.
- Aggregate captured value equal to or above the order total represents a paid commercial obligation, with overcapture handled separately.

### Overcapture integrity
- Manual capture that would exceed the order total is rejected before financial mutation.
- Gateway-confirmed overcapture is recorded rather than hidden because the external capture occurred.
- Gateway overcapture records an explicit `payment_overcapture` exception with:
  - payment id
  - transaction id
  - projected paid total
  - order total
  - refund-required flag
  - timestamp
- An overcaptured order cannot enter fulfillment while paid ledger value does not exactly match the order total.
- Activity history records the overcapture condition.

### Refund authority
- Refundable balance is derived from actual captured payment ledger value minus refund ledger value.
- Refunds cannot exceed money actually captured.
- Refund logic does not rely only on `grand_total`.
- Overcaptured amounts remain refundable through the canonical refund flow.
- Full financial refund is reached when refund ledger value reaches the total captured amount.

### Split-payment refund behavior
- A full refund across split captures marks all active Paid payment records Refunded.
- `refunded_at` is recorded for those captures.
- Refundable balance reaches zero after the complete captured value is refunded.

### Overcapture resolution
- Refunding only the commercial order value while excess capture remains keeps the order Partially Refunded.
- The overcapture exception remains open until the excess capture is also refunded.
- Once the full captured amount is refunded:
  - payment status becomes Refunded
  - `payment_overcapture.refund_required` becomes false
  - `payment_overcapture.resolved_at` is recorded
  - remaining refundable balance becomes zero

### Fixture / regression integrity
- Tests representing Paid orders now include real paid payment ledger records when financial refund/cancellation behavior depends on captured money.
- Legacy test fixtures no longer treat `orders.payment_status = paid` as sufficient financial evidence by itself.

## Verified commit chain
- `6318cf17` — prevent payment overcapture from fulfilling orders
- `0366c84d` — derive order paid status from captured total
- `a5484867` — cap refunds by captured payment ledger
- `08a5b7ab` — align paid order fixtures with payment ledger
- `0da6af31` — cover full refund across split captures

Final application head `0da6af31` was verified Green by GitHub CI.

## Future boundary
Introduce explicit payment allocation / receivables models only when requirements add concepts such as:
- invoices or installments
- customer credit accounts
- credit/debit memos
- one payment allocated across multiple documents
- one refund tied to a specific original capture
- accounting/GL posting requirements

Until then, keep the commerce settlement model order-centric and ledger-backed rather than inventing a pseudo-AR layer.

## Next phase
Continue the Business Process & ERP Integrity Audit on the next meaningful domain boundary.

Recommended next domain:
**Tax / Invoice / Fiscal Document Readiness**
- confirm current order receipts are intentionally non-fiscal
- audit tax calculation/storage semantics
- verify tax totals are immutable historical snapshots where needed
- identify what would be required before adding legal/fiscal invoices without pretending current receipts are invoices

Keep QAS promotion separate until a deliberate deployment checkpoint is chosen.
