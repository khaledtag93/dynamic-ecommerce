# Payment Stabilization Checklist

## Goal
Reach a reliable, production-ready payment flow before expanding channels in Phase 4.

## Core checks
- Verify callback URL correctness in all environments
- Confirm HMAC validation path for success and failure responses
- Prevent duplicate registration on refresh or repeated return visits
- Ensure declined payments do not remain misleadingly pending
- Confirm order, payment, and provider statuses stay synchronized
- Confirm customer-facing result pages are clear in Arabic and English
- Verify retry flow from failed/declined states
- Confirm admin payment log readability

## Operational checks
- Test with low-value and normal-value orders
- Test user cancellation before payment completion
- Test callback arrival after delayed redirect
- Test refresh on result page
- Test duplicate browser tabs

## Completion gate
Do not call payment fully closed until all scenarios above are manually validated in a public-like environment.


## Code-level hardening status — 2026-09-20
Verified in the V42 hardening branch:
- mandatory HMAC verification before payment-state mutation
- callback amount, currency, and integration-ID integrity checks
- paid/refunded terminal-state protection against late callback downgrades
- Order → Payment lock ordering for concurrent callback/refund safety
- full-refund Payment ledger synchronization
- duplicate/double-submit checkout protection
- callback log minimization; raw payload/full callback URL are not retained in failure logs
- production-like CI regression coverage on PHP 8.2 + MySQL 8

Still manual before Production:
- real Paymob test/sandbox success flow
- declined/failed flow
- delayed callback after redirect
- duplicate callback delivery
- browser refresh/retry and duplicate-tab behavior
- verify actual Paymob dashboard transaction state against Order and Payment records
