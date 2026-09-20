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


## 2026-09-20 — Concurrency and financial consistency
- Locked checkout cart rows inside the transaction to prevent duplicate orders from the same cart snapshot.
- Changed stock decrement to an atomic conditional database operation.
- Made coupon usage-limit consumption atomic.
- Serialized cancel/status/refund actions with order row locks.
- Made repeat cancellation idempotent so inventory cannot be restored twice.
- Revalidated refundable balance under lock to prevent concurrent over-refunds.
- Made refund records authoritative for order refund state.
- Blocked direct manual payment-to-refunded transitions that bypass the refund ledger.

## 2026-09-20 — Authorization and deploy safety
- Closed staff-role self-escalation through empty role assignment.
- Staff role administration now requires super admin.
- Deploy Center is disabled unless explicitly enabled by environment configuration.
- Production health checks no longer disable TLS verification and cannot be silently skipped when curl is unavailable.
- Database rollback remains a documented Production blocker.

## 2026-09-20 — CI and clean-MySQL validation
Initial CI exposed a real migration defect: the composite unique key on `growth_offer_learning_snapshots` exceeded MySQL 8's index width under utf8mb4.
- Reduced key-column lengths to domain-appropriate sizes.
- Added a compatibility migration for existing/partially-created databases.
- Re-ran the hardening pipeline successfully.

Successful pipeline checks:
- Composer
- PHP syntax
- MySQL 8 clean migrations
- Laravel boot and routes
- config/view compilation
- existing PHPUnit suite
- npm/Vite production build

The build is green, but the existing PHPUnit suite is intentionally not treated as sufficient business regression coverage.
