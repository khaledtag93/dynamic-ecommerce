# Tax / Invoice / Fiscal Document Readiness Checkpoint — 2026-09-29

## Branch and verification state
- Branch: `sec03-framework-upgrade`
- Latest verified application SHA: `0da6af31`
- No application code change was required for this readiness boundary.
- Production remains unchanged.
- QAS has not been advanced to this branch head by this checkpoint.

## Pass status
**Tax / Invoice / Fiscal Document Readiness: CLOSED for current scope**

This checkpoint does **not** mean tax calculation or fiscal invoicing is implemented.

## Current tax behavior
- Storefront cart tax is currently fixed at `0.00`.
- Checkout persists that value into `orders.tax_total` as part of the order snapshot.
- POS orders currently persist `tax_total = 0`.
- Grand total calculation includes the tax field structurally, but there is currently no tax engine that calculates VAT/sales tax by jurisdiction, product, customer, or rate.
- No active commerce flow identified in this audit mutates `orders.tax_total` after order creation.

## Current document boundary
- Customer-facing printable output is explicitly labeled **Order receipt**.
- The receipt explicitly states that it is **not a tax or fiscal invoice**.
- The receipt shows a Tax row only when recorded `tax_total > 0`.
- Current receipt behavior does not claim legal/fiscal invoice status.

## Historical snapshot boundary
The existing order model already stores useful commercial snapshots:
- subtotal
- discount total
- shipping total
- tax total
- grand total
- order item unit prices and line totals
- customer/billing/shipping information
- currency

These fields are useful prerequisites for future invoicing, but they are not by themselves a compliant fiscal invoice model.

## Not implemented
Flowra does not currently provide an authoritative tax/fiscal layer for:
- VAT/tax registration numbers
- jurisdiction-specific tax rates
- inclusive/exclusive tax pricing rules
- product tax categories
- customer tax exemptions
- line-level tax allocation
- tax rounding policy
- tax point / supply date semantics
- invoice numbering sequences
- credit/debit notes
- invoice cancellation/reversal lifecycle
- government/e-invoicing submission
- signed/immutable legal invoice documents
- tax reporting or statutory filing

## Integrity decision
Do not infer VAT from country or store location and do not silently turn the current `tax_total` field into a tax engine.

Tax calculation and fiscal invoicing must be introduced as an explicit future domain with its own:
- business rules
- configuration
- document lifecycle
- immutable snapshots
- permissions
- audit trail
- jurisdiction/provider integration where required
- regression suite

## Current readiness conclusion
The current implementation is safe for a non-fiscal commerce receipt model because:
- tax is not falsely calculated,
- the receipt does not claim invoice status,
- current commercial totals preserve a tax snapshot field for future extension,
- fiscal functionality is not implied where it does not exist.

## Next phase
Continue the Business Process & ERP Integrity Audit on the next meaningful domain boundary.

Recommended next domain:
**Accounting / General Ledger Integration Readiness**
- separate operational commerce events from accounting postings
- identify authoritative events for sales, refunds, inventory/COGS, supplier receipts/payments, and payroll
- verify no current dashboard/analytics metric is being treated as a GL ledger
- define future journal-posting boundaries without inventing accounting entries prematurely

Keep QAS promotion separate until a deliberate deployment checkpoint is chosen.
