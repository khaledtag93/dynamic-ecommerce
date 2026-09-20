# V42 Hardening Log

## 2026-09-20 — Baseline established
- Source snapshot: V42.
- Working branch: `v42-clean-baseline`.
- `main` and Production intentionally left unchanged.
- Verified expected V42 source against Git blob hashes after intentional runtime/security exclusions.
- Removed tracked `.env`, dependencies, local DB, logs, sessions, local uploads, generated Laravel cache files, and backup artifacts.

## 2026-09-20 — Clean-clone boot fix
- Added tracked placeholders for required Laravel runtime directories.
- Removed DB side effects from `GrowthController` construction.
- Route discovery verified successfully with 187 application routes.

## 2026-09-20 — Payment/security hardening
- Fixed Paymob iframe URL infinite recursion.
- Enforced callback HMAC validation before any payment state mutation.
- Added amount/currency/integration ID callback integrity checks.
- Prefer signed Paymob identifiers before merchant order fallback.
- Protected paid/refunded states from late or replayed gateway downgrades.
- Reduced sensitive Paymob logging and callback payload retention.
- Required HMAC secret for Paymob to report itself as configured.

## 2026-09-20 — Authorization hardening
- Changing legacy admin role state now requires a super admin, even when the caller has `customers.manage`.

## Verification evidence
- PHP syntax: 298 files checked, 0 syntax errors.
- Route discovery: 187 routes, success.
- Paymob HMAC smoke tests: valid GET/POST accepted; invalid signature rejected.
- Paymob iframe URL smoke test: success.

## Remaining Production blockers
- Credential rotation for any secret previously present in Git history.
- Full PHPUnit/CI run with required PHP extensions.
- End-to-end Paymob sandbox transaction.
- Concurrency/idempotency validation for stock, coupons, refunds, and orders.
- Deploy/rollback rehearsal on target environment.
