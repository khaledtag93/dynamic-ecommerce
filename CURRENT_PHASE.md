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


### Paymob iframe edit inspection — 2026-09-20
- Old dashboard IFrame `1024107` ("My new card Iframe") was opened in edit mode.
- The edit screen exposes only presentation/customization fields (Name, Description, HTML, JavaScript, CSS).
- No Payment Integration ID or MIGS linkage is exposed from the IFrame editor.
- Conclusion: the hidden MIGS/EGP Test integration cannot be recovered from the IFrame UI. Since both Payment Integrations pages are empty while creation is blocked as a duplicate, this is now treated as a Paymob account-side hidden/orphan integration state.
- Next action: Paymob support/account manager should reveal, restore, or reset the Test MIGS/EGP integration. Do not create/duplicate/delete IFrames as a workaround.


### Recovered legacy deployment/payment notes — 2026-09-20
Recovered from the user's old local notes (non-secret facts only):
- Paymob Merchant ID: `1147230`
- Paymob Test Integration ID previously used: `5596653`
- Paymob legacy IFrame previously used: `1024107`
- Production Laravel app path historically used: `/home/u637857322/domains/tag-marketplace.com/laravel_app`
- Production public webroot historically used: `/home/u637857322/domains/tag-marketplace.com/public_html`
- Historical SSH port used: `65002`
- Historical deploy routine used `deploy.sh` / `rollback.sh` from the Laravel app directory.

Security note:
- The recovered notes also contained plaintext provider, database, SSH, and account credentials.
- No credential values are copied into this repository documentation.
- Treat all historical credentials from those notes as exposed and rotate them before Production promotion.
- The recovered Paymob Integration ID / IFrame ID are identifiers, not secrets, and may be used for compatibility diagnostics.


### Gmail evidence for Paymob account state — 2026-09-20
Connected Gmail history confirms:
- Paymob welcomed the merchant account on 2026-03-31 after dashboard onboarding.
- Multiple Paymob TEST card transactions were executed on 2026-03-31 for EGP 98.00 through the hosted IFRAME flow; all surfaced transaction-status emails were declined.
- Starting 2026-04-01, Paymob repeatedly sent "Document Resubmission Required" onboarding emails for Merchant ID `1147230`.
- The same resubmission notice was still being sent as recently as 2026-09-18.
- Gmail search found no Paymob email confirming successful verification, activation, or Live-mode approval.
- Gmail search also found no sent support thread to `support@paymob.com` or `support@weaccept.co`.

Operational conclusion:
- The merchant account was integrated enough for Test IFRAME transactions, but onboarding/verification appears to have remained incomplete.
- This account state likely contributes to current dashboard limitations and is a separate concern from Laravel integration correctness.
- Continue V42 release hardening independently; do not block non-payment readiness work on Paymob verification.


### Production server discovery — 2026-09-20
- SSH login to Hostinger succeeded.
- The historical application path `/home/u637857322/domains/tag-marketplace.com/laravel_app` exists.
- Running `git status --short --branch` inside that directory returned: `fatal: not a git repository`.
- Therefore the current production Laravel directory is not a Git worktree. Do not run fetch/pull/reset/checkout there until the actual deployment layout is inspected and a safe migration/rehearsal plan is chosen.


### Production filesystem inspection — 2026-09-20
At `/home/u637857322/domains/tag-marketplace.com/laravel_app`:
- Laravel application files are present, including `artisan`, `composer.json`, `vendor/`, `storage/`, `.env`, `deploy.sh`, and `rollback.sh`.
- No `.git/` directory is present; this is a deployed file snapshot, not a Git checkout.
- Most application files are dated around 2026-04-17, while `.env` is server-local.
- Do not convert this live production directory into a Git worktree in place before a controlled rehearsal/backup plan.
- Preferred next step: inspect server runtime/tooling and free space, then prepare a separate V42 rehearsal directory so production remains untouched.


### Production runtime check — 2026-09-20
- Hostinger CLI PHP version: `PHP 8.3.33` (NTS) with Zend OPcache.
- This satisfies the application's Composer requirement (`php ^8.1`) at the runtime version level.


### Production Composer check — 2026-09-20
- Hostinger Composer version: `2.9.8`.
- Composer is running under PHP `8.3.33` from `/opt/alt/php83/usr/bin/php`.


### Production PHP extension check — 2026-09-20
Required runtime extensions were confirmed present on Hostinger CLI PHP 8.3.33:
- bcmath
- curl
- dom
- intl
- mbstring
- pdo_mysql
- xml / SimpleXML / xmlreader / xmlwriter
- zip

This clears the PHP-extension portion of the server preflight.


### Production Laravel runtime check — 2026-09-20
Hostinger production runtime currently reports:
- Application: Tag Marketplace
- Laravel: 10.48.29
- PHP: 8.3.33
- Composer: 2.9.8
- Environment: production
- Maintenance mode: OFF
- APP_DEBUG/runtime debug mode: ENABLED

Security/release note:
- Debug mode being enabled in production is a release blocker and should be changed to disabled before the next production deployment.
- Do not change it blindly mid-audit; verify the current server .env values first, then update in a controlled step and clear/rebuild config cache.


### Production debug configuration fix — 2026-09-20
- Confirmed production `.env` had `APP_DEBUG=true`.
- Created a server-side backup: `.env.backup_before_debug_fix`.
- Updated production `.env` to `APP_DEBUG=false`.
- Next step is to rebuild Laravel config cache and verify runtime reports Debug Mode disabled.


### Production debug verification — 2026-09-20
- Rebuilt Laravel config cache after changing production `APP_DEBUG=false`.
- Verified with `php artisan about --only=environment` that runtime Debug Mode is now OFF.
- Production remains out of maintenance mode.


### Production database connectivity check — 2026-09-20
- `php artisan migrate:status` completed successfully on production, confirming Laravel can connect to the configured database.
- Every migration file currently present in the deployed production snapshot is marked Ran.
- The V42 hardening branch contains three newer migration files not present in this production snapshot yet:
  - `2026_06_24_000000_create_cost_calculator_tables.php`
  - `2026_09_20_000000_normalize_growth_offer_learning_index.php`
  - `2026_09_20_235900_scrub_plaintext_provider_secrets.php`
- These must only be applied during the controlled V42 deployment/rehearsal after a database snapshot; do not run them on the current production snapshot now.


### Production env permission hardening — 2026-09-20
- Confirmed production `.env` and the temporary debug-fix backup were mode `644`.
- Changed both to mode `600`.
- Verified owner remains `u637857322`.
- This removes group/other read access from files containing production secrets.


### Production public webroot inspection — 2026-09-20
- `/home/u637857322/domains/tag-marketplace.com/public_html` exists and contains the deployed public assets.
- Visible entries include `index.php`, `.htaccess`, `build/`, `assets/`, `admin/`, `storage/`, and `uploads/`.
- The current deployment uses a split layout: Laravel application code under `laravel_app`, with web-facing public assets under `public_html`.
- Next check: inspect `public_html/index.php` to confirm exactly which Laravel application path it boots.


### Production bootstrap linkage confirmed — 2026-09-20
- `public_html/index.php` requires `../laravel_app/vendor/autoload.php`.
- It boots `../laravel_app/bootstrap/app.php`.
- Therefore the live webroot is explicitly wired to the sibling `laravel_app` directory.
- Any rehearsal must use a separate directory and must not repoint `public_html/index.php` until the release gate is approved.


### Production health check after hardening — 2026-09-20
- After setting `APP_DEBUG=false`, rebuilding config cache, and tightening `.env` permissions, the live site returned `HTTP 200` from `https://tag-marketplace.com`.
- Current production remains healthy after the server-side safety fixes.
- Next phase: prepare a separate V42 rehearsal directory; do not repoint `public_html` or run V42 migrations yet.
