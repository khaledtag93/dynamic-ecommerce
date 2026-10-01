# Payment Capture & Reconciliation Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `c88c9bd424356d91f8796ac92f05be8de575d8a7`
- Application commit: `c88c9bd4` — `fix: harden payment capture reconciliation cents`
- Hardening CI: **#2409 Green**
- Full PHPUnit: **868 passed / 17,839 assertions**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Payment capture and order/payment reconciliation are now cent-authoritative without changing the existing lifecycle policy:

- manual capture guards sum existing paid payments and the candidate payment in exact integer cents;
- gateway over-capture detection compares projected paid cents with order-total cents;
- over-capture metadata still records normal monetary values for operators while the decision is made in cents;
- order-payment synchronization derives refund-ledger, captured-ledger and paid-ledger totals in exact cents;
- split captures such as `0.10 + 0.20` deterministically match an order total of `0.30`;
- split refunds such as `0.10 + 0.20` deterministically produce a canonical refund snapshot of `0.30`;
- provider reversal reconciliation converts canonical refund totals through exact decimal arithmetic instead of binary-float multiplication;
- manual over-capture remains blocked, while gateway over-capture remains recorded as an exception requiring refund review. No lifecycle policy was changed.

## Regression coverage

Focused regressions now cover:

- manual exact-cent split capture: `0.10 + 0.20 = 0.30`;
- gateway exact-cent split capture without a false over-capture flag;
- exact-cent payment sync for split paid ledgers;
- exact-cent refund sync for split refund ledgers;
- the existing manual over-capture rejection and gateway over-capture exception behavior remain covered.

The local Windows checkout could not execute PHPUnit because its CLI PHP is 8.2.28 while the current repository requires PHP >= 8.3. Syntax checks and `git diff --check` passed locally; Hardening CI is the authoritative full-suite gate.
## CI evidence

Hardening CI #2409 passed on the exact application SHA with:

- **868 tests / 17,839 assertions**
- clean MySQL migration
- PHP/Bash syntax
- Laravel boot/routes
- Blade/config compilation
- dependency audits
- shared browser interaction tests
- frontend production build

## QAS runtime evidence

QAS was promoted from `28fd003d` to `c88c9bd4`.

Post-deploy verification confirmed:
- exact remote HEAD `c88c9bd424356d91f8796ac92f05be8de575d8a7`
- application/static HTTP 200
- maintenance OFF
- no pending migrations
- scheduler and queue worker healthy
- failed jobs 0
- strict ops health Green
A transaction-wrapped runtime smoke passed on deployed services:

- manual `0.10 + 0.20` capture paid an exact `0.30` order;
- manual `0.10 + 0.21` against `0.30` was rejected before capture and left the second payment Pending;
- gateway `0.10 + 0.20` paid the order without a false over-capture flag;
- gateway `0.10 + 0.21` recorded a `payment_overcapture` exception with projected total `0.31` and order total `0.30`;
- exact split paid-ledger sync produced Paid;
- exact split refund-ledger sync produced Refunded with `refund_total = 0.30`;
- outer rollback removed all smoke records.

## Next boundary

Continue the Business Process & ERP Integrity money audit from this exact checkpoint. Re-scan the remaining business-critical runtime calculations and choose one genuinely unclosed boundary. Do not reopen Fulfillment Payment or Payment Capture/Reconciliation money integrity without a reproduced defect.

Authenticated browser acceptance remains queued for the dedicated systematic QAS testing phase. Production release gates remain separate.
