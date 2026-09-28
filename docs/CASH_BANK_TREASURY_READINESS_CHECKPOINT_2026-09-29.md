# Cash / Bank Reconciliation & Treasury Readiness Checkpoint — 2026-09-29

## Branch and verification state
- Branch: `sec03-framework-upgrade`
- Latest verified application SHA: `5f354ed4`
- GitHub application check: completed / success
- Production remains unchanged.
- QAS has not been advanced to this branch head by this checkpoint.

## Pass status
**Cash / Bank Reconciliation & Treasury Readiness: CLOSED for current scope**

This checkpoint does **not** mean full treasury, bank reconciliation, or payment-provider settlement is implemented.

## Closed invariants

### Bank transfer evidence
- A manual Bank Transfer transition to Paid requires a dedicated bank-transfer reference.
- The rule is enforced in the Payment service, not only in the controller/UI.
- The evidence reference is persisted in payment metadata.
- The evidence is visible in the Payment workspace for finance review.
- Split-payment and overcapture flows preserve this evidence requirement.

### Capture vs merchant settlement
- Flowra Paid/Captured status represents confirmed payment transaction evidence.
- It does not claim that a provider payout reached the merchant bank account.
- Gateway logs are described as transaction verification evidence, not bank-settlement reconciliation.
- Payment workspace explicitly warns that capture does not prove merchant payout or bank settlement.

### Currency-safe captured totals
- Captured payment totals are grouped by recorded currency.
- Different currencies are never summed into a single displayed amount.
- The Payment dashboard no longer labels a cross-currency sum as EGP.
- No implicit FX conversion is performed.

### POS cash reconciliation
- POS cash shifts track opening cash.
- Cash sales are added to expected drawer cash.
- Cash refunds are subtracted from expected drawer cash.
- Expected cash is compared with counted cash to produce variance.
- Full cash refunds remain visible as original sales plus explicit refund movements, preserving reconciliation history.

## Current treasury boundary
Flowra does not currently model:
- gateway payout batches
- provider settlement dates
- bank statement imports
- merchant-bank reconciliation records
- gateway processing fees / MDR
- payout net amounts
- payout currency conversion
- settlement exceptions
- bank-account clearing
- treasury transfers

These should not be inferred from payment status.

## Future provider-settlement layer
Introduce settlement/reconciliation models only when a real provider or bank data source exists.

A future settlement model should include, where relevant:
- provider / merchant account
- payout or settlement batch identifier
- settlement date
- gross captured amount
- refunds / chargebacks
- provider fees
- taxes/withholding on fees
- net payout amount
- currency
- matched payment ids
- unmatched differences
- reconciliation status
- immutable reconciliation evidence
- import/API idempotency

## Current readiness conclusion
The current commerce system now cleanly separates:
- customer payment capture,
- bank-transfer evidence,
- POS physical cash reconciliation,
- and future provider/bank settlement.

No current UI or aggregate should be interpreted as proof that captured gateway money has reached the merchant bank account.

## Verified commit chain
- `c1abb8dc` — require evidence for bank transfer capture
- `fa17e0b7` — distinguish capture from gateway settlement
- `5f354ed4` — keep captured payment totals currency safe

Final application head `5f354ed4` was verified Green by GitHub CI.

## Next phase
Continue the Business Process & ERP Integrity Audit on the next meaningful domain boundary.

Recommended next domain:
**Chargebacks / Payment Disputes Readiness**
- verify whether gateway chargebacks/disputes are modeled at all
- distinguish refunds from involuntary payment reversals
- protect inventory/order/customer history from destructive dispute handling
- avoid treating a chargeback as a normal refund unless explicitly modeled

Keep QAS promotion separate until a deliberate deployment checkpoint is chosen.
