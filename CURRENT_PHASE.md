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

### Financial/destructive-action hardening follow-up — 2026-09-20
- gateway payment callbacks now serialize on the Payment row so competing paid/failed callbacks cannot race from the same stale pending state
- order payment-status synchronization now serializes on the Order row
- permanent order deletion is disabled in both backend and admin UI to preserve payment, refund, coupon, inventory, and audit history
- added regression coverage for paid-payment terminal behavior and order retention
- CI verification for this follow-up is pending on the latest branch commit


### Final code-hardening pass — 2026-09-20
Verified and closed on `v42-clean-baseline`:
- financial mutation lock ordering is standardized as Order → Payment to avoid callback/refund lock inversion
- a full refund now aligns the Payment ledger to `refunded`; partial refunds keep the original paid transaction while the Order remains `partially_refunded`
- late gateway failure callbacks cannot downgrade a fully refunded payment
- checkout rollback coverage proves that a late business-rule failure rolls back the Order, items, Payment, inventory movements, stock decrement, and cart mutation together
- cancellation restocking now creates exactly one `refund_restock` inventory movement and repeated cancellation cannot duplicate it
- cancellation restock records historical movement cost without overwriting the product's current cost valuation
- Cost Calculator `profit_margin` now uses profit / selling price; the old profit / cost formula was markup, not margin
- deploy backups no longer contain a duplicate app-level `.env`; deploy/rollback files use private `umask 077` handling
- manual rollback now enters maintenance mode, restores files, rebuilds caches, brings the app up, and requires a verified HTTPS health check
- rollback/deploy temporary environment copies are process-unique and private
- Paymob callback failure logging no longer stores the raw callback payload or full callback URL
- Paymob callback service results expose callback shape only, not the raw payload

Latest verification:
- GitHub Actions head `b9a46a6`: **green**
- PHP syntax: passed
- Bash syntax: passed
- MySQL 8 clean migration: passed
- Laravel boot/routes: passed
- config cache + Blade compile: passed
- PHPUnit including new hardening regressions: passed
- frontend production build: passed

### Remaining release gate
The remaining blockers are now operational/external rather than known code-hardening failures:
1. Rotate all credentials that may have appeared in historical Git, including production DB, APP_KEY where appropriate, SMTP, Paymob, WhatsApp/Meta, and deploy shared secrets actually in use.
2. Execute a real Paymob sandbox/test end-to-end payment: initiate, success callback, failed/declined callback, delayed callback, refresh/retry, and duplicate callback behavior.
3. Rehearse deploy on the target server/staging-like environment, confirm pre-migration database snapshot creation, and rehearse file rollback plus explicit database recovery procedure.
4. Run final production smoke checks, then promote V42 to `main`.
5. Deploy Production only after the above gate is green.


### Paymob account discovery — 2026-09-20
- New dashboard, Test mode: Payment Integrations table is empty.
- Old dashboard, Test mode: Developers -> Payment Integrations is also empty.
- Attempting to create a new Non-Shopify / MIGS / EGP Test integration returns: "Cannot create more than 1 test integration with the same gateway type and currency."
- This means the account backend recognizes an existing MIGS/EGP Test integration even though neither dashboard currently renders it.
- Next diagnostic: inspect Developers -> Iframes in the old dashboard for the previously used iframe/integration linkage. If no legacy artifact is visible there either, treat this as a Paymob account-side integration visibility/state issue and open a support case rather than creating another integration.


### Paymob legacy artifact confirmed — 2026-09-20
Old dashboard -> Developers -> Iframes shows two Test-mode IFrames:
- IFrame `1024106` — `Installment_Discount`
- IFrame `1024107` — `My new card Iframe`

This confirms the account still retains legacy checkout artifacts even though both old and new Payment Integrations pages render no integration rows. The next diagnostic is to open/edit IFrame `1024107` and inspect which payment integration(s) it is linked to. Do not create another MIGS/EGP Test integration while Paymob reports that one already exists.
