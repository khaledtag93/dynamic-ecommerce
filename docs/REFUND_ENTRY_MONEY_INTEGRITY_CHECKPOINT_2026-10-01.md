# Refund Entry Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `c81858d308bdeaf2ece7fbd8042921a4d5508b09`
- Application fix commit: `658c3860` — `fix: harden refund money boundaries`
- Regression-only follow-up: `c81858d3` — `test: make exact-cent refund assertion order independent`
- Hardening CI: **#2405 Green**
- Full PHPUnit: **863 passed / 17,823 assertions**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Refund entry is now cent-authoritative across Admin manual refunds, RMA completion, the canonical order-refund service, and component allocation:

- HTTP refund inputs enforce at most two decimal places and the persisted `DECIMAL(12,2)` range.
- Controllers preserve validated decimal strings instead of converting them to binary floats.
- `OrderActionService::refund()` canonicalizes new refund amounts to exact cents before any mutation.
- Idempotency compares canonical cents, so equivalent representations such as `20` and `20.00` are the same financial payload.
- Captured total, already-refunded total, exchange compensation, refundable balance, accumulated refund total, and full-vs-partial refund status are evaluated in integer cents.
- `RefundAllocationService` no longer converts money through `(float) * 100`; legacy/storage money is converted with exact decimal arithmetic and historical Half-Up cent semantics.
- RMA completion validates refund precision in the service boundary and compares the requested refund to RMA capacity in cents.
- New refund money/range/current-state failures have EN/AR copy.

## CI evidence

Hardening CI #2404 exposed one brittle new assertion that assumed refund-row ordering. Business values were correct and all RMA/refund-allocation tests were already Green.

The assertion was made order-independent in `c81858d3`. Hardening CI #2405 then passed:

- **863 tests / 17,823 assertions**
- clean MySQL migration
- PHP/Bash syntax
- Laravel boot/routes
- Blade/config compilation
- dependency audits
- shared browser interaction tests
- frontend production build

## QAS runtime evidence

QAS was promoted from `cff53fea` to `c81858d3`.

Post-deploy verification confirmed:
- exact remote HEAD `c81858d3`
- application/static HTTP 200
- maintenance OFF
- no pending migrations
- scheduler and queue worker healthy
- failed jobs 0
- strict ops health Green

A transaction-wrapped runtime smoke passed on deployed services:

- generic refund `0.101` was rejected before any ledger mutation;
- `0.10` then `0.20` produced exact `0.30` total and commercial refund snapshots;
- exact `0.30` capture moved the order and payment to Refunded;
- idempotency treated `20` and `20.00` as the same payload and left one ledger row;
- RMA completion rejected `50.001`, preserved Received status, and left the refund ledger empty;
- RMA completion with `50.00` completed and stored exactly `50.00`;
- outer rollback removed all smoke records.

## Next boundary

Continue Business Process & ERP Integrity at the remaining payment/fulfillment money comparisons. COD completion and non-COD fulfillment readiness still contain float/round comparisons between payment-ledger totals and order totals; audit and convert those comparisons to exact cents without changing their existing lifecycle policy.

Authenticated browser acceptance remains queued for the dedicated systematic QAS testing phase. Production release gates remain separate.
