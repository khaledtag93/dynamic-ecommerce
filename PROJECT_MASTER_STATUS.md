# MASTER PROJECT STATUS
## Dynamic E-commerce System (Tag Marketplace)

> Living status: start with [the documentation guide](docs/README.md) for the current implementation/CI/QAS/Production ledger and the dated audit links. Historical checkpoints below are retained as evidence; their “next step” statements describe the date they were written.

## Current state

- **Expanded product direction (2026-09-24):** UI/UX/design-system modernization, POS/cashier, barcode scanning, invoice/receipt, employee attendance/shifts/leave/payroll, and expanded delivery operations are now tracked in `docs/PRODUCT_ROADMAP_2026-09-24.md`. These are phased roadmap items; they are not yet deployed features.
- **Official application baseline:** V42
- **Baseline description:** Cost Calculator refactor + Arabic/English translation updates
- **Current working branch:** `v42-clean-baseline`
- **Current phase:** Incremental commercial-readiness fixes on the V42 working line, with CI → QAS → review → Production promotion per exact commit
- **GitHub `main`:** still on the older baseline and has not yet been promoted to the validated V42 branch
- **Production:** V42 routine deployment flow is validated; exact application commit `95e9f50` was deployed successfully with HTTP 200
- **Production domain:** `tag-marketplace.com`

## Latest working-line update — 2026-09-23
- An admin daily-work UX batch is CI-verified in source, pending QAS review: the default dashboard now shows four clearly defined 30-day metrics, permission-scoped priorities, workspaces and recent records. Its controller no longer computes the unused deep-dive panels. The sidebar no longer queries order/coupon/supplier counts on every render. Topbar/search and order actions respect route permissions.
- **Branding & Appearance V2 (2026-09-24):** source implementation now adds the `professional_commerce` neutral default direction, visual preset cards, reapply-preset behavior, a focused core palette with Advanced controls, a more representative live preview, unsaved-change state/protection, semantic admin success/warning/danger colors, supported badge-style choices, and Arabic/English strings. During the pass, homepage visibility handling was repaired for Featured categories, Manual featured products and Trust blocks. Automated coverage was added in `tests/Feature/BrandingSettingsExperienceTest.php`. Detailed scope/QAS checks: `docs/BRANDING_APPEARANCE_V2_2026-09-24.md`. Final branch-head CI, authenticated QAS review and Production deployment remain separate gates.
- A shared accessible section navigator splits product editing, branding and content settings into focused sections while retaining one form and both product save controls. Order details now have section links and an even four-card status grid. The section helper selects the first error section and preserves the active tab across Livewire updates; this behavior requires authenticated desktop/mobile Arabic/English QAS review, including variants and uploads.
- Admin code revision `9fe1a96` passed [CI run 35919171926](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35919171926): PHP syntax, clean MySQL migration, Blade compilation, 48 tests (208 assertions), frontend build. These changes have **not** been visually verified in QAS or deployed to Production. The [documentation ledger](docs/README.md) and [Admin UX QAS checklist](docs/ADMIN_UX_QAS_CHECKLIST.md) track the next gate.
- Batch 0 code now requires an explicit non-owner staff role when a customer receives admin access, assigns/removes that role transactionally, and prevents changing an owner through the customer profile. Customer-list access editing moved to the profile to simplify the table.
- The product page no longer creates synthetic ratings, reviews, sales, viewer and save counts; repeated unverified reassurance blocks were removed. The purchase/variant controls remain, and the initial stock note uses the selected variant stock.
- Growth validation demo seed/clear is now restricted to local, testing and QAS/staging at the HTTP and service layers; its operations controls are hidden in Production. Existing demo records have not been audited or removed.
- Focused promotion/demotion, permission-boundary and Production demo-guard regression tests were added. Code revision `f7ff4e8` passed [Hardening CI run 35916058338](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35916058338): PHP syntax, clean MySQL migration, Blade compilation, 45 PHPUnit tests and frontend build. **QAS visual verification and Production deployment are still pending**; neither environment is recorded as running this batch.
- The pre-existing roleless-admin Super Admin fallback is still active. This is a **partial authorization fix**; inventory current admins, designate and explicitly assign the owner, then remove the fallback with migration/rollback tests. Other P0 release gates in the audits remain open.
- The source audit and UI review are dated baselines, and their later implementation states are tracked in the [documentation guide](docs/README.md). Update this section and the ledger for every subsequent batch.

## Product Admin commercial UX checkpoint — 2026-09-24
- Product catalog management now has combined search/status/category/brand/content-readiness/inventory/featured filters, catalog-health counters, actionable low-stock and out-of-stock views, inline simple-product pricing/quantity controls, and clearer stock/content/featured state badges.
- Bulk operations now cover storefront visibility and featured merchandising. Activation intentionally keeps the current non-blocking content-readiness policy; incomplete content is advisory until barcode/variant/retail publication rules are finalized.
- Single-product and bulk destructive deletion now use explicit guarded confirmation state and in-app confirmation modals instead of immediate deletion/browser-only prompts. Managed product images remain part of the deletion flow.
- Regression coverage now exercises product editor retail identifiers, advisory readiness, bulk visibility, featured actions, and guarded bulk deletion. Additional bulk-visibility tests are currently running in CI at working-line head; do not treat this Product Admin checkpoint as QAS-verified until the final branch-head CI and authenticated visual checks pass.
- Product Admin remains source-complete for this iteration but not Production-promoted. Next gate: green branch-head CI, authenticated QAS desktop/mobile + Arabic/English review, then resolve any findings before moving the module to release-ready status.

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


## Full project audit — 2026-09-23
- A repository-wide architecture, commercial-readiness, security, UX, bilingual, performance, testing, and operations audit is recorded in `docs/FULL_PROJECT_AUDIT_2026-09-23.md`.
- Immediate priority sequence: explicit owner/Super Admin authorization; remove fabricated storefront social proof; close credential-rotation evidence; real storefront search; shipping/tax; unpaid-online-order stock release; Paymob E2E; returns/reviews/addresses; then architecture/framework modernization.
- Latest audited `v42-clean-baseline` CI is green.

## UI/UX and commercial-readiness addendum — 2026-09-23
- Detailed screen and journey review: `docs/UI_UX_AND_COMMERCIAL_READINESS_REVIEW_2026-09-23.md`; baseline inspected at `c1f2146` on `v42-clean-baseline`.
- Live Production storefront inspection found `Moble`/`Toolsssss`, an unrelated product description, default support email/phone, mixed Arabic/English text, fabricated review and activity counts, repeated single-product merchandising, and the still-deployed V42 marker labels. A no-match header search returned the ordinary homepage.
- Additional code finding: the EGP 600 free-shipping progress message is disconnected from the current zero-valued shipping calculation; “best sellers” may use non-paid order items and falls back to newest products; authenticated Growth Operations exposes demo-data seed/clear without an environment guard.
- Admin UX implementation order: section the Livewire product form while preserving its state/save behavior; focus order-detail actions; separate branding workspaces; shorten the dashboard's default operator view. Growth already provides an example of focused subpages. Admin visual/mobile behavior still needs authenticated QAS review.
- Before real commercial traffic, close public-content/trust and authorization gates; then implement search, honest shipping/tax totals and unpaid-order stock lifecycle. Measure search, checkout, payment, fulfillment, support and Web Vitals against non-demo baselines rather than assuming a design change guarantees sales.
- This addendum is documentation only. No application code, QAS, Production, database, payment configuration, or public content was changed by the review. The cleanup commit `41a2f99` remains not recorded as deployed; `main` remains on the older baseline until separately reconciled.
