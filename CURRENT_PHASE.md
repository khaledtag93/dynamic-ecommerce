# CURRENT PHASE
## V42 Production Hardening

### Official baseline
- **V42** — Cost Calculator refactor + Arabic/English translation updates
- Clean Git baseline completed on branch `v42-clean-baseline`.
- Baseline verification: all expected V42 source files matched by Git blob hash after intentional runtime/security exclusions.

### Current phase
- Production hardening before promotion to `main` and before any Production deploy.

### Completed baseline work
- Removed tracked `.env`.
- Removed tracked dependency/runtime/local-data artifacts including `vendor/`, `node_modules/`, local uploads, logs, sessions, Laravel cache files, local SQLite DB, and backup files.
- Tightened `.gitignore`.
- Preserved application source/assets required by V42.
- Kept `main` and Production unchanged.

### Current hardening gate
1. Rotate any credentials that have previously appeared in Git history.
2. Verify payment callback/HMAC validation and payment idempotency.
3. Verify authorization and privilege-escalation paths.
4. Verify stock, coupon, refund, and order concurrency/idempotency.
5. Verify Cost Calculator formulas and reporting semantics.
6. Run automated tests and critical manual regression flows.
7. Validate deployment and rollback procedure.
8. Promote the verified baseline to `main` only after the gate is green.

### Release rule
No Production deployment until the hardening gate is completed or a remaining risk is explicitly documented and accepted.


### Hardening progress — 2026-09-20
Closed in code:
- clean-clone Laravel runtime directories are preserved with tracked `.gitkeep` files
- Growth controller no longer performs database writes/queries during route discovery
- Paymob iframe URL recursion fixed
- Paymob callback HMAC is mandatory and enforced for state-changing callbacks
- Paymob callback amount, currency, and integration ID are checked against the expected payment
- paid/refunded gateway states are protected from late callback downgrades
- admin role changes require a super admin
- Paymob logs no longer retain full callback payloads/secrets/PII

Verification completed:
- V42 baseline hash verification: zero missing and zero mismatched expected source files
- PHP syntax scan: 298 files, zero syntax errors
- Laravel route discovery: 187 routes loaded successfully
- Paymob HMAC smoke test: valid GET/POST signatures accepted; invalid signature rejected
- Paymob iframe URL smoke test passed

Environment limitation during verification:
- PHPUnit/config/view cache commands cannot be fully executed in the current test container because required PHP extensions (DOM, mbstring, XML/XMLWriter) are unavailable there. This does not close the testing gate; CI/server verification remains required.


### Hardening checkpoint — Green CI (2026-09-20)
The V42 hardening branch now has a successful GitHub Actions production-like build on MySQL 8.

Closed in this checkpoint:
- checkout cart rows are locked during order creation to prevent duplicate orders from double-submit
- stock decrement is atomic and refuses overselling
- coupon usage limits are consumed atomically
- order cancel/status/refund mutations serialize on the order row
- repeated cancellation cannot restore stock twice
- concurrent refunds cannot exceed the remaining refundable balance
- manual payment status changes cannot bypass the order refund ledger
- refund ledger state takes precedence during order payment-status synchronization
- staff-role management is restricted to super admins and cannot clear an admin into implicit legacy super-admin state
- Deploy Center is opt-in instead of enabled by default
- production deploy health checks require curl and verified TLS
- MySQL clean migration failure caused by an oversized growth-learning unique index was fixed
- compatibility migration added for existing databases

Latest successful CI evidence:
- Composer validation/install: passed
- PHP syntax scan: passed
- MySQL 8 `migrate:fresh`: passed
- Laravel boot + route discovery: passed
- config cache + Blade view compile: passed
- current PHPUnit suite: passed
- npm install + Vite production build: passed

Important limitation:
- the current PHPUnit suite is still very small, so targeted regression tests for the hardened business rules remain required.

### Additional hardening — 2026-09-20
- custom staff roles cannot be deleted while assigned to admin accounts; deletion is serialized with a database row lock to prevent cascade-based privilege escalation
- the permissions UI no longer offers an empty legacy-role assignment option; legacy fallback admins are mapped to the explicit Super Admin role when saved
- added a regression test covering assigned-role deletion protection
- removed remaining tracked runtime/user uploads from `storage/app/livewire-tmp` and `storage/app/public` and added explicit Git ignore rules for both paths
