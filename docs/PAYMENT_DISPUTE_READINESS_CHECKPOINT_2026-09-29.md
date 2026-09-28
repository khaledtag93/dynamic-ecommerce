# Payment Disputes / Chargebacks Readiness Checkpoint — 2026-09-29

## Branch and verification state
- Branch: `sec03-framework-upgrade`
- Latest application SHA: `b42e525b` — `fix: preserve provider reversal evidence`
- Working tree was clean before this documentation checkpoint.
- Production remains unchanged.
- QAS has not been advanced to this branch head by this checkpoint.

## Pass status
**Payment disputes / chargebacks readiness: CLOSED for the current integration boundary**

This does **not** mean a full dispute-management or chargeback module exists.

## Closed invariants
- A late provider refund/void signal must not downgrade or destructively rewrite an already-paid payment.
- Provider refund/void evidence is preserved separately from Flowra's canonical `order_refunds` ledger.
- Provider reversal evidence is copied to the related order for finance/operations visibility.
- Replayed identical reversal evidence is idempotent and does not duplicate timeline events.
- A provider reversal does not fabricate an `order_refunds` row.
- A canonical order refund remains authoritative for Flowra's commercial refund history.
- Paid/refunded payment terminal-state protections remain intact.

## Explicit current boundary
The current Paymob callback contract exposes signed transaction fields including refund/void state, but no trusted chargeback/dispute lifecycle is modeled in Flowra.

Flowra must therefore **not** infer chargebacks from generic failure text, declined callbacks, refunds, voids, or provider status strings.

A future dispute layer should be introduced only when a trusted provider/API/webhook source is available. It should model, where applicable:
- provider dispute / chargeback identifier
- original payment/capture reference
- disputed amount and currency
- reason/category and evidence deadline
- opened / won / lost / withdrawn lifecycle
- evidence submissions and immutable provider responses
- fee/penalty and settlement impact
- accounting/reconciliation status
- idempotency key and audit actor/source

## Evidence implemented in the current slice
- `PaymentService::transitionGatewayPayment()` records provider refund/void evidence without mutating the canonical refund ledger.
- Paymob callback handling forwards signed `is_refunded` / `is_voided` evidence to the payment service.
- Regression coverage proves a paid payment remains terminal while reversal evidence is retained and no refund row is fabricated.
- EN/AR operational copy distinguishes provider reversal evidence from canonical order refunds.

## Verification note
Local PHP syntax, JSON parsing, and `git diff --check` passed before `b42e525b` was committed and pushed. Full PHPUnit cannot run in the current local checkout because `vendor/` is intentionally absent; integrated CI remains the authoritative full-suite gate.

## Next domain
Continue the Business Process & ERP Integrity Audit at the next unclosed financial/ERP boundary. Do not build a synthetic chargeback workflow until a real provider dispute source exists.
