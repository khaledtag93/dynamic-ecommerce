# MASTER PROJECT STATUS
## Dynamic E-commerce System (Tag Marketplace)

## Current state
- **Official application baseline:** V42
- **Baseline description:** Cost Calculator refactor + Arabic/English translation updates
- **Current working branch:** `v42-clean-baseline`
- **Current phase:** Post-deployment stabilization and source-control promotion
- **GitHub `main`:** still on the older baseline and has not yet been promoted to the validated V42 branch
- **Production:** V42 routine deployment flow is validated; exact application commit `95e9f50` was deployed successfully with HTTP 200
- **Production domain:** `tag-marketplace.com`

## V42 baseline status
- Clean V42 Git baseline completed and verified.
- Expected V42 source files matched the source snapshot by Git blob hash after intentional exclusions.
- Dependency/runtime/local-data artifacts are no longer tracked in the V42 branch.
- `.env` is no longer tracked in the V42 branch.
- Local DB, logs, sessions, uploads, generated Laravel cache files, and backup files are excluded from the baseline.

## Completed / strongly implemented
### Commerce core
- storefront, categories, brands, products, variants and attributes
- cart, checkout, orders and coupons
- cancellation/refund foundations
- Arabic/English translation foundation

### Operations
- suppliers and purchases
- inventory movement foundations
- cost/profit foundations
- Cost Calculator module introduced in V42

### Payments
- payment records/settings
- Paymob gateway foundation
- callback/result flows
- payment admin visibility

### Growth / intelligence foundations
- analytics events and daily stats
- growth automation rules
- message templates/logs
- audience segments
- experiments/validation foundations
- attribution/cohort/customer-scoring foundations

### Platform/admin foundations
- roles/permissions foundations
- notifications and settings
- branding/content controls
- deployment and rollback scripts

## Production-hardening priorities
1. **P0 — Credential rotation:** credentials previously committed to Git history must be treated as exposed and rotated before Production.
2. Verify payment callback/HMAC behavior and idempotency.
3. Verify authorization and privilege-escalation paths.
4. Verify concurrency behavior for stock, coupons, refunds, and orders.
5. Verify Cost Calculator formulas and reporting semantics.
6. Add/repair automated CI coverage for critical flows.
7. Execute deployment, health-check, and rollback validation.
8. Promote V42 to `main` only after verification.

## Planned / not yet closed
- WhatsApp/SMS multi-channel expansion
- deeper analytics/dashboard polish
- broader Arabic/English consistency pass
- HR/POS/barcode/receipt capabilities
- wishlist/comparison/smart search/customer feature closure
- subscription/trial/renewal controls
- deeper AI personalization
- SaaS multi-tenancy and mobile-app path

## Release rule
No commercial handoff or Production deployment is considered complete until the production-hardening priorities are closed or explicitly accepted with documented risk.


## V42 hardening checkpoint — 2026-09-20
### Newly closed
- duplicate checkout protection using transactional cart locking
- atomic inventory decrement / oversell protection
- atomic coupon usage-limit enforcement
- order cancellation idempotency and row-level serialization
- refund race / over-refund protection
- payment/refund-ledger consistency
- permissions self-escalation path
- deploy-center safe default and mandatory verified health check
- clean MySQL migration compatibility for growth learning index
- GitHub Actions hardening CI established

### CI status
A production-like GitHub Actions build on PHP 8.2 + MySQL 8 is green for the V42 hardening branch. It validates Composer, PHP syntax, a clean migration, Laravel boot/routes, config/views, the existing PHPUnit suite, and the frontend production build.

### Still required before Production
- rotate credentials that were historically committed
- execute a Paymob sandbox/test end-to-end transaction and negative-path callback checks
- rehearse deploy/rollback against the target server or staging-like environment
- validate the explicit database recovery procedure using the pre-migration SQL snapshot
- run final smoke checks, then promote V42 to `main`

### Additional V42 hardening — 2026-09-20
- custom role deletion now refuses assigned roles, preventing foreign-key cascade from leaving admins in implicit legacy Super Admin state
- permission-role deletion now has targeted automated regression coverage
- remaining tracked Livewire temporary files and product runtime uploads were removed from Git and their runtime storage paths are ignored

### Financial integrity follow-up — 2026-09-20
- Paymob/gateway status transitions are now row-locked and transactional, closing the simultaneous paid-vs-failed callback race
- order payment-state synchronization is row-locked
- permanent order hard-delete is disabled to retain financial and inventory audit history
- targeted tests now cover terminal paid state, retained cancelled orders, and assigned-role deletion protection


### Final V42 code-hardening checkpoint — 2026-09-20
Additional closures verified by green CI:
- full-refund Payment ledger synchronization and terminal refunded state
- consistent Order → Payment lock ordering for payment/refund concurrency
- checkout transaction rollback regression coverage for late failures
- cancellation inventory-restock audit movements without changing current product cost valuation
- correct Profit Margin semantics in Cost Calculator (profit / selling price)
- deploy backup secret minimization (`.env` excluded from app snapshot; private file creation)
- manual rollback maintenance mode + HTTPS health verification
- Paymob callback raw-payload/full-URL log minimization

The latest production-like GitHub Actions run for code head `b9a46a6` passed the complete pipeline on PHP 8.2 + MySQL 8, including the expanded PHPUnit regression suite and frontend production build.

Historical Git inspection confirms that `.env` existed in the older repository history before the hardening removal commit. Credential rotation therefore remains a mandatory P0 release gate even though V42 no longer tracks the file.


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


### Production Node.js check — 2026-09-20
- `node` is not installed/available in the Hostinger SSH shell.
- This is not a production deploy blocker for the current V42 flow because frontend assets are built in CI and `public/build` is committed/deployed with the application.
- The hardened `deploy.sh` does not require Node/npm on production.
- Do not install Node on the live server solely for deployment unless the deployment strategy changes.


## Post-deployment cleanup — 2026-09-23
- Routine QAS -> Production promotion was already validated end-to-end with application commit `95e9f50`.
- Two homepage labels used only as visible deployment markers were still present after validation: `V42 Special Offers` and `V42 Popular Products`.
- Cleanup commit `41a2f99` restores the intended customer-facing labels: `Today offers` and `Popular now`.
- Cleanup commit is pushed to `v42-clean-baseline` and is **not yet recorded as QAS/Production deployed**.
- Next operational step: deploy `41a2f99` to QAS, browser-verify, then Production dry-run + exact-commit execution if approved.
- After the cleanup is verified in Production, promote the validated V42 history to `main` so source control matches the production release line.
