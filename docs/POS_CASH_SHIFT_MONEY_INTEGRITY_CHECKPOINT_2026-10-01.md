# POS Cash Shift Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `1e90c5c3791dc8d9506e333996a9e9e42d612ddd`
- Core integrity commit: `9aa86a1f` — `fix: harden pos cash shift money reconciliation`
- Contract-alignment commit: `1e90c5c3` — `fix: align pos cash shift amount contract`
- Hardening CI: **#2414 Green**
- Full PHPUnit: **872 passed / 17,872 assertions**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

POS cash-shift opening, reconciliation, closing and variance calculations are now cent-authoritative while preserving the existing user-facing cash amount limit.

- opening and closing cash inputs preserve decimal strings through the controller boundary;
- inputs reject monetary over-precision instead of silently rounding;
- negative and unsupported-range values are rejected at the service boundary;
- stored opening/closing cash remains canonical two-decimal money;
- POS cash sales and qualifying POS cash refunds are normalized to integer cents before reconciliation;
- expected drawer cash is composed as opening cash + cash sales - cash refunds in integer cents;
- closing variance is calculated and persisted in integer cents;
- split-cent amounts such as `0.10 + 0.20` reconcile deterministically to `0.30`;
- the existing public/UI maximum of `999999999.99` remains unchanged; exact-cent hardening did not widen the business contract;
- existing POS refund qualification rules and cashier/shift ownership rules were not reopened.

## Regression coverage

Focused regressions cover:

- opening cash over-precision rejection;
- closing cash over-precision rejection without closing the shift;
- exact opening cash persistence;
- split POS cash sales `0.10 + 0.20 = 0.30`;
- exact POS cash refund `0.10`;
- exact expected cash `0.30`;
- counted cash `0.20`;
- persisted variance `-0.10`;
- existing whole-value reconciliation behavior;
- EN/AR service-message coverage and the POS form/server monetary contract.

The local Windows checkout cannot execute PHPUnit because its CLI PHP is 8.2.28 while the current repository requires PHP >= 8.3. Hardening CI is the authoritative full-suite gate.

## CI evidence
Hardening CI #2414 passed on the exact application SHA with:

- **872 tests / 17,872 assertions**
- clean MySQL migration
- PHP/Bash syntax
- Laravel boot/routes
- Blade/config compilation
- dependency audits
- shared browser interaction tests
- frontend production build

## QAS runtime evidence

QAS was promoted from `24022133` to `1e90c5c3`.

Post-deploy verification confirmed:

- exact remote HEAD `1e90c5c3791dc8d9506e333996a9e9e42d612ddd`
- application/static HTTP 200
- maintenance OFF
- no pending migrations
- scheduler and queue worker healthy
- failed jobs 0
- strict ops health Green

A transaction-wrapped runtime smoke passed on the deployed service:
- opening `0.109` rejected as over-precision;
- canonical opening cash persisted as `0.10`;
- split cash sales reconciled to `0.30`;
- POS cash refund reconciled as `0.10`;
- expected drawer cash was exactly `0.30`;
- closing `0.209` was rejected without closing the shift;
- closing counted cash persisted as `0.20`;
- persisted variance was exactly `-0.10`;
- outer rollback removed all smoke records.

## Next boundary

Continue the Business Process & ERP Integrity money audit from this exact checkpoint. Re-scan remaining business-critical monetary calculations and select one genuinely unclosed boundary.

Do not reopen POS Cash Shift, Cart/Checkout, Coupon, Promotion, Shipping, Fulfillment Payment or Payment Capture/Reconciliation money integrity without a reproduced defect.

Authenticated browser acceptance remains queued for the dedicated systematic QAS testing phase. Production release gates remain separate.
