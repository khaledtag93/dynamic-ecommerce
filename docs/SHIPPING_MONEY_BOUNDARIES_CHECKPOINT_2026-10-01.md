# Shipping Money Boundaries Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `1c1e386d423441e9143ee0a45179b36abe5e081d`
- Commit: `fix: harden shipping rate money boundaries`
- Hardening CI: **#2394 Green**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS failed jobs: **0**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Shipping rate money now respects the same exact-cent boundary as the persisted `DECIMAL(12,2)` fields:

- admin shipping amount rejects monetary over-precision instead of relying on database truncation/rounding;
- free-shipping threshold rejects over-precision;
- threshold comparison is performed in integer cents;
- exact threshold qualifies deterministically;
- one cent below threshold remains chargeable and reports exactly one cent remaining;
- before-discount and after-discount threshold policies remain distinct and explicit.

## QAS runtime evidence

A transaction-wrapped QAS smoke passed all of the following against deployed `1c1e386d`:

- exact `0.20` after-discount threshold qualifies and produces zero shipping;
- `0.19` does not qualify, keeps the configured shipping charge, and reports `0.01` remaining;
- before-discount threshold uses the exact subtotal-cent basis;
- deployed admin validation rejects over-precision for both rate amount and free-shipping threshold;
- smoke shipping method and zone were rolled back successfully with no test residue.

The QAS post-deploy verification also confirmed no pending migrations, scheduler and queue worker healthy, and no failed jobs.

## Next gate

Keep authenticated browser acceptance queued for the dedicated testing phase. Continue the Business Process & ERP Integrity audit at the next genuinely unclosed monetary/accounting boundary; do not reopen this shipping-money slice unless a reproduced defect appears.
